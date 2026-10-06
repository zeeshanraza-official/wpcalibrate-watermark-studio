<?php
declare(strict_types=1);

namespace WPCalibrate\WatermarkStudio\Queue;

use WPCalibrate\WatermarkStudio\Watermark\Processor;
use WPCalibrate\WatermarkStudio\Media\RestoreService;
use WPCalibrate\WatermarkStudio\Media\AttachmentRepository;
use WPCalibrate\WatermarkStudio\Support\Logger;
use WP_Error;

/**
 * Class BatchProcessor
 *
 * Coordinates asynchronous and bulk background batch operations, managing queues,
 * state persistence, transient lock guards, and progress metrics.
 */
final class BatchProcessor {
	public const OPTION_QUEUE_STATE = 'wpcalibrate_ws_bulk_queue_state';
	public const BATCH_SIZE         = 10;

	private SchedulerInterface $scheduler;

	public function __construct(
		private readonly Processor $processor,
		private readonly RestoreService $restore_service,
		private readonly AttachmentRepository $attachment_repository,
		private readonly Logger $logger,
		?SchedulerInterface $scheduler = null
	) {
		if ( null !== $scheduler ) {
			$this->scheduler = $scheduler;
		} else {
			$as_scheduler = new ActionSchedulerAdapter();
			$this->scheduler = $as_scheduler->is_available() ? $as_scheduler : new CronBatchScheduler();
		}
	}

	public function init(): void {
		// Hook Action Scheduler and WP-Cron hooks
		add_action( ActionSchedulerAdapter::HOOK_NAME, array( $this, 'handle_scheduled_batch' ) );
		add_action( CronBatchScheduler::CRON_HOOK, array( $this, 'handle_scheduled_batch' ) );
	}

	public function get_scheduler(): SchedulerInterface {
		return $this->scheduler;
	}

	/**
	 * Retrieve current bulk queue state.
	 *
	 * @return array<string, mixed>
	 */
	public function get_state(): array {
		$state = get_option( self::OPTION_QUEUE_STATE, array() );
		$defaults = array(
			'status'          => 'idle', // idle, running, completed, cancelled
			'operation'       => 'watermark', // watermark, restore
			'total_items'     => 0,
			'processed_items' => 0,
			'completed_count' => 0,
			'failed_count'    => 0,
			'skipped_count'   => 0,
			'remaining_ids'   => array(),
			'errors'          => array(),
			'started_at'      => 0,
			'updated_at'      => 0,
		);

		return is_array( $state ) ? wp_parse_args( $state, $defaults ) : $defaults;
	}

	/**
	 * Start a new bulk processing job.
	 *
	 * @param array<int, int> $attachment_ids
	 * @param string          $operation 'watermark' or 'restore'
	 * @return bool
	 */
	public function start_queue( array $attachment_ids, string $operation = 'watermark' ): bool {
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $attachment_ids ) ) ) );
		if ( empty( $ids ) ) {
			return false;
		}

		$state = array(
			'status'          => 'running',
			'operation'       => in_array( $operation, array( 'watermark', 'restore' ), true ) ? $operation : 'watermark',
			'total_items'     => count( $ids ),
			'processed_items' => 0,
			'completed_count' => 0,
			'failed_count'    => 0,
			'skipped_count'   => 0,
			'remaining_ids'   => $ids,
			'errors'          => array(),
			'started_at'      => time(),
			'updated_at'      => time(),
		);

		update_option( self::OPTION_QUEUE_STATE, $state, false );

		// Schedule background trigger immediately
		$this->scheduler->schedule_batch( 0 );
		$this->logger->info( 'Bulk queue started', array( 'total' => count( $ids ), 'operation' => $operation ) );

		return true;
	}

	/**
	 * Cancel remaining queue.
	 */
	public function cancel_queue(): void {
		$state           = $this->get_state();
		$state['status'] = 'cancelled';
		$state['updated_at'] = time();
		update_option( self::OPTION_QUEUE_STATE, $state, false );

		$this->scheduler->cancel_pending();
		JobLock::release( 'batch_processor' );

		$this->logger->info( 'Bulk queue cancelled by administrator' );
	}

	/**
	 * Reset queue state back to idle.
	 */
	public function reset_queue(): void {
		delete_option( self::OPTION_QUEUE_STATE );
		JobLock::release( 'batch_processor' );
	}

	/**
	 * Scheduled event handler.
	 */
	public function handle_scheduled_batch(): void {
		$this->process_next_batch();
	}

	/**
	 * Process the next batch of images.
	 * Can be invoked by Cron, Action Scheduler, or Admin AJAX poll.
	 *
	 * @return array<string, mixed> Updated queue state.
	 */
	public function process_next_batch(): array {
		$state = $this->get_state();
		if ( 'running' !== $state['status'] ) {
			return $state;
		}

		if ( empty( $state['remaining_ids'] ) ) {
			$state['status']     = 'completed';
			$state['updated_at'] = time();
			update_option( self::OPTION_QUEUE_STATE, $state, false );
			return $state;
		}

		// Prevent overlapping batch workers
		if ( ! JobLock::acquire( 'batch_processor', 120 ) ) {
			return $state;
		}

		try {
			$batch_ids = array_splice( $state['remaining_ids'], 0, self::BATCH_SIZE );
			$operation = $state['operation'];

			foreach ( $batch_ids as $attachment_id ) {
				$state['processed_items']++;

				if ( 'restore' === $operation ) {
					$result = $this->restore_service->restore_attachment( $attachment_id );
				} else {
					$result = $this->processor->process_attachment( $attachment_id, true );
				}

				if ( true === $result ) {
					$state['completed_count']++;
				} elseif ( is_wp_error( $result ) ) {
					if ( 'dimensions_below_minimum' === $result->get_error_code() || 'mime_disallowed' === $result->get_error_code() ) {
						$state['skipped_count']++;
					} else {
						$state['failed_count']++;
						$state['errors'][] = array(
							'attachment_id' => $attachment_id,
							'code'          => $result->get_error_code(),
							'message'       => $result->get_error_message(),
						);
						// Keep error list bounded
						if ( count( $state['errors'] ) > 25 ) {
							array_shift( $state['errors'] );
						}
					}
				}
			}

			if ( empty( $state['remaining_ids'] ) ) {
				$state['status'] = 'completed';
			}

			$state['updated_at'] = time();
			update_option( self::OPTION_QUEUE_STATE, $state, false );

			// If more remaining, schedule next background batch
			if ( 'running' === $state['status'] ) {
				$this->scheduler->schedule_batch( 2 );
			}
		} finally {
			JobLock::release( 'batch_processor' );
		}

		return $state;
	}
}

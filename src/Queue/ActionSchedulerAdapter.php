<?php
declare(strict_types=1);

namespace WPCalibrate\WatermarkStudio\Queue;

/**
 * Class ActionSchedulerAdapter
 *
 * Implements job scheduling via Action Scheduler when available.
 */
final class ActionSchedulerAdapter implements SchedulerInterface {
	public const HOOK_NAME = 'wpcalibrate_watermark_studio_batch_process';
	public const GROUP_NAME = 'wpcalibrate-watermark-studio';

	public function get_name(): string {
		return 'action_scheduler';
	}

	public function is_available(): bool {
		return function_exists( 'as_schedule_single_action' ) && function_exists( 'as_has_scheduled_action' );
	}

	public function schedule_batch( int $delay_seconds = 0 ): bool {
		if ( ! $this->is_available() ) {
			return false;
		}

		// Prevent duplicate pending action scheduling
		if ( as_has_scheduled_action( self::HOOK_NAME, array(), self::GROUP_NAME ) ) {
			return true;
		}

		$timestamp = time() + max( 0, $delay_seconds );
		$action_id = as_schedule_single_action( $timestamp, self::HOOK_NAME, array(), self::GROUP_NAME );

		return (int) $action_id > 0;
	}

	public function cancel_pending(): void {
		if ( $this->is_available() && function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::HOOK_NAME, array(), self::GROUP_NAME );
		}
	}
}

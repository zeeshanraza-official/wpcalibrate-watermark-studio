<?php
declare(strict_types=1);

namespace WPCalibrate\WatermarkStudio\Admin;

use WPCalibrate\WatermarkStudio\Queue\BatchProcessor;
use WPCalibrate\WatermarkStudio\Settings\Settings;
use WPCalibrate\WatermarkStudio\Media\AttachmentRepository;
use WPCalibrate\WatermarkStudio\Support\Capabilities;

/**
 * Class BulkPage
 *
 * Renders the Bulk Processing management console and coordinates AJAX-driven background queue workers.
 */
final class BulkPage {
	public function __construct(
		private readonly BatchProcessor $batch_processor,
		private readonly Settings $settings,
		private readonly AttachmentRepository $attachment_repository
	) {}

	public function init(): void {
		add_action( 'wpcalibrate_watermark_studio_render_bulk_page', array( $this, 'render' ) );

		// AJAX endpoints for interactive queue management
		add_action( 'wp_ajax_wpcalibrate_ws_bulk_start', array( $this, 'ajax_start' ) );
		add_action( 'wp_ajax_wpcalibrate_ws_bulk_status', array( $this, 'ajax_status' ) );
		add_action( 'wp_ajax_wpcalibrate_ws_bulk_cancel', array( $this, 'ajax_cancel' ) );
		add_action( 'wp_ajax_wpcalibrate_ws_bulk_work', array( $this, 'ajax_work' ) );
	}

	public function render(): void {
		if ( ! Capabilities::can_bulk_process() ) {
			wp_die( esc_html__( 'Unauthorized access.', 'wpcalibrate-watermark-studio' ), 403 );
		}

		$allowed_mimes = (array) $this->settings->get( 'allowed_mime_types', array( 'image/jpeg', 'image/png', 'image/webp' ) );
		$counts        = $this->attachment_repository->get_counts( $allowed_mimes );
		$queue_state   = $this->batch_processor->get_state();
		$nonce         = wp_create_nonce( 'wpcalibrate_ws_bulk_nonce' );
		?>
		<div class="wrap wpcalibrate-admin-wrap" id="wpcalibrate-bulk-console" data-nonce="<?php echo esc_attr( $nonce ); ?>">
			<div class="wpcalibrate-header">
				<div class="wpcalibrate-brand">
					<img src="<?php echo esc_url( WPCALIBRATE_WATERMARK_STUDIO_URL . 'branding/icon-dark.png' ); ?>" alt="WPCalibrate" class="wpcalibrate-logo" />
					<h1><?php esc_html_e( 'Bulk Watermark Processing', 'wpcalibrate-watermark-studio' ); ?></h1>
				</div>
			</div>

			<!-- Metrics Cards -->
			<div class="wpcalibrate-metrics-grid">
				<div class="metric-card">
					<span class="metric-title"><?php esc_html_e( 'Total Eligible Images', 'wpcalibrate-watermark-studio' ); ?></span>
					<span class="metric-value" id="metric-total"><?php echo esc_html( number_format_i18n( $counts['total'] ) ); ?></span>
				</div>
				<div class="metric-card metric-success">
					<span class="metric-title"><?php esc_html_e( 'Currently Watermarked', 'wpcalibrate-watermark-studio' ); ?></span>
					<span class="metric-value" id="metric-watermarked"><?php echo esc_html( number_format_i18n( $counts['watermarked'] ) ); ?></span>
				</div>
				<div class="metric-card metric-info">
					<span class="metric-title"><?php esc_html_e( 'Unwatermarked Images', 'wpcalibrate-watermark-studio' ); ?></span>
					<span class="metric-value" id="metric-unwatermarked"><?php echo esc_html( number_format_i18n( $counts['unwatermarked'] ) ); ?></span>
				</div>
				<div class="metric-card metric-warning">
					<span class="metric-title"><?php esc_html_e( 'Protected Backups', 'wpcalibrate-watermark-studio' ); ?></span>
					<span class="metric-value" id="metric-backed-up"><?php echo esc_html( number_format_i18n( $counts['backed_up'] ) ); ?></span>
				</div>
				<div class="metric-card metric-danger">
					<span class="metric-title"><?php esc_html_e( 'Failed Items', 'wpcalibrate-watermark-studio' ); ?></span>
					<span class="metric-value" id="metric-failed"><?php echo esc_html( number_format_i18n( $counts['failed'] ) ); ?></span>
				</div>
			</div>

			<!-- Bulk Control Section -->
			<div class="wpcalibrate-card" style="margin-top:20px;">
				<h2><?php esc_html_e( 'Queue Setup & Actions', 'wpcalibrate-watermark-studio' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Process hundreds of images safely in bounded batches without server timeouts or browser locks. An untouched original is always verified or preserved before modifying files.', 'wpcalibrate-watermark-studio' ); ?>
				</p>

				<div class="bulk-form-grid" style="display:flex; gap:20px; align-items:flex-end; margin:20px 0;">
					<div>
						<label for="bulk-operation" style="font-weight:600; display:block; margin-bottom:5px;">
							<?php esc_html_e( 'Operation', 'wpcalibrate-watermark-studio' ); ?>
						</label>
						<select id="bulk-operation" class="regular-text">
							<option value="watermark"><?php esc_html_e( 'Apply / Reapply Watermarks', 'wpcalibrate-watermark-studio' ); ?></option>
							<option value="restore"><?php esc_html_e( 'Restore Pristine Originals', 'wpcalibrate-watermark-studio' ); ?></option>
						</select>
					</div>

					<div>
						<label for="bulk-target" style="font-weight:600; display:block; margin-bottom:5px;">
							<?php esc_html_e( 'Target Scope', 'wpcalibrate-watermark-studio' ); ?>
						</label>
						<select id="bulk-target" class="regular-text">
							<option value="unwatermarked"><?php esc_html_e( 'Only Unwatermarked Images', 'wpcalibrate-watermark-studio' ); ?></option>
							<option value="backed_up"><?php esc_html_e( 'All Images with Protected Backups (Restore)', 'wpcalibrate-watermark-studio' ); ?></option>
							<option value="all"><?php esc_html_e( 'All Eligible Library Images', 'wpcalibrate-watermark-studio' ); ?></option>
							<option value="watermarked"><?php esc_html_e( 'Only Watermarked Images (Reapply / Restore)', 'wpcalibrate-watermark-studio' ); ?></option>
							<option value="failed"><?php esc_html_e( 'Only Failed Images (Retry)', 'wpcalibrate-watermark-studio' ); ?></option>
							<?php if ( class_exists( 'WooCommerce' ) ) : ?>
								<option value="woocommerce"><?php esc_html_e( 'WooCommerce Product Images Only', 'wpcalibrate-watermark-studio' ); ?></option>
							<?php endif; ?>
						</select>
					</div>

					<div>
						<button type="button" id="btn-start-bulk" class="button button-primary button-hero">
							<?php esc_html_e( 'Start Bulk Queue', 'wpcalibrate-watermark-studio' ); ?>
						</button>
						<button type="button" id="btn-cancel-bulk" class="button button-secondary button-hero" style="display:none; margin-left:10px;">
							<?php esc_html_e( 'Cancel Queue', 'wpcalibrate-watermark-studio' ); ?>
						</button>
					</div>
				</div>
			</div>

			<!-- Live Progress Console -->
			<div class="wpcalibrate-card" id="bulk-progress-panel" style="margin-top:20px; <?php echo 'running' === $queue_state['status'] ? '' : 'display:none;'; ?>">
				<h2><?php esc_html_e( 'Queue Execution Progress', 'wpcalibrate-watermark-studio' ); ?></h2>
				<div class="progress-bar-container">
					<div class="progress-bar-fill" id="bulk-progress-fill" style="width:0%;"></div>
				</div>
				<div class="progress-status-row" style="display:flex; justify-content:space-between; margin-top:10px; font-weight:600;">
					<span id="bulk-progress-text"><?php esc_html_e( 'Ready...', 'wpcalibrate-watermark-studio' ); ?></span>
					<span id="bulk-progress-percent">0%</span>
				</div>

				<div class="progress-summary-grid" style="display:flex; gap:30px; margin-top:15px; font-size:14px;">
					<div><strong><?php esc_html_e( 'Completed:', 'wpcalibrate-watermark-studio' ); ?></strong> <span id="stat-completed" style="color:#28a745;">0</span></div>
					<div><strong><?php esc_html_e( 'Skipped:', 'wpcalibrate-watermark-studio' ); ?></strong> <span id="stat-skipped" style="color:#6c757d;">0</span></div>
					<div><strong><?php esc_html_e( 'Failed:', 'wpcalibrate-watermark-studio' ); ?></strong> <span id="stat-failed" style="color:#dc3545;">0</span></div>
					<div><strong><?php esc_html_e( 'Remaining:', 'wpcalibrate-watermark-studio' ); ?></strong> <span id="stat-remaining">0</span></div>
				</div>

				<!-- Live Errors Table -->
				<div id="bulk-errors-container" style="margin-top:20px; display:none;">
					<h3><?php esc_html_e( 'Processing Errors', 'wpcalibrate-watermark-studio' ); ?></h3>
					<table class="widefat striped" id="bulk-errors-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Attachment ID', 'wpcalibrate-watermark-studio' ); ?></th>
								<th><?php esc_html_e( 'Error Code', 'wpcalibrate-watermark-studio' ); ?></th>
								<th><?php esc_html_e( 'Details', 'wpcalibrate-watermark-studio' ); ?></th>
							</tr>
						</thead>
						<tbody></tbody>
					</table>
				</div>
			</div>
		</div>
		<?php
	}

	public function ajax_start(): void {
		check_ajax_referer( 'wpcalibrate_ws_bulk_nonce', 'nonce' );

		if ( ! Capabilities::can_bulk_process() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wpcalibrate-watermark-studio' ) ), 403 );
		}

		$operation = isset( $_POST['operation'] ) ? sanitize_key( (string) $_POST['operation'] ) : 'watermark';
		$target    = isset( $_POST['target'] ) ? sanitize_key( (string) $_POST['target'] ) : 'unwatermarked';

		$allowed_mimes = (array) $this->settings->get( 'allowed_mime_types', array( 'image/jpeg', 'image/png', 'image/webp' ) );

		if ( 'woocommerce' === $target ) {
			$ids = \WPCalibrate\WatermarkStudio\Plugin::instance()->get_woo_integration()->get_product_attachment_ids( 5000 );
		} else {
			if ( 'restore' === $operation ) {
				$filter = match ( $target ) {
					'watermarked' => 'watermarked',
					'all'         => null,
					default       => 'backed_up',
				};
			} else {
				$filter = match ( $target ) {
					'watermarked' => 'watermarked',
					'failed'      => 'failed',
					'all'         => null,
					default       => 'unwatermarked',
				};
			}
			$ids = $this->attachment_repository->get_eligible_attachment_ids( 5000, 0, $allowed_mimes, $filter );
		}

		if ( empty( $ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No eligible images found for selected criteria.', 'wpcalibrate-watermark-studio' ) ) );
		}

		$started = $this->batch_processor->start_queue( $ids, $operation );
		if ( ! $started ) {
			wp_send_json_error( array( 'message' => __( 'Could not start queue.', 'wpcalibrate-watermark-studio' ) ) );
		}

		$allowed_mimes = (array) $this->settings->get( 'allowed_mime_types', array( 'image/jpeg', 'image/png', 'image/webp' ) );
		$counts        = $this->attachment_repository->get_counts( $allowed_mimes );

		wp_send_json_success( array(
			'state'  => $this->batch_processor->get_state(),
			'counts' => $counts,
		) );
	}

	public function ajax_status(): void {
		check_ajax_referer( 'wpcalibrate_ws_bulk_nonce', 'nonce' );

		if ( ! Capabilities::can_bulk_process() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wpcalibrate-watermark-studio' ) ), 403 );
		}

		$allowed_mimes = (array) $this->settings->get( 'allowed_mime_types', array( 'image/jpeg', 'image/png', 'image/webp' ) );
		$counts        = $this->attachment_repository->get_counts( $allowed_mimes );

		wp_send_json_success( array(
			'state'  => $this->batch_processor->get_state(),
			'counts' => $counts,
		) );
	}

	public function ajax_cancel(): void {
		check_ajax_referer( 'wpcalibrate_ws_bulk_nonce', 'nonce' );

		if ( ! Capabilities::can_bulk_process() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wpcalibrate-watermark-studio' ) ), 403 );
		}

		$this->batch_processor->cancel_queue();
		$allowed_mimes = (array) $this->settings->get( 'allowed_mime_types', array( 'image/jpeg', 'image/png', 'image/webp' ) );
		$counts        = $this->attachment_repository->get_counts( $allowed_mimes );

		wp_send_json_success( array(
			'state'  => $this->batch_processor->get_state(),
			'counts' => $counts,
		) );
	}

	public function ajax_work(): void {
		check_ajax_referer( 'wpcalibrate_ws_bulk_nonce', 'nonce' );

		if ( ! Capabilities::can_bulk_process() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wpcalibrate-watermark-studio' ) ), 403 );
		}

		$updated_state = $this->batch_processor->process_next_batch();
		$allowed_mimes = (array) $this->settings->get( 'allowed_mime_types', array( 'image/jpeg', 'image/png', 'image/webp' ) );
		$counts        = $this->attachment_repository->get_counts( $allowed_mimes );

		wp_send_json_success( array(
			'state'  => $updated_state,
			'counts' => $counts,
		) );
	}
}

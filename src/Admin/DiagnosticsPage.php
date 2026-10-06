<?php
declare(strict_types=1);

namespace WPCalibrate\WatermarkStudio\Admin;

use WPCalibrate\WatermarkStudio\Support\Diagnostics;
use WPCalibrate\WatermarkStudio\Support\Logger;
use WPCalibrate\WatermarkStudio\Support\Capabilities;

/**
 * Class DiagnosticsPage
 *
 * Renders the System Diagnostics & Health check admin page.
 */
final class DiagnosticsPage {
	public function __construct(
		private readonly Diagnostics $diagnostics,
		private readonly Logger $logger
	) {}

	public function init(): void {
		add_action( 'wpcalibrate_watermark_studio_render_diagnostics_page', array( $this, 'render' ) );
		add_action( 'admin_post_wpcalibrate_ws_clear_logs', array( $this, 'handle_clear_logs' ) );
	}

	public function render(): void {
		if ( ! Capabilities::can_view_diagnostics() ) {
			wp_die( esc_html__( 'Unauthorized access.', 'wpcalibrate-watermark-studio' ), 403 );
		}

		$report = $this->diagnostics->get_system_report();
		$logs   = $this->logger->get_recent_logs();
		?>
		<div class="wrap wpcalibrate-admin-wrap">
			<div class="wpcalibrate-header">
				<div class="wpcalibrate-brand">
					<img src="<?php echo esc_url( WPCALIBRATE_WATERMARK_STUDIO_URL . 'branding/icon-dark.png' ); ?>" alt="WPCalibrate" class="wpcalibrate-logo" />
					<h1><?php esc_html_e( 'Watermark Studio Diagnostics & System Health', 'wpcalibrate-watermark-studio' ); ?></h1>
				</div>
			</div>

			<div class="wpcalibrate-card-grid">
				<!-- Environment -->
				<div class="wpcalibrate-card">
					<h2><?php esc_html_e( 'Environment & WordPress', 'wpcalibrate-watermark-studio' ); ?></h2>
					<table class="widefat striped">
						<tbody>
							<tr>
								<td><strong><?php esc_html_e( 'WordPress Version', 'wpcalibrate-watermark-studio' ); ?></strong></td>
								<td><?php echo esc_html( $report['environment']['wordpress_version'] ); ?></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'PHP Version', 'wpcalibrate-watermark-studio' ); ?></strong></td>
								<td><?php echo esc_html( $report['environment']['php_version'] ); ?> (<?php echo esc_html( $report['environment']['php_sapi'] ); ?>)</td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'Memory Limit', 'wpcalibrate-watermark-studio' ); ?></strong></td>
								<td><?php echo esc_html( $report['environment']['memory_limit'] ); ?></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'Max Execution Time', 'wpcalibrate-watermark-studio' ); ?></strong></td>
								<td><?php echo esc_html( $report['environment']['max_exec_time'] ); ?>s</td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'Plugin Version', 'wpcalibrate-watermark-studio' ); ?></strong></td>
								<td><?php echo esc_html( $report['environment']['plugin_version'] ); ?> (Data v<?php echo esc_html( $report['environment']['data_version'] ); ?>)</td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'Multisite', 'wpcalibrate-watermark-studio' ); ?></strong></td>
								<td><?php echo $report['environment']['multisite'] ? esc_html__( 'Yes', 'wpcalibrate-watermark-studio' ) : esc_html__( 'No', 'wpcalibrate-watermark-studio' ); ?></td>
							</tr>
						</tbody>
					</table>
				</div>

				<!-- Image Processing Capabilities -->
				<div class="wpcalibrate-card">
					<h2><?php esc_html_e( 'Image Processing Engines', 'wpcalibrate-watermark-studio' ); ?></h2>
					<table class="widefat striped">
						<tbody>
							<tr>
								<td><strong><?php esc_html_e( 'Active Render Engine', 'wpcalibrate-watermark-studio' ); ?></strong></td>
								<td><span class="badge badge-success"><?php echo esc_html( strtoupper( $report['image_editors']['active_engine'] ) ); ?></span></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'PHP GD Extension', 'wpcalibrate-watermark-studio' ); ?></strong></td>
								<td><?php echo $report['image_editors']['gd_available'] ? '✔ ' . esc_html__( 'Installed', 'wpcalibrate-watermark-studio' ) : '✖ ' . esc_html__( 'Missing', 'wpcalibrate-watermark-studio' ); ?></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'GD JPEG / PNG Support', 'wpcalibrate-watermark-studio' ); ?></strong></td>
								<td><?php echo ( $report['image_editors']['gd_jpeg'] && $report['image_editors']['gd_png'] ) ? '✔ ' . esc_html__( 'Supported', 'wpcalibrate-watermark-studio' ) : '✖'; ?></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'GD WebP Support', 'wpcalibrate-watermark-studio' ); ?></strong></td>
								<td><?php echo $report['image_editors']['gd_webp'] ? '✔ ' . esc_html__( 'Supported', 'wpcalibrate-watermark-studio' ) : '✖ ' . esc_html__( 'Not supported by GD build', 'wpcalibrate-watermark-studio' ); ?></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'GD AVIF Support', 'wpcalibrate-watermark-studio' ); ?></strong></td>
								<td><?php echo $report['image_editors']['gd_avif'] ? '✔ ' . esc_html__( 'Supported', 'wpcalibrate-watermark-studio' ) : '✖ ' . esc_html__( 'Not supported by GD build', 'wpcalibrate-watermark-studio' ); ?></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'Imagick Extension', 'wpcalibrate-watermark-studio' ); ?></strong></td>
								<td><?php echo $report['image_editors']['imagick_available'] ? '✔ ' . esc_html__( 'Available', 'wpcalibrate-watermark-studio' ) : esc_html__( 'Not Installed (Optional)', 'wpcalibrate-watermark-studio' ); ?></td>
							</tr>
						</tbody>
					</table>
				</div>

				<!-- Storage Protection -->
				<div class="wpcalibrate-card">
					<h2><?php esc_html_e( 'Storage & Protection', 'wpcalibrate-watermark-studio' ); ?></h2>
					<table class="widefat striped">
						<tbody>
							<tr>
								<td><strong><?php esc_html_e( 'Uploads Directory Writable', 'wpcalibrate-watermark-studio' ); ?></strong></td>
								<td><?php echo $report['storage']['upload_dir_writable'] ? '✔ ' . esc_html__( 'Writable', 'wpcalibrate-watermark-studio' ) : '<span style="color:#a00;">✖ ' . esc_html__( 'Not Writable', 'wpcalibrate-watermark-studio' ) . '</span>'; ?></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'Protected Originals Directory', 'wpcalibrate-watermark-studio' ); ?></strong></td>
								<td><?php echo $report['storage']['backup_dir_writable'] ? '✔ ' . esc_html__( 'Secured & Writable', 'wpcalibrate-watermark-studio' ) : '<span style="color:#a00;">✖ ' . esc_html__( 'Not Writable', 'wpcalibrate-watermark-studio' ) . '</span>'; ?></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'Originals Relative Path', 'wpcalibrate-watermark-studio' ); ?></strong></td>
								<td><code><?php echo esc_html( $report['storage']['backup_rel_location'] ); ?></code></td>
							</tr>
						</tbody>
					</table>
				</div>

				<!-- Queue & Scheduler -->
				<div class="wpcalibrate-card">
					<h2><?php esc_html_e( 'Background Scheduler & WooCommerce', 'wpcalibrate-watermark-studio' ); ?></h2>
					<table class="widefat striped">
						<tbody>
							<tr>
								<td><strong><?php esc_html_e( 'Scheduler Engine', 'wpcalibrate-watermark-studio' ); ?></strong></td>
								<td><code><?php echo esc_html( $report['scheduler']['scheduler_type'] ); ?></code></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'Action Scheduler Present', 'wpcalibrate-watermark-studio' ); ?></strong></td>
								<td><?php echo $report['scheduler']['action_scheduler_on'] ? '✔ ' . esc_html__( 'Active', 'wpcalibrate-watermark-studio' ) : esc_html__( 'No (Using native WP-Cron)', 'wpcalibrate-watermark-studio' ); ?></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'WP-Cron Disabled Constant', 'wpcalibrate-watermark-studio' ); ?></strong></td>
								<td><?php echo $report['scheduler']['wp_cron_disabled'] ? esc_html__( 'Yes (Requires system cron)', 'wpcalibrate-watermark-studio' ) : esc_html__( 'No (Standard traffic-driven cron)', 'wpcalibrate-watermark-studio' ); ?></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'WooCommerce Status', 'wpcalibrate-watermark-studio' ); ?></strong></td>
								<td><?php echo $report['woocommerce']['is_active'] ? '✔ ' . sprintf( esc_html__( 'Active v%s (HPOS declared)', 'wpcalibrate-watermark-studio' ), esc_html( $report['woocommerce']['version'] ) ) : esc_html__( 'Not Active (Optional)', 'wpcalibrate-watermark-studio' ); ?></td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>

			<!-- Recent Diagnostic Logs -->
			<div class="wpcalibrate-card" style="margin-top:20px;">
				<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
					<h2 style="margin:0;"><?php esc_html_e( 'Recent Diagnostic Logs', 'wpcalibrate-watermark-studio' ); ?></h2>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'wpcalibrate_ws_clear_logs', '_ws_log_nonce' ); ?>
						<input type="hidden" name="action" value="wpcalibrate_ws_clear_logs" />
						<button type="submit" class="button button-secondary" onclick="return confirm('<?php esc_attr_e( 'Clear diagnostic logs?', 'wpcalibrate-watermark-studio' ); ?>');">
							<?php esc_html_e( 'Clear Diagnostic Logs', 'wpcalibrate-watermark-studio' ); ?>
						</button>
					</form>
				</div>
				<?php if ( empty( $logs ) ) : ?>
					<p style="color:#666;"><?php esc_html_e( 'No recent diagnostic logs recorded.', 'wpcalibrate-watermark-studio' ); ?></p>
				<?php else : ?>
					<div class="wpcalibrate-log-viewer">
						<table class="widefat striped">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Timestamp (UTC)', 'wpcalibrate-watermark-studio' ); ?></th>
									<th><?php esc_html_e( 'Level', 'wpcalibrate-watermark-studio' ); ?></th>
									<th><?php esc_html_e( 'Message', 'wpcalibrate-watermark-studio' ); ?></th>
									<th><?php esc_html_e( 'Context Details', 'wpcalibrate-watermark-studio' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $logs as $entry ) : ?>
									<tr>
										<td><code><?php echo esc_html( $entry['date'] ?? '' ); ?></code></td>
										<td>
											<span class="log-level-badge log-level-<?php echo esc_attr( strtolower( (string) ( $entry['level'] ?? '' ) ) ); ?>">
												<?php echo esc_html( $entry['level'] ?? '' ); ?>
											</span>
										</td>
										<td><?php echo esc_html( $entry['message'] ?? '' ); ?></td>
										<td><code><?php echo esc_html( wp_json_encode( $entry['context'] ?? array() ) ); ?></code></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	public function handle_clear_logs(): void {
		if ( ! Capabilities::can_view_diagnostics() ) {
			wp_die( esc_html__( 'Unauthorized.', 'wpcalibrate-watermark-studio' ), 403 );
		}

		check_admin_referer( 'wpcalibrate_ws_clear_logs', '_ws_log_nonce' );
		$this->logger->clear_logs();

		wp_safe_redirect( admin_url( 'admin.php?page=' . Menu::DIAGNOSTICS_SLUG . '&cleared=1' ) );
		exit;
	}
}

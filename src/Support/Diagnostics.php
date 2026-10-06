<?php
declare(strict_types=1);

namespace WPCalibrate\WatermarkStudio\Support;

use WPCalibrate\WatermarkStudio\Plugin;
use WPCalibrate\WatermarkStudio\Settings\Settings;
use WPCalibrate\WatermarkStudio\Media\OriginalManager;
use WPCalibrate\WatermarkStudio\Queue\BatchProcessor;
use WPCalibrate\WatermarkStudio\Watermark\EditorRendererAdapter;

/**
 * Class Diagnostics
 *
 * Compiles system, environment, storage, editor, and queue health diagnostics.
 */
final class Diagnostics {
	public function __construct(
		private readonly Settings $settings,
		private readonly OriginalManager $original_manager,
		private readonly BatchProcessor $batch_processor
	) {}

	/**
	 * Compile full diagnostic report array.
	 *
	 * @return array<string, mixed>
	 */
	public function get_system_report(): array {
		global $wp_version;

		$upload_dir = wp_upload_dir();
		$backup_dir = OriginalManager::get_storage_basedir();
		$adapter    = new EditorRendererAdapter();
		$gd         = $adapter->get_gd_renderer();
		$imagick    = $adapter->get_imagick_renderer();

		$cron_disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
		$alternate_cron = defined( 'ALTERNATE_WP_CRON' ) && ALTERNATE_WP_CRON;

		$queue_state = $this->batch_processor->get_state();

		return array(
			'environment' => array(
				'wordpress_version' => $wp_version ?? 'Unknown',
				'php_version'       => PHP_VERSION,
				'php_sapi'          => PHP_SAPI,
				'memory_limit'      => ini_get( 'memory_limit' ),
				'max_exec_time'     => ini_get( 'max_execution_time' ),
				'plugin_version'    => WPCALIBRATE_WATERMARK_STUDIO_VERSION,
				'data_version'      => get_option( Plugin::OPTION_DATA_VERSION, '1.0.0' ),
				'multisite'         => is_multisite(),
			),
			'image_editors' => array(
				'gd_available'      => $gd->is_supported(),
				'gd_jpeg'           => $gd->supports_mime_type( 'image/jpeg' ),
				'gd_png'            => $gd->supports_mime_type( 'image/png' ),
				'gd_webp'           => $gd->supports_mime_type( 'image/webp' ),
				'gd_avif'           => $gd->supports_mime_type( 'image/avif' ),
				'imagick_available' => $imagick->is_supported(),
				'imagick_version'   => extension_loaded( 'imagick' ) && class_exists( 'Imagick' ) ? \Imagick::getVersion()['versionString'] : 'Not Installed',
				'active_engine'     => $adapter->get_renderer()->get_name(),
			),
			'storage' => array(
				'upload_dir_writable'  => wp_is_writable( $upload_dir['basedir'] ),
				'upload_basedir'       => '[ABSPATH]/' . ltrim( str_replace( ABSPATH, '', Filesystem::normalize_path( $upload_dir['basedir'] ) ), '/' ),
				'backup_dir_exists'    => is_dir( $backup_dir ),
				'backup_dir_writable'  => wp_is_writable( $backup_dir ),
				'backup_rel_location'  => OriginalManager::BACKUP_SUBDIR,
			),
			'scheduler' => array(
				'scheduler_type'       => $this->batch_processor->get_scheduler()->get_name(),
				'action_scheduler_on'  => class_exists( 'ActionScheduler' ) || function_exists( 'as_schedule_single_action' ),
				'wp_cron_disabled'     => $cron_disabled,
				'alternate_wp_cron'    => $alternate_cron,
				'queue_status'         => $queue_state['status'],
				'queue_total'          => $queue_state['total_items'],
				'queue_remaining'      => count( $queue_state['remaining_ids'] ),
			),
			'woocommerce' => array(
				'is_active' => class_exists( 'WooCommerce' ) || function_exists( 'WC' ),
				'version'   => defined( 'WC_VERSION' ) ? WC_VERSION : 'N/A',
			),
		);
	}
}

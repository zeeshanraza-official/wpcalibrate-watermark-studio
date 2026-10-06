<?php
/**
 * WPCalibrate Watermark Studio - Uninstall Handler
 *
 * Safe, explicit data-retention enforcement upon plugin deletion.
 *
 * @package WPCalibrate\WatermarkStudio
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Load autoloader for clean class access
require_once plugin_dir_path( __FILE__ ) . 'src/Autoloader.php';
\WPCalibrate\WatermarkStudio\Autoloader::register( plugin_dir_path( __FILE__ ) . 'src/' );

$settings_option = get_option( \WPCalibrate\WatermarkStudio\Settings\Defaults::OPTION_NAME, array() );
$full_cleanup    = is_array( $settings_option ) && ! empty( $settings_option['uninstall_cleanup'] );

// 1. Always unschedule cron events and release locks
\WPCalibrate\WatermarkStudio\Queue\CronBatchScheduler::unschedule_all();
\WPCalibrate\WatermarkStudio\Queue\JobLock::release( 'batch_processor' );

// 2. Only perform destructive cleanup if administrator explicitly opted in
if ( $full_cleanup ) {
	global $wpdb;

	// Remove plugin options
	delete_option( \WPCalibrate\WatermarkStudio\Settings\Defaults::OPTION_NAME );
	delete_option( \WPCalibrate\WatermarkStudio\Plugin::OPTION_DATA_VERSION );
	delete_option( \WPCalibrate\WatermarkStudio\Queue\BatchProcessor::OPTION_QUEUE_STATE );
	delete_option( \WPCalibrate\WatermarkStudio\Support\Logger::OPTION_LOGS );

	// Remove all attachment post meta strictly prefixed with _wpcalibrate_ws_
	$meta_keys = array(
		\WPCalibrate\WatermarkStudio\Media\AttachmentRepository::META_HAS_BACKUP,
		\WPCalibrate\WatermarkStudio\Media\AttachmentRepository::META_BACKUP_REL_PATH,
		\WPCalibrate\WatermarkStudio\Media\AttachmentRepository::META_WATERMARKED,
		\WPCalibrate\WatermarkStudio\Media\AttachmentRepository::META_FINGERPRINT,
		\WPCalibrate\WatermarkStudio\Media\AttachmentRepository::META_PROCESSED_AT,
		\WPCalibrate\WatermarkStudio\Media\AttachmentRepository::META_STATUS,
		\WPCalibrate\WatermarkStudio\Media\AttachmentRepository::META_ERROR_CODE,
		\WPCalibrate\WatermarkStudio\Media\AttachmentRepository::META_ERROR_MSG,
		\WPCalibrate\WatermarkStudio\Media\AttachmentRepository::META_VERSION,
		\WPCalibrate\WatermarkStudio\Media\AttachmentRepository::META_ENGINE,
	);

	foreach ( $meta_keys as $key ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => $key ), array( '%s' ) );
	}

	// Purge protected originals folder
	\WPCalibrate\WatermarkStudio\Media\OriginalManager::purge_all_backups();
}

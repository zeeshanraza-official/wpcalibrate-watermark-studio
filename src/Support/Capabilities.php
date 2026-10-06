<?php
declare(strict_types=1);

namespace WPCalibrate\WatermarkStudio\Support;

/**
 * Class Capabilities
 *
 * Centralizes permission checks and capability constants across the plugin.
 */
final class Capabilities {
	public const CAP_MANAGE_SETTINGS = 'manage_options';
	public const CAP_BULK_PROCESS    = 'manage_options';
	public const CAP_DIAGNOSTICS     = 'manage_options';
	public const CAP_UPLOAD_MEDIA    = 'upload_files';

	/**
	 * Check if current user can manage plugin settings.
	 */
	public static function can_manage_settings(): bool {
		return current_user_can( self::CAP_MANAGE_SETTINGS );
	}

	/**
	 * Check if current user can process or restore a specific attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	public static function can_watermark_attachment( int $attachment_id ): bool {
		if ( ! current_user_can( self::CAP_UPLOAD_MEDIA ) ) {
			return false;
		}

		if ( $attachment_id <= 0 ) {
			return false;
		}

		return current_user_can( 'edit_post', $attachment_id );
	}

	/**
	 * Check if current user can trigger bulk operations.
	 */
	public static function can_bulk_process(): bool {
		return current_user_can( self::CAP_BULK_PROCESS );
	}

	/**
	 * Check if current user can view system diagnostics.
	 */
	public static function can_view_diagnostics(): bool {
		return current_user_can( self::CAP_DIAGNOSTICS );
	}
}

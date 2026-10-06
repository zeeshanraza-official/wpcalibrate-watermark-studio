<?php
declare(strict_types=1);

namespace WPCalibrate\WatermarkStudio;

use WPCalibrate\WatermarkStudio\Settings\Defaults;
use WPCalibrate\WatermarkStudio\Media\OriginalManager;

/**
 * Class Activation
 *
 * Handles activation tasks such as option initialization, folder provisioning, and requirement checks.
 */
final class Activation {
	/**
	 * Perform plugin activation.
	 *
	 * @param bool $network_wide Whether activated across the multisite network.
	 */
	public static function activate( bool $network_wide = false ): void {
		// Check PHP and WP version requirements.
		if ( version_compare( PHP_VERSION, WPCALIBRATE_WATERMARK_STUDIO_REQUIRED_PHP, '<' ) ) {
			deactivate_plugins( WPCALIBRATE_WATERMARK_STUDIO_BASENAME );
			wp_die(
				sprintf(
					/* translators: 1: Plugin name, 2: Minimum PHP version, 3: Current PHP version */
					esc_html__( '%1$s requires PHP %2$s or higher. Your server is running PHP %3$s.', 'wpcalibrate-watermark-studio' ),
					'<strong>WPCalibrate Watermark Studio</strong>',
					esc_html( WPCALIBRATE_WATERMARK_STUDIO_REQUIRED_PHP ),
					esc_html( PHP_VERSION )
				)
			);
		}

		if ( function_exists( 'is_multisite' ) && is_multisite() && $network_wide ) {
			$sites = get_sites( array( 'fields' => 'ids' ) );
			foreach ( $sites as $site_id ) {
				switch_to_blog( (int) $site_id );
				self::activate_single_site();
				restore_current_blog();
			}
		} else {
			self::activate_single_site();
		}
	}

	/**
	 * Activate for a single site / blog.
	 */
	private static function activate_single_site(): void {
		// 1. Initialize default options if not already present.
		$existing_settings = get_option( Defaults::OPTION_NAME, null );
		if ( null === $existing_settings ) {
			add_option( Defaults::OPTION_NAME, Defaults::get_all(), '', 'no' );
		}

		// 2. Initialize data schema version if not present.
		$installed_version = get_option( Plugin::OPTION_DATA_VERSION, null );
		if ( null === $installed_version ) {
			add_option( Plugin::OPTION_DATA_VERSION, Plugin::DATA_VERSION, '', 'no' );
		}

		// 3. Ensure protected original storage directory exists and is secured.
		OriginalManager::ensure_storage_directory();
	}
}

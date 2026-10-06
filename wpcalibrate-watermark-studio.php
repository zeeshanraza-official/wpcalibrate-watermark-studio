<?php
/**
 * Plugin Name:       WPCalibrate Watermark Studio
 * Plugin URI:        https://marketplace.wpcalibrate.com/
 * Description:       Professional image and text watermarking studio for WordPress Media Library and WooCommerce product images with safe original preservation, automatic processing, and bulk queue management.
 * Version:           1.0.2
 * Requires at least: 6.8
 * Requires PHP:      8.2
 * Author:            WPCalibrate
 * Author URI:        https://wpcalibrate.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpcalibrate-watermark-studio
 * Domain Path:       /languages
 * GitHub Plugin URI: zeeshanraza-official/wpcalibrate-watermark-studio
 * Primary Branch:    main
 *
 * @package           WPCalibrate\WatermarkStudio
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'WPCALIBRATE_WATERMARK_STUDIO_VERSION', '1.0.2' );
define( 'WPCALIBRATE_WATERMARK_STUDIO_REQUIRED_PHP', '8.2' );
define( 'WPCALIBRATE_WATERMARK_STUDIO_REQUIRED_WP', '6.8' );
define( 'WPCALIBRATE_WATERMARK_STUDIO_FILE', __FILE__ );
define( 'WPCALIBRATE_WATERMARK_STUDIO_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPCALIBRATE_WATERMARK_STUDIO_URL', plugin_dir_url( __FILE__ ) );
define( 'WPCALIBRATE_WATERMARK_STUDIO_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check environmental requirements before booting plugin.
 *
 * @return bool True if environment meets requirements, false otherwise.
 */
function wpcalibrate_watermark_studio_check_requirements(): bool {
	global $wp_version;

	$php_min = WPCALIBRATE_WATERMARK_STUDIO_REQUIRED_PHP;
	$wp_min  = WPCALIBRATE_WATERMARK_STUDIO_REQUIRED_WP;

	if ( version_compare( PHP_VERSION, $php_min, '<' ) ) {
		add_action(
			'admin_notices',
			static function () use ( $php_min ) {
				printf(
					'<div class="notice notice-error"><p>%s</p></div>',
					sprintf(
						/* translators: 1: Plugin name, 2: Required PHP version, 3: Current PHP version */
						esc_html__( '%1$s requires PHP version %2$s or higher. Your server is running PHP %3$s. Please upgrade PHP to activate and use this plugin.', 'wpcalibrate-watermark-studio' ),
						'<strong>WPCalibrate Watermark Studio</strong>',
						esc_html( $php_min ),
						esc_html( PHP_VERSION )
					)
				);
			}
		);
		return false;
	}

	if ( isset( $wp_version ) && version_compare( $wp_version, $wp_min, '<' ) ) {
		add_action(
			'admin_notices',
			static function () use ( $wp_min, $wp_version ) {
				printf(
					'<div class="notice notice-error"><p>%s</p></div>',
					sprintf(
						/* translators: 1: Plugin name, 2: Required WordPress version, 3: Current WordPress version */
						esc_html__( '%1$s requires WordPress version %2$s or higher. Your site is running WordPress %3$s. Please update WordPress to use this plugin.', 'wpcalibrate-watermark-studio' ),
						'<strong>WPCalibrate Watermark Studio</strong>',
						esc_html( $wp_min ),
						esc_html( $wp_version )
					)
				);
			}
		);
		return false;
	}

	return true;
}

// Ensure requirements are met.
if ( ! wpcalibrate_watermark_studio_check_requirements() ) {
	return;
}

// Register PSR-4 autoloader.
require_once WPCALIBRATE_WATERMARK_STUDIO_DIR . 'src/Autoloader.php';
\WPCalibrate\WatermarkStudio\Autoloader::register( WPCALIBRATE_WATERMARK_STUDIO_DIR . 'src/' );

// Register activation, deactivation, and uninstall hooks.
register_activation_hook(
	__FILE__,
	static function ( bool $network_wide = false ): void {
		\WPCalibrate\WatermarkStudio\Activation::activate( $network_wide );
	}
);

register_deactivation_hook(
	__FILE__,
	static function ( bool $network_wide = false ): void {
		\WPCalibrate\WatermarkStudio\Deactivation::deactivate( $network_wide );
	}
);

// Boot plugin lifecycle.
add_action(
	'plugins_loaded',
	static function (): void {
		\WPCalibrate\WatermarkStudio\Plugin::instance()->boot();
	}
);

<?php
declare(strict_types=1);

namespace WPCalibrate\WatermarkStudio\Admin;

use WPCalibrate\WatermarkStudio\Support\Capabilities;

/**
 * Class Menu
 *
 * Manages WordPress admin menu integration, safely creating or reusing the parent 'WPCalibrate' menu
 * and ensuring complete isolation between WPCalibrate plugins.
 */
final class Menu {
	/**
	 * Top-level menu slug for WPCalibrate suite.
	 *
	 * @var string
	 */
	public const PARENT_SLUG = 'wpcalibrate';

	/**
	 * Main settings submenu slug for Watermark Studio.
	 *
	 * @var string
	 */
	public const SETTINGS_SLUG = 'wpcalibrate-watermark-studio';

	/**
	 * Bulk processing submenu slug.
	 *
	 * @var string
	 */
	public const BULK_SLUG = 'wpcalibrate-watermark-studio-bulk';

	/**
	 * Diagnostics submenu slug.
	 *
	 * @var string
	 */
	public const DIAGNOSTICS_SLUG = 'wpcalibrate-watermark-studio-diagnostics';

	/**
	 * Hook admin menu.
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'register_menus' ), 9 );
		add_action( 'admin_head', array( $this, 'style_menu_icon' ) );
	}

	/**
	 * Register WPCalibrate top-level menu if absent, and register Watermark Studio submenus.
	 */
	public function register_menus(): void {
		if ( ! Capabilities::can_manage_settings() ) {
			return;
		}

		global $menu;

		// 1. Check if parent menu already exists.
		$parent_exists = false;
		if ( is_array( $menu ) ) {
			foreach ( $menu as $item ) {
				if ( isset( $item[2] ) && self::PARENT_SLUG === $item[2] ) {
					$parent_exists = true;
					break;
				}
			}
		}

		// 2. Register parent menu if no other WPCalibrate plugin has created it yet.
		if ( ! $parent_exists ) {
			$icon_url = WPCALIBRATE_WATERMARK_STUDIO_URL . 'branding/icon-white.png';
			add_menu_page(
				'WPCalibrate',
				'WPCalibrate',
				Capabilities::CAP_MANAGE_SETTINGS,
				self::PARENT_SLUG,
				array( $this, 'render_parent_fallback' ),
				$icon_url,
				58.2
			);

			// Rename first auto-generated submenu item to Watermark Studio.
			add_submenu_page(
				self::PARENT_SLUG,
				__( 'WPCalibrate Watermark Studio', 'wpcalibrate-watermark-studio' ),
				__( 'Watermark Studio', 'wpcalibrate-watermark-studio' ),
				Capabilities::CAP_MANAGE_SETTINGS,
				self::SETTINGS_SLUG,
				array( $this, 'route_settings_page' )
			);

			// Remove the redundant duplicate 'WPCalibrate' submenu that WP auto-creates.
			remove_submenu_page( self::PARENT_SLUG, self::PARENT_SLUG );
		} else {
			// Parent menu exists from another WPCalibrate plugin; add our submenu item.
			add_submenu_page(
				self::PARENT_SLUG,
				__( 'WPCalibrate Watermark Studio', 'wpcalibrate-watermark-studio' ),
				__( 'Watermark Studio', 'wpcalibrate-watermark-studio' ),
				Capabilities::CAP_MANAGE_SETTINGS,
				self::SETTINGS_SLUG,
				array( $this, 'route_settings_page' )
			);
		}

		// 3. Register Bulk Processing submenu.
		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Watermark Bulk Processing - WPCalibrate', 'wpcalibrate-watermark-studio' ),
			__( 'Bulk Processing', 'wpcalibrate-watermark-studio' ),
			Capabilities::CAP_MANAGE_SETTINGS,
			self::BULK_SLUG,
			array( $this, 'route_bulk_page' )
		);

		// 4. Register Diagnostics submenu.
		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Watermark Diagnostics - WPCalibrate', 'wpcalibrate-watermark-studio' ),
			__( 'Diagnostics', 'wpcalibrate-watermark-studio' ),
			Capabilities::CAP_MANAGE_SETTINGS,
			self::DIAGNOSTICS_SLUG,
			array( $this, 'route_diagnostics_page' )
		);
	}

	/**
	 * Render fallback if top-level parent is directly accessed.
	 */
	public function render_parent_fallback(): void {
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SETTINGS_SLUG ) );
		exit;
	}

	/**
	 * Route to Settings page.
	 */
	public function route_settings_page(): void {
		\WPCalibrate\WatermarkStudio\Plugin::instance()->get_settings();
		// The SettingsPage controller renders the view.
		do_action( 'wpcalibrate_watermark_studio_render_settings_page' );
	}

	/**
	 * Route to Bulk Processing page.
	 */
	public function route_bulk_page(): void {
		do_action( 'wpcalibrate_watermark_studio_render_bulk_page' );
	}

	/**
	 * Route to Diagnostics page.
	 */
	public function route_diagnostics_page(): void {
		do_action( 'wpcalibrate_watermark_studio_render_diagnostics_page' );
	}

	/**
	 * Output inline styling for the custom WPCalibrate admin menu icon to prevent distortion.
	 */
	public function style_menu_icon(): void {
		?>
		<style id="wpcalibrate-menu-icon-style">
			#toplevel_page_wpcalibrate .wp-menu-image {
				display: flex !important;
				align-items: center !important;
				justify-content: center !important;
			}
			#toplevel_page_wpcalibrate .wp-menu-image img {
				width: 20px !important;
				height: 20px !important;
				max-width: 20px !important;
				max-height: 20px !important;
				padding: 0 !important;
				margin: 0 !important;
				box-sizing: content-box !important;
				object-fit: contain !important;
				vertical-align: middle !important;
				opacity: 0.85;
				transition: opacity 0.15s ease-in-out;
			}
			#toplevel_page_wpcalibrate:hover .wp-menu-image img,
			#toplevel_page_wpcalibrate.wp-has-current-submenu .wp-menu-image img,
			#toplevel_page_wpcalibrate.current .wp-menu-image img {
				opacity: 1 !important;
			}
		</style>
		<?php
	}
}

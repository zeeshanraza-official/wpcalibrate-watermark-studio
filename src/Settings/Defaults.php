<?php
declare(strict_types=1);

namespace WPCalibrate\WatermarkStudio\Settings;

/**
 * Class Defaults
 *
 * Defines canonical option names and default settings for WPCalibrate Watermark Studio.
 */
final class Defaults {
	/**
	 * Main option key in wp_options.
	 */
	public const OPTION_NAME = 'wpcalibrate_watermark_studio_settings';

	/**
	 * Settings group for the Settings API.
	 */
	public const SETTINGS_GROUP = 'wpcalibrate_watermark_studio_group';

	/**
	 * Valid watermark positions.
	 */
	public const POSITIONS = array(
		'top_left'      => 'Top Left',
		'top_center'    => 'Top Center',
		'top_right'     => 'Top Right',
		'center_left'   => 'Center Left',
		'center'        => 'Center',
		'center_right'  => 'Center Right',
		'bottom_left'   => 'Bottom Left',
		'bottom_center' => 'Bottom Center',
		'bottom_right'  => 'Bottom Right',
	);

	/**
	 * Valid watermark modes.
	 */
	public const MODES = array(
		'image' => 'Image Only',
		'text'  => 'Text Only',
		'both'  => 'Image and Text',
	);

	/**
	 * Return all default configuration values.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_all(): array {
		return array(
			// General
			'enable_automatic'            => false,
			'watermark_mode'              => 'image',
			'min_source_width'            => 300,
			'min_source_height'           => 300,
			'allowed_mime_types'          => array( 'image/jpeg', 'image/png', 'image/webp' ),
			'preserve_originals'          => true, // Strictly enforced
			'uninstall_cleanup'           => false,
			'logging_enabled'             => false,

			// Image Watermark
			'image_attachment_id'         => 0,
			'image_url'                   => '',
			'image_relative_width'        => 25,
			'image_max_width_px'          => 0,
			'image_opacity'               => 80,
			'image_position'              => 'bottom_right',
			'image_offset_x'              => 20,
			'image_offset_y'              => 20,
			'image_maintain_aspect_ratio' => true,
			'image_prevent_upscale'       => true,

			// Text Watermark
			'text_content'                => '© WPCalibrate',
			'text_font_size'              => 24,
			'text_font_size_strategy'     => 'proportional',
			'text_proportional_scale'     => 5, // 5% of target width
			'text_color'                  => '#ffffff',
			'text_opacity'                => 80,
			'text_position'               => 'bottom_left',
			'text_offset_x'               => 20,
			'text_offset_y'               => 20,
			'text_shadow_enabled'         => true,
			'text_shadow_color'           => '#000000',

			// Image Sizes
			'target_sizes'                => array( 'large', 'medium_large' ),
			'include_full_size'           => false,

			// Automation
			'auto_target_context'         => 'all',
			'auto_post_types'             => array( 'post', 'page' ),

			// WooCommerce
			'woo_target_featured'         => true,
			'woo_target_gallery'          => true,
			'woo_target_variations'       => true,

			// Licensing
			'license_key'                 => '',
			'license_status'              => 'unconfigured',
		);
	}
}

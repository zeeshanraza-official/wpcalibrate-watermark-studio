<?php
declare(strict_types=1);

namespace WPCalibrate\WatermarkStudio\Media;

/**
 * Class ImageSizeRegistry
 *
 * Dynamically queries and discovers registered WordPress image sizes, their dimensions, and crop modes.
 */
final class ImageSizeRegistry {
	/**
	 * Get all registered image sizes with human-readable labels and dimensions.
	 *
	 * @return array<string, array{name: string, width: int, height: int, crop: bool, is_system: bool}>
	 */
	public static function get_registered_sizes(): array {
		global $_wp_additional_image_sizes;

		$sizes            = array();
		$core_sizes       = array( 'thumbnail', 'medium', 'medium_large', 'large' );
		$additional_sizes = is_array( $_wp_additional_image_sizes ) ? $_wp_additional_image_sizes : array();

		// Core standard sizes
		foreach ( $core_sizes as $size_key ) {
			$sizes[ $size_key ] = array(
				'name'      => ucwords( str_replace( array( '_', '-' ), ' ', $size_key ) ),
				'width'     => (int) get_option( "{$size_key}_size_w", 0 ),
				'height'    => (int) get_option( "{$size_key}_size_h", 0 ),
				'crop'      => (bool) get_option( "{$size_key}_crop", false ),
				'is_system' => true,
			);
		}

		// Full-size master
		$sizes['full'] = array(
			'name'      => __( 'Original Full Size', 'wpcalibrate-watermark-studio' ),
			'width'     => 0, // Natural dimensions
			'height'    => 0,
			'crop'      => false,
			'is_system' => true,
		);

		// Additional theme, plugin, and WooCommerce registered sizes
		foreach ( $additional_sizes as $size_key => $size_meta ) {
			$sizes[ $size_key ] = array(
				'name'      => ucwords( str_replace( array( '_', '-' ), ' ', (string) $size_key ) ),
				'width'     => (int) ( $size_meta['width'] ?? 0 ),
				'height'    => (int) ( $size_meta['height'] ?? 0 ),
				'crop'      => ! empty( $size_meta['crop'] ),
				'is_system' => false,
			);
		}

		return $sizes;
	}
}

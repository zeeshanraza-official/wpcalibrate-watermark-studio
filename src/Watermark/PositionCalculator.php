<?php
declare(strict_types=1);

namespace WPCalibrate\WatermarkStudio\Watermark;

/**
 * Class PositionCalculator
 *
 * Pure calculation service for watermark positioning, scaling, aspect ratio preservation,
 * and boundary calculations across the nine standard positions.
 */
final class PositionCalculator {
	/**
	 * Calculate scaled dimensions for an element against destination image dimensions.
	 *
	 * @param int  $dest_w Destination image width.
	 * @param int  $dest_h Destination image height.
	 * @param int  $elem_natural_w Watermark element natural width.
	 * @param int  $elem_natural_h Watermark element natural height.
	 * @param int  $percentage Relative width as percentage of destination image (1..100).
	 * @param int  $max_width_px Optional maximum pixel width cap (0 = uncapped).
	 * @param bool $prevent_upscale If true, element will never exceed its natural dimensions.
	 * @return array{width: int, height: int}
	 */
	public static function calculate_dimensions(
		int $dest_w,
		int $dest_h,
		int $elem_natural_w,
		int $elem_natural_h,
		int $percentage = 25,
		int $max_width_px = 0,
		bool $prevent_upscale = true
	): array {
		if ( $dest_w <= 0 || $dest_h <= 0 || $elem_natural_w <= 0 || $elem_natural_h <= 0 ) {
			return array( 'width' => 0, 'height' => 0 );
		}

		// Calculate target width as percentage of destination width
		$pct          = max( 1, min( 100, $percentage ) );
		$target_width = (int) round( ( $dest_w * $pct ) / 100 );

		// Apply max width cap if specified
		if ( $max_width_px > 0 && $target_width > $max_width_px ) {
			$target_width = $max_width_px;
		}

		// Prevent upscaling beyond natural size if enabled
		if ( $prevent_upscale && $target_width > $elem_natural_w ) {
			$target_width = $elem_natural_w;
		}

		// Keep aspect ratio
		$aspect_ratio  = $elem_natural_w / $elem_natural_h;
		$target_height = (int) round( $target_width / $aspect_ratio );

		// Ensure target does not exceed destination bounds
		if ( $target_width > $dest_w ) {
			$target_width  = $dest_w;
			$target_height = (int) round( $target_width / $aspect_ratio );
		}
		if ( $target_height > $dest_h ) {
			$target_height = $dest_h;
			$target_width  = (int) round( $target_height * $aspect_ratio );
		}

		return array(
			'width'  => max( 1, $target_width ),
			'height' => max( 1, $target_height ),
		);
	}

	/**
	 * Calculate X and Y coordinates for element on destination canvas based on the 9 positions and offsets.
	 *
	 * @param int    $dest_w Canvas width.
	 * @param int    $dest_h Canvas height.
	 * @param int    $elem_w Element width.
	 * @param int    $elem_h Element height.
	 * @param string $position One of the 9 position identifiers.
	 * @param int    $offset_x Horizontal pixel offset.
	 * @param int    $offset_y Vertical pixel offset.
	 * @return array{x: int, y: int}
	 */
	public static function calculate_coordinates(
		int $dest_w,
		int $dest_h,
		int $elem_w,
		int $elem_h,
		string $position,
		int $offset_x = 0,
		int $offset_y = 0
	): array {
		$x = 0;
		$y = 0;

		switch ( $position ) {
			case 'top_left':
				$x = $offset_x;
				$y = $offset_y;
				break;

			case 'top_center':
				$x = (int) round( ( $dest_w - $elem_w ) / 2 ) + $offset_x;
				$y = $offset_y;
				break;

			case 'top_right':
				$x = $dest_w - $elem_w - $offset_x;
				$y = $offset_y;
				break;

			case 'center_left':
				$x = $offset_x;
				$y = (int) round( ( $dest_h - $elem_h ) / 2 ) + $offset_y;
				break;

			case 'center':
				$x = (int) round( ( $dest_w - $elem_w ) / 2 ) + $offset_x;
				$y = (int) round( ( $dest_h - $elem_h ) / 2 ) + $offset_y;
				break;

			case 'center_right':
				$x = $dest_w - $elem_w - $offset_x;
				$y = (int) round( ( $dest_h - $elem_h ) / 2 ) + $offset_y;
				break;

			case 'bottom_left':
				$x = $offset_x;
				$y = $dest_h - $elem_h - $offset_y;
				break;

			case 'bottom_center':
				$x = (int) round( ( $dest_w - $elem_w ) / 2 ) + $offset_x;
				$y = $dest_h - $elem_h - $offset_y;
				break;

			case 'bottom_right':
			default:
				$x = $dest_w - $elem_w - $offset_x;
				$y = $dest_h - $elem_h - $offset_y;
				break;
		}

		// Ensure watermark coordinates stay safely within destination canvas bounds
		$max_x = max( 0, $dest_w - $elem_w );
		$max_y = max( 0, $dest_h - $elem_h );

		$x = max( 0, min( $max_x, $x ) );
		$y = max( 0, min( $max_y, $y ) );

		return array(
			'x' => $x,
			'y' => $y,
		);
	}
}

<?php
declare(strict_types=1);

namespace WPCalibrate\WatermarkStudio\Watermark;

use WPCalibrate\WatermarkStudio\Support\Filesystem;
use WP_Error;

/**
 * Class GdRenderer
 *
 * GD-based implementation of watermark compositing.
 * Provides high-fidelity rendering, true 32-bit alpha compositing, proportional scaling,
 * and text watermarking using the bundled Open Font License typeface.
 */
final class GdRenderer implements RendererInterface {
	/**
	 * Bundled TTF font path.
	 */
	private string $font_path;

	public function __construct( ?string $font_path = null ) {
		if ( null !== $font_path && file_exists( $font_path ) ) {
			$this->font_path = $font_path;
		} else {
			$this->font_path = WPCALIBRATE_WATERMARK_STUDIO_DIR . 'assets/fonts/Outfit-Regular.ttf';
		}
	}

	public function get_name(): string {
		return 'gd';
	}

	public function is_supported(): bool {
		return extension_loaded( 'gd' ) && function_exists( 'imagecreatetruecolor' );
	}

	public function supports_mime_type( string $mime_type ): bool {
		return match ( $mime_type ) {
			'image/jpeg', 'image/jpg' => function_exists( 'imagecreatefromjpeg' ),
			'image/png'               => function_exists( 'imagecreatefrompng' ),
			'image/webp'              => function_exists( 'imagecreatefromwebp' ),
			'image/avif'              => function_exists( 'imagecreatefromavif' ),
			default                   => false,
		};
	}

	public function apply_watermarks( string $source_path, array $settings, string $destination_path ): bool|WP_Error {
		if ( ! $this->is_supported() ) {
			return new WP_Error( 'gd_not_available', __( 'PHP GD extension is not available on this server.', 'wpcalibrate-watermark-studio' ) );
		}

		if ( ! file_exists( $source_path ) || ! is_readable( $source_path ) ) {
			return new WP_Error( 'source_not_found', __( 'Source image does not exist or is unreadable.', 'wpcalibrate-watermark-studio' ) );
		}

		$image_info = @getimagesize( $source_path );
		if ( false === $image_info ) {
			return new WP_Error( 'invalid_image', __( 'Could not read image metadata from source file.', 'wpcalibrate-watermark-studio' ) );
		}

		$mime_type = $image_info['mime'] ?? '';
		if ( ! $this->supports_mime_type( $mime_type ) ) {
			return new WP_Error( 'unsupported_format', sprintf( __( 'MIME type "%s" is not supported by GD.', 'wpcalibrate-watermark-studio' ), $mime_type ) );
		}

		// 1. Create GD image resource from source
		$canvas = $this->create_image_resource( $source_path, $mime_type );
		if ( ! $canvas ) {
			return new WP_Error( 'canvas_creation_failed', __( 'Failed to load source image into GD.', 'wpcalibrate-watermark-studio' ) );
		}

		// Normalize EXIF orientation for JPEGs
		if ( 'image/jpeg' === $mime_type && function_exists( 'exif_read_data' ) ) {
			$canvas = $this->fix_exif_orientation( $canvas, $source_path );
		}

		$canvas_w = imagesx( $canvas );
		$canvas_h = imagesy( $canvas );

		// Enable alpha blending and saving
		imagealphablending( $canvas, true );
		imagesavealpha( $canvas, true );

		$mode = $settings['watermark_mode'] ?? 'image';

		// 2. Render Image Watermark (Image first)
		if ( in_array( $mode, array( 'image', 'both' ), true ) ) {
			$img_error = $this->render_image_watermark( $canvas, $canvas_w, $canvas_h, $settings );
			if ( is_wp_error( $img_error ) ) {
				imagedestroy( $canvas );
				return $img_error;
			}
		}

		// 3. Render Text Watermark (Text second)
		if ( in_array( $mode, array( 'text', 'both' ), true ) ) {
			$txt_error = $this->render_text_watermark( $canvas, $canvas_w, $canvas_h, $settings );
			if ( is_wp_error( $txt_error ) ) {
				imagedestroy( $canvas );
				return $txt_error;
			}
		}

		// 4. Save to temporary destination and atomically move
		$saved = $this->save_canvas_to_file( $canvas, $mime_type, $destination_path );
		imagedestroy( $canvas );

		if ( ! $saved ) {
			return new WP_Error( 'save_failed', __( 'Failed to write watermarked image to destination.', 'wpcalibrate-watermark-studio' ) );
		}

		return true;
	}

	/**
	 * Create GD resource from file path according to MIME type.
	 */
	private function create_image_resource( string $path, string $mime_type ): \GdImage|false {
		return match ( $mime_type ) {
			'image/jpeg', 'image/jpg' => @imagecreatefromjpeg( $path ),
			'image/png'               => @imagecreatefrompng( $path ),
			'image/webp'              => @imagecreatefromwebp( $path ),
			'image/avif'              => function_exists( 'imagecreatefromavif' ) ? @imagecreatefromavif( $path ) : false,
			default                   => false,
		};
	}

	/**
	 * Auto-orient JPEG based on EXIF metadata if present.
	 */
	private function fix_exif_orientation( \GdImage $image, string $path ): \GdImage {
		$exif = @exif_read_data( $path );
		if ( empty( $exif['Orientation'] ) ) {
			return $image;
		}

		$orientation = (int) $exif['Orientation'];
		$new_image   = match ( $orientation ) {
			3 => imagerotate( $image, 180, 0 ),
			6 => imagerotate( $image, -90, 0 ),
			8 => imagerotate( $image, 90, 0 ),
			default => $image,
		};

		if ( false !== $new_image && $new_image !== $image ) {
			imagedestroy( $image );
			return $new_image;
		}

		return $image;
	}

	/**
	 * Resolve watermark image file path with robust protocol-agnostic, attachment ID, and upload dir handling.
	 *
	 * @param array<string, mixed> $settings
	 * @return string Absolute file path or empty string.
	 */
	private function resolve_image_path( array $settings ): string {
		$wm_id  = (int) ( $settings['image_attachment_id'] ?? 0 );
		$wm_url = trim( (string) ( $settings['image_url'] ?? '' ) );

		// 1. Direct attachment ID
		if ( $wm_id > 0 && function_exists( 'get_attached_file' ) ) {
			$attached = get_attached_file( $wm_id );
			if ( $attached && file_exists( $attached ) ) {
				return $attached;
			}
		}

		if ( '' === $wm_url ) {
			return '';
		}

		// 2. Direct absolute or relative file path
		if ( file_exists( $wm_url ) ) {
			return $wm_url;
		}

		// 3. Resolve via attachment_url_to_postid
		if ( function_exists( 'attachment_url_to_postid' ) ) {
			$found_id = attachment_url_to_postid( $wm_url );
			if ( $found_id > 0 ) {
				$attached = get_attached_file( $found_id );
				if ( $attached && file_exists( $attached ) ) {
					return $attached;
				}
			}
		}

		// 4. Resolve relative to uploads directory (protocol, domain, and port agnostic)
		if ( function_exists( 'wp_upload_dir' ) ) {
			$upload_dir = wp_upload_dir();
			$basedir    = function_exists( 'wp_normalize_path' ) ? wp_normalize_path( (string) ( $upload_dir['basedir'] ?? '' ) ) : str_replace( '\\', '/', (string) ( $upload_dir['basedir'] ?? '' ) );
			$baseurl    = (string) ( $upload_dir['baseurl'] ?? '' );

			// Check normalized relative paths
			$url_path     = (string) parse_url( $wm_url, PHP_URL_PATH );
			$baseurl_path = (string) parse_url( $baseurl, PHP_URL_PATH );

			if ( '' !== $url_path && '' !== $baseurl_path && str_starts_with( $url_path, $baseurl_path ) ) {
				$rel_sub   = substr( $url_path, strlen( $baseurl_path ) );
				$candidate = rtrim( $basedir, '/\\' ) . '/' . ltrim( $rel_sub, '/\\' );
				if ( file_exists( $candidate ) ) {
					return $candidate;
				}
			}

			// Fallback: match by uploads directory name directly
			$uploads_pos = strpos( $wm_url, '/wp-content/uploads/' );
			if ( false !== $uploads_pos ) {
				$sub_path  = substr( $wm_url, $uploads_pos + strlen( '/wp-content/uploads/' ) );
				$candidate = rtrim( $basedir, '/\\' ) . '/' . ltrim( $sub_path, '/\\' );
				if ( file_exists( $candidate ) ) {
					return $candidate;
				}
			}
		}

		return '';
	}

	/**
	 * Render image watermark onto canvas.
	 */
	private function render_image_watermark( \GdImage $canvas, int $canvas_w, int $canvas_h, array $settings ): bool|WP_Error {
		$wm_path = $this->resolve_image_path( $settings );

		if ( empty( $wm_path ) || ! file_exists( $wm_path ) ) {
			// No watermark image configured or file missing - skip silently
			return true;
		}

		$wm_info = @getimagesize( $wm_path );
		if ( false === $wm_info ) {
			return true;
		}

		$wm_mime = $wm_info['mime'] ?? '';
		$wm_res  = $this->create_image_resource( $wm_path, $wm_mime );
		if ( ! $wm_res ) {
			return true;
		}

		$wm_nat_w = imagesx( $wm_res );
		$wm_nat_h = imagesy( $wm_res );

		// Calculate scaled dimensions
		$scaled = PositionCalculator::calculate_dimensions(
			$canvas_w,
			$canvas_h,
			$wm_nat_w,
			$wm_nat_h,
			(int) ( $settings['image_relative_width'] ?? 25 ),
			(int) ( $settings['image_max_width_px'] ?? 0 ),
			! empty( $settings['image_prevent_upscale'] )
		);

		$dst_w = $scaled['width'];
		$dst_h = $scaled['height'];

		if ( $dst_w <= 0 || $dst_h <= 0 ) {
			imagedestroy( $wm_res );
			return true;
		}

		// Resample watermark to target size with true transparency
		$scaled_wm = imagecreatetruecolor( $dst_w, $dst_h );
		imagealphablending( $scaled_wm, false );
		imagesavealpha( $scaled_wm, true );
		$transparent = imagecolorallocatealpha( $scaled_wm, 0, 0, 0, 127 );
		imagefilledrectangle( $scaled_wm, 0, 0, $dst_w, $dst_h, $transparent );

		imagecopyresampled( $scaled_wm, $wm_res, 0, 0, 0, 0, $dst_w, $dst_h, $wm_nat_w, $wm_nat_h );
		imagedestroy( $wm_res );

		// Apply opacity (0..100)
		$opacity = (int) ( $settings['image_opacity'] ?? 80 );
		$this->apply_alpha_opacity( $scaled_wm, $dst_w, $dst_h, $opacity );

		// Calculate position coordinates
		$coords = PositionCalculator::calculate_coordinates(
			$canvas_w,
			$canvas_h,
			$dst_w,
			$dst_h,
			(string) ( $settings['image_position'] ?? 'bottom_right' ),
			(int) ( $settings['image_offset_x'] ?? 20 ),
			(int) ( $settings['image_offset_y'] ?? 20 )
		);

		// Composite onto canvas
		imagealphablending( $canvas, true );
		imagecopy( $canvas, $scaled_wm, $coords['x'], $coords['y'], 0, 0, $dst_w, $dst_h );
		imagedestroy( $scaled_wm );

		return true;
	}

	/**
	 * Apply opacity factor to a 32-bit alpha channel GD image.
	 */
	private function apply_alpha_opacity( \GdImage $image, int $w, int $h, int $opacity ): void {
		if ( $opacity >= 100 ) {
			return;
		}

		$opacity_factor = max( 0.0, min( 1.0, $opacity / 100.0 ) );

		imagealphablending( $image, false );
		imagesavealpha( $image, true );

		for ( $x = 0; $x < $w; $x++ ) {
			for ( $y = 0; $y < $h; $y++ ) {
				$rgba  = imagecolorat( $image, $x, $y );
				$alpha = ( $rgba >> 24 ) & 0x7F; // 0 (opaque) to 127 (transparent)

				if ( 127 === $alpha ) {
					continue; // Fully transparent pixel
				}

				$r = ( $rgba >> 16 ) & 0xFF;
				$g = ( $rgba >> 8 ) & 0xFF;
				$b = $rgba & 0xFF;

				// GD alpha formula: 127 is transparent, 0 is opaque
				$new_alpha = 127 - (int) round( ( 127 - $alpha ) * $opacity_factor );
				$new_alpha = max( 0, min( 127, $new_alpha ) );

				$new_color = imagecolorallocatealpha( $image, $r, $g, $b, $new_alpha );
				imagesetpixel( $image, $x, $y, $new_color );
			}
		}
	}

	/**
	 * Render text watermark onto canvas.
	 */
	private function render_text_watermark( \GdImage $canvas, int $canvas_w, int $canvas_h, array $settings ): bool|WP_Error {
		$text = trim( (string) ( $settings['text_content'] ?? '' ) );
		if ( '' === $text ) {
			return true;
		}

		if ( ! function_exists( 'imagettfbbox' ) || ! file_exists( $this->font_path ) ) {
			// Fallback: render basic GD text if TTF not supported
			return $this->render_fallback_gd_text( $canvas, $canvas_w, $canvas_h, $text, $settings );
		}

		// Calculate font size
		$strategy  = (string) ( $settings['text_font_size_strategy'] ?? 'proportional' );
		$font_size = (int) ( $settings['text_font_size'] ?? 24 );

		if ( 'proportional' === $strategy ) {
			$scale_pct = (int) ( $settings['text_proportional_scale'] ?? 5 );
			$font_size = max( 10, (int) round( ( $canvas_w * $scale_pct ) / 100 ) );
		}

		// Calculate text bounding box
		$bbox = @imagettfbbox( (float) $font_size, 0, $this->font_path, $text );
		if ( false === $bbox ) {
			return $this->render_fallback_gd_text( $canvas, $canvas_w, $canvas_h, $text, $settings );
		}

		$text_w = abs( $bbox[4] - $bbox[0] );
		$text_h = abs( $bbox[5] - $bbox[1] );

		// Dynamically scale down font if text width exceeds canvas width with margin
		$max_text_w = max( 20, (int) round( $canvas_w * 0.92 ) );
		while ( $text_w > $max_text_w && $font_size > 8 ) {
			$font_size = (int) floor( $font_size * 0.85 );
			$bbox      = @imagettfbbox( (float) $font_size, 0, $this->font_path, $text );
			if ( false === $bbox ) {
				break;
			}
			$text_w = abs( $bbox[4] - $bbox[0] );
			$text_h = abs( $bbox[5] - $bbox[1] );
		}

		// Determine coordinates
		$coords = PositionCalculator::calculate_coordinates(
			$canvas_w,
			$canvas_h,
			$text_w,
			$text_h,
			(string) ( $settings['text_position'] ?? 'bottom_left' ),
			(int) ( $settings['text_offset_x'] ?? 20 ),
			(int) ( $settings['text_offset_y'] ?? 20 )
		);

		// TTF Y-coordinate is at the baseline, ensure it stays safely within canvas
		$baseline_y = min( $canvas_h - 2, max( $text_h, $coords['y'] + $text_h ) );

		// Text opacity to GD alpha (0 = opaque, 127 = transparent)
		$opacity  = (int) ( $settings['text_opacity'] ?? 80 );
		$gd_alpha = 127 - (int) round( ( $opacity / 100.0 ) * 127 );
		$gd_alpha = max( 0, min( 127, $gd_alpha ) );

		// Render text shadow if enabled
		if ( ! empty( $settings['text_shadow_enabled'] ) ) {
			$shadow_hex = (string) ( $settings['text_shadow_color'] ?? '#000000' );
			$shadow_rgb = $this->hex_to_rgb( $shadow_hex );
			$shadow_col = imagecolorallocatealpha( $canvas, $shadow_rgb['r'], $shadow_rgb['g'], $shadow_rgb['b'], min( 127, $gd_alpha + 15 ) );
			@imagettftext( $canvas, (float) $font_size, 0, $coords['x'] + 2, $baseline_y + 2, $shadow_col, $this->font_path, $text );
		}

		// Render main text
		$color_hex = (string) ( $settings['text_color'] ?? '#ffffff' );
		$color_rgb = $this->hex_to_rgb( $color_hex );
		$color_col = imagecolorallocatealpha( $canvas, $color_rgb['r'], $color_rgb['g'], $color_rgb['b'], $gd_alpha );
		@imagettftext( $canvas, (float) $font_size, 0, $coords['x'], $baseline_y, $color_col, $this->font_path, $text );

		return true;
	}

	/**
	 * Fallback built-in GD font rendering when TTF is not available.
	 */
	private function render_fallback_gd_text( \GdImage $canvas, int $canvas_w, int $canvas_h, string $text, array $settings ): bool {
		$font   = 5; // Built-in font 1..5
		$text_w = imagefontwidth( $font ) * strlen( $text );
		$text_h = imagefontheight( $font );

		$coords = PositionCalculator::calculate_coordinates(
			$canvas_w,
			$canvas_h,
			$text_w,
			$text_h,
			(string) ( $settings['text_position'] ?? 'bottom_left' ),
			(int) ( $settings['text_offset_x'] ?? 20 ),
			(int) ( $settings['text_offset_y'] ?? 20 )
		);

		$color_hex = (string) ( $settings['text_color'] ?? '#ffffff' );
		$color_rgb = $this->hex_to_rgb( $color_hex );
		$color     = imagecolorallocate( $canvas, $color_rgb['r'], $color_rgb['g'], $color_rgb['b'] );

		imagestring( $canvas, $font, $coords['x'], $coords['y'], $text, $color );
		return true;
	}

	/**
	 * Save canvas to destination path atomically.
	 */
	private function save_canvas_to_file( \GdImage $canvas, string $mime_type, string $dest_path ): bool {
		$dir = dirname( $dest_path );
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return false;
		}

		$temp_path = $dir . '/tmp_ws_' . wp_generate_password( 8, false ) . '.tmp';

		$success = match ( $mime_type ) {
			'image/jpeg', 'image/jpg' => imagejpeg( $canvas, $temp_path, 88 ),
			'image/png'               => imagepng( $canvas, $temp_path, 6 ),
			'image/webp'              => imagewebp( $canvas, $temp_path, 85 ),
			'image/avif'              => function_exists( 'imageavif' ) ? imageavif( $canvas, $temp_path, 80 ) : false,
			default                   => false,
		};

		if ( ! $success || ! file_exists( $temp_path ) ) {
			@unlink( $temp_path );
			return false;
		}

		@chmod( $temp_path, 0644 );
		$renamed = @rename( $temp_path, $dest_path );
		if ( ! $renamed ) {
			@unlink( $temp_path );
			return false;
		}

		return true;
	}

	/**
	 * Convert hex color string to RGB integers.
	 *
	 * @return array{r: int, g: int, b: int}
	 */
	private function hex_to_rgb( string $hex ): array {
		$clean = ltrim( $hex, '#' );
		if ( 3 === strlen( $clean ) ) {
			$clean = $clean[0] . $clean[0] . $clean[1] . $clean[1] . $clean[2] . $clean[2];
		}
		if ( 6 !== strlen( $clean ) ) {
			return array( 'r' => 255, 'g' => 255, 'b' => 255 );
		}

		return array(
			'r' => (int) hexdec( substr( $clean, 0, 2 ) ),
			'g' => (int) hexdec( substr( $clean, 2, 2 ) ),
			'b' => (int) hexdec( substr( $clean, 4, 2 ) ),
		);
	}
}

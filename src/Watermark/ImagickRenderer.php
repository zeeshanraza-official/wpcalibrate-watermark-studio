<?php
declare(strict_types=1);

namespace WPCalibrate\WatermarkStudio\Watermark;

use WP_Error;

/**
 * Class ImagickRenderer
 *
 * Imagick-based implementation of watermark compositing.
 * Activates when ImageMagick PHP extension is present, providing vector-level compositing
 * and high-precision color management.
 */
final class ImagickRenderer implements RendererInterface {
	private string $font_path;

	public function __construct( ?string $font_path = null ) {
		if ( null !== $font_path && file_exists( $font_path ) ) {
			$this->font_path = $font_path;
		} else {
			$this->font_path = WPCALIBRATE_WATERMARK_STUDIO_DIR . 'assets/fonts/Outfit-Regular.ttf';
		}
	}

	public function get_name(): string {
		return 'imagick';
	}

	public function is_supported(): bool {
		return extension_loaded( 'imagick' ) && class_exists( 'Imagick' );
	}

	public function supports_mime_type( string $mime_type ): bool {
		if ( ! $this->is_supported() ) {
			return false;
		}

		$formats = \Imagick::queryFormats();
		$map     = array(
			'image/jpeg' => 'JPEG',
			'image/jpg'  => 'JPEG',
			'image/png'  => 'PNG',
			'image/webp' => 'WEBP',
			'image/avif' => 'AVIF',
		);

		$required_format = $map[ $mime_type ] ?? null;
		return null !== $required_format && in_array( $required_format, $formats, true );
	}

	public function apply_watermarks( string $source_path, array $settings, string $destination_path ): bool|WP_Error {
		if ( ! $this->is_supported() ) {
			return new WP_Error( 'imagick_not_supported', __( 'Imagick extension is not installed.', 'wpcalibrate-watermark-studio' ) );
		}

		try {
			$canvas = new \Imagick( $source_path );
			// Normalize EXIF orientation
			if ( method_exists( $canvas, 'autoOrient' ) ) {
				$canvas->autoOrient();
			}

			$canvas_w = $canvas->getImageWidth();
			$canvas_h = $canvas->getImageHeight();
			$mode     = $settings['watermark_mode'] ?? 'image';

			// 1. Image Watermark
			if ( in_array( $mode, array( 'image', 'both' ), true ) ) {
				$this->render_image_watermark( $canvas, $canvas_w, $canvas_h, $settings );
			}

			// 2. Text Watermark
			if ( in_array( $mode, array( 'text', 'both' ), true ) ) {
				$this->render_text_watermark( $canvas, $canvas_w, $canvas_h, $settings );
			}

			// Write to temp file then rename
			$dir = dirname( $destination_path );
			if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
				$canvas->clear();
				return new WP_Error( 'dir_failed', __( 'Could not create destination directory.', 'wpcalibrate-watermark-studio' ) );
			}

			$temp_path = $dir . '/tmp_im_' . wp_generate_password( 8, false ) . '.tmp';
			$canvas->writeImage( $temp_path );
			$canvas->clear();

			if ( ! file_exists( $temp_path ) ) {
				return new WP_Error( 'write_failed', __( 'Failed to save watermarked image with Imagick.', 'wpcalibrate-watermark-studio' ) );
			}

			@chmod( $temp_path, 0644 );
			@rename( $temp_path, $destination_path );
			return true;
		} catch ( \Exception $e ) {
			return new WP_Error( 'imagick_exception', $e->getMessage() );
		}
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

	private function render_image_watermark( \Imagick $canvas, int $canvas_w, int $canvas_h, array $settings ): void {
		$wm_path = $this->resolve_image_path( $settings );

		if ( empty( $wm_path ) || ! file_exists( $wm_path ) ) {
			return;
		}

		$wm = new \Imagick( $wm_path );
		$scaled = PositionCalculator::calculate_dimensions(
			$canvas_w,
			$canvas_h,
			$wm->getImageWidth(),
			$wm->getImageHeight(),
			(int) ( $settings['image_relative_width'] ?? 25 ),
			(int) ( $settings['image_max_width_px'] ?? 0 ),
			! empty( $settings['image_prevent_upscale'] )
		);

		if ( $scaled['width'] <= 0 || $scaled['height'] <= 0 ) {
			$wm->clear();
			return;
		}

		$wm->scaleImage( $scaled['width'], $scaled['height'], true );

		$opacity = (int) ( $settings['image_opacity'] ?? 80 );
		if ( $opacity < 100 ) {
			$wm->evaluateImage( \Imagick::EVALUATE_MULTIPLY, $opacity / 100.0, \Imagick::CHANNEL_ALPHA );
		}

		$coords = PositionCalculator::calculate_coordinates(
			$canvas_w,
			$canvas_h,
			$scaled['width'],
			$scaled['height'],
			(string) ( $settings['image_position'] ?? 'bottom_right' ),
			(int) ( $settings['image_offset_x'] ?? 20 ),
			(int) ( $settings['image_offset_y'] ?? 20 )
		);

		$canvas->compositeImage( $wm, \Imagick::COMPOSITE_OVER, $coords['x'], $coords['y'] );
		$wm->clear();
	}

	private function render_text_watermark( \Imagick $canvas, int $canvas_w, int $canvas_h, array $settings ): void {
		$text = trim( (string) ( $settings['text_content'] ?? '' ) );
		if ( '' === $text ) {
			return;
		}

		$strategy  = (string) ( $settings['text_font_size_strategy'] ?? 'proportional' );
		$font_size = (int) ( $settings['text_font_size'] ?? 24 );

		if ( 'proportional' === $strategy ) {
			$scale_pct = (int) ( $settings['text_proportional_scale'] ?? 5 );
			$font_size = max( 10, (int) round( ( $canvas_w * $scale_pct ) / 100 ) );
		}

		$draw = new \ImagickDraw();
		if ( file_exists( $this->font_path ) ) {
			$draw->setFont( $this->font_path );
		}
		$draw->setFontSize( $font_size );

		$metrics = $canvas->queryFontMetrics( $draw, $text );
		$text_w  = (int) ceil( $metrics['textWidth'] );
		$text_h  = (int) ceil( $metrics['textHeight'] );

		// Dynamically scale down font if text width exceeds canvas width with margin
		$max_text_w = max( 20, (int) round( $canvas_w * 0.92 ) );
		while ( $text_w > $max_text_w && $font_size > 8 ) {
			$font_size = (int) floor( $font_size * 0.85 );
			$draw->setFontSize( $font_size );
			$metrics = $canvas->queryFontMetrics( $draw, $text );
			$text_w  = (int) ceil( $metrics['textWidth'] );
			$text_h  = (int) ceil( $metrics['textHeight'] );
		}

		$coords = PositionCalculator::calculate_coordinates(
			$canvas_w,
			$canvas_h,
			$text_w,
			$text_h,
			(string) ( $settings['text_position'] ?? 'bottom_left' ),
			(int) ( $settings['text_offset_x'] ?? 20 ),
			(int) ( $settings['text_offset_y'] ?? 20 )
		);

		$opacity = (int) ( $settings['text_opacity'] ?? 80 );
		$draw->setFillOpacity( $opacity / 100.0 );

		// Shadow
		if ( ! empty( $settings['text_shadow_enabled'] ) ) {
			$shadow_draw = clone $draw;
			$shadow_draw->setFillColor( (string) ( $settings['text_shadow_color'] ?? '#000000' ) );
			$shadow_draw->setFillOpacity( min( 1.0, ( $opacity / 100.0 ) + 0.15 ) );
			$canvas->annotateImage( $shadow_draw, $coords['x'] + 2, $coords['y'] + $text_h + 2, 0, $text );
			$shadow_draw->clear();
		}

		// Main text
		$draw->setFillColor( (string) ( $settings['text_color'] ?? '#ffffff' ) );
		$canvas->annotateImage( $draw, $coords['x'], $coords['y'] + $text_h, 0, $text );
		$draw->clear();
	}
}

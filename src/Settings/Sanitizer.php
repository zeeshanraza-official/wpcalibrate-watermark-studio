<?php
declare(strict_types=1);

namespace WPCalibrate\WatermarkStudio\Settings;

/**
 * Class Sanitizer
 *
 * Provides strict, context-aware sanitization and validation for all plugin settings.
 */
final class Sanitizer {
	/**
	 * Sanitize raw settings array.
	 *
	 * @param mixed $input Raw input from options or request.
	 * @return array<string, mixed> Sanitized settings array.
	 */
	public static function sanitize( mixed $input ): array {
		$defaults = Defaults::get_all();
		if ( ! is_array( $input ) ) {
			return $defaults;
		}

		$output = array();

		// General
		$output['enable_automatic']  = ! empty( $input['enable_automatic'] );
		$mode                        = isset( $input['watermark_mode'] ) ? (string) $input['watermark_mode'] : 'image';
		$output['watermark_mode']    = array_key_exists( $mode, Defaults::MODES ) ? $mode : $defaults['watermark_mode'];
		$output['min_source_width']  = isset( $input['min_source_width'] ) ? max( 0, (int) $input['min_source_width'] ) : $defaults['min_source_width'];
		$output['min_source_height'] = isset( $input['min_source_height'] ) ? max( 0, (int) $input['min_source_height'] ) : $defaults['min_source_height'];

		// Allowed MIME types
		$valid_mimes = array( 'image/jpeg', 'image/png', 'image/webp', 'image/avif' );
		if ( ! empty( $input['allowed_mime_types'] ) && is_array( $input['allowed_mime_types'] ) ) {
			$output['allowed_mime_types'] = array_values( array_intersect( $input['allowed_mime_types'], $valid_mimes ) );
			if ( empty( $output['allowed_mime_types'] ) ) {
				$output['allowed_mime_types'] = array( 'image/jpeg', 'image/png' );
			}
		} else {
			$output['allowed_mime_types'] = $defaults['allowed_mime_types'];
		}

		// Core Principle: Preserve originals cannot be disabled
		$output['preserve_originals'] = true;
		$output['uninstall_cleanup']  = ! empty( $input['uninstall_cleanup'] );
		$output['logging_enabled']    = ! empty( $input['logging_enabled'] );

		// Image Watermark
		$output['image_attachment_id']         = isset( $input['image_attachment_id'] ) ? absint( $input['image_attachment_id'] ) : 0;
		$output['image_url']                   = isset( $input['image_url'] ) ? esc_url_raw( (string) $input['image_url'] ) : '';
		$output['image_relative_width']        = isset( $input['image_relative_width'] ) ? self::clamp( (int) $input['image_relative_width'], 1, 100 ) : $defaults['image_relative_width'];
		$output['image_max_width_px']          = isset( $input['image_max_width_px'] ) ? max( 0, (int) $input['image_max_width_px'] ) : 0;
		$output['image_opacity']               = isset( $input['image_opacity'] ) ? self::clamp( (int) $input['image_opacity'], 0, 100 ) : $defaults['image_opacity'];
		$img_pos                               = isset( $input['image_position'] ) ? (string) $input['image_position'] : 'bottom_right';
		$output['image_position']              = array_key_exists( $img_pos, Defaults::POSITIONS ) ? $img_pos : $defaults['image_position'];
		$output['image_offset_x']              = isset( $input['image_offset_x'] ) ? self::clamp( (int) $input['image_offset_x'], -2000, 2000 ) : $defaults['image_offset_x'];
		$output['image_offset_y']              = isset( $input['image_offset_y'] ) ? self::clamp( (int) $input['image_offset_y'], -2000, 2000 ) : $defaults['image_offset_y'];
		$output['image_maintain_aspect_ratio'] = ! empty( $input['image_maintain_aspect_ratio'] );
		$output['image_prevent_upscale']       = ! empty( $input['image_prevent_upscale'] );

		// Text Watermark
		$output['text_content']            = isset( $input['text_content'] ) ? sanitize_text_field( (string) $input['text_content'] ) : $defaults['text_content'];
		$output['text_font_size']          = isset( $input['text_font_size'] ) ? self::clamp( (int) $input['text_font_size'], 6, 250 ) : $defaults['text_font_size'];
		$text_strategy                     = isset( $input['text_font_size_strategy'] ) ? (string) $input['text_font_size_strategy'] : 'proportional';
		$output['text_font_size_strategy'] = in_array( $text_strategy, array( 'fixed', 'proportional' ), true ) ? $text_strategy : 'proportional';
		$output['text_proportional_scale'] = isset( $input['text_proportional_scale'] ) ? self::clamp( (int) $input['text_proportional_scale'], 1, 50 ) : $defaults['text_proportional_scale'];
		$output['text_color']              = self::sanitize_color( $input['text_color'] ?? '#ffffff', '#ffffff' );
		$output['text_opacity']            = isset( $input['text_opacity'] ) ? self::clamp( (int) $input['text_opacity'], 0, 100 ) : $defaults['text_opacity'];
		$txt_pos                           = isset( $input['text_position'] ) ? (string) $input['text_position'] : 'bottom_left';
		$output['text_position']           = array_key_exists( $txt_pos, Defaults::POSITIONS ) ? $txt_pos : $defaults['text_position'];
		$output['text_offset_x']           = isset( $input['text_offset_x'] ) ? self::clamp( (int) $input['text_offset_x'], -2000, 2000 ) : $defaults['text_offset_x'];
		$output['text_offset_y']           = isset( $input['text_offset_y'] ) ? self::clamp( (int) $input['text_offset_y'], -2000, 2000 ) : $defaults['text_offset_y'];
		$output['text_shadow_enabled']     = ! empty( $input['text_shadow_enabled'] );
		$output['text_shadow_color']       = self::sanitize_color( $input['text_shadow_color'] ?? '#000000', '#000000' );

		// Image Sizes
		if ( isset( $input['target_sizes'] ) && is_array( $input['target_sizes'] ) ) {
			$output['target_sizes'] = array_values( array_map( 'sanitize_key', $input['target_sizes'] ) );
		} else {
			$output['target_sizes'] = $defaults['target_sizes'];
		}
		$output['include_full_size'] = ! empty( $input['include_full_size'] );

		// Automation
		$context                      = isset( $input['auto_target_context'] ) ? (string) $input['auto_target_context'] : 'all';
		$output['auto_target_context'] = in_array( $context, array( 'all', 'post_types', 'woocommerce_products' ), true ) ? $context : 'all';
		if ( isset( $input['auto_post_types'] ) && is_array( $input['auto_post_types'] ) ) {
			$output['auto_post_types'] = array_values( array_map( 'sanitize_key', $input['auto_post_types'] ) );
		} else {
			$output['auto_post_types'] = $defaults['auto_post_types'];
		}

		// WooCommerce
		$output['woo_target_featured']   = ! empty( $input['woo_target_featured'] );
		$output['woo_target_gallery']    = ! empty( $input['woo_target_gallery'] );
		$output['woo_target_variations'] = ! empty( $input['woo_target_variations'] );

		// License (masked handling: if user submitted masked '••••••••' or empty, retain saved)
		$existing_options = get_option( Defaults::OPTION_NAME, array() );
		$existing_key     = is_array( $existing_options ) && isset( $existing_options['license_key'] ) ? (string) $existing_options['license_key'] : '';

		if ( isset( $input['license_key'] ) ) {
			$submitted_key = trim( sanitize_text_field( (string) $input['license_key'] ) );
			if ( '' === $submitted_key || str_contains( $submitted_key, '••••' ) ) {
				$output['license_key'] = $existing_key;
			} else {
				$output['license_key'] = $submitted_key;
			}
		} else {
			$output['license_key'] = $existing_key;
		}

		$output['license_status'] = isset( $existing_options['license_status'] ) ? (string) $existing_options['license_status'] : 'unconfigured';

		return $output;
	}

	/**
	 * Clamp an integer between min and max.
	 */
	public static function clamp( int $val, int $min, int $max ): int {
		return max( $min, min( $max, $val ) );
	}

	/**
	 * Sanitize Hex color code.
	 */
	public static function sanitize_color( mixed $color, string $default = '#ffffff' ): string {
		if ( ! is_string( $color ) ) {
			return $default;
		}

		$color = trim( $color );
		if ( preg_match( '/^#([a-fA-F0-9]{3}){1,2}$/', $color ) ) {
			return strtolower( $color );
		}

		return $default;
	}
}

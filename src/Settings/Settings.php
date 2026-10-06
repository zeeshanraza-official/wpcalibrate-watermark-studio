<?php
declare(strict_types=1);

namespace WPCalibrate\WatermarkStudio\Settings;

/**
 * Class Settings
 *
 * Provides typed, cached access to plugin options and configuration values.
 */
final class Settings {
	/**
	 * In-memory settings cache.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $cache = null;

	/**
	 * Get a single setting value.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $default Fallback value if setting not found.
	 * @return mixed
	 */
	public function get( string $key, mixed $default = null ): mixed {
		$all = $this->all();
		return $all[ $key ] ?? $default;
	}

	/**
	 * Retrieve all sanitized plugin settings.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		if ( null !== $this->cache ) {
			return $this->cache;
		}

		$raw = get_option( Defaults::OPTION_NAME, array() );
		if ( ! is_array( $raw ) || empty( $raw ) ) {
			$raw = Defaults::get_all();
		} else {
			$raw = wp_parse_args( $raw, Defaults::get_all() );
		}

		$this->cache = Sanitizer::sanitize( $raw );
		return $this->cache;
	}

	/**
	 * Update multiple settings.
	 *
	 * @param array<string, mixed> $values New settings to merge and save.
	 * @return bool
	 */
	public function update( array $values ): bool {
		$current = $this->all();
		$merged  = array_merge( $current, $values );
		$clean   = Sanitizer::sanitize( $merged );

		$this->cache = $clean;
		return update_option( Defaults::OPTION_NAME, $clean, false );
	}

	/**
	 * Clear local memory cache.
	 */
	public function flush_cache(): void {
		$this->cache = null;
	}

	/**
	 * Returns only the subset of settings that directly affect the visual output of watermarked images.
	 * This is critical so that changes to unrelated admin options (e.g., logging, retention)
	 * do not invalidate watermark fingerprints or trigger unnecessary reprocessing.
	 *
	 * @return array<string, mixed>
	 */
	public function get_visual_render_config(): array {
		$s = $this->all();
		return array(
			'mode'                    => $s['watermark_mode'],
			// Image settings
			'img_id'                  => $s['image_attachment_id'],
			'img_url'                 => $s['image_url'],
			'img_rel_width'           => $s['image_relative_width'],
			'img_max_px'              => $s['image_max_width_px'],
			'img_opacity'             => $s['image_opacity'],
			'img_position'            => $s['image_position'],
			'img_off_x'               => $s['image_offset_x'],
			'img_off_y'               => $s['image_offset_y'],
			'img_aspect'              => $s['image_maintain_aspect_ratio'],
			'img_prevent_upscale'     => $s['image_prevent_upscale'],
			// Text settings
			'txt_content'             => $s['text_content'],
			'txt_font_size'           => $s['text_font_size'],
			'txt_strategy'            => $s['text_font_size_strategy'],
			'txt_prop_scale'          => $s['text_proportional_scale'],
			'txt_color'               => $s['text_color'],
			'txt_opacity'             => $s['text_opacity'],
			'txt_pos'                 => $s['text_position'],
			'txt_off_x'               => $s['text_offset_x'],
			'txt_off_y'               => $s['text_offset_y'],
			'txt_shadow'              => $s['text_shadow_enabled'],
			'txt_shadow_color'        => $s['text_shadow_color'],
			// Target sizes
			'target_sizes'            => $s['target_sizes'],
			'include_full'            => $s['include_full_size'],
		);
	}
}

<?php
declare(strict_types=1);

namespace WPCalibrate\WatermarkStudio\Watermark;

use WP_Error;

/**
 * Interface RendererInterface
 *
 * Defines the contract for low-level image compositing and watermark rendering engines.
 */
interface RendererInterface {
	/**
	 * Get the engine name identifier (e.g. 'gd', 'imagick').
	 *
	 * @return string
	 */
	public function get_name(): string;

	/**
	 * Check if the engine is available and can process the current PHP environment.
	 *
	 * @return bool
	 */
	public function is_supported(): bool;

	/**
	 * Check if the engine supports a specific MIME type.
	 *
	 * @param string $mime_type
	 * @return bool
	 */
	public function supports_mime_type( string $mime_type ): bool;

	/**
	 * Apply image and/or text watermarks to the specified file and save to destination.
	 *
	 * @param string               $source_path Absolute path to the source image.
	 * @param array<string, mixed> $settings Active watermark settings.
	 * @param string               $destination_path Absolute path to save the watermarked image.
	 * @return bool|WP_Error True on success or WP_Error on failure.
	 */
	public function apply_watermarks( string $source_path, array $settings, string $destination_path ): bool|WP_Error;
}

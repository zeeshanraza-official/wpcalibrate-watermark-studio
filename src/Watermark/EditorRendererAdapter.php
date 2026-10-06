<?php
declare(strict_types=1);

namespace WPCalibrate\WatermarkStudio\Watermark;

/**
 * Class EditorRendererAdapter
 *
 * Coordinates between WordPress WP_Image_Editor abstraction and available rendering engines.
 */
final class EditorRendererAdapter {
	private GdRenderer $gd_renderer;
	private ImagickRenderer $imagick_renderer;

	public function __construct() {
		$this->gd_renderer      = new GdRenderer();
		$this->imagick_renderer = new ImagickRenderer();
	}

	/**
	 * Select the optimal available rendering engine.
	 * Prioritizes Imagick if available and active in WordPress, otherwise falls back to GD.
	 *
	 * @return RendererInterface
	 */
	public function get_renderer(): RendererInterface {
		// If Imagick is installed and supported by WordPress editor implementation
		if ( $this->imagick_renderer->is_supported() ) {
			// Check if WP allows Imagick
			if ( function_exists( 'wp_image_editor_supports' ) && wp_image_editor_supports( array( 'methods' => array( 'rotate', 'resize' ) ) ) ) {
				return $this->imagick_renderer;
			}
		}

		return $this->gd_renderer;
	}

	public function get_gd_renderer(): GdRenderer {
		return $this->gd_renderer;
	}

	public function get_imagick_renderer(): ImagickRenderer {
		return $this->imagick_renderer;
	}
}

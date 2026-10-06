<?php
declare(strict_types=1);

namespace WPCalibrate\WatermarkStudio\Watermark;

use WPCalibrate\WatermarkStudio\Settings\Settings;

/**
 * Class ConfigurationFingerprint
 *
 * Generates a deterministic hash of settings that directly affect visual watermark output.
 * Ensures that changes to non-visual settings (e.g., logging, license, context) do not trigger invalidations.
 */
final class ConfigurationFingerprint {
	/**
	 * Compute deterministic SHA-256 fingerprint from settings.
	 *
	 * @param Settings $settings
	 * @return string
	 */
	public static function generate( Settings $settings ): string {
		$config = $settings->get_visual_render_config();
		return self::from_array( $config );
	}

	/**
	 * Compute deterministic hash from configuration array.
	 *
	 * @param array<string, mixed> $config
	 * @return string
	 */
	public static function from_array( array $config ): string {
		// Sort keys recursively for canonical serialization
		self::recursive_ksort( $config );
		$serialized = wp_json_encode( $config );
		return hash( 'sha256', (string) $serialized );
	}

	/**
	 * Sort array keys recursively.
	 *
	 * @param array<string, mixed> $array
	 */
	private static function recursive_ksort( array &$array ): void {
		ksort( $array );
		foreach ( $array as &$value ) {
			if ( is_array( $value ) ) {
				self::recursive_ksort( $value );
			}
		}
	}
}

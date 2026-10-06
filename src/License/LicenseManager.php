<?php
declare(strict_types=1);

namespace WPCalibrate\WatermarkStudio\License;

use WPCalibrate\WatermarkStudio\Settings\Defaults;

/**
 * Class LicenseManager
 *
 * Coordinates license lifecycle management, masking, and pluggable provider architecture.
 */
final class LicenseManager {
	private LicenseProviderInterface $provider;

	public function __construct( ?LicenseProviderInterface $provider = null ) {
		if ( null !== $provider ) {
			$this->provider = $provider;
		} else {
			/**
			 * Filter the license provider instance for future extension.
			 *
			 * @param LicenseProviderInterface $provider
			 */
			$filtered = apply_filters( 'wpcalibrate_watermark_studio_license_provider', new UnconfiguredLicenseProvider() );
			$this->provider = ( $filtered instanceof LicenseProviderInterface ) ? $filtered : new UnconfiguredLicenseProvider();
		}
	}

	public function get_provider(): LicenseProviderInterface {
		return $this->provider;
	}

	/**
	 * Retrieve stored license key.
	 */
	public function get_raw_key(): string {
		$options = get_option( Defaults::OPTION_NAME, array() );
		return is_array( $options ) && isset( $options['license_key'] ) ? (string) $options['license_key'] : '';
	}

	/**
	 * Return safely masked version of the license key for display in admin UI.
	 * Never exposes the full secret.
	 *
	 * @return string
	 */
	public function get_masked_key(): string {
		$key = $this->get_raw_key();
		if ( '' === $key ) {
			return '';
		}

		$len = strlen( $key );
		if ( $len <= 4 ) {
			return str_repeat( '•', $len );
		}

		$suffix = substr( $key, -4 );
		return str_repeat( '•', max( 8, $len - 4 ) ) . $suffix;
	}

	/**
	 * Get current license status string.
	 */
	public function get_status(): string {
		if ( ! $this->provider->is_configured() ) {
			return 'unconfigured';
		}

		$options = get_option( Defaults::OPTION_NAME, array() );
		return is_array( $options ) && isset( $options['license_status'] ) ? (string) $options['license_status'] : 'unconfigured';
	}

	/**
	 * Trigger activation through the provider.
	 *
	 * @return array{success: bool, message: string}
	 */
	public function activate(): array {
		$key = $this->get_raw_key();
		if ( '' === $key ) {
			return array(
				'success' => false,
				'message' => __( 'Please enter a license key before activating.', 'wpcalibrate-watermark-studio' ),
			);
		}

		$result = $this->provider->activate( $key );

		$options = get_option( Defaults::OPTION_NAME, array() );
		if ( is_array( $options ) ) {
			$options['license_status'] = $result['status'];
			update_option( Defaults::OPTION_NAME, $options, false );
		}

		return array(
			'success' => $result['success'],
			'message' => $result['message'],
		);
	}

	/**
	 * Trigger deactivation through the provider.
	 *
	 * @return array{success: bool, message: string}
	 */
	public function deactivate(): array {
		$key = $this->get_raw_key();
		$result = $this->provider->deactivate( $key );

		$options = get_option( Defaults::OPTION_NAME, array() );
		if ( is_array( $options ) ) {
			$options['license_status'] = 'deactivated';
			update_option( Defaults::OPTION_NAME, $options, false );
		}

		return array(
			'success' => $result['success'],
			'message' => $result['message'],
		);
	}
}

<?php
declare(strict_types=1);

namespace WPCalibrate\WatermarkStudio\License;

/**
 * Class UnconfiguredLicenseProvider
 *
 * Default unconfigured provider used until an official WPCalibrate licensing API provider is registered.
 * Never fakes activation, never reports fake expiry dates, and never transmits credentials.
 */
final class UnconfiguredLicenseProvider implements LicenseProviderInterface {
	public function is_configured(): bool {
		return false;
	}

	public function get_provider_name(): string {
		return 'Unconfigured Provider';
	}

	public function activate( string $license_key ): array {
		return array(
			'success'     => false,
			'message'     => __( 'License service not configured. No remote licensing endpoint has been registered.', 'wpcalibrate-watermark-studio' ),
			'status'      => 'unconfigured',
			'expires_at'  => null,
			'renewal_url' => null,
		);
	}

	public function deactivate( string $license_key ): array {
		return array(
			'success' => false,
			'message' => __( 'License service not configured.', 'wpcalibrate-watermark-studio' ),
			'status'  => 'unconfigured',
		);
	}

	public function check_status( string $license_key ): array {
		return array(
			'status'      => 'unconfigured',
			'expires_at'  => null,
			'renewal_url' => null,
		);
	}
}

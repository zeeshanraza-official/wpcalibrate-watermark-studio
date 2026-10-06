<?php
declare(strict_types=1);

namespace WPCalibrate\WatermarkStudio\License;

/**
 * Interface LicenseProviderInterface
 *
 * Defines the contract for remote or local license validation and lifecycle operations.
 */
interface LicenseProviderInterface {
	/**
	 * Whether a real licensing endpoint or provider is configured.
	 */
	public function is_configured(): bool;

	/**
	 * Name of the licensing provider.
	 */
	public function get_provider_name(): string;

	/**
	 * Activate a license key.
	 *
	 * @param string $license_key
	 * @return array{success: bool, message: string, status: string, expires_at: ?string, renewal_url: ?string}
	 */
	public function activate( string $license_key ): array;

	/**
	 * Deactivate a license key.
	 *
	 * @param string $license_key
	 * @return array{success: bool, message: string, status: string}
	 */
	public function deactivate( string $license_key ): array;

	/**
	 * Check current license status.
	 *
	 * @param string $license_key
	 * @return array{status: string, expires_at: ?string, renewal_url: ?string}
	 */
	public function check_status( string $license_key ): array;
}

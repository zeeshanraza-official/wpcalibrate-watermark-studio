<?php
declare(strict_types=1);

namespace WPCalibrate\WatermarkStudio;

use WPCalibrate\WatermarkStudio\Media\OriginalManager;

/**
 * Class Upgrade
 *
 * Handles database option and storage schema migrations across versions.
 */
final class Upgrade {
	/**
	 * Check and run any required upgrade migrations.
	 */
	public static function check_upgrade(): void {
		$installed_version = (string) get_option( Plugin::OPTION_DATA_VERSION, '0.0.0' );

		if ( version_compare( $installed_version, Plugin::DATA_VERSION, '<' ) ) {
			self::run_upgrades( $installed_version );
			update_option( Plugin::OPTION_DATA_VERSION, Plugin::DATA_VERSION, false );
		}
	}

	/**
	 * Run migrations step-by-step.
	 *
	 * @param string $current_version Currently installed data version.
	 */
	private static function run_upgrades( string $current_version ): void {
		// Example versioned upgrade step: 1.0.0 initialization
		if ( version_compare( $current_version, '1.0.0', '<' ) ) {
			OriginalManager::ensure_storage_directory();
		}

		// Future versions will add conditional migrations here without destructive reprocessing.
	}
}

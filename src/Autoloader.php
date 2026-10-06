<?php
declare(strict_types=1);

namespace WPCalibrate\WatermarkStudio;

/**
 * Class Autoloader
 *
 * Lightweight, safe PSR-4 autoloader for WPCalibrate Watermark Studio.
 * Avoids third-party runtime dependencies while strictly loading classes.
 */
final class Autoloader {
	/**
	 * Namespace prefix.
	 *
	 * @var string
	 */
	private const NAMESPACE_PREFIX = 'WPCalibrate\\WatermarkStudio\\';

	/**
	 * Base directory for namespace prefix.
	 *
	 * @var string
	 */
	private string $base_dir;

	/**
	 * Autoloader constructor.
	 *
	 * @param string $base_dir Root directory of the source classes.
	 */
	public function __construct( string $base_dir ) {
		$this->base_dir = rtrim( $base_dir, '/\\' ) . DIRECTORY_SEPARATOR;
	}

	/**
	 * Register the autoloader.
	 *
	 * @param string $base_dir Root directory of the source classes.
	 * @return self
	 */
	public static function register( string $base_dir ): self {
		$loader = new self( $base_dir );
		spl_autoload_register( array( $loader, 'autoload' ) );
		return $loader;
	}

	/**
	 * Autoload callback.
	 *
	 * @param string $class Fully qualified class name.
	 * @return bool
	 */
	public function autoload( string $class ): bool {
		$len = strlen( self::NAMESPACE_PREFIX );
		if ( strncmp( self::NAMESPACE_PREFIX, $class, $len ) !== 0 ) {
			return false;
		}

		$relative_class = substr( $class, $len );
		$file           = $this->base_dir . str_replace( '\\', DIRECTORY_SEPARATOR, $relative_class ) . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
			return true;
		}

		return false;
	}
}

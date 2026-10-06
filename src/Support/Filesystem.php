<?php
declare(strict_types=1);

namespace WPCalibrate\WatermarkStudio\Support;

/**
 * Class Filesystem
 *
 * Safe filesystem helpers using WordPress filesystem abstractions and strict canonical path normalization.
 */
final class Filesystem {
	/**
	 * Normalize path separators and remove null bytes.
	 *
	 * @param string $path Raw path.
	 * @return string Normalized path.
	 */
	public static function normalize_path( string $path ): string {
		$normalized = wp_normalize_path( $path );
		$normalized = str_replace( chr( 0 ), '', $normalized );
		return $normalized;
	}

	/**
	 * Resolve canonical path collapsing any '.' and '..' segments without requiring file existence.
	 *
	 * @param string $path
	 * @return string
	 */
	public static function canonical_path( string $path ): string {
		$norm   = self::normalize_path( $path );
		$prefix = '';

		// Check Windows drive letter (e.g. C:/)
		if ( preg_match( '/^[a-zA-Z]:\//', $norm, $matches ) ) {
			$prefix = $matches[0];
			$norm   = substr( $norm, strlen( $prefix ) );
		} elseif ( str_starts_with( $norm, '/' ) ) {
			$prefix = '/';
			$norm   = ltrim( $norm, '/' );
		}

		$segments = explode( '/', $norm );
		$stack    = array();

		foreach ( $segments as $seg ) {
			if ( '' === $seg || '.' === $seg ) {
				continue;
			}
			if ( '..' === $seg ) {
				array_pop( $stack );
			} else {
				$stack[] = $seg;
			}
		}

		return $prefix . implode( '/', $stack );
	}

	/**
	 * Verify that a target path is safely contained inside an allowed base directory.
	 * Prevents directory traversal attacks even for non-existent destination files.
	 *
	 * @param string $path Target file or directory path.
	 * @param string $allowed_base Base directory that must contain the target.
	 * @return bool True if safe and contained, false if path traversal detected.
	 */
	public static function is_path_within( string $path, string $allowed_base ): bool {
		$canon_path = strtolower( self::canonical_path( $path ) );
		$canon_base = rtrim( strtolower( self::canonical_path( $allowed_base ) ), '/' ) . '/';

		// Realpath check if both exist
		$real_base = realpath( $allowed_base );
		$real_path = realpath( $path );
		if ( false !== $real_base && false !== $real_path ) {
			$norm_real_base = rtrim( strtolower( wp_normalize_path( $real_base ) ), '/' ) . '/';
			$norm_real_path = strtolower( wp_normalize_path( $real_path ) );
			return str_starts_with( $norm_real_path, $norm_real_base );
		}

		return str_starts_with( $canon_path, $canon_base );
	}

	/**
	 * Safely write file atomically via temporary file and rename.
	 *
	 * @param string $destination Target file destination path.
	 * @param string $content File contents.
	 * @param int    $chmod Permissions mode.
	 * @return bool
	 */
	public static function atomic_write( string $destination, string $content, int $chmod = 0644 ): bool {
		$dir = dirname( $destination );
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return false;
		}

		$temp_file = $dir . '/tmp_' . wp_generate_password( 12, false ) . '.tmp';
		if ( false === file_put_contents( $temp_file, $content ) ) {
			return false;
		}

		@chmod( $temp_file, $chmod );

		if ( ! @rename( $temp_file, $destination ) ) {
			@unlink( $temp_file );
			return false;
		}

		return true;
	}
}

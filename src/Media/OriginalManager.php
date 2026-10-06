<?php
declare(strict_types=1);

namespace WPCalibrate\WatermarkStudio\Media;

use WPCalibrate\WatermarkStudio\Support\Filesystem;
use WPCalibrate\WatermarkStudio\Support\Logger;
use WP_Error;

/**
 * Class OriginalManager
 *
 * Manages the safe isolation, storage, verification, and restoration of untouched original image files.
 * Guarantees that untouched sources are never overwritten and that backup files are protected from direct web access.
 */
final class OriginalManager {
	/**
	 * Subdirectory name within wp-content/uploads.
	 */
	public const BACKUP_SUBDIR = 'wpcalibrate-watermark-studio/originals';

	public function __construct( private readonly Logger $logger ) {}

	/**
	 * Get the absolute directory path where untouched originals are stored.
	 *
	 * @return string
	 */
	public static function get_storage_basedir(): string {
		$upload_dir = wp_upload_dir();
		$basedir    = Filesystem::normalize_path( $upload_dir['basedir'] . '/' . self::BACKUP_SUBDIR );
		return rtrim( $basedir, '/' );
	}

	/**
	 * Ensure the protected originals directory exists and is secured against direct web access.
	 *
	 * @return bool
	 */
	public static function ensure_storage_directory(): bool {
		$dir = self::get_storage_basedir();
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return false;
		}

		// 1. Write Apache / LiteSpeed .htaccess protection
		$htaccess_file = $dir . '/.htaccess';
		if ( ! file_exists( $htaccess_file ) ) {
			$htaccess_content = "# WPCalibrate Watermark Studio - Protected Originals\n"
				. "<IfModule mod_authz_core.c>\n"
				. "    Require all denied\n"
				. "</IfModule>\n"
				. "<IfModule !mod_authz_core.c>\n"
				. "    Order deny,allow\n"
				. "    Deny from all\n"
				. "</IfModule>\n";
			@file_put_contents( $htaccess_file, $htaccess_content );
		}

		// 2. Write empty index.php to prevent directory listings
		$index_file = $dir . '/index.php';
		if ( ! file_exists( $index_file ) ) {
			@file_put_contents( $index_file, "<?php\n// Silence is golden.\n" );
		}

		// 3. Write IIS web.config protection
		$web_config = $dir . '/web.config';
		if ( ! file_exists( $web_config ) ) {
			$iis_content = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
				. "<configuration>\n"
				. "    <system.webServer>\n"
				. "        <authorization>\n"
				. "            <deny users=\"*\" />\n"
				. "        </authorization>\n"
				. "    </system.webServer>\n"
				. "</configuration>\n";
			@file_put_contents( $web_config, $iis_content );
		}

		return true;
	}

	/**
	 * Get the relative path for an attachment inside the backup directory.
	 *
	 * @param int $attachment_id
	 * @return string
	 */
	public function get_relative_backup_path( int $attachment_id ): string {
		$attached_file = (string) get_post_meta( $attachment_id, '_wp_attached_file', true );
		if ( '' !== $attached_file ) {
			// Strip any traversal
			$clean = Filesystem::normalize_path( $attached_file );
			$clean = ltrim( str_replace( array( '../', '..\\' ), '', $clean ), '/' );
			return $clean;
		}

		return 'backup_' . $attachment_id . '.bin';
	}

	/**
	 * Get absolute path to the backup file for an attachment.
	 *
	 * @param int $attachment_id
	 * @return string
	 */
	public function get_absolute_backup_path( int $attachment_id ): string {
		$rel  = $this->get_relative_backup_path( $attachment_id );
		$base = self::get_storage_basedir();
		return Filesystem::normalize_path( $base . '/' . $rel );
	}

	/**
	 * Verify if a verified backup file exists on disk.
	 *
	 * @param int $attachment_id
	 * @return bool
	 */
	public function has_backup( int $attachment_id ): bool {
		$path = $this->get_absolute_backup_path( $attachment_id );
		return file_exists( $path ) && is_file( $path ) && filesize( $path ) > 0;
	}

	/**
	 * Safely backup an untouched source image prior to destructive watermarking.
	 * Idempotent: Never overwrites an existing backup.
	 *
	 * @param int    $attachment_id
	 * @param string $source_file_path Absolute path to current un-watermarked full-size file.
	 * @return string|WP_Error Relative path of saved backup or WP_Error on failure.
	 */
	public function backup_original( int $attachment_id, string $source_file_path ): string|WP_Error {
		self::ensure_storage_directory();

		$backup_path = $this->get_absolute_backup_path( $attachment_id );
		$rel_path    = $this->get_relative_backup_path( $attachment_id );

		// 1. If backup already exists on disk, preserve it and reuse it.
		// Never overwrite an existing backup with what could be a previously watermarked derivative!
		if ( file_exists( $backup_path ) && filesize( $backup_path ) > 0 ) {
			$this->logger->info(
				'Existing untouched backup preserved and reused',
				array( 'attachment_id' => $attachment_id )
			);
			return $rel_path;
		}

		// 2. Validate source file existence and readability.
		if ( ! file_exists( $source_file_path ) || ! is_readable( $source_file_path ) ) {
			$err = new WP_Error(
				'source_not_found',
				__( 'Source image file does not exist or is unreadable for backup.', 'wpcalibrate-watermark-studio' )
			);
			$this->logger->error( 'Backup failed: source not found', array( 'attachment_id' => $attachment_id ) );
			return $err;
		}

		// 3. Ensure target backup directory exists.
		$backup_dir = dirname( $backup_path );
		if ( ! is_dir( $backup_dir ) && ! wp_mkdir_p( $backup_dir ) ) {
			$err = new WP_Error(
				'mkdir_failed',
				__( 'Could not create protected original backup folder.', 'wpcalibrate-watermark-studio' )
			);
			$this->logger->error( 'Backup failed: cannot create directory', array( 'attachment_id' => $attachment_id ) );
			return $err;
		}

		// 4. Verify path safety - must remain inside backup directory.
		$base_dir = self::get_storage_basedir();
		if ( ! Filesystem::is_path_within( $backup_path, $base_dir ) ) {
			$err = new WP_Error(
				'path_traversal_prevented',
				__( 'Security violation: Backup path is outside permitted storage directory.', 'wpcalibrate-watermark-studio' )
			);
			$this->logger->error( 'Path traversal prevented during backup', array( 'attachment_id' => $attachment_id ) );
			return $err;
		}

		// 5. Copy untouched file to backup destination.
		$copied = @copy( $source_file_path, $backup_path );
		if ( ! $copied || ! file_exists( $backup_path ) || filesize( $backup_path ) === 0 ) {
			@unlink( $backup_path );
			$err = new WP_Error(
				'backup_copy_failed',
				__( 'Failed to copy original image to protected backup storage.', 'wpcalibrate-watermark-studio' )
			);
			$this->logger->error( 'Backup copy operation failed', array( 'attachment_id' => $attachment_id ) );
			return $err;
		}

		@chmod( $backup_path, 0644 );

		$this->logger->info(
			'Protected original backup created successfully',
			array( 'attachment_id' => $attachment_id, 'bytes' => filesize( $backup_path ) )
		);

		return $rel_path;
	}

	/**
	 * Restore untouched original file to destination.
	 *
	 * @param int    $attachment_id
	 * @param string $destination_file_path
	 * @return bool|WP_Error True on success or WP_Error.
	 */
	public function restore_to_destination( int $attachment_id, string $destination_file_path ): bool|WP_Error {
		$backup_path = $this->get_absolute_backup_path( $attachment_id );

		if ( ! file_exists( $backup_path ) || filesize( $backup_path ) === 0 ) {
			return new WP_Error(
				'backup_missing',
				__( 'Protected original backup file is missing or empty.', 'wpcalibrate-watermark-studio' )
			);
		}

		// Validate destination path safety within uploads
		$upload_dir = wp_upload_dir();
		if ( ! Filesystem::is_path_within( $destination_file_path, $upload_dir['basedir'] ) ) {
			return new WP_Error(
				'invalid_destination_path',
				__( 'Security violation: Restoration destination is outside uploads directory.', 'wpcalibrate-watermark-studio' )
			);
		}

		$copied = @copy( $backup_path, $destination_file_path );
		if ( ! $copied ) {
			return new WP_Error(
				'restore_copy_failed',
				__( 'Failed to restore original file to target destination.', 'wpcalibrate-watermark-studio' )
			);
		}

		@chmod( $destination_file_path, 0644 );
		return true;
	}

	/**
	 * Delete backup file for an attachment (e.g. when attachment is deleted).
	 *
	 * @param int $attachment_id
	 * @return bool
	 */
	public function delete_backup( int $attachment_id ): bool {
		$path = $this->get_absolute_backup_path( $attachment_id );
		$base = self::get_storage_basedir();

		if ( Filesystem::is_path_within( $path, $base ) && file_exists( $path ) ) {
			return @unlink( $path );
		}

		return false;
	}

	/**
	 * Recursively remove backup folder and files (ONLY during explicit uninstall cleanup).
	 */
	public static function purge_all_backups(): void {
		$base = self::get_storage_basedir();
		if ( ! is_dir( $base ) ) {
			return;
		}

		$files = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $base, \RecursiveDirectoryIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $files as $fileinfo ) {
			if ( $fileinfo->isDir() ) {
				@rmdir( $fileinfo->getRealPath() );
			} else {
				@unlink( $fileinfo->getRealPath() );
			}
		}

		@rmdir( $base );
	}
}

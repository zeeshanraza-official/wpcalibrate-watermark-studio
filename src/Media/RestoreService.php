<?php
declare(strict_types=1);

namespace WPCalibrate\WatermarkStudio\Media;

use WPCalibrate\WatermarkStudio\Support\Logger;
use WPCalibrate\WatermarkStudio\Queue\JobLock;
use WP_Error;

/**
 * Class RestoreService
 *
 * Restores watermarked images to their original untouched state from the protected originals repository,
 * cleanly regenerating WordPress intermediate sizes and resetting attachment metadata.
 */
final class RestoreService {
	public function __construct(
		private readonly OriginalManager $original_manager,
		private readonly AttachmentRepository $attachment_repository,
		private readonly Logger $logger
	) {}

	/**
	 * Restore an attachment from its protected original backup.
	 *
	 * @param int $attachment_id
	 * @return bool|WP_Error
	 */
	public function restore_attachment( int $attachment_id ): bool|WP_Error {
		$lock_key = 'att_' . $attachment_id;
		if ( ! JobLock::acquire( $lock_key ) ) {
			return new WP_Error( 'locked', __( 'Attachment is currently locked by another process.', 'wpcalibrate-watermark-studio' ) );
		}

		try {
			return $this->execute_restore( $attachment_id );
		} finally {
			JobLock::release( $lock_key );
		}
	}

	private function execute_restore( int $attachment_id ): bool|WP_Error {
		// 1. Verify backup exists
		if ( ! $this->original_manager->has_backup( $attachment_id ) ) {
			$err = new WP_Error(
				'backup_not_found',
				__( 'Protected original backup does not exist for this attachment.', 'wpcalibrate-watermark-studio' )
			);
			$this->logger->error( 'Restore failed: no backup file', array( 'attachment_id' => $attachment_id ) );
			return $err;
		}

		$destination_path = get_attached_file( $attachment_id );
		if ( ! $destination_path ) {
			return new WP_Error( 'invalid_attachment', __( 'Could not locate attachment file path.', 'wpcalibrate-watermark-studio' ) );
		}

		// 2. Restore full-size master from untouched backup
		$restored = $this->original_manager->restore_to_destination( $attachment_id, $destination_path );
		if ( is_wp_error( $restored ) ) {
			$this->logger->error( 'Restore copy failed', array( 'attachment_id' => $attachment_id, 'error' => $restored->get_error_message() ) );
			return $restored;
		}

		// 3. Cleanly regenerate all intermediate sizes directly from pristine untouched master
		$backup_path = $this->original_manager->get_absolute_backup_path( $attachment_id );
		$metadata    = function_exists( 'wp_get_attachment_metadata' ) ? wp_get_attachment_metadata( $attachment_id ) : array();

		if ( is_array( $metadata ) && ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
			$base_dir = dirname( $destination_path );
			foreach ( $metadata['sizes'] as $size_key => $size_info ) {
				if ( ! empty( $size_info['file'] ) ) {
					$intermediate_path = $base_dir . '/' . $size_info['file'];
					$this->regenerate_clean_intermediate_size(
						$backup_path,
						$intermediate_path,
						(int) ( $size_info['width'] ?? 0 ),
						(int) ( $size_info['height'] ?? 0 ),
						! empty( $size_info['crop'] )
					);
				}
			}
		}

		// 4. Regenerate WordPress core attachment metadata
		if ( function_exists( 'wp_generate_attachment_metadata' ) ) {
			if ( ! function_exists( 'wp_read_image_metadata' ) && defined( 'ABSPATH' ) && file_exists( ABSPATH . 'wp-admin/includes/image.php' ) ) {
				require_once ABSPATH . 'wp-admin/includes/image.php';
			}
			$new_metadata = wp_generate_attachment_metadata( $attachment_id, $destination_path );
			if ( is_array( $new_metadata ) && ! empty( $new_metadata ) ) {
				wp_update_attachment_metadata( $attachment_id, $new_metadata );
			}
		}

		// 5. Update repository state: record restored (leaves backup intact for future watermarking!)
		$this->attachment_repository->record_restored( $attachment_id );

		$this->logger->info(
			'Attachment restored to pristine original successfully without watermarks',
			array( 'attachment_id' => $attachment_id )
		);

		return true;
	}

	/**
	 * Cleanly regenerate an intermediate size derivative directly from the untouched backup master
	 * into the target upload destination to remove any existing watermarks.
	 */
	private function regenerate_clean_intermediate_size( string $clean_backup_path, string $destination_path, int $width, int $height, bool $crop ): bool {
		if ( ! file_exists( $clean_backup_path ) || $width <= 0 || $height <= 0 ) {
			return false;
		}

		// 1. Primary: Use WordPress image editor
		if ( function_exists( 'wp_get_image_editor' ) ) {
			$editor = wp_get_image_editor( $clean_backup_path );
			if ( ! is_wp_error( $editor ) ) {
				$resized = $editor->resize( $width, $height, $crop );
				if ( ! is_wp_error( $resized ) ) {
					$saved = $editor->save( $destination_path );
					if ( ! is_wp_error( $saved ) && file_exists( $destination_path ) ) {
						return true;
					}
				}
			}
		}

		// 2. Fallback: image_make_intermediate_size (generates in source dir, copy to destination)
		if ( function_exists( 'image_make_intermediate_size' ) ) {
			$regenerated = image_make_intermediate_size( $clean_backup_path, $width, $height, $crop );
			if ( is_array( $regenerated ) && ! empty( $regenerated['file'] ) ) {
				$backup_dir = dirname( $clean_backup_path );
				$gen_file   = $backup_dir . '/' . $regenerated['file'];
				if ( file_exists( $gen_file ) ) {
					@copy( $gen_file, $destination_path );
					@unlink( $gen_file );
					return true;
				}
			}
		}

		// 3. Fallback: GD basic resize if GD is active
		if ( extension_loaded( 'gd' ) ) {
			$img_info = @getimagesize( $clean_backup_path );
			if ( false !== $img_info ) {
				$mime = $img_info['mime'] ?? '';
				$src  = match ( $mime ) {
					'image/jpeg', 'image/jpg' => @imagecreatefromjpeg( $clean_backup_path ),
					'image/png'               => @imagecreatefrompng( $clean_backup_path ),
					'image/webp'              => @imagecreatefromwebp( $clean_backup_path ),
					default                   => false,
				};
				if ( $src ) {
					$src_w = imagesx( $src );
					$src_h = imagesy( $src );
					$dims  = function_exists( 'image_resize_dimensions' ) ? image_resize_dimensions( $src_w, $src_h, $width, $height, $crop ) : false;
					if ( is_array( $dims ) ) {
						list( $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w_use, $src_h_use ) = $dims;
						$dst = imagecreatetruecolor( $dst_w, $dst_h );
						if ( in_array( $mime, array( 'image/png', 'image/webp' ), true ) ) {
							imagealphablending( $dst, false );
							imagesavealpha( $dst, true );
							$trans = imagecolorallocatealpha( $dst, 0, 0, 0, 127 );
							imagefilledrectangle( $dst, 0, 0, $dst_w, $dst_h, $trans );
						}
						imagecopyresampled( $dst, $src, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w_use, $src_h_use );
						match ( $mime ) {
							'image/jpeg', 'image/jpg' => @imagejpeg( $dst, $destination_path, 88 ),
							'image/png'               => @imagepng( $dst, $destination_path, 6 ),
							'image/webp'              => @imagewebp( $dst, $destination_path, 85 ),
							default                   => null,
						};
						imagedestroy( $src );
						imagedestroy( $dst );
						return file_exists( $destination_path );
					}
					imagedestroy( $src );
				}
			}
		}

		return false;
	}
}

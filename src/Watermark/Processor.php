<?php
declare(strict_types=1);

namespace WPCalibrate\WatermarkStudio\Watermark;

use WPCalibrate\WatermarkStudio\Settings\Settings;
use WPCalibrate\WatermarkStudio\Media\OriginalManager;
use WPCalibrate\WatermarkStudio\Media\AttachmentRepository;
use WPCalibrate\WatermarkStudio\Queue\JobLock;
use WPCalibrate\WatermarkStudio\Support\Logger;
use WPCalibrate\WatermarkStudio\Support\Filesystem;
use WP_Error;

/**
 * Class Processor
 *
 * Core watermark processing engine. Coordinates original backup preservation,
 * image size targeting, watermark rendering, idempotency, and concurrency locking.
 */
final class Processor {
	private EditorRendererAdapter $renderer_adapter;

	public function __construct(
		private readonly Settings $settings,
		private readonly OriginalManager $original_manager,
		private readonly AttachmentRepository $attachment_repository,
		private readonly Logger $logger,
		?EditorRendererAdapter $renderer_adapter = null
	) {
		$this->renderer_adapter = $renderer_adapter ?? new EditorRendererAdapter();
	}

	/**
	 * Process watermarking for a single attachment.
	 *
	 * @param int  $attachment_id
	 * @param bool $force Reprocess even if fingerprint matches.
	 * @return bool|WP_Error
	 */
	public function process_attachment( int $attachment_id, bool $force = false ): bool|WP_Error {
		// 1. Concurrency control
		$lock_key = 'att_' . $attachment_id;
		if ( ! JobLock::acquire( $lock_key ) ) {
			$this->logger->warning( 'Skipped concurrent processing; attachment is locked', array( 'attachment_id' => $attachment_id ) );
			return new WP_Error( 'locked', __( 'Attachment is currently being processed by another worker.', 'wpcalibrate-watermark-studio' ) );
		}

		try {
			return $this->execute_processing( $attachment_id, $force );
		} finally {
			JobLock::release( $lock_key );
		}
	}

	/**
	 * Internal execution pipeline.
	 */
	private function execute_processing( int $attachment_id, bool $force ): bool|WP_Error {
		// 2. Validate attachment post
		$post = get_post( $attachment_id );
		if ( ! $post || 'attachment' !== $post->post_type ) {
			return new WP_Error( 'not_an_attachment', __( 'Specified ID is not a valid attachment.', 'wpcalibrate-watermark-studio' ) );
		}

		$mime_type = get_post_mime_type( $attachment_id );
		if ( ! $mime_type || ! str_starts_with( $mime_type, 'image/' ) ) {
			return new WP_Error( 'not_an_image', __( 'Attachment is not an image.', 'wpcalibrate-watermark-studio' ) );
		}

		// Strictly reject SVG and non-raster formats
		if ( 'image/svg+xml' === $mime_type || str_contains( $mime_type, 'svg' ) ) {
			$this->attachment_repository->record_skipped( $attachment_id, 'svg_unsupported', 'SVG images cannot be raster watermarked.' );
			return new WP_Error( 'svg_unsupported', __( 'SVG files cannot be watermarked.', 'wpcalibrate-watermark-studio' ) );
		}

		$allowed_mimes = (array) $this->settings->get( 'allowed_mime_types', array( 'image/jpeg', 'image/png', 'image/webp' ) );
		if ( ! in_array( $mime_type, $allowed_mimes, true ) ) {
			$this->attachment_repository->record_skipped( $attachment_id, 'mime_disallowed', "MIME type {$mime_type} not in allowed list." );
			return new WP_Error( 'mime_disallowed', sprintf( __( 'MIME type "%s" is not enabled for watermarking.', 'wpcalibrate-watermark-studio' ), $mime_type ) );
		}

		// 3. Validate dimensions
		$metadata = wp_get_attachment_metadata( $attachment_id );
		$width    = (int) ( $metadata['width'] ?? 0 );
		$height   = (int) ( $metadata['height'] ?? 0 );

		$min_w = (int) $this->settings->get( 'min_source_width', 300 );
		$min_h = (int) $this->settings->get( 'min_source_height', 300 );

		if ( ( $width > 0 && $width < $min_w ) || ( $height > 0 && $height < $min_h ) ) {
			$this->attachment_repository->record_skipped( $attachment_id, 'dimensions_below_minimum', "Image ({$width}x{$height}) is smaller than required ({$min_w}x{$min_h})." );
			return new WP_Error( 'dimensions_below_minimum', __( 'Image dimensions are below configured minimum threshold.', 'wpcalibrate-watermark-studio' ) );
		}

		// 4. Check configuration and fingerprint
		$current_fingerprint = ConfigurationFingerprint::generate( $this->settings );
		$state               = $this->attachment_repository->get_state( $attachment_id );

		if ( ! $force && $state->matches_fingerprint( $current_fingerprint ) ) {
			$this->logger->info( 'Attachment already up-to-date with current watermark settings', array( 'attachment_id' => $attachment_id ) );
			return true; // Already processed, idempotent skip
		}

		// 5. Get file path
		$full_file_path = get_attached_file( $attachment_id );
		if ( ! $full_file_path || ! file_exists( $full_file_path ) ) {
			$this->attachment_repository->record_failure( $attachment_id, 'file_not_found', 'Main attachment file does not exist on disk.' );
			return new WP_Error( 'file_not_found', __( 'Main attachment file does not exist on disk.', 'wpcalibrate-watermark-studio' ) );
		}

		// 6. CORE PRINCIPLE: Untouched original preservation
		if ( $this->original_manager->has_backup( $attachment_id ) ) {
			// An untouched backup exists: ensure metadata reflects backup and restore clean master
			$this->attachment_repository->set_backup_info( $attachment_id, $this->original_manager->get_relative_backup_path( $attachment_id ) );
			$this->original_manager->restore_to_destination( $attachment_id, $full_file_path );
		} else {
			// First time watermarking: backup the pristine original
			$backup_result = $this->original_manager->backup_original( $attachment_id, $full_file_path );
			if ( is_wp_error( $backup_result ) ) {
				$this->attachment_repository->record_failure( $attachment_id, 'backup_failed', $backup_result->get_error_message() );
				return $backup_result;
			}
			$this->attachment_repository->set_backup_info( $attachment_id, $backup_result );
		}

		// 7. Determine sizes to watermark
		$target_sizes      = (array) $this->settings->get( 'target_sizes', array( 'large', 'medium_large' ) );
		$include_full_size = (bool) $this->settings->get( 'include_full_size', false );

		$renderer = $this->renderer_adapter->get_renderer();
		if ( ! $renderer->is_supported() ) {
			$err = new WP_Error( 'renderer_unavailable', __( 'No supported image rendering engine is available.', 'wpcalibrate-watermark-studio' ) );
			$this->attachment_repository->record_failure( $attachment_id, 'renderer_unavailable', $err->get_error_message() );
			return $err;
		}

		$settings_data = $this->settings->all();
		$base_dir      = dirname( $full_file_path );

		// 8. Process intermediate sizes first (regenerated clean from untouched backup)
		if ( is_array( $metadata ) && ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
			$clean_backup_path = $this->original_manager->get_absolute_backup_path( $attachment_id );

			foreach ( $metadata['sizes'] as $size_key => $size_info ) {
				if ( ! in_array( $size_key, $target_sizes, true ) ) {
					continue;
				}

				if ( empty( $size_info['file'] ) ) {
					continue;
				}

				$intermediate_path = $base_dir . '/' . $size_info['file'];

				// If image was previously watermarked, regenerate clean intermediate size from pristine backup first to prevent stacking
				if ( $state->is_watermarked && file_exists( $clean_backup_path ) ) {
					$this->regenerate_clean_intermediate_size(
						$clean_backup_path,
						$intermediate_path,
						(int) ( $size_info['width'] ?? 0 ),
						(int) ( $size_info['height'] ?? 0 ),
						! empty( $size_info['crop'] )
					);
				}

				if ( file_exists( $intermediate_path ) ) {
					$render_res = $renderer->apply_watermarks( $intermediate_path, $settings_data, $intermediate_path );
					if ( is_wp_error( $render_res ) ) {
						$this->logger->warning(
							"Failed watermarking intermediate size '{$size_key}'",
							array( 'attachment_id' => $attachment_id, 'error' => $render_res->get_error_message() )
						);
					}
				}
			}
		}

		// 9. Process full size if selected
		if ( $include_full_size ) {
			$apply_res = $renderer->apply_watermarks( $full_file_path, $settings_data, $full_file_path );
			if ( is_wp_error( $apply_res ) ) {
				$this->attachment_repository->record_failure( $attachment_id, 'render_failed_full', $apply_res->get_error_message() );
				return $apply_res;
			}
		}

		// 10. Record success
		$this->attachment_repository->record_success( $attachment_id, $current_fingerprint, $renderer->get_name() );
		$this->logger->info(
			'Attachment successfully watermarked',
			array( 'attachment_id' => $attachment_id, 'engine' => $renderer->get_name() )
		);

		return true;
	}

	/**
	 * Cleanly regenerate an intermediate size derivative directly from the untouched backup master
	 * into the target upload destination to prevent watermark stacking / duplication.
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

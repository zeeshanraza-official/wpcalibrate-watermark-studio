<?php
declare(strict_types=1);

namespace WPCalibrate\WatermarkStudio\Media;

use WPCalibrate\WatermarkStudio\Settings\Settings;
use WPCalibrate\WatermarkStudio\Watermark\Processor;
use WPCalibrate\WatermarkStudio\WooCommerce\Integration as WooIntegration;
use WPCalibrate\WatermarkStudio\Support\Logger;

/**
 * Class UploadHandler
 *
 * Hooks into the WordPress media upload and deletion lifecycles to automatically
 * process eligible incoming images without hook recursion or upload breakage.
 */
final class UploadHandler {
	/**
	 * Recursion guard flag.
	 */
	private static bool $is_processing = false;

	public function __construct(
		private readonly Settings $settings,
		private readonly Processor $processor,
		private readonly WooIntegration $woo_integration,
		private readonly Logger $logger
	) {}

	/**
	 * Register upload and deletion lifecycle hooks.
	 */
	public function init(): void {
		// Hook into metadata generation lifecycle after WordPress creates intermediate sizes
		add_filter( 'wp_generate_attachment_metadata', array( $this, 'handle_upload_metadata' ), 20, 2 );

		// Clean up attachment backup/state when an image is permanently deleted
		add_action( 'delete_attachment', array( $this, 'handle_delete_attachment' ), 10, 1 );
	}

	/**
	 * Handle automatic watermarking after WordPress generates attachment sub-sizes.
	 *
	 * @param array<string, mixed> $metadata WordPress attachment metadata.
	 * @param int                  $attachment_id Attachment ID.
	 * @return array<string, mixed>
	 */
	public function handle_upload_metadata( array $metadata, int $attachment_id ): array {
		if ( self::$is_processing ) {
			return $metadata;
		}

		// Check if automatic watermarking is enabled
		if ( ! $this->settings->get( 'enable_automatic', false ) ) {
			return $metadata;
		}

		// Check context restriction (all vs specific post types vs WooCommerce)
		if ( ! $this->is_eligible_context( $attachment_id ) ) {
			return $metadata;
		}

		self::$is_processing = true;

		try {
			// Process without throwing unhandled exceptions that could break the upload
			$result = $this->processor->process_attachment( $attachment_id );
			if ( is_wp_error( $result ) ) {
				$this->logger->warning(
					'Automatic upload watermarking skipped or failed',
					array(
						'attachment_id' => $attachment_id,
						'code'          => $result->get_error_code(),
						'message'       => $result->get_error_message(),
					)
				);
			}
		} catch ( \Throwable $t ) {
			$this->logger->error(
				'Unexpected error during upload watermarking: ' . $t->getMessage(),
				array( 'attachment_id' => $attachment_id )
			);
		} finally {
			self::$is_processing = false;
		}

		return $metadata;
	}

	/**
	 * Check if upload matches configured automation context restrictions.
	 */
	private function is_eligible_context( int $attachment_id ): bool {
		$context = (string) $this->settings->get( 'auto_target_context', 'all' );
		if ( 'all' === $context ) {
			return true;
		}

		$post   = get_post( $attachment_id );
		$parent = $post && $post->post_parent > 0 ? get_post( $post->post_parent ) : null;

		if ( 'woocommerce_products' === $context ) {
			if ( ! $this->woo_integration->is_active() ) {
				return false;
			}
			return $parent && 'product' === $parent->post_type;
		}

		if ( 'post_types' === $context ) {
			$allowed_types = (array) $this->settings->get( 'auto_post_types', array( 'post', 'page' ) );
			return $parent && in_array( $parent->post_type, $allowed_types, true );
		}

		return true;
	}

	/**
	 * Clean up when an attachment is permanently deleted from WordPress.
	 *
	 * @param int $attachment_id
	 */
	public function handle_delete_attachment( int $attachment_id ): void {
		// Only delete backup if administrator explicitly selected full cleanup
		if ( (bool) $this->settings->get( 'uninstall_cleanup', false ) ) {
			\WPCalibrate\WatermarkStudio\Plugin::instance()->get_original_manager()->delete_backup( $attachment_id );
		}

		\WPCalibrate\WatermarkStudio\Plugin::instance()->get_attachment_repository()->delete_state( $attachment_id );
	}
}

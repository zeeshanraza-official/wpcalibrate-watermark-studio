<?php
declare(strict_types=1);

namespace WPCalibrate\WatermarkStudio\Media;

/**
 * Class AttachmentState
 *
 * Immutable data transfer object representing the watermark and backup state of an attachment.
 */
final class AttachmentState {
	public function __construct(
		public readonly int $attachment_id,
		public readonly bool $has_backup,
		public readonly string $backup_relative_path,
		public readonly bool $is_watermarked,
		public readonly string $fingerprint,
		public readonly int $processed_timestamp,
		public readonly string $status,
		public readonly string $error_code,
		public readonly string $error_message,
		public readonly string $processing_version,
		public readonly string $engine
	) {}

	/**
	 * Check if original backup exists and can be restored.
	 */
	public function is_restorable(): bool {
		return $this->has_backup && ! empty( $this->backup_relative_path );
	}

	/**
	 * Check if the attachment already matches the provided configuration fingerprint.
	 *
	 * @param string $current_fingerprint
	 */
	public function matches_fingerprint( string $current_fingerprint ): bool {
		return $this->is_watermarked && '' !== $this->fingerprint && hash_equals( $this->fingerprint, $current_fingerprint );
	}
}

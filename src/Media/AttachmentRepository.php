<?php
declare(strict_types=1);

namespace WPCalibrate\WatermarkStudio\Media;

/**
 * Class AttachmentRepository
 *
 * Persists and retrieves attachment watermark metadata and provides indexed queries.
 */
final class AttachmentRepository {
	public const META_HAS_BACKUP       = '_wpcalibrate_ws_has_backup';
	public const META_BACKUP_REL_PATH  = '_wpcalibrate_ws_backup_rel_path';
	public const META_WATERMARKED      = '_wpcalibrate_ws_watermarked';
	public const META_FINGERPRINT      = '_wpcalibrate_ws_fingerprint';
	public const META_PROCESSED_AT     = '_wpcalibrate_ws_processed_at';
	public const META_STATUS           = '_wpcalibrate_ws_status';
	public const META_ERROR_CODE       = '_wpcalibrate_ws_error_code';
	public const META_ERROR_MSG        = '_wpcalibrate_ws_error_msg';
	public const META_VERSION          = '_wpcalibrate_ws_version';
	public const META_ENGINE           = '_wpcalibrate_ws_engine';

	/**
	 * Retrieve current state of an attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return AttachmentState
	 */
	public function get_state( int $attachment_id ): AttachmentState {
		$has_backup   = '1' === (string) get_post_meta( $attachment_id, self::META_HAS_BACKUP, true );
		$backup_rel   = (string) get_post_meta( $attachment_id, self::META_BACKUP_REL_PATH, true );
		$watermarked  = '1' === (string) get_post_meta( $attachment_id, self::META_WATERMARKED, true );
		$fingerprint  = (string) get_post_meta( $attachment_id, self::META_FINGERPRINT, true );
		$timestamp    = (int) get_post_meta( $attachment_id, self::META_PROCESSED_AT, true );
		$status       = (string) get_post_meta( $attachment_id, self::META_STATUS, true );
		$err_code     = (string) get_post_meta( $attachment_id, self::META_ERROR_CODE, true );
		$err_msg      = (string) get_post_meta( $attachment_id, self::META_ERROR_MSG, true );
		$version      = (string) get_post_meta( $attachment_id, self::META_VERSION, true );
		$engine       = (string) get_post_meta( $attachment_id, self::META_ENGINE, true );

		return new AttachmentState(
			$attachment_id,
			$has_backup,
			$backup_rel,
			$watermarked,
			$fingerprint,
			$timestamp,
			$status,
			$err_code,
			$err_msg,
			$version,
			$engine
		);
	}

	/**
	 * Mark that backup was saved for attachment.
	 *
	 * @param int    $attachment_id
	 * @param string $relative_path
	 */
	public function set_backup_info( int $attachment_id, string $relative_path ): void {
		update_post_meta( $attachment_id, self::META_HAS_BACKUP, '1' );
		update_post_meta( $attachment_id, self::META_BACKUP_REL_PATH, sanitize_text_field( $relative_path ) );
	}

	/**
	 * Record successful watermark processing.
	 *
	 * @param int    $attachment_id
	 * @param string $fingerprint
	 * @param string $engine
	 */
	public function record_success( int $attachment_id, string $fingerprint, string $engine ): void {
		update_post_meta( $attachment_id, self::META_WATERMARKED, '1' );
		update_post_meta( $attachment_id, self::META_FINGERPRINT, sanitize_text_field( $fingerprint ) );
		update_post_meta( $attachment_id, self::META_PROCESSED_AT, time() );
		update_post_meta( $attachment_id, self::META_STATUS, 'success' );
		update_post_meta( $attachment_id, self::META_VERSION, WPCALIBRATE_WATERMARK_STUDIO_VERSION );
		update_post_meta( $attachment_id, self::META_ENGINE, sanitize_text_field( $engine ) );

		delete_post_meta( $attachment_id, self::META_ERROR_CODE );
		delete_post_meta( $attachment_id, self::META_ERROR_MSG );
	}

	/**
	 * Record skipped processing (e.g. below minimum dimensions or non-matching context).
	 *
	 * @param int    $attachment_id
	 * @param string $reason_code
	 * @param string $message
	 */
	public function record_skipped( int $attachment_id, string $reason_code, string $message ): void {
		update_post_meta( $attachment_id, self::META_STATUS, 'skipped' );
		update_post_meta( $attachment_id, self::META_PROCESSED_AT, time() );
		update_post_meta( $attachment_id, self::META_ERROR_CODE, sanitize_text_field( $reason_code ) );
		update_post_meta( $attachment_id, self::META_ERROR_MSG, sanitize_text_field( $message ) );
	}

	/**
	 * Record failed processing.
	 *
	 * @param int    $attachment_id
	 * @param string $error_code
	 * @param string $error_message
	 */
	public function record_failure( int $attachment_id, string $error_code, string $error_message ): void {
		update_post_meta( $attachment_id, self::META_STATUS, 'failed' );
		update_post_meta( $attachment_id, self::META_PROCESSED_AT, time() );
		update_post_meta( $attachment_id, self::META_ERROR_CODE, sanitize_text_field( $error_code ) );
		update_post_meta( $attachment_id, self::META_ERROR_MSG, sanitize_text_field( $error_message ) );
	}

	/**
	 * Record restoration back to clean original state.
	 * Note: Backup relative path remains saved so image can be re-watermarked later!
	 *
	 * @param int $attachment_id
	 */
	public function record_restored( int $attachment_id ): void {
		update_post_meta( $attachment_id, self::META_WATERMARKED, '0' );
		update_post_meta( $attachment_id, self::META_STATUS, 'restored' );
		update_post_meta( $attachment_id, self::META_PROCESSED_AT, time() );
		delete_post_meta( $attachment_id, self::META_FINGERPRINT );
		delete_post_meta( $attachment_id, self::META_ERROR_CODE );
		delete_post_meta( $attachment_id, self::META_ERROR_MSG );
	}

	/**
	 * Remove all plugin-related metadata for an attachment (e.g. upon attachment deletion).
	 *
	 * @param int $attachment_id
	 */
	public function delete_state( int $attachment_id ): void {
		delete_post_meta( $attachment_id, self::META_HAS_BACKUP );
		delete_post_meta( $attachment_id, self::META_BACKUP_REL_PATH );
		delete_post_meta( $attachment_id, self::META_WATERMARKED );
		delete_post_meta( $attachment_id, self::META_FINGERPRINT );
		delete_post_meta( $attachment_id, self::META_PROCESSED_AT );
		delete_post_meta( $attachment_id, self::META_STATUS );
		delete_post_meta( $attachment_id, self::META_ERROR_CODE );
		delete_post_meta( $attachment_id, self::META_ERROR_MSG );
		delete_post_meta( $attachment_id, self::META_VERSION );
		delete_post_meta( $attachment_id, self::META_ENGINE );
	}

	/**
	 * Query batch of eligible image attachment IDs.
	 *
	 * @param int                   $limit Max items per batch.
	 * @param int                   $offset Query offset.
	 * @param array<int, string>    $allowed_mimes Supported MIME types.
	 * @param string|null           $filter Status filter: 'all', 'unwatermarked', 'watermarked', 'failed'.
	 * @return array<int, int> List of attachment IDs.
	 */
	public function get_eligible_attachment_ids(
		int $limit = 50,
		int $offset = 0,
		array $allowed_mimes = array( 'image/jpeg', 'image/png', 'image/webp' ),
		?string $filter = null
	): array {
		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => $allowed_mimes,
			'posts_per_page' => $limit,
			'offset'         => $offset,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'DESC',
		);

		if ( 'watermarked' === $filter ) {
			$args['meta_query'] = array(
				array(
					'key'   => self::META_WATERMARKED,
					'value' => '1',
				),
			);
		} elseif ( 'backed_up' === $filter ) {
			$args['meta_query'] = array(
				array(
					'key'   => self::META_HAS_BACKUP,
					'value' => '1',
				),
			);
		} elseif ( 'unwatermarked' === $filter ) {
			// Robust unwatermarked exclusion: exclude all attachments that are currently watermarked
			global $wpdb;
			$watermarked_ids = $wpdb->get_col( $wpdb->prepare(
				"SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = '1'",
				self::META_WATERMARKED
			) );
			if ( ! empty( $watermarked_ids ) ) {
				$args['post__not_in'] = array_map( 'intval', $watermarked_ids );
			}
		} elseif ( 'failed' === $filter ) {
			$args['meta_query'] = array(
				array(
					'key'   => self::META_STATUS,
					'value' => 'failed',
				),
			);
		}

		$query = new \WP_Query( $args );
		return array_map( 'intval', $query->posts );
	}

	/**
	 * Get total counts for dashboard / bulk metrics.
	 *
	 * @param array<int, string> $allowed_mimes
	 * @return array{total: int, watermarked: int, unwatermarked: int, backed_up: int, failed: int}
	 */
	public function get_counts( array $allowed_mimes = array( 'image/jpeg', 'image/png', 'image/webp' ) ): array {
		global $wpdb;

		// 1. Total images in library matching supported mimes
		$mime_placeholders = implode( ',', array_fill( 0, count( $allowed_mimes ), '%s' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql_total = $wpdb->prepare(
			"SELECT COUNT(p.ID) FROM {$wpdb->posts} p WHERE p.post_type = 'attachment' AND p.post_status = 'inherit' AND p.post_mime_type IN ($mime_placeholders)",
			...$allowed_mimes
		);
		$total = (int) $wpdb->get_var( $sql_total );

		// 2. Watermarked count (matching eligible attachments)
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql_watermarked = $wpdb->prepare(
			"SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			 WHERE p.post_type = 'attachment' AND p.post_status = 'inherit' AND p.post_mime_type IN ($mime_placeholders)
			 AND pm.meta_key = %s AND pm.meta_value = '1'",
			...array_merge( $allowed_mimes, array( self::META_WATERMARKED ) )
		);
		$watermarked = (int) $wpdb->get_var( $sql_watermarked );

		// 3. Backed up count (matching eligible attachments)
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql_backed_up = $wpdb->prepare(
			"SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			 WHERE p.post_type = 'attachment' AND p.post_status = 'inherit' AND p.post_mime_type IN ($mime_placeholders)
			 AND pm.meta_key = %s AND pm.meta_value = '1'",
			...array_merge( $allowed_mimes, array( self::META_HAS_BACKUP ) )
		);
		$backed_up = (int) $wpdb->get_var( $sql_backed_up );

		// 4. Failed count (matching eligible attachments)
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql_failed = $wpdb->prepare(
			"SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			 WHERE p.post_type = 'attachment' AND p.post_status = 'inherit' AND p.post_mime_type IN ($mime_placeholders)
			 AND pm.meta_key = %s AND pm.meta_value = 'failed'",
			...array_merge( $allowed_mimes, array( self::META_STATUS ) )
		);
		$failed = (int) $wpdb->get_var( $sql_failed );

		$unwatermarked = max( 0, $total - $watermarked );

		return array(
			'total'         => $total,
			'watermarked'   => $watermarked,
			'unwatermarked' => $unwatermarked,
			'backed_up'     => $backed_up,
			'failed'        => $failed,
		);
	}
}

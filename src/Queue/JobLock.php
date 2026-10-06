<?php
declare(strict_types=1);

namespace WPCalibrate\WatermarkStudio\Queue;

/**
 * Class JobLock
 *
 * Implements transient-based atomic locking to prevent race conditions and concurrent processing of the same attachment.
 */
final class JobLock {
	private const LOCK_PREFIX = 'wpcalibrate_ws_lock_';
	private const DEFAULT_TTL = 180; // 3 minutes timeout

	/**
	 * Acquire lock on a resource.
	 *
	 * @param string $resource_id Identifier (e.g. "att_123" or "batch_processor").
	 * @param int    $ttl Time to live in seconds.
	 * @return bool True if acquired, false if already locked.
	 */
	public static function acquire( string $resource_id, int $ttl = self::DEFAULT_TTL ): bool {
		$transient_key = self::LOCK_PREFIX . sanitize_key( $resource_id );
		$existing      = get_transient( $transient_key );

		if ( false !== $existing ) {
			return false; // Already locked
		}

		return set_transient( $transient_key, time(), $ttl );
	}

	/**
	 * Release lock on a resource.
	 *
	 * @param string $resource_id Identifier.
	 */
	public static function release( string $resource_id ): void {
		$transient_key = self::LOCK_PREFIX . sanitize_key( $resource_id );
		delete_transient( $transient_key );
	}

	/**
	 * Check if a resource is currently locked.
	 *
	 * @param string $resource_id Identifier.
	 * @return bool
	 */
	public static function is_locked( string $resource_id ): bool {
		$transient_key = self::LOCK_PREFIX . sanitize_key( $resource_id );
		return false !== get_transient( $transient_key );
	}
}

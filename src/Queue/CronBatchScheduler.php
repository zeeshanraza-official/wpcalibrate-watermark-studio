<?php
declare(strict_types=1);

namespace WPCalibrate\WatermarkStudio\Queue;

/**
 * Class CronBatchScheduler
 *
 * Implements fallback batch background scheduling via native WordPress WP-Cron events.
 */
final class CronBatchScheduler implements SchedulerInterface {
	public const CRON_HOOK = 'wpcalibrate_watermark_studio_batch_cron';

	public function get_name(): string {
		return 'wp_cron';
	}

	public function is_available(): bool {
		return true; // Always available in WordPress
	}

	public function schedule_batch( int $delay_seconds = 0 ): bool {
		$next = wp_next_scheduled( self::CRON_HOOK );
		if ( false !== $next ) {
			return true; // Already scheduled, avoid duplicate event flooding
		}

		$time = time() + max( 1, $delay_seconds );
		return (bool) wp_schedule_single_event( $time, self::CRON_HOOK );
	}

	public function cancel_pending(): void {
		self::unschedule_all();
	}

	public static function unschedule_all(): void {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		while ( false !== $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
			$timestamp = wp_next_scheduled( self::CRON_HOOK );
		}
	}
}

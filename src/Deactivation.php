<?php
declare(strict_types=1);

namespace WPCalibrate\WatermarkStudio;

use WPCalibrate\WatermarkStudio\Queue\CronBatchScheduler;
use WPCalibrate\WatermarkStudio\Queue\JobLock;

/**
 * Class Deactivation
 *
 * Cleanly suspends background processes, unschedules cron jobs, and clears transient locks on deactivation.
 */
final class Deactivation {
	/**
	 * Perform deactivation cleanup.
	 *
	 * @param bool $network_wide Whether deactivated across the multisite network.
	 */
	public static function deactivate( bool $network_wide = false ): void {
		if ( function_exists( 'is_multisite' ) && is_multisite() && $network_wide ) {
			$sites = get_sites( array( 'fields' => 'ids' ) );
			foreach ( $sites as $site_id ) {
				switch_to_blog( (int) $site_id );
				self::deactivate_single_site();
				restore_current_blog();
			}
		} else {
			self::deactivate_single_site();
		}
	}

	/**
	 * Deactivate for a single site / blog.
	 */
	private static function deactivate_single_site(): void {
		// 1. Unschedule cron batch event.
		CronBatchScheduler::unschedule_all();

		// 2. Clear any queue execution lock.
		JobLock::release( 'batch_processor' );
	}
}

<?php
declare(strict_types=1);

namespace WPCalibrate\WatermarkStudio\Queue;

/**
 * Interface SchedulerInterface
 *
 * Abstraction layer for background job queue dispatchers (Action Scheduler, WP-Cron, etc.).
 */
interface SchedulerInterface {
	/**
	 * Get scheduler identifier ('action_scheduler' or 'wp_cron').
	 */
	public function get_name(): string;

	/**
	 * Check if this scheduler engine is active and usable.
	 */
	public function is_available(): bool;

	/**
	 * Schedule a recurring or single background batch execution.
	 *
	 * @param int $delay_seconds Delay in seconds before triggering.
	 * @return bool
	 */
	public function schedule_batch( int $delay_seconds = 0 ): bool;

	/**
	 * Unschedule any pending scheduled batch jobs.
	 */
	public function cancel_pending(): void;
}

<?php
/**
 * Automatic Update Scheduler
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\Cron
 */

declare(strict_types=1);

namespace Automattic\MSM_Sitemap\Infrastructure\Cron;

/**
 * WordPress cron scheduling adapter for automatic sitemap updates.
 *
 * Handles the low-level WordPress cron operations for scheduling and
 * unscheduling the recurring automatic update cron event. This class
 * contains no business logic - it's a pure adapter for WordPress cron
 * functions.
 *
 * For on-demand/background sitemap generation scheduling, see BackgroundGenerationScheduler.
 */
class AutomaticUpdateScheduler {

	/**
	 * The cron hook name for automatic sitemap updates.
	 */
	public const CRON_HOOK = 'msm_cron_update_sitemap';

	/**
	 * Schedule a recurring cron event for automatic sitemap updates.
	 *
	 * @param string $interval The WordPress cron interval (e.g., 'hourly', 'ms-sitemap-15-min-cron-interval').
	 * @return bool True if scheduled successfully, false if already scheduled or failed.
	 */
	public function schedule_recurring_event( string $interval ): bool {
		if ( $this->is_event_scheduled() ) {
			return false;
		}

		$result = wp_schedule_event( time(), $interval, self::CRON_HOOK );
		return false !== $result;
	}

	/**
	 * Reschedule the recurring cron event with a new interval.
	 *
	 * @param string $interval The WordPress cron interval.
	 * @return bool True if rescheduled successfully.
	 */
	public function reschedule_event( string $interval ): bool {
		$this->unschedule_event();
		return $this->schedule_recurring_event( $interval );
	}

	/**
	 * Unschedule the automatic update cron event.
	 */
	public function unschedule_event(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}


	/**
	 * Check if the automatic update event is scheduled.
	 *
	 * @return bool True if scheduled, false otherwise.
	 */
	public function is_event_scheduled(): bool {
		return false !== wp_next_scheduled( self::CRON_HOOK );
	}

	/**
	 * Get the next scheduled time for automatic updates.
	 *
	 * @return int|false Unix timestamp of next run, or false if not scheduled.
	 */
	public function get_next_scheduled_time(): int|false {
		return wp_next_scheduled( self::CRON_HOOK );
	}

	/**
	 * Check if WordPress cron is working properly.
	 *
	 * @return bool True if cron appears to be working, false otherwise.
	 */
	public function is_wp_cron_working(): bool {
		$scheduled_events = _get_cron_array();
		return ! empty( $scheduled_events );
	}

	/**
	 * Map a frequency string to a WordPress cron interval.
	 *
	 * @param string $frequency The frequency string (e.g., '5min', '15min', 'hourly').
	 * @return string The WordPress cron interval, or empty string if invalid.
	 */
	public function map_frequency_to_interval( string $frequency ): string {
		$interval_map = array(
			'5min'    => 'ms-sitemap-5-min-cron-interval',
			'10min'   => 'ms-sitemap-10-min-cron-interval',
			'15min'   => 'ms-sitemap-15-min-cron-interval',
			'30min'   => 'ms-sitemap-30-min-cron-interval',
			'hourly'  => 'hourly',
			'2hourly' => 'ms-sitemap-2-hour-cron-interval',
			'3hourly' => 'ms-sitemap-3-hour-cron-interval',
		);

		return $interval_map[ $frequency ] ?? '';
	}
}

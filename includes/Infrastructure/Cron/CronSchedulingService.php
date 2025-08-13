<?php
/**
 * Cron Scheduling Service
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\Cron
 */

declare(strict_types=1);

namespace Automattic\MSM_Sitemap\Infrastructure\Cron;

/**
 * Service class for managing global cron functionality.
 * 
 * Handles enabling/disabling cron, halt operations, and general
 * cron management across all sitemap generation handlers.
 * Used by CLI, admin UI, and other cron handlers.
 */
class CronSchedulingService {

	/**
	 * Option name for tracking if cron is enabled
	 */
	const CRON_ENABLED_OPTION = 'msm_sitemap_cron_enabled';

	/**
	 * Enable the sitemap cron functionality
	 * 
	 * This schedules the recurring incremental update cron job
	 * and marks cron as enabled in the database.
	 */
	public static function enable(): void {
		// Set cron as enabled
		update_option( self::CRON_ENABLED_OPTION, 1 );

		// Schedule incremental updates if not already scheduled
		if ( ! wp_next_scheduled( 'msm_cron_update_sitemap' ) ) {
			$container         = \Automattic\MSM_Sitemap\Infrastructure\DI\msm_sitemap_container();
			$settings_service  = $container->get( \Automattic\MSM_Sitemap\Application\Services\SettingsService::class );
			$current_frequency = $settings_service->get_setting( 'cron_frequency', '15min' );
			$interval = self::map_frequency_to_interval( $current_frequency );
			wp_schedule_event( time(), $interval, 'msm_cron_update_sitemap' );
		}
	}

	/**
	 * Disable the sitemap cron functionality
	 * 
	 * This clears all scheduled cron events and marks cron as disabled.
	 */
	public static function disable(): void {
		// Set cron as disabled
		update_option( self::CRON_ENABLED_OPTION, 0 );

		// Clear all scheduled events
		wp_clear_scheduled_hook( 'msm_cron_update_sitemap' );
		wp_clear_scheduled_hook( 'msm_cron_generate_sitemap_for_year' );
		wp_clear_scheduled_hook( 'msm_cron_generate_sitemap_for_year_month' );
		wp_clear_scheduled_hook( 'msm_cron_generate_sitemap_for_year_month_day' );

		// Clear generation state
		delete_option( 'msm_generation_in_progress' );
		delete_option( 'msm_years_to_process' );
		delete_option( 'msm_months_to_process' );
		delete_option( 'msm_days_to_process' );
		delete_option( 'msm_current_year' );
		delete_option( 'msm_current_month' );
	}

	/**
	 * Check if cron is enabled
	 * 
	 * @return bool True if cron is enabled, false otherwise.
	 */
	public static function is_enabled(): bool {
		// Allow override via filter (useful for testing)
		$filter_override = apply_filters( 'msm_sitemap_cron_enabled', null );
		if ( null !== $filter_override ) {
			return (bool) $filter_override;
		}

		return (bool) get_option( self::CRON_ENABLED_OPTION, 0 );
	}

	/**
	 * Check if cron is enabled (legacy method name for backward compatibility)
	 * 
	 * @return bool True if cron is enabled, false otherwise.
	 */
	public static function is_cron_enabled(): bool {
		$is_enabled = self::is_enabled();
		$next_scheduled = wp_next_scheduled( 'msm_cron_update_sitemap' );

		// Ensure consistency - if enabled is false but there's a scheduled event, clear it
		if ( ! $is_enabled && $next_scheduled ) {
			wp_unschedule_hook( 'msm_cron_update_sitemap' );
		}

		return $is_enabled;
	}

	/**
	 * Enable cron (legacy method name for backward compatibility)
	 * 
	 * @return bool True if enabled successfully, false if already enabled.
	 */
	public static function enable_cron(): bool {
		if ( self::is_enabled() ) {
			return false; // Already enabled
		}

		self::enable();
		return true;
	}

	/**
	 * Disable cron (legacy method name for backward compatibility)
	 * 
	 * @return bool True if disabled successfully, false if already disabled.
	 */
	public static function disable_cron(): bool {
		if ( ! self::is_enabled() ) {
			return false; // Already disabled
		}

		self::disable();
		return true;
	}

	/**
	 * Reschedule the cron job with a new frequency
	 * 
	 * @param string $frequency The new frequency (e.g., '5min', '10min', '15min', '30min', 'hourly', 'twicedaily', 'thricehourly').
	 * @return bool True if rescheduled successfully, false otherwise.
	 */
	public static function reschedule_cron( string $frequency ): bool {
		if ( ! self::is_enabled() ) {
			return false; // Cron must be enabled to reschedule
		}

		// Clear the existing schedule
		wp_clear_scheduled_hook( 'msm_cron_update_sitemap' );

		// Map frequency to WordPress cron interval
		$interval = self::map_frequency_to_interval( $frequency );
		if ( empty( $interval ) ) {
			return false; // Invalid frequency
		}

		// Schedule with new frequency - WordPress cron will start immediately and then repeat at the interval
		wp_schedule_event( time(), $interval, 'msm_cron_update_sitemap' );

		return true;
	}

	/**
	 * Map frequency string to WordPress cron interval
	 * 
	 * @param string $frequency The frequency string.
	 * @return string The WordPress cron interval or empty string if invalid.
	 */
	private static function map_frequency_to_interval( string $frequency ): string {
		$interval_map = array(
			'5min'        => 'ms-sitemap-5-min-cron-interval',
			'10min'       => 'ms-sitemap-10-min-cron-interval',
			'15min'       => 'ms-sitemap-15-min-cron-interval',
			'30min'       => 'ms-sitemap-30-min-cron-interval',
			'hourly'      => 'hourly',
			'2hourly'     => 'ms-sitemap-2-hour-cron-interval',
			'3hourly'     => 'ms-sitemap-3-hour-cron-interval',
		);

		return isset( $interval_map[ $frequency ] ) ? $interval_map[ $frequency ] : '';
	}

	/**
	 * Get the number of seconds for a given interval
	 * 
	 * @param string $interval The WordPress cron interval.
	 * @return int The number of seconds.
	 */
	private static function get_interval_seconds( string $interval ): int {
		$interval_map = array(
			'ms-sitemap-5-min-cron-interval'  => 300,   // 5 minutes
			'ms-sitemap-10-min-cron-interval' => 600,   // 10 minutes
			'ms-sitemap-15-min-cron-interval' => 900,   // 15 minutes
			'ms-sitemap-30-min-cron-interval' => 1800,  // 30 minutes
			'hourly'                          => 3600,  // 1 hour
			'ms-sitemap-2-hour-cron-interval' => 7200,  // 2 hours
			'ms-sitemap-3-hour-cron-interval' => 10800, // 3 hours
		);

		return isset( $interval_map[ $interval ] ) ? $interval_map[ $interval ] : 900; // Default to 15 minutes
	}

	/**
	 * Reset cron (clears all scheduled events and resets state)
	 * 
	 * @return bool True on success.
	 */
	public static function reset_cron(): bool {
		self::disable();
		
		// Clear additional legacy options used by tests
		delete_option( 'msm_generation_in_progress' );
		delete_option( 'msm_stop_processing' );
		
		return true;
	}

	/**
	 * Handle full generation (legacy method name for backward compatibility)
	 * 
	 * Bypasses can_execute() checks for direct calls (tests, etc.)
	 */
	public static function handle_full_generation(): void {
		// Set the generation in progress flag
		update_option( 'msm_generation_in_progress', true );

		// Clear any existing generation state to start fresh
		delete_option( 'msm_years_to_process' );
		delete_option( 'msm_months_to_process' );
		delete_option( 'msm_days_to_process' );

				$all_years_with_posts = msm_sitemap_plugin()->get_years_with_posts();
		update_option( 'msm_years_to_process', $all_years_with_posts );

		if ( ! empty( $all_years_with_posts ) ) {
			// Schedule the first year for processing, but keep all years in the option for test inspection
			$year = $all_years_with_posts[0];

			// Schedule year generation
			wp_schedule_single_event( time() + 10, 'msm_cron_generate_sitemap_for_year', array( $year ) );
		} else {
			// No years to process, mark generation as complete
			delete_option( 'msm_generation_in_progress' );
		}
	}

	/**
	 * Get the current cron status (legacy method name for backward compatibility)
	 * 
	 * @return array Status information about the cron
	 */
	public static function get_cron_status(): array {
		$is_enabled     = self::is_cron_enabled();
		$next_scheduled = wp_next_scheduled( 'msm_cron_update_sitemap' );
		$is_blog_public = \Automattic\MSM_Sitemap\Domain\ValueObjects\Site::is_public();
		$is_generating  = (bool) get_option( 'msm_generation_in_progress' );
		$is_halted      = (bool) get_option( 'msm_stop_processing' );

		// Ensure consistency - if enabled is false but there's a scheduled event, clear it
		if ( ! $is_enabled && $next_scheduled ) {
			wp_unschedule_hook( 'msm_cron_update_sitemap' );
			$next_scheduled = false;
		}

		return array(
			'enabled'        => $is_enabled,
			'next_scheduled' => $next_scheduled,
			'blog_public'    => $is_blog_public,
			'generating'     => $is_generating,
			'halted'         => $is_halted,
		);
	}

	/**
	 * Get the current cron status with additional information
	 * 
	 * @return array Status information including enabled state and scheduled events.
	 */
	public static function get_status(): array {
		$enabled = self::is_enabled();
		$next_incremental = wp_next_scheduled( 'msm_cron_update_sitemap' );
		$generation_in_progress = get_option( 'msm_generation_in_progress', false );

		return array(
			'enabled' => $enabled,
			'next_incremental_update' => $next_incremental ? $next_incremental : false,
			'generation_in_progress' => $generation_in_progress,
			'last_run' => get_option( 'msm_sitemap_update_last_run', false ),
		);
	}

	/**
	 * Halt the current generation process
	 * 
	 * This stops any ongoing full generation and clears all scheduled events.
	 */
	public static function halt_execution(): void {
		// Clear all scheduled events
		wp_clear_scheduled_hook( 'msm_cron_generate_sitemap_for_year' );
		wp_clear_scheduled_hook( 'msm_cron_generate_sitemap_for_year_month' );
		wp_clear_scheduled_hook( 'msm_cron_generate_sitemap_for_year_month_day' );

		// Clear generation state
		delete_option( 'msm_generation_in_progress' );
		delete_option( 'msm_years_to_process' );
		delete_option( 'msm_months_to_process' );
		delete_option( 'msm_days_to_process' );
		delete_option( 'msm_current_year' );
		delete_option( 'msm_current_month' );
	}

	/**
	 * Check if a generation process is currently running
	 * 
	 * @return bool True if generation is in progress, false otherwise.
	 */
	public static function is_generation_in_progress(): bool {
		return (bool) get_option( 'msm_generation_in_progress', false );
	}

	/**
	 * Schedule a full generation process
	 * 
	 * This initiates the cascading full sitemap generation process.
	 */
	public static function schedule_full_generation(): void {
		if ( ! self::is_enabled() ) {
			return;
		}

		// Check if generation is already in progress
		if ( self::is_generation_in_progress() ) {
			return;
		}

		// Set the generation in progress flag
		update_option( 'msm_generation_in_progress', true );

		// Clear any existing generation state to start fresh
		delete_option( 'msm_years_to_process' );
		delete_option( 'msm_months_to_process' );
		delete_option( 'msm_days_to_process' );

		// Check if a partial generation is already running
		$is_partial_or_running = get_option( 'msm_generation_in_progress', false );

		if ( empty( $is_partial_or_running ) ) {
			$all_years_with_posts = msm_sitemap_plugin()->get_years_with_posts();
			update_option( 'msm_years_to_process', $all_years_with_posts );
		} else {
			// Continue with existing year list if generation was in progress
			$all_years_with_posts = get_option( 'msm_years_to_process', array() );
		}

		if ( ! empty( $all_years_with_posts ) ) {
			// Schedule the first year for processing
			$year = array_shift( $all_years_with_posts );
			update_option( 'msm_years_to_process', $all_years_with_posts );

			// Schedule year generation
			wp_schedule_single_event( time() + 10, 'msm_cron_generate_sitemap_for_year', array( $year ) );
		} else {
			// No years to process, mark generation as complete
			delete_option( 'msm_generation_in_progress' );
		}
	}

	/**
	 * Check if WordPress cron is working properly
	 * 
	 * @return bool True if cron appears to be working, false otherwise.
	 */
	public static function is_wp_cron_working(): bool {
		// Simple check - if we have scheduled events, WP-Cron is likely working
		$scheduled_events = _get_cron_array();
		return ! empty( $scheduled_events );
	}

	/**
	 * Get information about scheduled sitemap cron events
	 * 
	 * @return array Information about scheduled events.
	 */
	public static function get_scheduled_events(): array {
		$events = array();

		// Check for incremental updates
		$next_incremental = wp_next_scheduled( 'msm_cron_update_sitemap' );
		if ( $next_incremental ) {
			$events['incremental_update'] = array(
				'hook' => 'msm_cron_update_sitemap',
				'next_run' => $next_incremental,
				'recurrence' => 'ms-sitemap-15-min-cron-interval',
			);
		}

		// Check for full generation events
		$hooks = array(
			'msm_cron_generate_sitemap_for_year',
			'msm_cron_generate_sitemap_for_year_month',
			'msm_cron_generate_sitemap_for_year_month_day',
		);

		foreach ( $hooks as $hook ) {
			$next_run = wp_next_scheduled( $hook );
			if ( $next_run ) {
				$events[ $hook ] = array(
					'hook' => $hook,
					'next_run' => $next_run,
					'recurrence' => false, // These are single events
				);
			}
		}

		return $events;
	}
}

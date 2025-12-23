<?php
/**
 * Cron Scheduler
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\Cron
 */

declare(strict_types=1);

namespace Automattic\MSM_Sitemap\Infrastructure\Cron;

use Automattic\MSM_Sitemap\Application\Services\SettingsService;
use Automattic\MSM_Sitemap\Application\Services\GenerationStateService;
use Automattic\MSM_Sitemap\Domain\ValueObjects\Site;

/**
 * Scheduler for WordPress cron jobs.
 *
 * Handles enabling/disabling the recurring automatic update cron job
 * and provides status information about cron state.
 */
class CronScheduler {

	/**
	 * Option name for tracking if cron is enabled.
	 */
	public const CRON_ENABLED_OPTION = 'msm_sitemap_cron_enabled';

	/**
	 * The settings service.
	 *
	 * @var SettingsService
	 */
	private SettingsService $settings;

	/**
	 * The generation state service.
	 *
	 * @var GenerationStateService
	 */
	private GenerationStateService $generation_state;

	/**
	 * Constructor.
	 *
	 * @param SettingsService        $settings         The settings service.
	 * @param GenerationStateService $generation_state The generation state service.
	 */
	public function __construct( SettingsService $settings, GenerationStateService $generation_state ) {
		$this->settings         = $settings;
		$this->generation_state = $generation_state;
	}

	/**
	 * Enable the sitemap cron functionality.
	 *
	 * Schedules the recurring automatic update cron job
	 * and marks cron as enabled in the database.
	 */
	public function enable(): void {
		update_option( self::CRON_ENABLED_OPTION, 1 );

		if ( ! wp_next_scheduled( 'msm_cron_update_sitemap' ) ) {
			$current_frequency = $this->settings->get_setting( 'cron_frequency', '15min' );
			$interval          = $this->map_frequency_to_interval( $current_frequency );
			wp_schedule_event( time(), $interval, 'msm_cron_update_sitemap' );
		}
	}

	/**
	 * Disable the sitemap cron functionality.
	 *
	 * Clears all scheduled cron events and marks cron as disabled.
	 */
	public function disable(): void {
		update_option( self::CRON_ENABLED_OPTION, 0 );

		// Clear the automatic update cron
		wp_clear_scheduled_hook( 'msm_cron_update_sitemap' );

		// Clear any background generation events
		wp_clear_scheduled_hook( SitemapGenerationScheduler::CRON_HOOK );

		// Clear generation state
		$this->generation_state->clear_all_state();
	}

	/**
	 * Check if cron is enabled.
	 *
	 * @return bool True if cron is enabled, false otherwise.
	 */
	public function is_enabled(): bool {
		// Allow override via filter (useful for testing)
		$filter_override = apply_filters( 'msm_sitemap_cron_enabled', null );
		if ( null !== $filter_override ) {
			return (bool) $filter_override;
		}

		return (bool) get_option( self::CRON_ENABLED_OPTION, 0 );
	}

	/**
	 * Check if cron is enabled (legacy method name for backward compatibility).
	 *
	 * @return bool True if cron is enabled, false otherwise.
	 */
	public function is_cron_enabled(): bool {
		$is_enabled     = $this->is_enabled();
		$next_scheduled = wp_next_scheduled( 'msm_cron_update_sitemap' );

		// Ensure consistency - if enabled is false but there's a scheduled event, clear it
		if ( ! $is_enabled && $next_scheduled ) {
			wp_unschedule_hook( 'msm_cron_update_sitemap' );
		}

		return $is_enabled;
	}

	/**
	 * Enable cron (legacy method name for backward compatibility).
	 *
	 * @return bool True if enabled successfully, false if already enabled.
	 */
	public function enable_cron(): bool {
		if ( $this->is_enabled() ) {
			return false;
		}

		$this->enable();
		return true;
	}

	/**
	 * Disable cron (legacy method name for backward compatibility).
	 *
	 * @return bool True if disabled successfully, false if already disabled.
	 */
	public function disable_cron(): bool {
		if ( ! $this->is_enabled() ) {
			return false;
		}

		$this->disable();
		return true;
	}

	/**
	 * Reschedule the cron job with a new frequency.
	 *
	 * @param string $frequency The new frequency (e.g., '5min', '15min', 'hourly').
	 * @return bool True if rescheduled successfully, false otherwise.
	 */
	public function reschedule_cron( string $frequency ): bool {
		if ( ! $this->is_enabled() ) {
			return false;
		}

		wp_clear_scheduled_hook( 'msm_cron_update_sitemap' );

		$interval = $this->map_frequency_to_interval( $frequency );
		if ( empty( $interval ) ) {
			return false;
		}

		wp_schedule_event( time(), $interval, 'msm_cron_update_sitemap' );

		return true;
	}

	/**
	 * Reset cron (clears all scheduled events and resets state).
	 *
	 * @return bool True on success.
	 */
	public function reset_cron(): bool {
		$this->disable();
		$this->generation_state->clear_all_state();

		return true;
	}

	/**
	 * Get the current cron status.
	 *
	 * @return array{enabled: bool, next_scheduled: int|false, blog_public: bool, generating: bool, halted: bool}
	 */
	public function get_cron_status(): array {
		$is_enabled     = $this->is_cron_enabled();
		$next_scheduled = wp_next_scheduled( 'msm_cron_update_sitemap' );
		$is_blog_public = Site::is_public();

		$is_generating = $this->generation_state->is_generation_in_progress();
		$is_halted     = $this->generation_state->is_stop_requested();

		// Ensure consistency
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
	 * Get the current cron status with additional information.
	 *
	 * @return array{enabled: bool, next_incremental_update: int|false, generation_in_progress: bool, last_run: int|false}
	 */
	public function get_status(): array {
		$enabled          = $this->is_enabled();
		$next_incremental = wp_next_scheduled( 'msm_cron_update_sitemap' );

		$generation_in_progress = $this->generation_state->is_generation_in_progress();
		$last_run               = $this->generation_state->get_last_run_time();

		return array(
			'enabled'                 => $enabled,
			'next_incremental_update' => $next_incremental ? $next_incremental : false,
			'generation_in_progress'  => $generation_in_progress,
			'last_run'                => $last_run ? $last_run : false,
		);
	}

	/**
	 * Check if a generation process is currently running.
	 *
	 * @return bool True if generation is in progress, false otherwise.
	 */
	public function is_generation_in_progress(): bool {
		return $this->generation_state->is_generation_in_progress();
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
	 * Map frequency string to WordPress cron interval.
	 *
	 * @param string $frequency The frequency string.
	 * @return string The WordPress cron interval or empty string if invalid.
	 */
	private function map_frequency_to_interval( string $frequency ): string {
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

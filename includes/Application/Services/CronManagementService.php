<?php
/**
 * Cron Management Service
 *
 * @package Automattic\MSM_Sitemap\Application\Services
 */

declare(strict_types=1);

namespace Automattic\MSM_Sitemap\Application\Services;

use Automattic\MSM_Sitemap\Infrastructure\Cron\AutomaticUpdateScheduler;
use Automattic\MSM_Sitemap\Domain\ValueObjects\Site;

/**
 * Service for managing cron functionality.
 *
 * This Application layer service handles all business logic around the
 * automatic sitemap update feature, including:
 * - Enabling/disabling the feature (persisting the setting)
 * - Managing update frequency
 * - Providing comprehensive status information
 *
 * Delegates to CronScheduler for actual WordPress cron operations.
 */
class CronManagementService {

	/**
	 * Option name for tracking if cron is enabled.
	 */
	public const CRON_ENABLED_OPTION = 'msm_sitemap_cron_enabled';

	/**
	 * Valid cron frequencies.
	 */
	public const VALID_FREQUENCIES = array(
		'5min',
		'10min',
		'15min',
		'30min',
		'hourly',
		'2hourly',
		'3hourly',
	);

	/**
	 * Default cron frequency.
	 */
	public const DEFAULT_FREQUENCY = '15min';

	/**
	 * The settings service.
	 *
	 * @var SettingsService
	 */
	private SettingsService $settings;

	/**
	 * The automatic update scheduler.
	 *
	 * @var AutomaticUpdateScheduler
	 */
	private AutomaticUpdateScheduler $update_scheduler;

	/**
	 * The generation state service.
	 *
	 * @var GenerationStateService
	 */
	private GenerationStateService $generation_state;

	/**
	 * Constructor.
	 *
	 * @param SettingsService          $settings         The settings service.
	 * @param AutomaticUpdateScheduler $update_scheduler The automatic update scheduler.
	 * @param GenerationStateService   $generation_state The generation state service.
	 */
	public function __construct(
		SettingsService $settings,
		AutomaticUpdateScheduler $update_scheduler,
		GenerationStateService $generation_state
	) {
		$this->settings         = $settings;
		$this->update_scheduler = $update_scheduler;
		$this->generation_state = $generation_state;
	}

	/**
	 * Check if automatic sitemap updates are enabled.
	 *
	 * @return bool True if enabled, false otherwise.
	 */
	public function is_enabled(): bool {
		// Allow override via filter (useful for testing).
		$filter_override = apply_filters( 'msm_sitemap_cron_enabled', null );
		if ( null !== $filter_override ) {
			return (bool) $filter_override;
		}

		return (bool) get_option( self::CRON_ENABLED_OPTION, 0 );
	}

	/**
	 * Enable automatic sitemap updates.
	 *
	 * @return array{success: bool, message: string, error_code?: string} Result.
	 */
	public function enable_cron(): array {
		if ( ! Site::is_public() ) {
			return array(
				'success'    => false,
				'message'    => __( 'Cannot enable cron: blog is not public.', 'msm-sitemap' ),
				'error_code' => 'blog_not_public',
			);
		}

		if ( $this->is_enabled() ) {
			return array(
				'success'    => false,
				'message'    => __( 'Automatic updates are already enabled.', 'msm-sitemap' ),
				'error_code' => 'already_enabled',
			);
		}

		// Mark as enabled.
		update_option( self::CRON_ENABLED_OPTION, 1 );

		// Schedule the cron event.
		$frequency = $this->get_current_frequency();
		$interval  = $this->update_scheduler->map_frequency_to_interval( $frequency );
		$this->update_scheduler->schedule_recurring_event( $interval );

		return array(
			'success' => true,
			'message' => __( 'Automatic sitemap updates enabled successfully.', 'msm-sitemap' ),
		);
	}

	/**
	 * Disable automatic sitemap updates.
	 *
	 * @return array{success: bool, message: string, error_code?: string} Result.
	 */
	public function disable_cron(): array {
		if ( ! $this->is_enabled() ) {
			return array(
				'success'    => false,
				'message'    => __( 'Automatic updates are already disabled.', 'msm-sitemap' ),
				'error_code' => 'already_disabled',
			);
		}

		// Mark as disabled.
		update_option( self::CRON_ENABLED_OPTION, 0 );

		// Unschedule automatic update cron event.
		$this->update_scheduler->unschedule_event();

		// Also clear any pending background generation events.
		wp_clear_scheduled_hook( 'msm_cron_generate_sitemap_for_date' );

		// Clear generation state.
		$this->generation_state->clear_all_state();

		return array(
			'success' => true,
			'message' => __( 'Automatic sitemap updates disabled successfully.', 'msm-sitemap' ),
		);
	}

	/**
	 * Reset cron to a clean state.
	 *
	 * @return array{success: bool, message: string} Result.
	 */
	public function reset_cron(): array {
		// Mark as disabled.
		update_option( self::CRON_ENABLED_OPTION, 0 );

		// Unschedule automatic update cron event.
		$this->update_scheduler->unschedule_event();

		// Also clear any pending background generation events.
		wp_clear_scheduled_hook( 'msm_cron_generate_sitemap_for_date' );

		// Clear all generation state.
		$this->generation_state->clear_all_state();

		return array(
			'success' => true,
			'message' => __( 'Sitemap cron reset to clean state.', 'msm-sitemap' ),
		);
	}

	/**
	 * Get all valid cron frequencies.
	 *
	 * @return array<string> Array of valid frequency strings.
	 */
	public static function get_valid_frequencies(): array {
		return self::VALID_FREQUENCIES;
	}

	/**
	 * Check if a frequency is valid.
	 *
	 * @param string $frequency The frequency to validate.
	 * @return bool True if valid, false otherwise.
	 */
	public static function is_valid_frequency( string $frequency ): bool {
		return in_array( $frequency, self::VALID_FREQUENCIES, true );
	}

	/**
	 * Get current cron frequency.
	 *
	 * @return string Current frequency setting.
	 */
	public function get_current_frequency(): string {
		return $this->settings->get_setting( 'cron_frequency', self::DEFAULT_FREQUENCY );
	}

	/**
	 * Update cron frequency.
	 *
	 * @param string $frequency New frequency to set.
	 * @return array{success: bool, message: string, frequency?: string, error_code?: string} Result.
	 */
	public function update_frequency( string $frequency ): array {
		if ( ! self::is_valid_frequency( $frequency ) ) {
			return array(
				'success'    => false,
				'message'    => __( 'Invalid frequency specified.', 'msm-sitemap' ),
				'error_code' => 'invalid_frequency',
			);
		}

		// Update the frequency setting.
		$this->settings->update_setting( 'cron_frequency', $frequency );

		// Reschedule if currently enabled.
		if ( $this->is_enabled() ) {
			$interval = $this->update_scheduler->map_frequency_to_interval( $frequency );
			$success  = $this->update_scheduler->reschedule_event( $interval );

			if ( ! $success ) {
				return array(
					'success'    => false,
					'message'    => __( 'Failed to reschedule cron job. Please try again.', 'msm-sitemap' ),
					'error_code' => 'reschedule_failed',
				);
			}
		}

		return array(
			'success'   => true,
			'message'   => __( 'Automatic update frequency successfully changed.', 'msm-sitemap' ),
			'frequency' => $frequency,
		);
	}

	/**
	 * Get comprehensive cron status.
	 *
	 * @return array{enabled: bool, next_scheduled: int|false, blog_public: bool, generating: bool, halted: bool, current_frequency: string, valid_frequencies: array<string>} Status.
	 */
	public function get_cron_status(): array {
		$is_enabled     = $this->is_enabled();
		$next_scheduled = $this->update_scheduler->get_next_scheduled_time();

		// Ensure consistency - if disabled but there's a scheduled event, clear it.
		if ( ! $is_enabled && $next_scheduled ) {
			$this->update_scheduler->unschedule_event();
			$next_scheduled = false;
		}

		return array(
			'enabled'           => $is_enabled,
			'next_scheduled'    => $next_scheduled,
			'blog_public'       => Site::is_public(),
			'generating'        => $this->generation_state->is_generation_in_progress(),
			'halted'            => $this->generation_state->is_stop_requested(),
			'current_frequency' => $this->get_current_frequency(),
			'valid_frequencies' => self::get_valid_frequencies(),
		);
	}

	/**
	 * Get extended status information.
	 *
	 * @return array{enabled: bool, next_incremental_update: int|false, generation_in_progress: bool, last_run: int|false} Status.
	 */
	public function get_status(): array {
		return array(
			'enabled'                 => $this->is_enabled(),
			'next_incremental_update' => $this->update_scheduler->get_next_scheduled_time() ?: false,
			'generation_in_progress'  => $this->generation_state->is_generation_in_progress(),
			'last_run'                => $this->generation_state->get_last_run_time() ?: false,
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
		return $this->update_scheduler->is_wp_cron_working();
	}
}

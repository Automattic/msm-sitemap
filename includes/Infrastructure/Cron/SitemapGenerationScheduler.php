<?php
/**
 * Sitemap Generation Scheduler
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\Cron
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Infrastructure\Cron;

use Automattic\MSM_Sitemap\Application\UseCases\GenerateSitemapUseCase;
use Automattic\MSM_Sitemap\Application\Commands\GenerateSitemapCommand;
use Automattic\MSM_Sitemap\Application\DTOs\SitemapOperationResult;

/**
 * Scheduler for sitemap generation tasks.
 *
 * Provides both direct (blocking) and background (async via cron) generation methods.
 * Used by different callers (missing detection, stale detection, full regeneration)
 * to schedule sitemap generation for specific dates.
 */
class SitemapGenerationScheduler {

	/**
	 * Interval between scheduled sitemap generation events (in seconds).
	 *
	 * @var int
	 */
	private const INTERVAL_BETWEEN_EVENTS = 5;

	/**
	 * The cron hook name for individual date generation.
	 *
	 * @var string
	 */
	public const CRON_HOOK = 'msm_cron_generate_sitemap_for_date';

	/**
	 * The generate sitemap use case.
	 *
	 * @var GenerateSitemapUseCase
	 */
	private GenerateSitemapUseCase $generate_use_case;

	/**
	 * The cron scheduling service.
	 *
	 * @var CronSchedulingService
	 */
	private CronSchedulingService $cron_scheduler;

	/**
	 * Constructor.
	 *
	 * @param GenerateSitemapUseCase $generate_use_case The generate sitemap use case.
	 * @param CronSchedulingService  $cron_scheduler    The cron scheduling service.
	 */
	public function __construct(
		GenerateSitemapUseCase $generate_use_case,
		CronSchedulingService $cron_scheduler
	) {
		$this->generate_use_case = $generate_use_case;
		$this->cron_scheduler    = $cron_scheduler;
	}

	/**
	 * Schedule background generation for a list of dates.
	 *
	 * Schedules individual cron events for each date, staggered to avoid
	 * overwhelming the server. Each event will generate one sitemap.
	 *
	 * @param array<string> $dates Array of dates in YYYY-MM-DD format.
	 * @return array{scheduled_count: int, dates: array<string>}
	 */
	public function schedule( array $dates ): array {
		if ( empty( $dates ) ) {
			return array(
				'scheduled_count' => 0,
				'dates'           => array(),
			);
		}

		// Mark that background generation is in progress
		update_option( 'msm_background_generation_in_progress', true, false );
		update_option( 'msm_background_generation_total', count( $dates ), false );
		update_option( 'msm_background_generation_remaining', count( $dates ), false );

		// Schedule individual events for each date, staggered
		$scheduled_count = 0;
		$base_time       = time();

		foreach ( $dates as $index => $date ) {
			$scheduled_time = $base_time + ( $index * self::INTERVAL_BETWEEN_EVENTS );

			// Schedule the event if not already scheduled
			if ( ! wp_next_scheduled( self::CRON_HOOK, array( $date ) ) ) {
				wp_schedule_single_event( $scheduled_time, self::CRON_HOOK, array( $date ) );
				++$scheduled_count;
			}
		}

		return array(
			'scheduled_count' => $scheduled_count,
			'dates'           => $dates,
		);
	}

	/**
	 * Generate sitemaps directly (blocking) for a list of dates.
	 *
	 * Use this when cron is not available or for small numbers of dates
	 * where immediate completion is preferred.
	 *
	 * @param array<string> $dates Array of dates in YYYY-MM-DD format.
	 * @return array{success: bool, generated_count: int, message: string}
	 */
	public function generate_now( array $dates ): array {
		if ( empty( $dates ) ) {
			return array(
				'success'         => true,
				'generated_count' => 0,
				'message'         => __( 'No dates to generate.', 'msm-sitemap' ),
			);
		}

		$generated_count = 0;

		foreach ( $dates as $date ) {
			// Check if generation should be stopped
			if ( (bool) get_option( 'msm_sitemap_stop_generation', false ) ) {
				break;
			}

			$result = $this->generate_for_date( $date );
			if ( $result->is_success() ) {
				++$generated_count;
			}
		}

		// Update last update timestamp if sitemaps were generated
		if ( $generated_count > 0 ) {
			update_option( 'msm_sitemap_last_update', time() );
		}

		// Always update the last run timestamp
		update_option( 'msm_sitemap_update_last_run', time() );

		$message = sprintf(
			/* translators: %d is the number of sitemaps generated */
			_n( 'Generated %d sitemap successfully.', 'Generated %d sitemaps successfully.', $generated_count, 'msm-sitemap' ),
			$generated_count
		);

		return array(
			'success'         => true,
			'generated_count' => $generated_count,
			'message'         => $message,
		);
	}

	/**
	 * Generate sitemap for a specific date.
	 *
	 * Used by both direct generation and cron event handlers.
	 * Always forces generation since the caller already decided this date needs it.
	 *
	 * @param string $date Date in YYYY-MM-DD format.
	 * @return SitemapOperationResult The result of the generation.
	 */
	public function generate_for_date( string $date ): SitemapOperationResult {
		// Create command for specific date generation (always force)
		$command = new GenerateSitemapCommand( $date, array(), true, false );

		// Execute use case
		return $this->generate_use_case->execute( $command );
	}

	/**
	 * Handle completion of a single date generation (called by cron handler).
	 *
	 * Updates progress tracking and cleans up state when complete.
	 *
	 * @param bool $was_successful Whether the generation was successful.
	 */
	public function record_date_completion( bool $was_successful ): void {
		// Update timestamp if successful
		if ( $was_successful ) {
			update_option( 'msm_sitemap_last_update', time(), false );
		}

		// Decrement remaining count
		$remaining = (int) get_option( 'msm_background_generation_remaining', 0 );
		if ( $remaining > 0 ) {
			--$remaining;
			update_option( 'msm_background_generation_remaining', $remaining, false );

			// If this was the last one, clean up state
			if ( 0 === $remaining ) {
				$this->clear_progress_state();
				update_option( 'msm_sitemap_update_last_run', time(), false );
			}
		}
	}

	/**
	 * Check if background generation is currently in progress.
	 *
	 * @return bool True if background generation is in progress.
	 */
	public function is_in_progress(): bool {
		return (bool) get_option( 'msm_background_generation_in_progress', false );
	}

	/**
	 * Get background generation progress.
	 *
	 * @return array{in_progress: bool, total: int, remaining: int, completed: int}
	 */
	public function get_progress(): array {
		$in_progress = $this->is_in_progress();
		$total       = (int) get_option( 'msm_background_generation_total', 0 );
		$remaining   = (int) get_option( 'msm_background_generation_remaining', 0 );

		return array(
			'in_progress' => $in_progress,
			'total'       => $total,
			'remaining'   => $remaining,
			'completed'   => $total - $remaining,
		);
	}

	/**
	 * Cancel any in-progress background generation.
	 *
	 * Clears scheduled events and progress state.
	 */
	public function cancel(): void {
		// Unschedule all pending events
		wp_unschedule_hook( self::CRON_HOOK );

		// Clear progress state
		$this->clear_progress_state();

		// Set stop flag for any currently running generation
		update_option( 'msm_sitemap_stop_generation', true, false );
	}

	/**
	 * Check if cron is available for background generation.
	 *
	 * @return bool True if cron is enabled.
	 */
	public function is_cron_available(): bool {
		return $this->cron_scheduler->is_cron_enabled();
	}

	/**
	 * Clear background generation progress state options.
	 */
	private function clear_progress_state(): void {
		delete_option( 'msm_background_generation_in_progress' );
		delete_option( 'msm_background_generation_total' );
		delete_option( 'msm_background_generation_remaining' );
		// Also clear the full generation flag (set by FullGenerationService)
		delete_option( 'msm_generation_in_progress' );
	}
}

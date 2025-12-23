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
use Automattic\MSM_Sitemap\Application\Services\GenerationStateService;

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
	 */
	private const INTERVAL_BETWEEN_EVENTS = 5;

	/**
	 * The cron hook name for individual date generation.
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
	 * The generation state service.
	 *
	 * @var GenerationStateService
	 */
	private GenerationStateService $generation_state;

	/**
	 * Constructor.
	 *
	 * @param GenerateSitemapUseCase $generate_use_case The generate sitemap use case.
	 * @param CronSchedulingService  $cron_scheduler    The cron scheduling service.
	 * @param GenerationStateService $generation_state  The generation state service.
	 */
	public function __construct(
		GenerateSitemapUseCase $generate_use_case,
		CronSchedulingService $cron_scheduler,
		GenerationStateService $generation_state
	) {
		$this->generate_use_case = $generate_use_case;
		$this->cron_scheduler    = $cron_scheduler;
		$this->generation_state  = $generation_state;
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
		$this->generation_state->start_background_generation( count( $dates ) );

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
			if ( $this->generation_state->is_stop_requested() ) {
				break;
			}

			$result = $this->generate_for_date( $date );
			if ( $result->is_success() ) {
				++$generated_count;
			}
		}

		// Update timestamps
		if ( $generated_count > 0 ) {
			$this->generation_state->update_last_update_time();
		}
		$this->generation_state->update_last_run_time();

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
		$command = new GenerateSitemapCommand( $date, array(), true, false );
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
		if ( $was_successful ) {
			$this->generation_state->update_last_update_time();
		}

		$remaining = $this->generation_state->record_background_date_completed();

		// If this was the last one, clean up state
		if ( 0 === $remaining ) {
			$this->generation_state->clear_background_generation_state();
			$this->generation_state->update_last_run_time();
		}
	}

	/**
	 * Check if background generation is currently in progress.
	 *
	 * @return bool True if background generation is in progress.
	 */
	public function is_in_progress(): bool {
		return $this->generation_state->is_background_generation_in_progress();
	}

	/**
	 * Get background generation progress.
	 *
	 * @return array{in_progress: bool, total: int, remaining: int, completed: int}
	 */
	public function get_progress(): array {
		return $this->generation_state->get_background_progress();
	}

	/**
	 * Cancel any in-progress background generation.
	 *
	 * Clears scheduled events and progress state.
	 */
	public function cancel(): void {
		wp_unschedule_hook( self::CRON_HOOK );
		$this->generation_state->clear_background_generation_state();
		$this->generation_state->request_stop();
	}

	/**
	 * Check if cron is available for background generation.
	 *
	 * @return bool True if cron is enabled.
	 */
	public function is_cron_available(): bool {
		return $this->cron_scheduler->is_cron_enabled();
	}
}

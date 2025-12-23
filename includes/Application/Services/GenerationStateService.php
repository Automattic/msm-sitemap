<?php
/**
 * Generation State Service
 *
 * @package Automattic\MSM_Sitemap\Application\Services
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Application\Services;

/**
 * Service for managing sitemap generation state.
 *
 * Encapsulates all option handling for generation progress tracking,
 * using non-autoloaded options to avoid object cache thrashing on
 * sites with persistent cache.
 */
class GenerationStateService {

	/**
	 * Option names for generation state.
	 */
	private const OPTION_GENERATION_IN_PROGRESS            = 'msm_generation_in_progress';
	private const OPTION_BACKGROUND_GENERATION_IN_PROGRESS = 'msm_background_generation_in_progress';
	private const OPTION_BACKGROUND_GENERATION_TOTAL       = 'msm_background_generation_total';
	private const OPTION_BACKGROUND_GENERATION_REMAINING   = 'msm_background_generation_remaining';
	private const OPTION_STOP_GENERATION                   = 'msm_sitemap_stop_generation';
	private const OPTION_LAST_CHECK                        = 'msm_sitemap_last_check';
	private const OPTION_LAST_UPDATE                       = 'msm_sitemap_last_update';
	private const OPTION_LAST_RUN                          = 'msm_sitemap_update_last_run';

	/**
	 * Mark that generation is in progress.
	 */
	public function mark_generation_in_progress(): void {
		update_option( self::OPTION_GENERATION_IN_PROGRESS, true, false );
	}

	/**
	 * Mark that generation is complete.
	 */
	public function mark_generation_complete(): void {
		delete_option( self::OPTION_GENERATION_IN_PROGRESS );
	}

	/**
	 * Check if generation is in progress.
	 *
	 * @return bool True if generation is in progress.
	 */
	public function is_generation_in_progress(): bool {
		return (bool) get_option( self::OPTION_GENERATION_IN_PROGRESS, false );
	}

	/**
	 * Start background generation with a given number of dates.
	 *
	 * @param int $total_dates The total number of dates to generate.
	 */
	public function start_background_generation( int $total_dates ): void {
		update_option( self::OPTION_BACKGROUND_GENERATION_IN_PROGRESS, true, false );
		update_option( self::OPTION_BACKGROUND_GENERATION_TOTAL, $total_dates, false );
		update_option( self::OPTION_BACKGROUND_GENERATION_REMAINING, $total_dates, false );
	}

	/**
	 * Record that a date has been completed in background generation.
	 *
	 * @return int The number of remaining dates.
	 */
	public function record_background_date_completed(): int {
		$remaining = (int) get_option( self::OPTION_BACKGROUND_GENERATION_REMAINING, 0 );
		if ( $remaining > 0 ) {
			--$remaining;
			update_option( self::OPTION_BACKGROUND_GENERATION_REMAINING, $remaining, false );
		}
		return $remaining;
	}

	/**
	 * Clear all background generation state.
	 */
	public function clear_background_generation_state(): void {
		delete_option( self::OPTION_BACKGROUND_GENERATION_IN_PROGRESS );
		delete_option( self::OPTION_BACKGROUND_GENERATION_TOTAL );
		delete_option( self::OPTION_BACKGROUND_GENERATION_REMAINING );
		delete_option( self::OPTION_GENERATION_IN_PROGRESS );
	}

	/**
	 * Check if background generation is in progress.
	 *
	 * @return bool True if background generation is in progress.
	 */
	public function is_background_generation_in_progress(): bool {
		return (bool) get_option( self::OPTION_BACKGROUND_GENERATION_IN_PROGRESS, false );
	}

	/**
	 * Get the background generation progress.
	 *
	 * @return array{in_progress: bool, total: int, remaining: int, completed: int}
	 */
	public function get_background_progress(): array {
		$in_progress = $this->is_background_generation_in_progress();
		$total       = (int) get_option( self::OPTION_BACKGROUND_GENERATION_TOTAL, 0 );
		$remaining   = (int) get_option( self::OPTION_BACKGROUND_GENERATION_REMAINING, 0 );

		return array(
			'in_progress' => $in_progress,
			'total'       => $total,
			'remaining'   => $remaining,
			'completed'   => $total - $remaining,
		);
	}

	/**
	 * Request that generation should stop.
	 */
	public function request_stop(): void {
		update_option( self::OPTION_STOP_GENERATION, true, false );
	}

	/**
	 * Clear the stop request.
	 */
	public function clear_stop_request(): void {
		delete_option( self::OPTION_STOP_GENERATION );
	}

	/**
	 * Check if a stop has been requested.
	 *
	 * @return bool True if stop has been requested.
	 */
	public function is_stop_requested(): bool {
		return (bool) get_option( self::OPTION_STOP_GENERATION, false );
	}

	/**
	 * Update the last check timestamp.
	 */
	public function update_last_check_time(): void {
		update_option( self::OPTION_LAST_CHECK, time(), false );
	}

	/**
	 * Update the last update timestamp.
	 */
	public function update_last_update_time(): void {
		update_option( self::OPTION_LAST_UPDATE, time(), false );
	}

	/**
	 * Update the last run timestamp.
	 */
	public function update_last_run_time(): void {
		update_option( self::OPTION_LAST_RUN, time(), false );
	}

	/**
	 * Get the last check timestamp.
	 *
	 * @return int|null The timestamp or null if never checked.
	 */
	public function get_last_check_time(): ?int {
		$time = get_option( self::OPTION_LAST_CHECK, null );
		return null !== $time ? (int) $time : null;
	}

	/**
	 * Get the last update timestamp.
	 *
	 * @return int|null The timestamp or null if never updated.
	 */
	public function get_last_update_time(): ?int {
		$time = get_option( self::OPTION_LAST_UPDATE, null );
		return null !== $time ? (int) $time : null;
	}

	/**
	 * Get the last run timestamp.
	 *
	 * @return int|null The timestamp or null if never run.
	 */
	public function get_last_run_time(): ?int {
		$time = get_option( self::OPTION_LAST_RUN, null );
		return null !== $time ? (int) $time : null;
	}

	/**
	 * Clear all generation state (for reset operations).
	 */
	public function clear_all_state(): void {
		$this->clear_background_generation_state();
		$this->clear_stop_request();
		delete_option( self::OPTION_LAST_CHECK );
		delete_option( self::OPTION_LAST_UPDATE );
		delete_option( self::OPTION_LAST_RUN );
	}
}

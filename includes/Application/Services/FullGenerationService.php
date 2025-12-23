<?php
/**
 * Full Generation Service
 *
 * @package Automattic\MSM_Sitemap\Application\Services
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Application\Services;

use Automattic\MSM_Sitemap\Infrastructure\Cron\SitemapGenerationScheduler;

/**
 * Service for full sitemap regeneration.
 *
 * Orchestrates AllDatesWithPostsService (date provider) and
 * SitemapGenerationScheduler to regenerate all sitemaps.
 */
class FullGenerationService {

	/**
	 * The sitemap generation scheduler.
	 *
	 * @var SitemapGenerationScheduler
	 */
	private SitemapGenerationScheduler $scheduler;

	/**
	 * The all dates service.
	 *
	 * @var AllDatesWithPostsService
	 */
	private AllDatesWithPostsService $all_dates_service;

	/**
	 * Constructor.
	 *
	 * @param SitemapGenerationScheduler $scheduler         The scheduler.
	 * @param AllDatesWithPostsService   $all_dates_service The all dates service.
	 */
	public function __construct(
		SitemapGenerationScheduler $scheduler,
		AllDatesWithPostsService $all_dates_service
	) {
		$this->scheduler         = $scheduler;
		$this->all_dates_service = $all_dates_service;
	}

	/**
	 * Start full sitemap generation.
	 *
	 * Gets all dates with posts and schedules them for background generation.
	 *
	 * @return array{success: bool, method: string, message: string, scheduled_count: int}
	 */
	public function start_full_generation(): array {
		// Check if cron is available
		if ( ! $this->scheduler->is_cron_available() ) {
			return array(
				'success'         => false,
				'method'          => 'background',
				'message'         => __( 'Full generation requires cron to be enabled.', 'msm-sitemap' ),
				'scheduled_count' => 0,
			);
		}

		// Check if generation is already in progress
		if ( $this->scheduler->is_in_progress() ) {
			return array(
				'success'         => false,
				'method'          => 'background',
				'message'         => __( 'Generation is already in progress.', 'msm-sitemap' ),
				'scheduled_count' => 0,
			);
		}

		// Get all dates with posts
		$all_dates = $this->all_dates_service->get_dates();

		if ( empty( $all_dates ) ) {
			return array(
				'success'         => true,
				'method'          => 'none',
				'message'         => __( 'No dates with posts found.', 'msm-sitemap' ),
				'scheduled_count' => 0,
			);
		}

		// Set generation in progress flag (for UI)
		update_option( 'msm_generation_in_progress', true );

		// Schedule all dates via the shared scheduler
		$result = $this->scheduler->schedule( $all_dates );

		return array(
			'success'         => true,
			'method'          => 'background',
			'message'         => sprintf(
				/* translators: %d is the number of sitemaps scheduled */
				_n(
					'Started full generation: %d sitemap scheduled.',
					'Started full generation: %d sitemaps scheduled.',
					$result['scheduled_count'],
					'msm-sitemap'
				),
				$result['scheduled_count']
			),
			'scheduled_count' => $result['scheduled_count'],
		);
	}

	/**
	 * Check if full generation can be started.
	 *
	 * @return bool True if generation can start.
	 */
	public function can_start(): bool {
		return $this->scheduler->is_cron_available() && ! $this->scheduler->is_in_progress();
	}

	/**
	 * Get full generation progress.
	 *
	 * @return array{in_progress: bool, total: int, remaining: int, completed: int}
	 */
	public function get_progress(): array {
		return $this->scheduler->get_progress();
	}

	/**
	 * Cancel any in-progress full generation.
	 */
	public function cancel(): void {
		$this->scheduler->cancel();
		delete_option( 'msm_generation_in_progress' );
	}
}

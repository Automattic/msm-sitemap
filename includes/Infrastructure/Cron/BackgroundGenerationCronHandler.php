<?php
/**
 * Background Generation Cron Handler
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\Cron
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Infrastructure\Cron;

use Automattic\MSM_Sitemap\Domain\Contracts\WordPressIntegrationInterface;
use Automattic\MSM_Sitemap\Application\Services\SitemapCleanupService;

/**
 * Handler for scheduled background sitemap generation events.
 *
 * This handler processes individual `msm_cron_generate_sitemap_for_date` events
 * that are scheduled by the BackgroundGenerationScheduler when running background
 * generation (either Full or Incremental).
 *
 * Used by:
 * - FullGenerationService (regenerate all sitemaps)
 * - IncrementalGenerationService::schedule() (background missing/stale generation)
 */
class BackgroundGenerationCronHandler implements WordPressIntegrationInterface {

	/**
	 * The sitemap generation scheduler.
	 *
	 * @var BackgroundGenerationScheduler
	 */
	private BackgroundGenerationScheduler $scheduler;

	/**
	 * The sitemap cleanup service.
	 *
	 * @var SitemapCleanupService
	 */
	private SitemapCleanupService $cleanup_service;

	/**
	 * Constructor.
	 *
	 * @param BackgroundGenerationScheduler $scheduler       The scheduler.
	 * @param SitemapCleanupService      $cleanup_service The cleanup service.
	 */
	public function __construct(
		BackgroundGenerationScheduler $scheduler,
		SitemapCleanupService $cleanup_service
	) {
		$this->scheduler       = $scheduler;
		$this->cleanup_service = $cleanup_service;
	}

	/**
	 * Register WordPress hooks for cron event handling.
	 */
	public function register_hooks(): void {
		add_action( BackgroundGenerationScheduler::CRON_HOOK, array( $this, 'handle_generate_for_date' ), 10, 1 );
	}

	/**
	 * Handle the cron event for generating a sitemap for a specific date.
	 *
	 * This is a thin handler - it delegates to the scheduler for actual generation
	 * and progress tracking.
	 *
	 * @param string $date The date to generate sitemap for (YYYY-MM-DD format).
	 */
	public function handle_generate_for_date( string $date ): void {
		// Check if generation should be stopped
		if ( (bool) get_option( 'msm_sitemap_stop_generation', false ) ) {
			$this->scheduler->cancel();
			delete_option( 'msm_sitemap_stop_generation' );
			return;
		}

		// Generate the sitemap
		$result = $this->scheduler->generate_for_date( $date );

		// Record completion and update progress
		$this->scheduler->record_date_completion( $result->is_success() );

		// If this was the last one, run cleanup
		$progress = $this->scheduler->get_progress();
		if ( ! $progress->isInProgress() ) {
			$this->cleanup_service->cleanup_all_orphaned_sitemaps();
		}
	}
}

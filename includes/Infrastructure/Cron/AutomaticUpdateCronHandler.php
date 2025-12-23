<?php
/**
 * Automatic Update Cron Handler
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\Cron
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Infrastructure\Cron;

use Automattic\MSM_Sitemap\Application\Services\IncrementalGenerationService;
use Automattic\MSM_Sitemap\Application\Services\SitemapCleanupService;
use Automattic\MSM_Sitemap\Domain\Contracts\CronHandlerInterface;

/**
 * Handler for the recurring automatic sitemap update cron job.
 *
 * This handler processes the `msm_cron_update_sitemap` event that runs on a
 * recurring schedule (hourly by default) to automatically regenerate missing
 * and stale sitemaps.
 *
 * Unlike BackgroundGenerationCronHandler (which handles on-demand scheduled events),
 * this handler runs on a fixed recurring schedule when automatic updates are enabled.
 */
class AutomaticUpdateCronHandler implements CronHandlerInterface {

	/**
	 * The incremental generation service.
	 *
	 * @var IncrementalGenerationService
	 */
	private IncrementalGenerationService $generation_service;

	/**
	 * The cron scheduling service.
	 *
	 * @var CronSchedulingService
	 */
	private CronSchedulingService $cron_scheduler;

	/**
	 * The sitemap cleanup service.
	 *
	 * @var SitemapCleanupService
	 */
	private SitemapCleanupService $cleanup_service;

	/**
	 * Constructor.
	 *
	 * @param IncrementalGenerationService $generation_service The incremental generation service.
	 * @param CronSchedulingService        $cron_scheduler     The cron scheduling service.
	 * @param SitemapCleanupService        $cleanup_service    The sitemap cleanup service.
	 */
	public function __construct(
		IncrementalGenerationService $generation_service,
		CronSchedulingService $cron_scheduler,
		SitemapCleanupService $cleanup_service
	) {
		$this->generation_service = $generation_service;
		$this->cron_scheduler     = $cron_scheduler;
		$this->cleanup_service    = $cleanup_service;
	}

	/**
	 * Register WordPress hooks for the recurring cron job.
	 */
	public function register_hooks(): void {
		add_action( 'msm_cron_update_sitemap', array( $this, 'execute' ) );
	}

	/**
	 * Execute the automatic update process.
	 *
	 * Called by the recurring cron job to generate missing and stale sitemaps.
	 */
	public function execute(): void {
		if ( ! $this->can_execute() ) {
			return;
		}

		// Track when we last checked for updates
		update_option( 'msm_sitemap_last_check', time(), false );

		// Generate missing and stale sitemaps
		$this->generation_service->generate();

		// Clean up orphaned sitemaps for dates that no longer have posts
		$this->cleanup_service->cleanup_all_orphaned_sitemaps();
	}

	/**
	 * Check if the cron handler can execute.
	 *
	 * @return bool True if cron is enabled.
	 */
	public function can_execute(): bool {
		return $this->cron_scheduler->is_cron_enabled();
	}
}

<?php
/**
 * Automatic Update Cron Handler
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\Cron
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Infrastructure\Cron;

use Automattic\MSM_Sitemap\Application\Services\CronManagementService;
use Automattic\MSM_Sitemap\Application\Services\GenerationStateService;
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
	 * The cron management service.
	 *
	 * @var CronManagementService
	 */
	private CronManagementService $cron_management;

	/**
	 * The sitemap cleanup service.
	 *
	 * @var SitemapCleanupService
	 */
	private SitemapCleanupService $cleanup_service;

	/**
	 * The generation state service.
	 *
	 * @var GenerationStateService
	 */
	private GenerationStateService $generation_state;

	/**
	 * Constructor.
	 *
	 * @param IncrementalGenerationService $generation_service The incremental generation service.
	 * @param CronManagementService        $cron_management    The cron management service.
	 * @param SitemapCleanupService        $cleanup_service    The sitemap cleanup service.
	 * @param GenerationStateService       $generation_state   The generation state service.
	 */
	public function __construct(
		IncrementalGenerationService $generation_service,
		CronManagementService $cron_management,
		SitemapCleanupService $cleanup_service,
		GenerationStateService $generation_state
	) {
		$this->generation_service = $generation_service;
		$this->cron_management    = $cron_management;
		$this->cleanup_service    = $cleanup_service;
		$this->generation_state   = $generation_state;
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

		// Track when we last checked for updates.
		$this->generation_state->update_last_check_time();

		// Generate missing and stale sitemaps.
		$this->generation_service->generate();

		// Clean up orphaned sitemaps for dates that no longer have posts.
		$this->cleanup_service->cleanup_all_orphaned_sitemaps();
	}

	/**
	 * Check if the cron handler can execute.
	 *
	 * @return bool True if cron is enabled.
	 */
	public function can_execute(): bool {
		return $this->cron_management->is_enabled();
	}
}

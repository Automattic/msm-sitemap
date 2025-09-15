<?php
/**
 * Missing Sitemap Generation Cron Handler
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\Cron
 */

declare(strict_types=1);

namespace Automattic\MSM_Sitemap\Infrastructure\Cron;

use Automattic\MSM_Sitemap\Infrastructure\Cron\CronSchedulingService;
use Automattic\MSM_Sitemap\Application\Services\MissingSitemapDetectionService;
use Automattic\MSM_Sitemap\Application\Services\SitemapCleanupService;
use Automattic\MSM_Sitemap\Domain\Contracts\CronHandlerInterface;
use Automattic\MSM_Sitemap\Application\UseCases\GenerateSitemapUseCase;
use Automattic\MSM_Sitemap\Application\Commands\GenerateSitemapCommand;

/**
 * Handler class for managing missing sitemap generation via cron jobs.
 * 
 * This handler is responsible for generating sitemaps for dates that are missing
 * or need updates due to recent post modifications.
 */
class MissingSitemapGenerationHandler implements CronHandlerInterface {

	/**
	 * The cron scheduling service.
	 *
	 * @var CronSchedulingService
	 */
	private CronSchedulingService $cron_scheduler;

	/**
	 * The missing sitemap detection service.
	 *
	 * @var MissingSitemapDetectionService
	 */
	private MissingSitemapDetectionService $missing_service;

	/**
	 * The generate sitemap use case.
	 *
	 * @var GenerateSitemapUseCase
	 */
	private GenerateSitemapUseCase $generate_use_case;

	/**
	 * The sitemap cleanup service.
	 *
	 * @var SitemapCleanupService
	 */
	private SitemapCleanupService $cleanup_service;

	/**
	 * Constructor.
	 *
	 * @param CronSchedulingService $cron_scheduler The cron scheduling service.
	 * @param MissingSitemapDetectionService $missing_service The missing sitemap detection service.
	 * @param GenerateSitemapUseCase $generate_use_case The generate sitemap use case.
	 * @param SitemapCleanupService $cleanup_service The sitemap cleanup service.
	 */
	public function __construct( 
		CronSchedulingService $cron_scheduler,
		MissingSitemapDetectionService $missing_service,
		GenerateSitemapUseCase $generate_use_case,
		SitemapCleanupService $cleanup_service
	) {
		$this->cron_scheduler    = $cron_scheduler;
		$this->missing_service   = $missing_service;
		$this->generate_use_case = $generate_use_case;
		$this->cleanup_service   = $cleanup_service;
	}

	/**
	 * Register WordPress hooks and filters for missing sitemap generation cron.
	 */
	public function register_hooks(): void {
		// Register the cron handler for missing sitemap generation
		add_action( 'msm_cron_update_sitemap', array( $this, 'execute' ) );
	}

	/**
	 * Execute the missing sitemap generation process.
	 * 
	 * This is the main entry point that generates sitemaps for any missing dates,
	 * including dates with recently modified posts.
	 */
	public function execute(): void {
		if ( ! $this->can_execute() ) {
			return;
		}

		$this->handle_missing_sitemap_generation();
	}

	/**
	 * Check if the missing sitemap generation process can run.
	 * 
	 * @return bool True if cron is enabled, false otherwise.
	 */
	public function can_execute(): bool {
		return $this->cron_scheduler->is_cron_enabled();
	}

	/**
	 * Handle missing sitemap generation
	 * 
	 * This is called by the cron job to generate sitemaps for any missing dates,
	 * including dates with recently modified posts.
	 */
	public function handle_missing_sitemap_generation(): void {
		// Check if cron is enabled before processing
		if ( ! $this->cron_scheduler->is_cron_enabled() ) {
			return;
		}

		// Update last check time at the beginning
		update_option( 'msm_sitemap_last_check', time(), false );

		// Get missing sitemap dates and dates needing updates
		$missing_data          = $this->missing_service->get_missing_sitemaps();
		$all_dates_to_generate = $missing_data['all_dates_to_generate'] ?? array();

		if ( empty( $all_dates_to_generate ) ) {
			return;
		}

		// Track that we're about to generate sitemaps
		$sitemaps_generated = false;

		// Generate sitemaps for all dates that need generation
		foreach ( $all_dates_to_generate as $date ) {
			// Check if generation should be stopped
			if ( (bool) get_option( 'msm_sitemap_stop_generation', false ) ) {
				break;
			}

			// Check if this date needs force generation (has sitemap but needs update)
			$dates_needing_updates = $missing_data['dates_needing_updates'] ?? array();
			$force_generation      = in_array( $date, $dates_needing_updates, true );

			// Create command for specific date generation
			$command = new GenerateSitemapCommand( $date, array(), $force_generation, false );
			
			// Execute use case
			$this->generate_use_case->execute( $command );
			$sitemaps_generated = true;
		}

		// Update last update time if sitemaps were actually generated
		if ( $sitemaps_generated ) {
			update_option( 'msm_sitemap_last_update', time(), false );
		}
		
		// Always update the last run timestamp since we checked for missing/outdated sitemaps
		update_option( 'msm_sitemap_update_last_run', time(), false );

		// Clean up orphaned sitemaps for dates that no longer have posts
		$this->cleanup_service->cleanup_all_orphaned_sitemaps();
	}

	/**
	 * Check if there are missing sitemaps to generate
	 * 
	 * @return bool True if there are missing sitemaps, false otherwise.
	 */
	public function has_missing_sitemaps(): bool {
		$missing_data = $this->missing_service->get_missing_sitemaps();
		
		return ! empty( $missing_data['missing_dates'] );
	}
}

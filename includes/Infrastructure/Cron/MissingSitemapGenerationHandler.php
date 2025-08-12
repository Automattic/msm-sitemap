<?php
/**
 * Missing Sitemap Generation Handler
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\Cron
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Infrastructure\Cron;

use Automattic\MSM_Sitemap\Application\Services\MissingSitemapDetectionService;
use Automattic\MSM_Sitemap\Application\Services\SitemapService;
use Automattic\MSM_Sitemap\Domain\Contracts\CronHandlerInterface;

/**
 * Handles automatic generation of missing sitemaps via cron.
 * 
 * This replaces the old IncrementalGenerationHandler and provides
 * more comprehensive coverage by generating sitemaps for any missing
 * dates, including recently modified posts.
 */
class MissingSitemapGenerationHandler implements CronHandlerInterface {

	/**
	 * Setup the cron handler.
	 */
	public static function setup(): void {
		// Register the cron handler for missing sitemap generation
		add_action( 'msm_cron_update_sitemap', array( __CLASS__, 'execute' ) );
	}

	/**
	 * Execute the missing sitemap generation process.
	 * 
	 * This is the main entry point that generates sitemaps for any missing dates,
	 * including dates with recently modified posts.
	 */
	public static function execute(): void {
		if ( ! self::can_execute() ) {
			return;
		}

		self::handle_missing_sitemap_generation();
	}

	/**
	 * Check if the missing sitemap generation process can run.
	 * 
	 * @return bool True if cron is enabled, false otherwise.
	 */
	public static function can_execute(): bool {
		return CronSchedulingService::is_cron_enabled();
	}

	/**
	 * Handle missing sitemap generation
	 * 
	 * This is called by the cron job to generate sitemaps for any missing dates,
	 * including dates with recently modified posts.
	 */
	public static function handle_missing_sitemap_generation(): void {
		// Check if cron is enabled before processing
		if ( ! CronSchedulingService::is_cron_enabled() ) {
			return;
		}

		// Update last check time at the beginning
		update_option( 'msm_sitemap_last_check', time(), false );

		$container = \Automattic\MSM_Sitemap\Infrastructure\DI\msm_sitemap_container();
		$missing_service = $container->get( MissingSitemapDetectionService::class );
		$sitemap_service = $container->get( SitemapService::class );

		// Get missing sitemap dates and dates needing updates
		$missing_data = $missing_service->get_missing_sitemaps();
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
			$force_generation = in_array( $date, $dates_needing_updates, true );

			$sitemap_service->create_for_date( $date, $force_generation );
			$sitemaps_generated = true;
		}

		// Update last update time if sitemaps were actually generated
		if ( $sitemaps_generated ) {
			update_option( 'msm_sitemap_last_update', time(), false );
		}
		
		// Always update the last run timestamp since we checked for missing/outdated sitemaps
		update_option( 'msm_sitemap_update_last_run', time(), false );

		// Clean up orphaned sitemaps for dates that no longer have posts
		$cleanup_service = $container->get( \Automattic\MSM_Sitemap\Application\Services\SitemapCleanupService::class );
		$cleanup_service->cleanup_all_orphaned_sitemaps();
	}

	/**
	 * Check if there are missing sitemaps to generate
	 * 
	 * @return bool True if there are missing sitemaps, false otherwise.
	 */
	public static function has_missing_sitemaps(): bool {
		$container = \Automattic\MSM_Sitemap\Infrastructure\DI\msm_sitemap_container();
		$missing_service = $container->get( MissingSitemapDetectionService::class );
		$missing_data = $missing_service->get_missing_sitemaps();
		
		return ! empty( $missing_data['missing_dates'] );
	}
}

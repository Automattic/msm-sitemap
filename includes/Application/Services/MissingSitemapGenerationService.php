<?php
/**
 * Missing Sitemap Generation Service
 *
 * @package Automattic\MSM_Sitemap\Application\Services
 */

declare(strict_types=1);

namespace Automattic\MSM_Sitemap\Application\Services;

use Automattic\MSM_Sitemap\Infrastructure\Cron\CronSchedulingService;
use Automattic\MSM_Sitemap\Infrastructure\Cron\MissingSitemapGenerationHandler;

/**
 * Service for generating missing sitemaps
 */
class MissingSitemapGenerationService {

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
	private MissingSitemapDetectionService $missing_detection_service;

	/**
	 * The missing sitemap generation handler.
	 *
	 * @var MissingSitemapGenerationHandler
	 */
	private MissingSitemapGenerationHandler $missing_generation_handler;

	/**
	 * The sitemap service.
	 *
	 * @var SitemapService
	 */
	private SitemapService $sitemap_service;

	/**
	 * Constructor.
	 *
	 * @param CronSchedulingService $cron_scheduler The cron scheduling service.
	 * @param MissingSitemapDetectionService $missing_detection_service The missing sitemap detection service.
	 * @param MissingSitemapGenerationHandler $missing_generation_handler The missing sitemap generation handler.
	 * @param SitemapService $sitemap_service The sitemap service.
	 */
	public function __construct( 
		CronSchedulingService $cron_scheduler, 
		MissingSitemapDetectionService $missing_detection_service,
		MissingSitemapGenerationHandler $missing_generation_handler,
		SitemapService $sitemap_service
	) {
		$this->cron_scheduler             = $cron_scheduler;
		$this->missing_detection_service  = $missing_detection_service;
		$this->missing_generation_handler = $missing_generation_handler;
		$this->sitemap_service            = $sitemap_service;
	}

	/**
	 * Generate missing sitemaps using the appropriate method based on cron status
	 *
	 * @return array Result with success status, method used, and message
	 */
	public function generate_missing_sitemaps(): array {
		// Check for missing content first
		$missing_data  = $this->missing_detection_service->get_missing_sitemaps();
		$message_parts = $this->missing_detection_service->get_success_message_parts();

		if ( empty( $message_parts ) ) {
			return array(
				'success' => true,
				'method'  => 'none',
				'message' => __( 'No missing sitemaps detected.', 'msm-sitemap' ),
				'counts'  => array(),
			);
		}

		// Update last check timestamp (when generation was initiated)
		update_option( 'msm_sitemap_last_check', current_time( 'timestamp' ) );

		// Decide whether to use cron or direct generation
		if ( $this->cron_scheduler->is_cron_enabled() ) {
			// Use cron handler for better performance
			$this->missing_generation_handler->handle_missing_sitemap_generation();
			
			$message = implode( ' and ', $message_parts );
			return array(
				'success' => true,
				'method'  => 'cron',
				'message' => sprintf( 
					/* translators: %s is the description of what will be generated */
					__( 'Scheduled generation of %s.', 'msm-sitemap' ), 
					$message
				),
				'counts'  => $message_parts,
			);
		} else {
			// Direct generation when cron is disabled
			$result = $this->generate_missing_sitemaps_directly( $missing_data );
			
			return array(
				'success'         => $result['success'],
				'method'          => 'direct',
				'message'         => $result['message'],
				'counts'          => $result['counts'],
				'generated_count' => $result['generated_count'],
			);
		}
	}

	/**
	 * Generate missing sitemaps directly (when cron is disabled)
	 *
	 * @param array $missing_data Data from MissingSitemapDetectionService
	 * @return array Result with success status, message, and counts
	 */
	private function generate_missing_sitemaps_directly( array $missing_data ): array {
		
		$generated_count       = 0;
		$all_dates_to_generate = $missing_data['all_dates_to_generate'] ?? array();
		$dates_needing_updates = $missing_data['dates_needing_updates'] ?? array();
		
		foreach ( $all_dates_to_generate as $date ) {
			// Check if this date needs force generation (has sitemap but needs update)
			$force_generation = in_array( $date, $dates_needing_updates, true );
			
			$result = $this->sitemap_service->create_for_date( $date, $force_generation );
			if ( $result && $result->is_success() ) {
				++$generated_count;
			}
		}
		
		// Update last update timestamp if sitemaps were actually generated
		if ( $generated_count > 0 ) {
			update_option( 'msm_sitemap_last_update', current_time( 'timestamp' ) );
		}
		
		// Always update the last run timestamp since we checked for missing/outdated sitemaps
		update_option( 'msm_sitemap_update_last_run', current_time( 'timestamp' ) );
		
		$message = sprintf( 
			/* translators: %d is the number of sitemaps generated */
			_n( 'Generated %d sitemap successfully.', 'Generated %d sitemaps successfully.', $generated_count, 'msm-sitemap' ), 
			$generated_count
		);
		
		return array(
			'success'         => true,
			'message'         => $message,
			'counts'          => $this->missing_detection_service->get_success_message_parts(),
			'generated_count' => $generated_count,
		);
	}
}

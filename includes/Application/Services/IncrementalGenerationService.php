<?php
/**
 * Incremental Generation Service
 *
 * @package Automattic\MSM_Sitemap\Application\Services
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Application\Services;

use Automattic\MSM_Sitemap\Infrastructure\Cron\SitemapGenerationScheduler;

/**
 * Service for incremental sitemap generation (missing + stale).
 *
 * Orchestrates the detection service and scheduler to provide
 * both direct and background generation capabilities.
 *
 * "Incremental" means only generating what needs updating, as opposed
 * to "Full" which regenerates everything.
 */
class IncrementalGenerationService {

	/**
	 * The sitemap generation scheduler.
	 *
	 * @var SitemapGenerationScheduler
	 */
	private SitemapGenerationScheduler $scheduler;

	/**
	 * The detection service (finds missing + stale dates).
	 *
	 * @var MissingSitemapDetectionService
	 */
	private MissingSitemapDetectionService $detection_service;

	/**
	 * Constructor.
	 *
	 * @param SitemapGenerationScheduler     $scheduler         The scheduler.
	 * @param MissingSitemapDetectionService $detection_service The detection service.
	 */
	public function __construct(
		SitemapGenerationScheduler $scheduler,
		MissingSitemapDetectionService $detection_service
	) {
		$this->scheduler         = $scheduler;
		$this->detection_service = $detection_service;
	}

	/**
	 * Generate incrementally (blocking).
	 *
	 * Generates all missing and stale sitemaps synchronously.
	 * Use for small numbers of sitemaps where immediate completion is preferred.
	 *
	 * @return array{success: bool, method: string, message: string, counts: array<string>, generated_count: int}
	 */
	public function generate(): array {
		// Get dates that need generation (missing + stale)
		$detection_data = $this->detection_service->get_missing_sitemaps();
		$message_parts  = $this->detection_service->get_success_message_parts();

		if ( empty( $message_parts ) ) {
			return array(
				'success'         => true,
				'method'          => 'none',
				'message'         => __( 'All sitemaps are up to date.', 'msm-sitemap' ),
				'counts'          => array(),
				'generated_count' => 0,
			);
		}

		$dates_to_generate = $detection_data['all_dates_to_generate'] ?? array();

		// Direct generation via scheduler
		$result = $this->scheduler->generate_now( $dates_to_generate );

		return array(
			'success'         => $result['success'],
			'method'          => 'direct',
			'message'         => $result['message'],
			'counts'          => $message_parts,
			'generated_count' => $result['generated_count'],
		);
	}

	/**
	 * Schedule background generation.
	 *
	 * Schedules individual cron events for each missing/stale sitemap,
	 * staggered to avoid overwhelming the server.
	 *
	 * @param array<string> $dates Optional. Specific dates to generate. If empty, auto-detects.
	 * @return array{success: bool, method: string, message: string, counts: array<string>, scheduled_count: int}
	 */
	public function schedule( array $dates = array() ): array {
		// Check if cron is enabled
		if ( ! $this->scheduler->is_cron_available() ) {
			return array(
				'success'         => false,
				'method'          => 'background',
				'message'         => __( 'Background generation requires cron to be enabled.', 'msm-sitemap' ),
				'counts'          => array(),
				'scheduled_count' => 0,
			);
		}

		// If no dates provided, detect what needs generation
		if ( empty( $dates ) ) {
			$detection_data = $this->detection_service->get_missing_sitemaps();
			$dates          = $detection_data['all_dates_to_generate'] ?? array();
			$message_parts  = $this->detection_service->get_success_message_parts();
		} else {
			// Custom dates provided - create message
			$message_parts = array(
				sprintf(
					/* translators: %d is the number of sitemaps */
					_n( '%d sitemap', '%d sitemaps', count( $dates ), 'msm-sitemap' ),
					count( $dates )
				),
			);
		}

		if ( empty( $dates ) ) {
			return array(
				'success'         => true,
				'method'          => 'none',
				'message'         => __( 'All sitemaps are up to date.', 'msm-sitemap' ),
				'counts'          => array(),
				'scheduled_count' => 0,
			);
		}

		// Schedule via scheduler
		$result  = $this->scheduler->schedule( $dates );
		$message = implode( ' and ', $message_parts );

		return array(
			'success'         => true,
			'method'          => 'background',
			'message'         => sprintf(
				/* translators: %s is the description of what will be generated */
				__( 'Scheduled background generation of %s.', 'msm-sitemap' ),
				$message
			),
			'counts'          => $message_parts,
			'scheduled_count' => $result['scheduled_count'],
		);
	}

	/**
	 * Check if background generation is in progress.
	 *
	 * @return bool True if background generation is in progress.
	 */
	public function is_in_progress(): bool {
		return $this->scheduler->is_in_progress();
	}

	/**
	 * Get background generation progress.
	 *
	 * @return array{in_progress: bool, total: int, remaining: int, completed: int}
	 */
	public function get_progress(): array {
		return $this->scheduler->get_progress();
	}
}

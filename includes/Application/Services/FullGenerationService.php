<?php
/**
 * Full Generation Service
 *
 * @package Automattic\MSM_Sitemap\Application\Services
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Application\Services;

use Automattic\MSM_Sitemap\Infrastructure\Cron\BackgroundGenerationScheduler;
use Automattic\MSM_Sitemap\Domain\ValueObjects\GenerationProgress;

/**
 * Service for full sitemap regeneration.
 *
 * Orchestrates AllDatesWithPostsService (date provider) and
 * BackgroundGenerationScheduler to regenerate all sitemaps.
 */
class FullGenerationService {

	/**
	 * The sitemap generation scheduler.
	 *
	 * @var BackgroundGenerationScheduler
	 */
	private BackgroundGenerationScheduler $scheduler;

	/**
	 * The all dates service.
	 *
	 * @var AllDatesWithPostsService
	 */
	private AllDatesWithPostsService $all_dates_service;

	/**
	 * The generation state service.
	 *
	 * @var GenerationStateService
	 */
	private GenerationStateService $generation_state;

	/**
	 * The settings service.
	 *
	 * @var SettingsService
	 */
	private SettingsService $settings_service;

	/**
	 * The sitemap cleanup service.
	 *
	 * @var SitemapCleanupService
	 */
	private SitemapCleanupService $cleanup_service;

	/**
	 * Constructor.
	 *
	 * @param BackgroundGenerationScheduler $scheduler         The scheduler.
	 * @param AllDatesWithPostsService      $all_dates_service The all dates service.
	 * @param GenerationStateService        $generation_state  The generation state service.
	 * @param SettingsService               $settings_service  The settings service.
	 * @param SitemapCleanupService         $cleanup_service   The sitemap cleanup service.
	 */
	public function __construct(
		BackgroundGenerationScheduler $scheduler,
		AllDatesWithPostsService $all_dates_service,
		GenerationStateService $generation_state,
		SettingsService $settings_service,
		SitemapCleanupService $cleanup_service
	) {
		$this->scheduler         = $scheduler;
		$this->all_dates_service = $all_dates_service;
		$this->generation_state  = $generation_state;
		$this->settings_service  = $settings_service;
		$this->cleanup_service   = $cleanup_service;
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
			// No dates with posts found - clean up any orphaned sitemaps
			$deleted_count = $this->cleanup_service->cleanup_all_orphaned_sitemaps();

			// Save the settings hash since we've now synced with current settings
			$this->settings_service->save_content_settings_hash();

			// Check if any content types are enabled to provide appropriate message
			$enabled_post_types = $this->settings_service->get_setting( 'enabled_post_types', array() );

			if ( empty( $enabled_post_types ) ) {
				// No content types enabled
				if ( $deleted_count > 0 ) {
					return array(
						'success'         => true,
						'method'          => 'cleanup',
						'message'         => sprintf(
							/* translators: %d is the number of sitemaps deleted */
							_n(
								'%d orphaned sitemap deleted. Enable content types to generate new sitemaps.',
								'%d orphaned sitemaps deleted. Enable content types to generate new sitemaps.',
								$deleted_count,
								'msm-sitemap'
							),
							$deleted_count
						),
						'scheduled_count' => 0,
					);
				}

				return array(
					'success'         => true,
					'method'          => 'none',
					'message'         => __( 'No content types enabled. Enable at least one content type to generate sitemaps.', 'msm-sitemap' ),
					'scheduled_count' => 0,
				);
			}

			// Content types are enabled but no posts found
			if ( $deleted_count > 0 ) {
				return array(
					'success'         => true,
					'method'          => 'cleanup',
					'message'         => sprintf(
						/* translators: %d is the number of sitemaps deleted */
						_n(
							'%d orphaned sitemap deleted. No published content found for the selected content types.',
							'%d orphaned sitemaps deleted. No published content found for the selected content types.',
							$deleted_count,
							'msm-sitemap'
						),
						$deleted_count
					),
					'scheduled_count' => 0,
				);
			}

			return array(
				'success'         => true,
				'method'          => 'none',
				'message'         => __( 'No published content found for the selected content types.', 'msm-sitemap' ),
				'scheduled_count' => 0,
			);
		}

		// Set generation in progress flag (for UI)
		$this->generation_state->mark_generation_in_progress();

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
	 * @return GenerationProgress The current generation progress.
	 */
	public function get_progress(): GenerationProgress {
		return $this->scheduler->get_progress();
	}

	/**
	 * Cancel any in-progress full generation.
	 */
	public function cancel(): void {
		$this->scheduler->cancel();
		$this->generation_state->mark_generation_complete();
	}
}

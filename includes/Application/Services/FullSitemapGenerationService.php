<?php
/**
 * Full Sitemap Generation Service
 *
 * @package Automattic\MSM_Sitemap\Application\Services
 */

declare(strict_types=1);

namespace Automattic\MSM_Sitemap\Application\Services;

use Automattic\MSM_Sitemap\Infrastructure\Cron\CronSchedulingService;

/**
 * Service for managing full sitemap generation with centralized business logic
 */
class FullSitemapGenerationService {

	/**
	 * Start or resume full sitemap generation
	 *
	 * @return array Result with success status and message.
	 */
	public static function start_full_generation(): array {
		// Check if cron is enabled before processing
		if ( ! CronSchedulingService::is_cron_enabled() ) {
			return array(
				'success' => false,
				'message' => __( 'Cannot generate sitemap: automatic updates must be enabled.', 'msm-sitemap' ),
				'error_code' => 'cron_disabled',
			);
		}

		$sitemap_create_in_progress = (bool) get_option( 'msm_generation_in_progress' );
		
		// Update last check timestamp (when manual generation was initiated)
		update_option( 'msm_sitemap_last_check', current_time( 'timestamp' ) );
		
		// Delegate to the full generation service
		CronSchedulingService::handle_full_generation();

		$message = empty( $sitemap_create_in_progress )
			? __( 'Starting sitemap generation...', 'msm-sitemap' )
			: __( 'Resuming sitemap creation', 'msm-sitemap' );

		return array(
			'success' => true,
			'message' => $message,
			'was_in_progress' => $sitemap_create_in_progress,
		);
	}

	/**
	 * Halt ongoing sitemap generation
	 *
	 * @return array Result with success status and message.
	 */
	public static function halt_generation(): array {
		// Can only halt generation if sitemap creation is already in process
		if ( (bool) get_option( 'msm_sitemap_stop_generation' ) === true ) {
			return array(
				'success' => false,
				'message' => __( 'Cannot stop sitemap generation: sitemap generation is already being halted.', 'msm-sitemap' ),
				'error_code' => 'already_halting',
			);
		} elseif ( (bool) get_option( 'msm_generation_in_progress' ) === true ) {
			update_option( 'msm_sitemap_stop_generation', true );
			return array(
				'success' => true,
				'message' => __( 'Stopping sitemap generation...', 'msm-sitemap' ),
			);
		} else {
			return array(
				'success' => false,
				'message' => __( 'Cannot stop sitemap generation: sitemap generation not in progress.', 'msm-sitemap' ),
				'error_code' => 'not_in_progress',
			);
		}
	}

	/**
	 * Get generation status
	 *
	 * @return array Generation status information
	 */
	public static function get_generation_status(): array {
		return array(
			'in_progress' => (bool) get_option( 'msm_generation_in_progress' ),
			'halt_requested' => (bool) get_option( 'msm_sitemap_stop_generation' ),
			'last_check' => get_option( 'msm_sitemap_last_check' ),
			'last_update' => get_option( 'msm_sitemap_last_update' ),
		);
	}
}

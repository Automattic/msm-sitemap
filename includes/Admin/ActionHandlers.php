<?php
/**
 * Admin action handlers
 *
 * @package MSM_Sitemap
 */

namespace Automattic\MSM_Sitemap\Admin;

use Automattic\MSM_Sitemap\Admin\Notifications;
use Automattic\MSM_Sitemap\Infrastructure\Cron\CronSchedulingService;
use Automattic\MSM_Sitemap\Domain\Contracts\SitemapRepositoryInterface;

/**
 * Handles all admin form action submissions
 */
class Action_Handlers {

	/**
	 * Handle enable cron action
	 */
	public static function handle_enable_cron() {
		$result = CronSchedulingService::enable_cron();
		if ( $result ) {
			Notifications::show_success( __( 'Automatic sitemap updates enabled successfully.', 'msm-sitemap' ) );
		} else {
			Notifications::show_warning( __( 'Automatic updates are already enabled.', 'msm-sitemap' ) );
		}
	}

	/**
	 * Handle disable cron action
	 */
	public static function handle_disable_cron() {
		$result = CronSchedulingService::disable_cron();
		if ( $result ) {
			Notifications::show_success( __( 'Automatic sitemap updates disabled successfully.', 'msm-sitemap' ) );
		} else {
			Notifications::show_warning( __( 'Automatic updates are already disabled.', 'msm-sitemap' ) );
		}
	}

	/**
	 * Handle generate full sitemap action
	 */
	public static function handle_generate_full() {
		// Check if cron is enabled before processing
		if ( ! CronSchedulingService::is_cron_enabled() ) {
			Notifications::show_error( __( 'Cannot generate sitemap: automatic updates must be enabled.', 'msm-sitemap' ) );
			return;
		}

		$sitemap_create_in_progress = (bool) get_option( 'msm_generation_in_progress' );
		
		// Update last check timestamp (when manual generation was initiated)
		update_option( 'msm_sitemap_last_check', current_time( 'timestamp' ) );
		
		// Delegate to the full generation service (bypasses can_execute check for direct calls)
		CronSchedulingService::handle_full_generation();

		if ( empty( $sitemap_create_in_progress ) ) {
			Notifications::show_success( __( 'Starting sitemap generation...', 'msm-sitemap' ) );
		} else {
			Notifications::show_info( __( 'Resuming sitemap creation', 'msm-sitemap' ) );
		}
	}

	/**
	 * Handle generate missing sitemaps action
	 */
	public static function handle_generate_missing_sitemaps() {
		// Check for missing content using the new service
		$missing_service = new \Automattic\MSM_Sitemap\Application\Services\MissingSitemapDetectionService();
		$missing_data = $missing_service::get_missing_sitemaps();

		// Get message parts to determine if there's actually something to generate
		$message_parts = \Automattic\MSM_Sitemap\Application\Services\MissingSitemapDetectionService::get_success_message_parts();

		if ( ! empty( $message_parts ) ) {
			// If cron is enabled, use the cron handler for better performance
			if ( CronSchedulingService::is_cron_enabled() ) {
				// Delegate to the cron handler
				\Automattic\MSM_Sitemap\Infrastructure\Cron\MissingSitemapGenerationHandler::handle_missing_sitemap_generation();
				
				$message = implode( ' and ', $message_parts );
				Notifications::show_success( 
					sprintf( 
						/* translators: %s is the description of what will be generated */
						__( 'Scheduled generation of %s.', 'msm-sitemap' ), 
						$message
					) 
				);
			} else {
				// Direct generation when cron is disabled
				$container = \Automattic\MSM_Sitemap\Infrastructure\DI\msm_sitemap_container();
				$service = new \Automattic\MSM_Sitemap\Application\Services\SitemapService(
					msm_sitemap_plugin()->get_sitemap_generator(),
					$container->get( SitemapRepositoryInterface::class )
				);
				
				$generated_count = 0;
				$all_dates_to_generate = $missing_data['all_dates_to_generate'] ?? array();
				$dates_needing_updates = $missing_data['dates_needing_updates'] ?? array();
				
				foreach ( $all_dates_to_generate as $date ) {
					// Check if this date needs force generation (has sitemap but needs update)
					$force_generation = in_array( $date, $dates_needing_updates, true );
					
					$result = $service->create_for_date( $date, $force_generation );
					if ( $result && $result->is_success() ) {
						$generated_count++;
					}
				}
				
				// Update last check timestamp (when manual generation was initiated)
				update_option( 'msm_sitemap_last_check', current_time( 'timestamp' ) );
				
				// Update last update timestamp if sitemaps were actually generated
				if ( $generated_count > 0 ) {
					update_option( 'msm_sitemap_last_update', current_time( 'timestamp' ) );
				}
				
				// Always update the last run timestamp since we checked for missing/outdated sitemaps
				update_option( 'msm_sitemap_update_last_run', current_time( 'timestamp' ) );
				
				Notifications::show_success( 
					sprintf( 
						/* translators: %d is the number of sitemaps generated */
						_n( 'Generated %d sitemap successfully.', 'Generated %d sitemaps successfully.', $generated_count, 'msm-sitemap' ), 
						$generated_count
					) 
				);
			}
		} else {
			Notifications::show_info( __( 'No missing sitemaps detected.', 'msm-sitemap' ) );
		}
	}

	/**
	 * Handle halt generation action
	 */
	public static function handle_halt_generation() {
		// Can only halt generation if sitemap creation is already in process
		if ( (bool) get_option( 'msm_sitemap_stop_generation' ) === true ) {
			Notifications::show_warning( __( 'Cannot stop sitemap generation: sitemap generation is already being halted.', 'msm-sitemap' ) );
		} elseif ( (bool) get_option( 'msm_generation_in_progress' ) === true ) {
			update_option( 'msm_sitemap_stop_generation', true );
			Notifications::show_success( __( 'Stopping sitemap generation...', 'msm-sitemap' ) );
		} else {
			Notifications::show_warning( __( 'Cannot stop sitemap generation: sitemap generation not in progress.', 'msm-sitemap' ) );
		}
	}

	/**
	 * Handle update frequency action
	 */
	public static function handle_update_frequency() {
		check_admin_referer( 'msm-sitemap-action' );
		
		if ( ! isset( $_POST['cron_frequency'] ) ) {
			Notifications::show_error( __( 'No frequency selected.', 'msm-sitemap' ) );
			return;
		}

		$frequency = sanitize_text_field( $_POST['cron_frequency'] );
		$valid_frequencies = array( '5min', '10min', '15min', '30min', 'hourly', '2hourly', '3hourly' );
		
		if ( ! in_array( $frequency, $valid_frequencies, true ) ) {
			Notifications::show_error( __( 'Invalid frequency selected.', 'msm-sitemap' ) );
			return;
		}

		// Update the frequency option
		update_option( 'msm_sitemap_cron_frequency', $frequency );
		
		// Reschedule the cron job with the new frequency
		$success = \Automattic\MSM_Sitemap\Infrastructure\Cron\CronSchedulingService::reschedule_cron( $frequency );
		
		if ( ! $success ) {
			Notifications::show_error( __( 'Failed to reschedule cron job. Please try again.', 'msm-sitemap' ) );
			return;
		}
		
		Notifications::show_success( __( 'Cron frequency updated successfully.', 'msm-sitemap' ) );
	}

	/**
	 * Handle reset sitemap data action
	 */
	public static function handle_reset_data() {
		// Create service to handle the reset
		$generator = msm_sitemap_plugin()->get_sitemap_generator();
		$repository = new \Automattic\MSM_Sitemap\Infrastructure\Repositories\SitemapPostRepository();
		$service = new \Automattic\MSM_Sitemap\Application\Services\SitemapService( $generator, $repository );
		
		$service->reset_all_data();
		
		Notifications::show_success(
			__( 'Sitemap data reset. All sitemap posts, metadata, and processing options have been cleared.', 'msm-sitemap' )
		);
	}
} 

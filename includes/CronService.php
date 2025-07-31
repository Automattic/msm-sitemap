<?php
/**
 * Sitemap Cron Service
 *
 * @package Automattic\MSM_Sitemap
 */

declare(strict_types=1);

namespace Automattic\MSM_Sitemap;

/**
 * Service class for managing sitemap cron functionality.
 * 
 * This is the single source of truth for all cron management logic.
 * Used by CLI, admin UI, and cron job handlers.
 */
class Cron_Service {

	/**
	 * Option name for tracking if cron is enabled
	 */
	const CRON_ENABLED_OPTION = 'msm_sitemap_cron_enabled';

	/**
	 * Enable the sitemap cron functionality
	 *
	 * @return bool True if cron was enabled, false if already enabled
	 */
	public static function enable_cron() {
		// Check if cron is already enabled
		if ( self::is_cron_enabled() ) {
			return false;
		}

		// Enable the cron
		update_option( self::CRON_ENABLED_OPTION, true );

		// Schedule the cron event if it's not already scheduled
		if ( ! wp_next_scheduled( 'msm_cron_update_sitemap' ) ) {
			wp_schedule_event( time(), 'ms-sitemap-15-min-cron-interval', 'msm_cron_update_sitemap' );
		}

		return true;
	}

	/**
	 * Disable the sitemap cron functionality
	 *
	 * @return bool True if cron was disabled, false if already disabled
	 */
	public static function disable_cron() {
		// Check if cron is already disabled
		if ( ! self::is_cron_enabled() ) {
			return false;
		}

		// Disable the cron
		update_option( self::CRON_ENABLED_OPTION, false );

		// Clear all scheduled cron events related to sitemap generation
		// Use wp_unschedule_hook() which is more thorough than wp_clear_scheduled_hook()
		wp_unschedule_hook( 'msm_cron_update_sitemap' );
		wp_unschedule_hook( 'msm_cron_generate_sitemap_for_year' );
		wp_unschedule_hook( 'msm_cron_generate_sitemap_for_year_month' );
		wp_unschedule_hook( 'msm_cron_generate_sitemap_for_year_month_day' );

		// Also clear any ongoing sitemap generation
		delete_option( 'msm_sitemap_create_in_progress' );
		delete_option( 'msm_stop_processing' );
		delete_option( 'msm_years_to_process' );
		delete_option( 'msm_months_to_process' );
		delete_option( 'msm_days_to_process' );

		return true;
	}

	/**
	 * Check if the sitemap cron is currently enabled
	 *
	 * @return bool True if cron is enabled, false otherwise
	 */
	public static function is_cron_enabled() {
		$option = get_option( self::CRON_ENABLED_OPTION );
		
		// If the option doesn't exist, it means cron was never explicitly set
		// In this case, we should return false (disabled) to prevent auto-enabling
		if ( $option === false ) {
			// If the option doesn't exist but there are scheduled cron events,
			// we should clear them to maintain consistency
			if ( wp_next_scheduled( 'msm_cron_update_sitemap' ) ) {
				wp_unschedule_hook( 'msm_cron_update_sitemap' );
			}
			return false;
		}
		
		return (bool) $option;
	}

	/**
	 * Get the current cron status
	 *
	 * @return array Status information about the cron
	 */
	public static function get_cron_status() {
		$is_enabled = self::is_cron_enabled();
		$next_scheduled = wp_next_scheduled( 'msm_cron_update_sitemap' );
		$is_blog_public = \Metro_Sitemap::is_blog_public();
		$is_generating = (bool) get_option( 'msm_sitemap_create_in_progress' );
		$is_halted = (bool) get_option( 'msm_stop_processing' );

		// Ensure consistency - if enabled is false but there's a scheduled event, clear it
		if ( ! $is_enabled && $next_scheduled ) {
			wp_unschedule_hook( 'msm_cron_update_sitemap' );
			$next_scheduled = false;
		}

		return array(
			'enabled' => $is_enabled,
			'next_scheduled' => $next_scheduled,
			'blog_public' => $is_blog_public,
			'generating' => $is_generating,
			'halted' => $is_halted,
			'can_enable' => $is_blog_public && ! $is_enabled,
			'can_disable' => $is_enabled,
		);
	}

	/**
	 * Reset the sitemap cron to a clean state (for testing)
	 *
	 * @return bool True if reset was successful
	 */
	public static function reset_cron() {
		// Clear all cron events
		wp_unschedule_hook( 'msm_cron_update_sitemap' );
		wp_unschedule_hook( 'msm_cron_generate_sitemap_for_year' );
		wp_unschedule_hook( 'msm_cron_generate_sitemap_for_year_month' );
		wp_unschedule_hook( 'msm_cron_generate_sitemap_for_year_month_day' );

		// Delete all related options
		delete_option( self::CRON_ENABLED_OPTION );
		delete_option( 'msm_sitemap_create_in_progress' );
		delete_option( 'msm_stop_processing' );
		delete_option( 'msm_years_to_process' );
		delete_option( 'msm_months_to_process' );
		delete_option( 'msm_days_to_process' );

		return true;
	}

	/**
	 * Check if cron should be enabled for the current site
	 * 
	 * This is used to determine if auto-enable should happen
	 * (currently disabled to prevent resource issues on large sites)
	 *
	 * @return bool True if cron should be enabled
	 */
	public static function should_auto_enable_cron() {
		// Cron is NOT auto-enabled to prevent resource issues on large sites
		// Users must explicitly enable via CLI or admin interface
		return false;
	}
} 

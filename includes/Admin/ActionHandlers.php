<?php
/**
 * Admin action handlers
 *
 * @package MSM_Sitemap
 */

namespace Automattic\MSM_Sitemap\Admin;

use Automattic\MSM_Sitemap\Admin\Notifications;
use Automattic\MSM_Sitemap\Infrastructure\Cron\CronSchedulingService;

/**
 * Handles all admin form action submissions
 */
class Action_Handlers {

	/**
	 * Handle enable cron action
	 */
	public static function handle_enable_cron() {
		$result = \Automattic\MSM_Sitemap\Application\Services\CronManagementService::enable_cron();
		if ( $result['success'] ) {
			Notifications::show_success( $result['message'] );
		} else {
			Notifications::show_warning( $result['message'] );
		}
	}

	/**
	 * Handle disable cron action
	 */
	public static function handle_disable_cron() {
		$result = \Automattic\MSM_Sitemap\Application\Services\CronManagementService::disable_cron();
		if ( $result['success'] ) {
			Notifications::show_success( $result['message'] );
		} else {
			Notifications::show_warning( $result['message'] );
		}
	}

	/**
	 * Handle generate full sitemap action
	 */
	public static function handle_generate_full() {
		$result = \Automattic\MSM_Sitemap\Application\Services\FullSitemapGenerationService::start_full_generation();
		
		if ( $result['success'] ) {
			Notifications::show_success( $result['message'] );
		} else {
			Notifications::show_error( $result['message'] );
		}
	}

	/**
	 * Handle generate missing sitemaps action
	 */
	public static function handle_generate_missing_sitemaps() {
		$result = \Automattic\MSM_Sitemap\Application\Services\MissingSitemapGenerationService::generate_missing_sitemaps();
		
		if ( $result['success'] ) {
			Notifications::show_success( $result['message'] );
		} else {
			Notifications::show_info( $result['message'] );
		}
	}

	/**
	 * Handle halt generation action
	 */
	public static function handle_halt_generation() {
		$result = \Automattic\MSM_Sitemap\Application\Services\FullSitemapGenerationService::halt_generation();
		
		if ( $result['success'] ) {
			Notifications::show_success( $result['message'] );
		} else {
			Notifications::show_warning( $result['message'] );
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
		$result = \Automattic\MSM_Sitemap\Application\Services\CronManagementService::update_frequency( $frequency );
		
		if ( $result['success'] ) {
			Notifications::show_success( $result['message'] );
		} else {
			Notifications::show_error( $result['message'] );
		}
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

	/**
	 * Handle save content provider settings action
	 */
	public static function handle_save_content_provider_settings() {
		check_admin_referer( 'msm-sitemap-action' );
		
		// Save image provider settings
		$images_enabled = isset( $_POST['images_provider_enabled'] ) ? '1' : '0';
		update_option( 'msm_sitemap_images_provider_enabled', $images_enabled );
		
		// Save featured images setting - if not submitted, it's unchecked (false)
		$include_featured_images = isset( $_POST['include_featured_images'] ) && $_POST['include_featured_images'] === '1' ? '1' : '0';
		update_option( 'msm_sitemap_include_featured_images', $include_featured_images );
		
		// Save content images setting - if not submitted, it's unchecked (false)
		$include_content_images = isset( $_POST['include_content_images'] ) && $_POST['include_content_images'] === '1' ? '1' : '0';
		update_option( 'msm_sitemap_include_content_images', $include_content_images );
		
		// Save max images per sitemap
		$max_images = isset( $_POST['max_images_per_sitemap'] ) ? intval( $_POST['max_images_per_sitemap'] ) : 1000;
		$max_images = max( 1, min( 10000, $max_images ) ); // Clamp between 1 and 10000
		update_option( 'msm_sitemap_max_images_per_sitemap', $max_images );
		
		Notifications::show_success(
			__( 'Content provider settings saved successfully.', 'msm-sitemap' )
		);
	}
} 

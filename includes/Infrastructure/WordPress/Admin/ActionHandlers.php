<?php
/**
 * Admin action handlers
 *
 * @package MSM_Sitemap
 */

namespace Automattic\MSM_Sitemap\Infrastructure\WordPress\Admin;

use Automattic\MSM_Sitemap\Application\Services\CronManagementService;
use Automattic\MSM_Sitemap\Application\Services\SettingsService;
use Automattic\MSM_Sitemap\Application\Services\SitemapService;
use Automattic\MSM_Sitemap\Application\Services\FullSitemapGenerationService;
use Automattic\MSM_Sitemap\Application\Services\MissingSitemapGenerationService;
use Automattic\MSM_Sitemap\Infrastructure\WordPress\Admin\Notifications;

/**
 * Handles all admin form action submissions
 */
class ActionHandlers {

	/**
	 * The cron management service.
	 *
	 * @var CronManagementService
	 */
	private CronManagementService $cron_management;

	/**
	 * The settings service.
	 *
	 * @var SettingsService
	 */
	private SettingsService $settings;

	/**
	 * The sitemap service.
	 *
	 * @var SitemapService
	 */
	private SitemapService $sitemap_service;

	/**
	 * The full sitemap generation service.
	 *
	 * @var FullSitemapGenerationService
	 */
	private FullSitemapGenerationService $full_sitemap_generation_service;

	/**
	 * The missing sitemap generation service.
	 *
	 * @var MissingSitemapGenerationService
	 */
	private MissingSitemapGenerationService $missing_sitemap_generation_service;

	/**
	 * Constructor.
	 *
	 * @param CronManagementService $cron_management The cron management service.
	 * @param SettingsService $settings The settings service.
	 * @param SitemapService $sitemap_service The sitemap service.
	 * @param FullSitemapGenerationService $full_sitemap_generation_service The full sitemap generation service.
	 * @param MissingSitemapGenerationService $missing_sitemap_generation_service The missing sitemap generation service.
	 */
	public function __construct( 
		CronManagementService $cron_management, 
		SettingsService $settings, 
		SitemapService $sitemap_service,
		FullSitemapGenerationService $full_sitemap_generation_service,
		MissingSitemapGenerationService $missing_sitemap_generation_service
	) {
		$this->cron_management                    = $cron_management;
		$this->settings                           = $settings;
		$this->sitemap_service                    = $sitemap_service;
		$this->full_sitemap_generation_service    = $full_sitemap_generation_service;
		$this->missing_sitemap_generation_service = $missing_sitemap_generation_service;
	}

	/**
	 * Handle enable cron action
	 */
	public function handle_enable_cron(): void {
		$result = $this->cron_management->enable_cron();
		if ( $result['success'] ) {
			Notifications::show_success( $result['message'] );
		} else {
			Notifications::show_warning( $result['message'] );
		}
	}

	/**
	 * Handle disable cron action
	 */
	public function handle_disable_cron(): void {
		$result = $this->cron_management->disable_cron();
		if ( $result['success'] ) {
			Notifications::show_success( $result['message'] );
		} else {
			Notifications::show_warning( $result['message'] );
		}
	}

	/**
	 * Handle generate full sitemap action
	 */
	public function handle_generate_full(): void {
		$result = $this->full_sitemap_generation_service->start_full_generation();
		
		if ( $result['success'] ) {
			Notifications::show_success( $result['message'] );
		} else {
			Notifications::show_error( $result['message'] );
		}
	}

	/**
	 * Handle generate missing sitemaps action
	 */
	public function handle_generate_missing_sitemaps(): void {
		$result = $this->missing_sitemap_generation_service->generate_missing_sitemaps();
		
		if ( $result['success'] ) {
			Notifications::show_success( $result['message'] );
		} else {
			Notifications::show_info( $result['message'] );
		}
	}

	/**
	 * Handle halt generation action
	 */
	public function handle_halt_generation(): void {
		$result = $this->full_sitemap_generation_service->halt_generation();
		
		if ( $result['success'] ) {
			Notifications::show_success( $result['message'] );
		} else {
			Notifications::show_warning( $result['message'] );
		}
	}

	/**
	 * Handle update frequency action
	 */
	public function handle_update_frequency(): void {
		check_admin_referer( 'msm-sitemap-action' );
		
		if ( ! isset( $_POST['cron_frequency'] ) ) {
			Notifications::show_error( __( 'No frequency selected.', 'msm-sitemap' ) );
			return;
		}

		$frequency = sanitize_text_field( $_POST['cron_frequency'] );
		$result    = $this->cron_management->update_frequency( $frequency );
		
		if ( $result['success'] ) {
			Notifications::show_success( $result['message'] );
		} else {
			Notifications::show_error( $result['message'] );
		}
	}

	/**
	 * Handle reset sitemap data action
	 */
	public function handle_reset_data(): void {
		$this->sitemap_service->reset_all_data();
		
		Notifications::show_success(
			__( 'Sitemap data reset. All sitemap posts, metadata, and processing options have been cleared.', 'msm-sitemap' )
		);
	}

	/**
	 * Handle save content provider settings action
	 */
	public function handle_save_content_provider_settings(): void {
		check_admin_referer( 'msm-sitemap-action' );
		
		// Prepare settings from form data
		$settings = array(
			'include_images'  => isset( $_POST['images_provider_enabled'] ),
			'featured_images' => isset( $_POST['include_featured_images'] ) && '1' === $_POST['include_featured_images'],
			'content_images'  => isset( $_POST['include_content_images'] ) && '1' === $_POST['include_content_images'],
		);
		
		// Handle max images per sitemap if provided
		if ( isset( $_POST['max_images_per_sitemap'] ) ) {
			$settings['max_images_per_sitemap'] = intval( $_POST['max_images_per_sitemap'] );
		}
		
		// Use service to update settings
		$result = $this->settings->update_settings( $settings );
		
		if ( $result['success'] ) {
			Notifications::show_success( $result['message'] );
		} else {
			Notifications::show_error( $result['message'] );
		}
	}
} 

<?php
/**
 * Admin action handlers
 *
 * @package MSM_Sitemap
 */

namespace Automattic\MSM_Sitemap\Infrastructure\WordPress\Admin;

use Automattic\MSM_Sitemap\Application\Services\CronManagementService;
use Automattic\MSM_Sitemap\Application\Services\FullGenerationService;
use Automattic\MSM_Sitemap\Application\Services\IncrementalGenerationService;
use Automattic\MSM_Sitemap\Application\Services\SettingsService;
use Automattic\MSM_Sitemap\Application\Services\SitemapService;
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
	 * The full generation service.
	 *
	 * @var FullGenerationService
	 */
	private FullGenerationService $full_generation_service;

	/**
	 * The incremental generation service.
	 *
	 * @var IncrementalGenerationService
	 */
	private IncrementalGenerationService $incremental_generation_service;

	/**
	 * Constructor.
	 *
	 * @param CronManagementService        $cron_management                 The cron management service.
	 * @param SettingsService              $settings                        The settings service.
	 * @param SitemapService               $sitemap_service                 The sitemap service.
	 * @param FullGenerationService        $full_generation_service         The full generation service.
	 * @param IncrementalGenerationService $incremental_generation_service  The incremental generation service.
	 */
	public function __construct(
		CronManagementService $cron_management,
		SettingsService $settings,
		SitemapService $sitemap_service,
		FullGenerationService $full_generation_service,
		IncrementalGenerationService $incremental_generation_service
	) {
		$this->cron_management                = $cron_management;
		$this->settings                       = $settings;
		$this->sitemap_service                = $sitemap_service;
		$this->full_generation_service        = $full_generation_service;
		$this->incremental_generation_service = $incremental_generation_service;
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
		$result = $this->full_generation_service->start_full_generation();

		if ( $result['success'] ) {
			Notifications::show_success( $result['message'] );
		} else {
			Notifications::show_error( $result['message'] );
		}
	}

	/**
	 * Handle generate missing sitemaps action (direct/blocking)
	 */
	public function handle_generate_missing_sitemaps(): void {
		$result = $this->incremental_generation_service->generate();

		if ( $result['success'] ) {
			Notifications::show_success( $result['message'] );
		} else {
			Notifications::show_info( $result['message'] );
		}
	}

	/**
	 * Handle schedule background generation action
	 */
	public function handle_schedule_background_generation(): void {
		$result = $this->incremental_generation_service->schedule();

		if ( $result['success'] ) {
			Notifications::show_success( $result['message'] );
		} else {
			Notifications::show_warning( $result['message'] );
		}
	}

	/**
	 * Handle halt generation action
	 */
	public function handle_halt_generation(): void {
		// Check if halt is already in progress
		if ( (bool) get_option( 'msm_sitemap_stop_generation' ) ) {
			Notifications::show_warning( __( 'Sitemap generation is already being halted.', 'msm-sitemap' ) );
			return;
		}

		// Check if generation is actually in progress
		$progress = $this->full_generation_service->get_progress();
		if ( ! $progress->isInProgress() && ! (bool) get_option( 'msm_generation_in_progress' ) ) {
			Notifications::show_warning( __( 'Sitemap generation is not in progress.', 'msm-sitemap' ) );
			return;
		}

		// Cancel the generation
		$this->full_generation_service->cancel();
		Notifications::show_success( __( 'Stopping sitemap generation...', 'msm-sitemap' ) );
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

		// Handle taxonomy settings
		$old_taxonomies_enabled = $this->settings->get_setting( 'include_taxonomies', '0' );
		$settings['include_taxonomies'] = isset( $_POST['taxonomies_provider_enabled'] );

		// Handle enabled taxonomies array
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below via array_map.
		if ( isset( $_POST['enabled_taxonomies'] ) && is_array( $_POST['enabled_taxonomies'] ) ) {
			$settings['enabled_taxonomies'] = array_map( 'sanitize_text_field', wp_unslash( $_POST['enabled_taxonomies'] ) );
		} else {
			// If taxonomies are enabled but none are selected, default to empty array
			$settings['enabled_taxonomies'] = array();
		}

		// Handle author settings
		$old_authors_enabled = $this->settings->get_setting( 'include_authors', '0' );
		$settings['include_authors'] = isset( $_POST['authors_provider_enabled'] );

		// Use service to update settings
		$result = $this->settings->update_settings( $settings );

		// Flush rewrite rules if taxonomy or author settings changed
		$new_taxonomies_enabled = $settings['include_taxonomies'] ? '1' : '0';
		$new_authors_enabled    = $settings['include_authors'] ? '1' : '0';
		if ( $old_taxonomies_enabled !== $new_taxonomies_enabled || $old_authors_enabled !== $new_authors_enabled ) {
			flush_rewrite_rules();
		}

		if ( $result['success'] ) {
			Notifications::show_success( $result['message'] );
		} else {
			Notifications::show_error( $result['message'] );
		}
	}
} 

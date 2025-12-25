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

		// Handle post type settings - enabled_post_types array
		$old_post_types = $this->settings->get_setting( 'enabled_post_types', array( 'post' ) );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below via array_map.
		if ( isset( $_POST['enabled_post_types'] ) && is_array( $_POST['enabled_post_types'] ) ) {
			$settings['enabled_post_types'] = array_map( 'sanitize_text_field', wp_unslash( $_POST['enabled_post_types'] ) );
		} else {
			$settings['enabled_post_types'] = array();
		}

		// Handle taxonomy settings - enabled_taxonomies array
		$old_taxonomies = $this->settings->get_setting( 'enabled_taxonomies', array( 'category', 'post_tag' ) );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below via array_map.
		if ( isset( $_POST['enabled_taxonomies'] ) && is_array( $_POST['enabled_taxonomies'] ) ) {
			$settings['enabled_taxonomies'] = array_map( 'sanitize_text_field', wp_unslash( $_POST['enabled_taxonomies'] ) );
		} else {
			$settings['enabled_taxonomies'] = array();
		}
		// Set include_taxonomies based on whether any taxonomies are enabled
		$settings['include_taxonomies'] = ! empty( $settings['enabled_taxonomies'] );

		// Handle author settings
		$old_authors_enabled = $this->settings->get_setting( 'include_authors', '0' );
		$settings['include_authors'] = isset( $_POST['authors_provider_enabled'] );

		// Handle page settings - enabled_page_types array
		$old_page_types = $this->settings->get_setting( 'enabled_page_types', array( 'page' ) );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below via array_map.
		if ( isset( $_POST['enabled_page_types'] ) && is_array( $_POST['enabled_page_types'] ) ) {
			$settings['enabled_page_types'] = array_map( 'sanitize_text_field', wp_unslash( $_POST['enabled_page_types'] ) );
		} else {
			$settings['enabled_page_types'] = array();
		}
		// Set include_pages based on whether any page types are enabled
		$settings['include_pages'] = ! empty( $settings['enabled_page_types'] );

		// Handle cache TTL settings
		if ( isset( $_POST['taxonomy_cache_ttl'] ) ) {
			$settings['taxonomy_cache_ttl'] = intval( $_POST['taxonomy_cache_ttl'] );
		}

		if ( isset( $_POST['author_cache_ttl'] ) ) {
			$settings['author_cache_ttl'] = intval( $_POST['author_cache_ttl'] );
		}

		if ( isset( $_POST['page_cache_ttl'] ) ) {
			$settings['page_cache_ttl'] = intval( $_POST['page_cache_ttl'] );
		}

		// Handle cron frequency if provided
		if ( isset( $_POST['cron_frequency'] ) ) {
			$frequency        = sanitize_text_field( wp_unslash( $_POST['cron_frequency'] ) );
			$frequency_result = $this->cron_management->update_frequency( $frequency );
			// Only show error for actual failures, not "unchanged" or similar
			if ( ! $frequency_result['success'] && isset( $frequency_result['error_code'] ) ) {
				$error_code = $frequency_result['error_code'];
				if ( 'invalid_frequency' === $error_code || 'reschedule_failed' === $error_code ) {
					Notifications::show_error( $frequency_result['message'] );
					return;
				}
			}
		}

		// Handle automatic updates (cron) enable/disable
		$cron_currently_enabled = $this->cron_management->is_enabled();
		$cron_should_be_enabled = isset( $_POST['automatic_updates_enabled'] );

		if ( $cron_should_be_enabled && ! $cron_currently_enabled ) {
			$this->cron_management->enable_cron();
		} elseif ( ! $cron_should_be_enabled && $cron_currently_enabled ) {
			$this->cron_management->disable_cron();
		}

		// Use service to update settings
		$result = $this->settings->update_settings( $settings );

		// Clear caches that depend on content provider settings
		// Clear post-related caches if post types changed
		if ( $old_post_types !== $settings['enabled_post_types'] ) {
			wp_cache_delete( 'oldest_post_date_year', 'msm_sitemap' );
		}

		// Clear taxonomy sitemap caches if taxonomy settings changed
		if ( $old_taxonomies !== $settings['enabled_taxonomies'] ) {
			foreach ( array_merge( $old_taxonomies, $settings['enabled_taxonomies'] ) as $taxonomy ) {
				wp_cache_delete( 'sitemap_' . $taxonomy . '_1', 'msm_taxonomy_sitemap' );
			}
		}

		// Clear author sitemap cache if author settings changed
		if ( $old_authors_enabled !== ( $settings['include_authors'] ? '1' : '0' ) ) {
			wp_cache_delete( 'sitemap_authors_1', 'msm_author_sitemap' );
		}

		// Clear page sitemap cache if page settings changed
		if ( $old_page_types !== $settings['enabled_page_types'] ) {
			wp_cache_delete( 'sitemap_pages_1', 'msm_page_sitemap' );
		}

		// Clear REST API transients
		delete_transient( 'msm_sitemap_stats' );

		// Flush rewrite rules if taxonomy, author, or page settings changed
		$new_taxonomies     = $settings['enabled_taxonomies'];
		$new_authors_enabled = $settings['include_authors'] ? '1' : '0';
		$new_page_types     = $settings['enabled_page_types'];
		if ( $old_taxonomies !== $new_taxonomies || $old_authors_enabled !== $new_authors_enabled || $old_page_types !== $new_page_types ) {
			flush_rewrite_rules();
		}

		if ( $result['success'] ) {
			Notifications::show_success( $result['message'] );
		} else {
			Notifications::show_error( $result['message'] );
		}
	}
} 

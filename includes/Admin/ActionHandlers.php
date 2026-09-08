<?php
/**
 * Admin action handlers
 *
 * @package MSM_Sitemap
 */

namespace Automattic\MSM_Sitemap\Admin;

use Automattic\MSM_Sitemap\Admin\Notifications;
use Automattic\MSM_Sitemap\Cron_Service;
use Metro_Sitemap;

/**
 * Handles all admin form action submissions
 */
class Action_Handlers {

	/**
	 * Handle enable cron action
	 */
	public static function handle_enable_cron() {
		$result = Cron_Service::enable_cron();
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
		$result = Cron_Service::disable_cron();
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
		if ( ! Cron_Service::is_cron_enabled() ) {
			Notifications::show_error( __( 'Cannot generate sitemap: automatic updates must be enabled.', 'msm-sitemap' ) );
			return;
		}

		$sitemap_create_in_progress = (bool) get_option( 'msm_sitemap_create_in_progress' );
		
		// Delegate to the builder cron class
		\MSM_Sitemap_Builder_Cron::action_generate();

		if ( false !== get_option( 'msm_sitemap_create_in_progress', false ) ) {
			update_option( 'msm_sitemap_create_in_progress', true );
		} else {
			add_option( 'msm_sitemap_create_in_progress', true, '', 'no' );
		}

		if ( empty( $sitemap_create_in_progress ) ) {
			Notifications::show_success( __( 'Starting sitemap generation...', 'msm-sitemap' ) );
		} else {
			Notifications::show_info( __( 'Resuming sitemap creation', 'msm-sitemap' ) );
		}
	}

	/**
	 * Handle generate from latest posts action
	 */
	public static function handle_generate_from_latest() {
		// Check if cron is enabled before processing
		if ( ! Cron_Service::is_cron_enabled() ) {
			Notifications::show_error( __( 'Cannot generate sitemap: Automatic updates must be enabled.', 'msm-sitemap' ) );
			return;
		}

		if ( \MSM_Sitemap_Builder_Cron::can_generate_from_latest() ) {
			\MSM_Sitemap_Builder_Cron::generate_from_latest();
			Notifications::show_success( __( 'Updating sitemap from recently modified posts...', 'msm-sitemap' ) );
		} else {
			Notifications::show_error( __( 'Cannot generate from recently modified posts: no posts updated lately.', 'msm-sitemap' ) );
		}
	}

	/**
	 * Handle halt generation action
	 */
	public static function handle_halt_generation() {
		// Can only halt generation if sitemap creation is already in process
		if ( (bool) get_option( 'msm_stop_processing' ) === true ) {
			Notifications::show_warning( __( 'Cannot stop sitemap generation: sitemap generation is already being halted.', 'msm-sitemap' ) );
		} elseif ( (bool) get_option( 'msm_sitemap_create_in_progress' ) === true ) {
			update_option( 'msm_stop_processing', true );
			Notifications::show_success( __( 'Stopping sitemap generation...', 'msm-sitemap' ) );
		} else {
			Notifications::show_warning( __( 'Cannot stop sitemap generation: sitemap generation not in progress.', 'msm-sitemap' ) );
		}
	}

	/**
	 * Handle reset sitemap data action
	 */
	public static function handle_reset_data() {
		\MSM_Sitemap_Builder_Cron::reset_sitemap_data();
		Notifications::show_success(
			sprintf(
				/* translators: 1: post type, 2: WP-CLI command */
				__( 'Sitemap data reset. If you want to completely remove the data you must do so manually by deleting all posts with post type <code>%1$s</code>. The WP-CLI command to do this is: <code>%2$s</code>.', 'msm-sitemap' ),
				Metro_Sitemap::SITEMAP_CPT,
				'wp msm-sitemap delete --all'
			) 
		);
	}
} 

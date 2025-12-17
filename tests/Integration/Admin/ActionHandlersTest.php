<?php
/**
 * ActionHandlersTest
 *
 * @package Metro_Sitemap/unit_tests
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Integration\Admin;

use Automattic\MSM_Sitemap\Admin\Action_Handlers;
use Automattic\MSM_Sitemap\Cron_Service;
use MSM_Sitemap_Builder_Cron;

/**
 * Unit Tests for Admin\Action_Handlers class
 */
class ActionHandlersTest extends \Automattic\MSM_Sitemap\Tests\Integration\TestCase {

	/**
	 * Set up the test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		// Clear any existing cron options
		delete_option( Cron_Service::CRON_ENABLED_OPTION );
		wp_unschedule_hook( 'msm_cron_update_sitemap' );
		// Clear any generation progress
		delete_option( 'msm_sitemap_create_in_progress' );
		delete_option( 'msm_stop_processing' );
	}

	/**
	 * Clean up after tests.
	 */
	public function tearDown(): void {
		// Clean up cron options and events
		delete_option( Cron_Service::CRON_ENABLED_OPTION );
		wp_unschedule_hook( 'msm_cron_update_sitemap' );
		delete_option( 'msm_sitemap_create_in_progress' );
		delete_option( 'msm_stop_processing' );
		parent::tearDown();
	}

	/**
	 * Test that handle_enable_cron() enables cron and shows success message.
	 */
	public function test_handle_enable_cron(): void {
		// Remove the filter that forces cron enabled in tests
		remove_filter( 'msm_sitemap_cron_enabled', '__return_true' );
		
		ob_start();
		Action_Handlers::handle_enable_cron();
		$output = ob_get_clean();

		$this->assertTrue( (bool) get_option( Cron_Service::CRON_ENABLED_OPTION ) );
		$this->assertNotFalse( wp_next_scheduled( 'msm_cron_update_sitemap' ) );
		$this->assertStringContainsString( 'Automatic sitemap updates enabled successfully', $output );
		
		// Restore the filter for other tests
		add_filter( 'msm_sitemap_cron_enabled', '__return_true' );
	}

	/**
	 * Test that handle_enable_cron() shows warning when already enabled.
	 */
	public function test_handle_enable_cron_when_already_enabled(): void {
		// Remove the filter that forces cron enabled in tests
		remove_filter( 'msm_sitemap_cron_enabled', '__return_true' );
		
		// Enable cron first
		Cron_Service::enable_cron();

		ob_start();
		Action_Handlers::handle_enable_cron();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Automatic updates are already enabled', $output );
		
		// Restore the filter for other tests
		add_filter( 'msm_sitemap_cron_enabled', '__return_true' );
	}

	/**
	 * Test that handle_disable_cron() disables cron and shows success message.
	 */
	public function test_handle_disable_cron(): void {
		// Remove the filter that forces cron enabled in tests
		remove_filter( 'msm_sitemap_cron_enabled', '__return_true' );
		
		// Enable cron first
		Cron_Service::enable_cron();

		ob_start();
		Action_Handlers::handle_disable_cron();
		$output = ob_get_clean();

		$this->assertFalse( (bool) get_option( Cron_Service::CRON_ENABLED_OPTION ) );
		$this->assertFalse( wp_next_scheduled( 'msm_cron_update_sitemap' ) );
		$this->assertStringContainsString( 'Automatic sitemap updates disabled successfully', $output );
		
		// Restore the filter for other tests
		add_filter( 'msm_sitemap_cron_enabled', '__return_true' );
	}

	/**
	 * Test that handle_disable_cron() shows warning when already disabled.
	 */
	public function test_handle_disable_cron_when_already_disabled(): void {
		// Remove the filter that forces cron enabled in tests
		remove_filter( 'msm_sitemap_cron_enabled', '__return_true' );
		
		ob_start();
		Action_Handlers::handle_disable_cron();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Automatic updates are already disabled', $output );
		
		// Restore the filter for other tests
		add_filter( 'msm_sitemap_cron_enabled', '__return_true' );
	}

	/**
	 * Test that handle_generate_full() starts generation and shows success message.
	 */
	public function test_handle_generate_full(): void {
		// Enable cron first
		Cron_Service::enable_cron();

		ob_start();
		Action_Handlers::handle_generate_full();
		$output = ob_get_clean();

		$this->assertTrue( (bool) get_option( 'msm_sitemap_create_in_progress' ) );
		$this->assertStringContainsString( 'Starting sitemap generation', $output );
	}

	/**
	 * Test that handle_generate_full() shows error when cron is disabled.
	 */
	public function test_handle_generate_full_when_cron_disabled(): void {
		// Remove the filter that forces cron enabled in tests
		remove_filter( 'msm_sitemap_cron_enabled', '__return_true' );
		
		ob_start();
		Action_Handlers::handle_generate_full();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Cannot generate sitemap: automatic updates must be enabled', $output );
		$this->assertFalse( (bool) get_option( 'msm_sitemap_create_in_progress' ) );
		
		// Restore the filter for other tests
		add_filter( 'msm_sitemap_cron_enabled', '__return_true' );
	}

	/**
	 * Test that handle_generate_from_latest() starts generation and shows success message.
	 */
	public function test_handle_generate_from_latest(): void {
		// Enable cron first
		Cron_Service::enable_cron();

		// Create a recent post to ensure there are latest posts
		$post_id = wp_insert_post(
			array(
				'post_title'   => 'Test Post',
				'post_content' => 'Test content',
				'post_status'  => 'publish',
				'post_date'    => current_time( 'mysql' ),
			) 
		);

		ob_start();
		Action_Handlers::handle_generate_from_latest();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Updating sitemap from recently modified posts', $output );
	}

	/**
	 * Test that handle_generate_from_latest() shows error when cron is disabled.
	 */
	public function test_handle_generate_from_latest_when_cron_disabled(): void {
		// Remove the filter that forces cron enabled in tests
		remove_filter( 'msm_sitemap_cron_enabled', '__return_true' );
		
		ob_start();
		Action_Handlers::handle_generate_from_latest();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Cannot generate sitemap: Automatic updates must be enabled', $output );
		
		// Restore the filter for other tests
		add_filter( 'msm_sitemap_cron_enabled', '__return_true' );
	}

	/**
	 * Test that handle_halt_generation() stops generation and shows success message.
	 */
	public function test_handle_halt_generation(): void {
		// Set generation in progress
		update_option( 'msm_sitemap_create_in_progress', true );

		ob_start();
		Action_Handlers::handle_halt_generation();
		$output = ob_get_clean();

		$this->assertTrue( (bool) get_option( 'msm_stop_processing' ) );
		$this->assertStringContainsString( 'Stopping sitemap generation', $output );
	}

	/**
	 * Test that handle_halt_generation() shows warning when not in progress.
	 */
	public function test_handle_halt_generation_when_not_in_progress(): void {
		ob_start();
		Action_Handlers::handle_halt_generation();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Cannot stop sitemap generation: sitemap generation not in progress', $output );
	}

	/**
	 * Test that handle_reset_data() resets data and shows success message.
	 */
	public function test_handle_reset_data(): void {
		// Set some data to reset
		update_option( 'msm_years_to_process', array( '2024' ) );
		update_option( 'msm_months_to_process', array( 1 ) );
		update_option( 'msm_days_to_process', array( 1 ) );
		update_option( 'msm_sitemap_create_in_progress', true );
		update_option( 'msm_stop_processing', true );

		ob_start();
		Action_Handlers::handle_reset_data();
		$output = ob_get_clean();

		$this->assertEmpty( get_option( 'msm_years_to_process' ) );
		$this->assertEmpty( get_option( 'msm_months_to_process' ) );
		$this->assertEmpty( get_option( 'msm_days_to_process' ) );
		$this->assertFalse( (bool) get_option( 'msm_sitemap_create_in_progress' ) );
		$this->assertFalse( (bool) get_option( 'msm_stop_processing' ) );
		$this->assertStringContainsString( 'Sitemap data reset', $output );
	}
} 

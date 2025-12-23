<?php
/**
 * ActionHandlersTest
 *
 * @package Automattic\MSM_Sitemap\Tests
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Admin;

use Automattic\MSM_Sitemap\Infrastructure\WordPress\Admin\ActionHandlers;
use Automattic\MSM_Sitemap\Infrastructure\Cron\CronSchedulingService;

/**
 * Unit Tests for Admin\Action_Handlers class
 */
class ActionHandlersTest extends \Automattic\MSM_Sitemap\Tests\TestCase {

	/**
	 * Get a CronSchedulingService instance for testing.
	 *
	 * @return \Automattic\MSM_Sitemap\Infrastructure\Cron\CronSchedulingService
	 */
	private function get_cron_scheduler(): \Automattic\MSM_Sitemap\Infrastructure\Cron\CronSchedulingService {
		return $this->get_service( \Automattic\MSM_Sitemap\Infrastructure\Cron\CronSchedulingService::class );
	}

	/**
	 * Get an Action_Handlers instance for testing.
	 *
	 * @return \Automattic\MSM_Sitemap\Infrastructure\WordPress\Admin\ActionHandlers
	 */
	private function get_action_handlers(): \Automattic\MSM_Sitemap\Infrastructure\WordPress\Admin\ActionHandlers {
		return $this->get_service( \Automattic\MSM_Sitemap\Infrastructure\WordPress\Admin\ActionHandlers::class );
	}

	/**
	 * Set up the test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		// Clear any existing cron options
		delete_option( CronSchedulingService::CRON_ENABLED_OPTION );
		wp_unschedule_hook( 'msm_cron_update_sitemap' );
		// Clear any generation progress
		delete_option( 'msm_generation_in_progress' );
		delete_option( 'msm_stop_processing' );
	}

	/**
	 * Clean up after tests.
	 */
	public function tearDown(): void {
		// Clean up cron options and events
		delete_option( CronSchedulingService::CRON_ENABLED_OPTION );
		wp_unschedule_hook( 'msm_cron_update_sitemap' );
		delete_option( 'msm_generation_in_progress' );
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
		$this->get_action_handlers()->handle_enable_cron();
		$output = ob_get_clean();

		$this->assertTrue( (bool) get_option( CronSchedulingService::CRON_ENABLED_OPTION ) );
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
		$this->get_cron_scheduler()->enable_cron();

		ob_start();
		$this->get_action_handlers()->handle_enable_cron();
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
		$this->get_cron_scheduler()->enable_cron();

		ob_start();
		$this->get_action_handlers()->handle_disable_cron();
		$output = ob_get_clean();

		$this->assertFalse( (bool) get_option( CronSchedulingService::CRON_ENABLED_OPTION ) );
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
		$this->get_action_handlers()->handle_disable_cron();
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
		$this->get_cron_scheduler()->enable_cron();

		// Create a post to ensure there are years with posts
		$post_id = wp_insert_post(
			array(
				'post_title'   => 'Test Post for Full Generation',
				'post_content' => 'Test content',
				'post_status'  => 'publish',
				'post_date'    => '2024-01-15 10:00:00',
			) 
		);

		ob_start();
		$this->get_action_handlers()->handle_generate_full();
		$output = ob_get_clean();

		$this->assertTrue( (bool) get_option( 'msm_generation_in_progress' ) );
		$this->assertStringContainsString( 'Started full generation', $output );
	}

	/**
	 * Test that handle_generate_full() shows error when cron is disabled.
	 */
	public function test_handle_generate_full_when_cron_disabled(): void {
		// Remove the filter that forces cron enabled in tests
		remove_filter( 'msm_sitemap_cron_enabled', '__return_true' );

		ob_start();
		$this->get_action_handlers()->handle_generate_full();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'requires cron to be enabled', $output );
		$this->assertFalse( (bool) get_option( 'msm_generation_in_progress' ) );

		// Restore the filter for other tests
		add_filter( 'msm_sitemap_cron_enabled', '__return_true' );
	}

	/**
	 * Test that handle_generate_missing_sitemaps() generates directly and shows success message.
	 *
	 * Note: As of 2.x, generate_missing_sitemaps() always runs directly (blocking).
	 * Background generation is available via schedule_background_generation() method.
	 */
	public function test_handle_generate_from_latest(): void {
		// Enable cron first
		$this->get_cron_scheduler()->enable_cron();

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
		$this->get_action_handlers()->handle_generate_missing_sitemaps();
		$output = ob_get_clean();

		// Should generate directly even when cron is enabled
		$this->assertStringContainsString( 'Generated', $output );
	}

	/**
	 * Test that handle_generate_missing_sitemaps() works directly when cron is disabled.
	 */
	public function test_handle_generate_from_latest_when_cron_disabled(): void {
		// Remove the filter that forces cron enabled in tests
		remove_filter( 'msm_sitemap_cron_enabled', '__return_true' );

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
		$this->get_action_handlers()->handle_generate_missing_sitemaps();
		$output = ob_get_clean();

		// Should work directly when cron is disabled
		$this->assertStringContainsString( 'Generated', $output );

		// Restore the filter for other tests
		add_filter( 'msm_sitemap_cron_enabled', '__return_true' );
	}

	/**
	 * Test that handle_schedule_background_generation() schedules generation.
	 */
	public function test_handle_schedule_background_generation(): void {
		// Enable cron first
		$this->get_cron_scheduler()->enable_cron();

		// Create a recent post to ensure there are missing sitemaps
		$post_id = wp_insert_post(
			array(
				'post_title'   => 'Test Post',
				'post_content' => 'Test content',
				'post_status'  => 'publish',
				'post_date'    => current_time( 'mysql' ),
			)
		);

		ob_start();
		$this->get_action_handlers()->handle_schedule_background_generation();
		$output = ob_get_clean();

		// Should schedule background generation
		$this->assertStringContainsString( 'Scheduled background generation', $output );
	}

	/**
	 * Test that handle_schedule_background_generation() requires cron to be enabled.
	 */
	public function test_handle_schedule_background_generation_requires_cron(): void {
		// Remove the filter that forces cron enabled in tests
		remove_filter( 'msm_sitemap_cron_enabled', '__return_true' );

		ob_start();
		$this->get_action_handlers()->handle_schedule_background_generation();
		$output = ob_get_clean();

		// Should show warning about cron being required
		$this->assertStringContainsString( 'Background generation requires cron', $output );

		// Restore the filter for other tests
		add_filter( 'msm_sitemap_cron_enabled', '__return_true' );
	}

	/**
	 * Test that handle_halt_generation() stops generation and shows success message.
	 */
	public function test_handle_halt_generation(): void {
		// Set generation in progress
		update_option( 'msm_generation_in_progress', true );

		ob_start();
		$this->get_action_handlers()->handle_halt_generation();
		$output = ob_get_clean();

		$this->assertTrue( (bool) get_option( 'msm_sitemap_stop_generation' ) );
		$this->assertStringContainsString( 'Stopping sitemap generation', $output );
	}

	/**
	 * Test that handle_halt_generation() shows warning when not in progress.
	 */
	public function test_handle_halt_generation_when_not_in_progress(): void {
		ob_start();
		$this->get_action_handlers()->handle_halt_generation();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Sitemap generation is not in progress', $output );
	}

	/**
	 * Test that handle_reset_data() resets data and shows success message.
	 */
	public function test_handle_reset_data(): void {
		// Set some data to reset
		update_option( 'msm_generation_in_progress', true );
		update_option( 'msm_sitemap_stop_generation', true );
		update_option( 'msm_background_generation_in_progress', true );
		update_option( 'msm_background_generation_total', 10 );
		update_option( 'msm_background_generation_remaining', 5 );

		ob_start();
		$this->get_action_handlers()->handle_reset_data();
		$output = ob_get_clean();

		$this->assertFalse( (bool) get_option( 'msm_generation_in_progress' ) );
		$this->assertFalse( (bool) get_option( 'msm_sitemap_stop_generation' ) );
		$this->assertFalse( (bool) get_option( 'msm_background_generation_in_progress' ) );
		$this->assertEmpty( get_option( 'msm_background_generation_total' ) );
		$this->assertEmpty( get_option( 'msm_background_generation_remaining' ) );
		$this->assertStringContainsString( 'Sitemap data reset', $output );
	}
} 

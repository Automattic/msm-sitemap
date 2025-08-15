<?php
/**
 * CronServiceTest
 *
 * @package Automattic\MSM_Sitemap\Tests
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests;

use Automattic\MSM_Sitemap\Infrastructure\Cron\CronSchedulingService;

/**
 * Unit Tests for Cron_Service class
 */
class CronServiceTest extends TestCase {

	/**
	 * Get a CronSchedulingService instance for testing.
	 *
	 * @return CronSchedulingService
	 */
	private function get_cron_scheduler(): \Automattic\MSM_Sitemap\Infrastructure\Cron\CronSchedulingService {
		return $this->get_service( CronSchedulingService::class );
	}

	/**
	 * Set up the test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		// Clear any existing cron options
		delete_option( CronSchedulingService::CRON_ENABLED_OPTION );
		wp_unschedule_hook( 'msm_cron_update_sitemap' );
	}

	/**
	 * Clean up after tests.
	 */
	public function tearDown(): void {
		// Clean up cron options and events
		delete_option( CronSchedulingService::CRON_ENABLED_OPTION );
		wp_unschedule_hook( 'msm_cron_update_sitemap' );
		parent::tearDown();
	}

	/**
	 * Test that enable_cron() sets the option and schedules the event.
	 */
	public function test_enable_cron(): void {
		// Remove the filter that forces cron enabled in tests
		remove_filter( 'msm_sitemap_cron_enabled', '__return_true' );
		
		$result = $this->get_cron_scheduler()->enable_cron();
		
		$this->assertTrue( $result );
		$this->assertTrue( (bool) get_option( CronSchedulingService::CRON_ENABLED_OPTION ) );
		$this->assertNotFalse( wp_next_scheduled( 'msm_cron_update_sitemap' ) );
		
		// Restore the filter for other tests
		add_filter( 'msm_sitemap_cron_enabled', '__return_true' );
	}

	/**
	 * Test that enable_cron() returns false when already enabled.
	 */
	public function test_enable_cron_when_already_enabled(): void {
		// Remove the filter that forces cron enabled in tests
		remove_filter( 'msm_sitemap_cron_enabled', '__return_true' );
		
		// Enable cron first
		$this->get_cron_scheduler()->enable_cron();
		
		// Try to enable again
		$result = $this->get_cron_scheduler()->enable_cron();
		
		$this->assertFalse( $result );
		$this->assertTrue( (bool) get_option( CronSchedulingService::CRON_ENABLED_OPTION ) );
		
		// Restore the filter for other tests
		add_filter( 'msm_sitemap_cron_enabled', '__return_true' );
	}

	/**
	 * Test that disable_cron() unsets the option and unschedules the event.
	 */
	public function test_disable_cron(): void {
		// Enable cron first
		$this->get_cron_scheduler()->enable_cron();
		
		$result = $this->get_cron_scheduler()->disable_cron();
		
		$this->assertTrue( $result );
		$this->assertFalse( (bool) get_option( CronSchedulingService::CRON_ENABLED_OPTION ) );
		$this->assertFalse( wp_next_scheduled( 'msm_cron_update_sitemap' ) );
	}

	/**
	 * Test that disable_cron() returns false when already disabled.
	 */
	public function test_disable_cron_when_already_disabled(): void {
		// Remove the filter that forces cron enabled in tests
		remove_filter( 'msm_sitemap_cron_enabled', '__return_true' );
		
		$result = $this->get_cron_scheduler()->disable_cron();
		
		$this->assertFalse( $result );
		$this->assertFalse( (bool) get_option( CronSchedulingService::CRON_ENABLED_OPTION ) );
		
		// Restore the filter for other tests
		add_filter( 'msm_sitemap_cron_enabled', '__return_true' );
	}

	/**
	 * Test that is_cron_enabled() returns true when enabled.
	 */
	public function test_is_cron_enabled_when_enabled(): void {
		$this->get_cron_scheduler()->enable_cron();
		
		$result = $this->get_cron_scheduler()->is_cron_enabled();
		
		$this->assertTrue( $result );
	}

	/**
	 * Test that is_cron_enabled() returns false when disabled.
	 */
	public function test_is_cron_enabled_when_disabled(): void {
		// Remove the filter that forces cron enabled in tests
		remove_filter( 'msm_sitemap_cron_enabled', '__return_true' );
		
		$result = $this->get_cron_scheduler()->is_cron_enabled();
		
		$this->assertFalse( $result );
		
		// Restore the filter for other tests
		add_filter( 'msm_sitemap_cron_enabled', '__return_true' );
	}

	/**
	 * Test that is_cron_enabled() clears scheduled events when option doesn't exist.
	 */
	public function test_is_cron_enabled_clears_events_when_option_missing(): void {
		// Remove the filter that forces cron enabled in tests
		remove_filter( 'msm_sitemap_cron_enabled', '__return_true' );
		
		// Manually schedule an event without setting the option
		wp_schedule_event( time(), 'hourly', 'msm_cron_update_sitemap' );
		
		$result = $this->get_cron_scheduler()->is_cron_enabled();
		
		$this->assertFalse( $result );
		$this->assertFalse( wp_next_scheduled( 'msm_cron_update_sitemap' ) );
		
		// Restore the filter for other tests
		add_filter( 'msm_sitemap_cron_enabled', '__return_true' );
	}

	/**
	 * Test that is_cron_enabled() respects the filter.
	 */
	public function test_is_cron_enabled_with_filter(): void {
		add_filter( 'msm_sitemap_cron_enabled', '__return_true' );
		
		$result = $this->get_cron_scheduler()->is_cron_enabled();
		
		$this->assertTrue( $result );
		
		remove_filter( 'msm_sitemap_cron_enabled', '__return_true' );
	}

	/**
	 * Test that get_cron_status() returns correct status when enabled.
	 */
	public function test_get_cron_status_when_enabled(): void {
		$this->get_cron_scheduler()->enable_cron();
		
		$status = $this->get_cron_scheduler()->get_cron_status();
		
		$this->assertTrue( $status['enabled'] );
		// next_scheduled might be false if cron event hasn't been scheduled yet
		$this->assertIsBool( $status['next_scheduled'] );
		$this->assertArrayHasKey( 'blog_public', $status );
		$this->assertArrayHasKey( 'generating', $status );
		$this->assertArrayHasKey( 'halted', $status );
	}

	/**
	 * Test that get_cron_status() returns correct status when disabled.
	 */
	public function test_get_cron_status_when_disabled(): void {
		// Remove the filter that forces cron enabled in tests
		remove_filter( 'msm_sitemap_cron_enabled', '__return_true' );
		
		$status = $this->get_cron_scheduler()->get_cron_status();
		
		$this->assertFalse( $status['enabled'] );
		$this->assertFalse( $status['next_scheduled'] );
		$this->assertArrayHasKey( 'blog_public', $status );
		$this->assertArrayHasKey( 'generating', $status );
		$this->assertArrayHasKey( 'halted', $status );
		
		// Restore the filter for other tests
		add_filter( 'msm_sitemap_cron_enabled', '__return_true' );
	}

	/**
	 * Test that get_cron_status() clears events when inconsistent.
	 */
	public function test_get_cron_status_clears_events_when_inconsistent(): void {
		// Remove the filter that forces cron enabled in tests
		remove_filter( 'msm_sitemap_cron_enabled', '__return_true' );
		
		// Set option to false but schedule an event
		update_option( CronSchedulingService::CRON_ENABLED_OPTION, false );
		wp_schedule_event( time(), 'hourly', 'msm_cron_update_sitemap' );
		
		$status = $this->get_cron_scheduler()->get_cron_status();
		
		$this->assertFalse( $status['enabled'] );
		$this->assertFalse( $status['next_scheduled'] );
		$this->assertFalse( wp_next_scheduled( 'msm_cron_update_sitemap' ) );
		
		// Restore the filter for other tests
		add_filter( 'msm_sitemap_cron_enabled', '__return_true' );
	}

	/**
	 * Test that reset_cron() clears all cron data.
	 */
	public function test_reset_cron(): void {
		// Enable cron and add some processing options
		$this->get_cron_scheduler()->enable_cron();
		update_option( 'msm_years_to_process', array( '2024' ) );
		update_option( 'msm_months_to_process', array( 1 ) );
		update_option( 'msm_days_to_process', array( 1 ) );
		update_option( 'msm_generation_in_progress', true );
		update_option( 'msm_stop_processing', true );
		
		$this->get_cron_scheduler()->reset_cron();
		
		$this->assertFalse( (bool) get_option( CronSchedulingService::CRON_ENABLED_OPTION ) );
		$this->assertFalse( wp_next_scheduled( 'msm_cron_update_sitemap' ) );
		$this->assertEmpty( get_option( 'msm_years_to_process' ) );
		$this->assertEmpty( get_option( 'msm_months_to_process' ) );
		$this->assertEmpty( get_option( 'msm_days_to_process' ) );
		$this->assertFalse( (bool) get_option( 'msm_generation_in_progress' ) );
		$this->assertFalse( (bool) get_option( 'msm_stop_processing' ) );
	}



	/**
	 * Test that incremental update handles orphaned sitemaps when posts are moved
	 */
	public function test_incremental_update_handles_orphaned_sitemaps() {
		// Enable cron
		$this->get_cron_scheduler()->enable_cron();
		
		// Create a post for August 6th
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Test Post',
				'post_status' => 'publish',
				'post_date'   => '2025-08-06 10:00:00',
			) 
		);
		
		// Verify post was created correctly
		$post = get_post( $post_id );
		$this->assertEquals( '2025-08-06 10:00:00', $post->post_date );
		
		// Generate sitemap for August 6th
		$service = new \Automattic\MSM_Sitemap\Application\Services\SitemapService(
			\Automattic\MSM_Sitemap\Infrastructure\Factories\SitemapGeneratorFactory::create( $this->get_service( \Automattic\MSM_Sitemap\Application\Services\ContentTypesService::class )->get_content_types() ),
			new \Automattic\MSM_Sitemap\Infrastructure\Repositories\SitemapPostRepository()
		);
		$service->create_for_date( '2025-08-06', true );
		
		// Verify sitemap exists for August 6th
		$this->assertNotNull( $this->get_sitemap_post_id( 2025, 8, 6 ) );
		
		// Move the post to August 7th
		wp_update_post(
			array(
				'ID'        => $post_id,
				'post_date' => '2025-08-07 10:00:00',
			) 
		);
		
		// Clear any caches
		clean_post_cache( $post_id );
		wp_cache_flush();
		
		// Verify post was moved correctly
		$updated_post = get_post( $post_id );
		$this->assertEquals( '2025-08-07 10:00:00', $updated_post->post_date );
		
		// Run the incremental update
		$missing_handler = $this->get_service( \Automattic\MSM_Sitemap\Infrastructure\Cron\MissingSitemapGenerationHandler::class );
		$missing_handler->execute();
		
		// Verify sitemap for August 6th is deleted (orphaned)
		$this->assertFalse( $this->get_sitemap_post_id( 2025, 8, 6 ) );
		
		// Verify sitemap for August 7th exists
		$this->assertNotNull( $this->get_sitemap_post_id( 2025, 8, 7 ) );
		
		// Clean up
		wp_delete_post( $post_id, true );
		$this->get_cron_scheduler()->disable_cron();
	}
} 

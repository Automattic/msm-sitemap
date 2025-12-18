<?php
/**
 * CronServiceTest
 *
 * @package Metro_Sitemap/unit_tests
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Integration;

use Automattic\MSM_Sitemap\Cron_Service;

/**
 * Unit Tests for Cron_Service class
 */
class CronServiceTest extends TestCase {

	/**
	 * Set up the test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		// Clear any existing cron options
		delete_option( Cron_Service::CRON_ENABLED_OPTION );
		wp_unschedule_hook( 'msm_cron_update_sitemap' );
	}

	/**
	 * Clean up after tests.
	 */
	public function tearDown(): void {
		// Clean up cron options and events
		delete_option( Cron_Service::CRON_ENABLED_OPTION );
		wp_unschedule_hook( 'msm_cron_update_sitemap' );
		parent::tearDown();
	}

	/**
	 * Test that enable_cron() sets the option and schedules the event.
	 */
	public function test_enable_cron(): void {
		// Remove the filter that forces cron enabled in tests
		remove_filter( 'msm_sitemap_cron_enabled', '__return_true' );
		
		$result = Cron_Service::enable_cron();
		
		$this->assertTrue( $result );
		$this->assertTrue( (bool) get_option( Cron_Service::CRON_ENABLED_OPTION ) );
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
		Cron_Service::enable_cron();
		
		// Try to enable again
		$result = Cron_Service::enable_cron();
		
		$this->assertFalse( $result );
		$this->assertTrue( (bool) get_option( Cron_Service::CRON_ENABLED_OPTION ) );
		
		// Restore the filter for other tests
		add_filter( 'msm_sitemap_cron_enabled', '__return_true' );
	}

	/**
	 * Test that disable_cron() unsets the option and unschedules the event.
	 */
	public function test_disable_cron(): void {
		// Enable cron first
		Cron_Service::enable_cron();
		
		$result = Cron_Service::disable_cron();
		
		$this->assertTrue( $result );
		$this->assertFalse( (bool) get_option( Cron_Service::CRON_ENABLED_OPTION ) );
		$this->assertFalse( wp_next_scheduled( 'msm_cron_update_sitemap' ) );
	}

	/**
	 * Test that disable_cron() returns false when already disabled.
	 */
	public function test_disable_cron_when_already_disabled(): void {
		// Remove the filter that forces cron enabled in tests
		remove_filter( 'msm_sitemap_cron_enabled', '__return_true' );
		
		$result = Cron_Service::disable_cron();
		
		$this->assertFalse( $result );
		$this->assertFalse( (bool) get_option( Cron_Service::CRON_ENABLED_OPTION ) );
		
		// Restore the filter for other tests
		add_filter( 'msm_sitemap_cron_enabled', '__return_true' );
	}

	/**
	 * Test that is_cron_enabled() returns true when enabled.
	 */
	public function test_is_cron_enabled_when_enabled(): void {
		Cron_Service::enable_cron();
		
		$result = Cron_Service::is_cron_enabled();
		
		$this->assertTrue( $result );
	}

	/**
	 * Test that is_cron_enabled() returns false when disabled.
	 */
	public function test_is_cron_enabled_when_disabled(): void {
		// Remove the filter that forces cron enabled in tests
		remove_filter( 'msm_sitemap_cron_enabled', '__return_true' );
		
		$result = Cron_Service::is_cron_enabled();
		
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
		
		$result = Cron_Service::is_cron_enabled();
		
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
		
		$result = Cron_Service::is_cron_enabled();
		
		$this->assertTrue( $result );
		
		remove_filter( 'msm_sitemap_cron_enabled', '__return_true' );
	}

	/**
	 * Test that get_cron_status() returns correct status when enabled.
	 */
	public function test_get_cron_status_when_enabled(): void {
		Cron_Service::enable_cron();
		
		$status = Cron_Service::get_cron_status();
		
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
		
		$status = Cron_Service::get_cron_status();
		
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
		update_option( Cron_Service::CRON_ENABLED_OPTION, false );
		wp_schedule_event( time(), 'hourly', 'msm_cron_update_sitemap' );
		
		$status = Cron_Service::get_cron_status();
		
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
		Cron_Service::enable_cron();
		update_option( 'msm_years_to_process', array( '2024' ) );
		update_option( 'msm_months_to_process', array( 1 ) );
		update_option( 'msm_days_to_process', array( 1 ) );
		update_option( 'msm_sitemap_create_in_progress', true );
		update_option( 'msm_stop_processing', true );
		
		Cron_Service::reset_cron();
		
		$this->assertFalse( (bool) get_option( Cron_Service::CRON_ENABLED_OPTION ) );
		$this->assertFalse( wp_next_scheduled( 'msm_cron_update_sitemap' ) );
		$this->assertEmpty( get_option( 'msm_years_to_process' ) );
		$this->assertEmpty( get_option( 'msm_months_to_process' ) );
		$this->assertEmpty( get_option( 'msm_days_to_process' ) );
		$this->assertFalse( (bool) get_option( 'msm_sitemap_create_in_progress' ) );
		$this->assertFalse( (bool) get_option( 'msm_stop_processing' ) );
	}

	/**
	 * Test that should_auto_enable_cron() returns false by default.
	 */
	public function test_should_auto_enable_cron(): void {
		$result = Cron_Service::should_auto_enable_cron();
		
		$this->assertFalse( $result );
	}
} 

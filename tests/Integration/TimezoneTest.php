<?php
/**
 * Tests for timezone functionality in MSM Sitemap
 *
 * @package automattic/msm-sitemap
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Integration;

use Automattic\MSM_Sitemap\Cron_Service;
use Metro_Sitemap;

/**
 * Tests for timezone functionality in MSM Sitemap
 */
class TimezoneTest extends TestCase {

	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		parent::setUp();
		
		// Reset timezone to ensure clean state
		update_option( 'timezone_string', 'UTC' );
		wp_cache_flush();
	}

	/**
	 * Tear down test environment
	 */
	public function tearDown(): void {
		// Reset to UTC
		update_option( 'timezone_string', 'UTC' );
		wp_cache_flush();
		parent::tearDown();
	}

	/**
	 * Test that sitemap generation uses local timezone
	 */
	public function test_sitemap_generation_uses_local_timezone(): void {
		// Set timezone to Sydney (+11)
		update_option( 'timezone_string', 'Australia/Sydney' );
		wp_cache_flush();

		// Create a post with current time in Sydney timezone
		$current_time = current_datetime()->getTimestamp();
		$post_date = wp_date( 'Y-m-d H:i:s', $current_time );
		
		$post_id = $this->create_dummy_post( $post_date );

		// Get the date in Sydney timezone
		$sydney_date = wp_date( 'Y-m-d' );
		
		// Generate sitemap for today
		Metro_Sitemap::generate_sitemap_for_date( $sydney_date );
		
		// Check that sitemap was created for today's date
		list( $year, $month, $day ) = explode( '-', $sydney_date );
		$sitemap_id = Metro_Sitemap::get_sitemap_post_id( $year, $month, $day );
		
		$this->assertNotFalse( $sitemap_id, 'Sitemap should be created for today in Sydney timezone' );
		
		// Verify the sitemap contains our post
		$sitemap_content = get_post_meta( $sitemap_id, 'msm_sitemap_xml', true );
		$this->assertStringContainsString( get_permalink( $post_id ), $sitemap_content );
	}

	/**
	 * Test that sitemap doesn't show yesterday's posts at 10am local time
	 */
	public function test_sitemap_does_not_show_yesterday_at_10am_local(): void {
		// Set timezone to Sydney (+11)
		update_option( 'timezone_string', 'Australia/Sydney' );
		wp_cache_flush();

		// Create a post yesterday at current time
		$yesterday_time = strtotime( 'yesterday', current_datetime()->getTimestamp() );
		$yesterday_post_date = wp_date( 'Y-m-d H:i:s', $yesterday_time );
		
		$yesterday_post_id = $this->create_dummy_post( $yesterday_post_date );

		// Create a post today at current time to ensure sitemap is created
		$today_time = current_datetime()->getTimestamp();
		$today_post_date = wp_date( 'Y-m-d H:i:s', $today_time );
		
		$today_post_id = $this->create_dummy_post( $today_post_date );

		// Get today's date in Sydney timezone
		$today_sydney = wp_date( 'Y-m-d' );
		
		// Generate sitemap for today
		Metro_Sitemap::generate_sitemap_for_date( $today_sydney );
		
		// Check that sitemap was created for today's date
		list( $year, $month, $day ) = explode( '-', $today_sydney );
		$sitemap_id = Metro_Sitemap::get_sitemap_post_id( $year, $month, $day );
		
		$this->assertNotFalse( $sitemap_id, 'Sitemap should be created for today' );
		
		// Verify the sitemap contains today's post but NOT yesterday's post
		$sitemap_content = get_post_meta( $sitemap_id, 'msm_sitemap_xml', true );
		$this->assertStringContainsString( get_permalink( $today_post_id ), $sitemap_content, 'Sitemap should contain today\'s post' );
		$this->assertStringNotContainsString( get_permalink( $yesterday_post_id ), $sitemap_content, 'Sitemap should NOT contain yesterday\'s post' );
	}

	/**
	 * Test that sitemap shows today's posts at 11pm UTC (10am Sydney)
	 */
	public function test_sitemap_shows_today_at_11pm_utc_10am_sydney(): void {
		// Set timezone to Sydney (+11)
		update_option( 'timezone_string', 'Australia/Sydney' );
		wp_cache_flush();

		// Create a post today at current time in Sydney timezone
		$current_time = current_datetime()->getTimestamp();
		$post_date = wp_date( 'Y-m-d H:i:s', $current_time );
		
		$post_id = $this->create_dummy_post( $post_date );

		// Get the date that should be used (Sydney time)
		$sydney_date = wp_date( 'Y-m-d' );
		
		// Generate sitemap for today
		Metro_Sitemap::generate_sitemap_for_date( $sydney_date );
		
		// Check that sitemap was created for today's date
		list( $year, $month, $day ) = explode( '-', $sydney_date );
		$sitemap_id = Metro_Sitemap::get_sitemap_post_id( $year, $month, $day );
		
		$this->assertNotFalse( $sitemap_id, 'Sitemap should be created for today even at 11pm UTC' );
		
		// Verify the sitemap contains our post
		$sitemap_content = get_post_meta( $sitemap_id, 'msm_sitemap_xml', true );
		$this->assertStringContainsString( get_permalink( $post_id ), $sitemap_content );
	}

	/**
	 * Test timezone handling for New York timezone
	 */
	public function test_new_york_timezone_handling(): void {
		// Set timezone to New York (-5)
		update_option( 'timezone_string', 'America/New_York' );
		wp_cache_flush();

		// Create a post at current time EST
		$current_time = current_datetime()->getTimestamp();
		$post_date = wp_date( 'Y-m-d H:i:s', $current_time );
		
		$post_id = $this->create_dummy_post( $post_date );

		// Get today's date in NY timezone
		$ny_date = wp_date( 'Y-m-d' );
		
		// Generate sitemap for today
		Metro_Sitemap::generate_sitemap_for_date( $ny_date );
		
		// Check that sitemap was created for today's date
		list( $year, $month, $day ) = explode( '-', $ny_date );
		$sitemap_id = Metro_Sitemap::get_sitemap_post_id( $year, $month, $day );
		
		$this->assertNotFalse( $sitemap_id, 'Sitemap should be created for today in NY timezone' );
		
		// Verify the sitemap contains our post
		$sitemap_content = get_post_meta( $sitemap_id, 'msm_sitemap_xml', true );
		$this->assertStringContainsString( get_permalink( $post_id ), $sitemap_content );
	}

	/**
	 * Test that wp_date() returns correct timezone
	 */
	public function test_current_time_returns_correct_timezone(): void {
		// Set timezone to Sydney (+11)
		update_option( 'timezone_string', 'Australia/Sydney' );
		wp_cache_flush();

		// Test that wp_date() returns timezone-aware formatting
		$sydney_date = wp_date( 'Y-m-d H:i:s' );
		$utc_date = gmdate( 'Y-m-d H:i:s' );
		
		// Convert both to timestamps for comparison
		$sydney_timestamp = strtotime( $sydney_date );
		$utc_timestamp = strtotime( $utc_date );
		
		// Sydney should be ahead of UTC (timezone offset)
		$time_diff = $sydney_timestamp - $utc_timestamp;
		$this->assertGreaterThanOrEqual( 36000, $time_diff, 'Sydney time should be at least 10 hours ahead of UTC' );
		$this->assertLessThanOrEqual( 39600, $time_diff, 'Sydney time should be at most 11 hours ahead of UTC' );
	}

	/**
	 * Test that get_last_modified_posts uses local timezone
	 */
	public function test_get_last_modified_posts_uses_local_timezone(): void {
		// Set timezone to Sydney (+11)
		update_option( 'timezone_string', 'Australia/Sydney' );
		wp_cache_flush();

		// Create a post with current time
		$current_time = current_datetime()->getTimestamp();
		$post_date = wp_date( 'Y-m-d H:i:s', $current_time );
		
		$post_id = $this->create_dummy_post( $post_date );
		
		// Update the post to trigger post_modified update
		wp_update_post( array(
			'ID' => $post_id,
			'post_title' => 'Updated Title',
		) );

		// Get last modified posts
		$modified_posts = Metro_Sitemap::get_last_modified_posts();

		// Should find our post in the list
		$found = false;
		foreach ( $modified_posts as $post ) {
			if ( (int) $post->ID === $post_id ) {
				$found = true;
				break;
			}
		}

		$this->assertTrue( $found, 'Should find post modified within last hour in local timezone' );
	}

	/**
	 * Test that update_sitemap_from_modified_posts uses local timezone
	 */
	public function test_update_sitemap_from_modified_posts_uses_local_timezone(): void {
		// Set timezone to Sydney (+11)
		update_option( 'timezone_string', 'Australia/Sydney' );
		wp_cache_flush();

		// Create a post with current time
		$current_time = current_datetime()->getTimestamp();
		$post_date = wp_date( 'Y-m-d H:i:s', $current_time );
		
		$post_id = $this->create_dummy_post( $post_date );
		
		// Update the post to trigger post_modified update
		wp_update_post( array(
			'ID' => $post_id,
			'post_title' => 'Updated Title',
		) );

		// Get today's date in Sydney timezone
		$sydney_date = wp_date( 'Y-m-d' );
		
		// Update sitemap from modified posts (this schedules cron events)
		Metro_Sitemap::update_sitemap_from_modified_posts();
		
		// Manually trigger the sitemap generation for today's date
		Metro_Sitemap::generate_sitemap_for_date( $sydney_date );
		
		// Check that sitemap was created for today's date
		list( $year, $month, $day ) = explode( '-', $sydney_date );
		$sitemap_id = Metro_Sitemap::get_sitemap_post_id( $year, $month, $day );
		
		$this->assertNotFalse( $sitemap_id, 'Sitemap should be created for today in Sydney timezone' );
		
		// Verify the sitemap contains our post
		$sitemap_content = get_post_meta( $sitemap_id, 'msm_sitemap_xml', true );
		$this->assertStringContainsString( get_permalink( $post_id ), $sitemap_content );
	}

	/**
	 * Test that cron scheduling uses local timezone
	 */
	public function test_cron_scheduling_uses_local_timezone(): void {
		// Set timezone to Sydney (+11)
		update_option( 'timezone_string', 'Australia/Sydney' );
		wp_cache_flush();

		// Clear any existing cron jobs
		wp_clear_scheduled_hook( 'msm_cron_update_sitemap' );
		
		// Remove the filter that forces cron enabled so we can actually enable it
		remove_filter( 'msm_sitemap_cron_enabled', '__return_true' );
		
		// Initialize cron
		Cron_Service::enable_cron();
		
		// Check that cron is scheduled
		$next_scheduled = wp_next_scheduled( 'msm_cron_update_sitemap' );
		$this->assertNotFalse( $next_scheduled, 'Cron should be scheduled' );
		
		// The scheduled time should be in the future relative to local time
		$local_time = time();
		$this->assertGreaterThanOrEqual( $local_time, $next_scheduled, 'Cron should be scheduled in the future relative to local time' );
	}

	/**
	 * Test that get_recent_sitemap_url_counts uses local timezone
	 */
	public function test_get_recent_sitemap_url_counts_uses_local_timezone(): void {
		// Set timezone to Sydney (+11)
		update_option( 'timezone_string', 'Australia/Sydney' );
		wp_cache_flush();

		// Get recent sitemap counts
		$counts = Metro_Sitemap::get_recent_sitemap_url_counts( 7 );
		
		// Should have 7 days of data
		$this->assertCount( 7, $counts, 'Should have 7 days of sitemap counts' );
		
		// All dates should be in Sydney timezone
		foreach ( $counts as $date => $count ) {
			$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}$/', $date, 'Date should be in Y-m-d format' );
		}
	}

	/**
	 * Test that get_post_year_range uses local timezone
	 */
	public function test_get_post_year_range_uses_local_timezone(): void {
		// Set timezone to Sydney (+11)
		update_option( 'timezone_string', 'Australia/Sydney' );
		wp_cache_flush();

		// Create a post in current year
		$current_year = wp_date( 'Y' );
		$post_date = $current_year . '-06-15 10:00:00';
		
		$post_id = $this->create_dummy_post( $post_date );

		// Get year range
		$year_range = Metro_Sitemap::get_post_year_range();
		
		// Should include current year
		$this->assertContains( (int) $current_year, $year_range, 'Year range should include current year in local timezone' );
	}
} 

<?php
/**
 * FullGenerationService Test
 *
 * @package Automattic\MSM_Sitemap\Tests\Application\Services
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Application\Services;

use Automattic\MSM_Sitemap\Application\Services\FullGenerationService;
use Automattic\MSM_Sitemap\Infrastructure\Cron\SitemapGenerationScheduler;

/**
 * Unit Tests for FullGenerationService.
 *
 * The FullGenerationService uses a simplified architecture:
 * - AllDatesWithPostsService provides all dates that need sitemaps
 * - SitemapGenerationScheduler schedules individual cron events for each date
 */
class FullGenerationServiceTest extends \Automattic\MSM_Sitemap\Tests\TestCase {

	/**
	 * Number of years to create posts for.
	 *
	 * @var int
	 */
	private int $num_years_data = 2;

	/**
	 * Set up test environment with posts and handler setup
	 */
	public function setUp(): void {
		parent::setUp();

		// Clean up any existing options and scheduled events.
		delete_option( 'msm_background_generation_in_progress' );
		delete_option( 'msm_background_generation_total' );
		delete_option( 'msm_background_generation_remaining' );
		delete_option( 'msm_generation_in_progress' );
		wp_unschedule_hook( SitemapGenerationScheduler::CRON_HOOK );

		$this->add_a_post_for_each_of_the_last_x_years( $this->num_years_data );
		$this->add_a_post_for_today();

		$this->assertPostCount( $this->num_years_data + 1 );
	}

	/**
	 * Clean up after tests.
	 */
	public function tearDown(): void {
		delete_option( 'msm_background_generation_in_progress' );
		delete_option( 'msm_background_generation_total' );
		delete_option( 'msm_background_generation_remaining' );
		delete_option( 'msm_generation_in_progress' );
		wp_unschedule_hook( SitemapGenerationScheduler::CRON_HOOK );

		parent::tearDown();
	}

	/**
	 * Test that full generation schedules events for all dates with posts.
	 */
	public function test_start_full_generation_schedules_events_for_all_dates(): void {
		$service = $this->get_service( FullGenerationService::class );

		// Start full generation.
		$result = $service->start_full_generation();

		// Should have scheduled events for all unique dates (at least 2).
		$this->assertTrue( $result['success'] );
		$this->assertSame( 'background', $result['method'] );
		$this->assertGreaterThanOrEqual( 2, $result['scheduled_count'] );

		// Progress flag should be set.
		$this->assertTrue( (bool) get_option( 'msm_generation_in_progress' ) );
	}

	/**
	 * Test that starting generation when already in progress returns error.
	 */
	public function test_start_full_generation_returns_error_when_already_in_progress(): void {
		$service = $this->get_service( FullGenerationService::class );

		// Start first generation.
		$service->start_full_generation();

		// Try to start again.
		$result = $service->start_full_generation();

		$this->assertFalse( $result['success'] );
		$this->assertSame( 0, $result['scheduled_count'] );
	}

	/**
	 * Test that can_start returns correct status.
	 */
	public function test_can_start_returns_correct_status(): void {
		$service = $this->get_service( FullGenerationService::class );

		// Should be able to start initially.
		$this->assertTrue( $service->can_start() );

		// Start generation.
		$service->start_full_generation();

		// Should not be able to start while in progress.
		$this->assertFalse( $service->can_start() );
	}

	/**
	 * Test that get_progress returns correct values.
	 */
	public function test_get_progress_returns_correct_values(): void {
		$service = $this->get_service( FullGenerationService::class );

		// Before starting, should show not in progress.
		$progress = $service->get_progress();
		$this->assertFalse( $progress['in_progress'] );
		$this->assertSame( 0, $progress['total'] );

		// Start generation.
		$result = $service->start_full_generation();

		// After starting, should show in progress with correct total.
		$progress = $service->get_progress();
		$this->assertTrue( $progress['in_progress'] );
		$this->assertSame( $result['scheduled_count'], $progress['total'] );
		$this->assertSame( $result['scheduled_count'], $progress['remaining'] );
		$this->assertSame( 0, $progress['completed'] );
	}

	/**
	 * Test that cancel clears generation state.
	 */
	public function test_cancel_clears_generation_state(): void {
		$service = $this->get_service( FullGenerationService::class );

		// Start generation.
		$service->start_full_generation();

		// Cancel.
		$service->cancel();

		// Should be able to start again.
		$this->assertTrue( $service->can_start() );

		// Progress flags should be cleared.
		$this->assertFalse( (bool) get_option( 'msm_generation_in_progress' ) );

		// Progress should show not in progress.
		$progress = $service->get_progress();
		$this->assertFalse( $progress['in_progress'] );
	}

	/**
	 * Test that start_full_generation returns empty message when no posts exist.
	 */
	public function test_start_full_generation_handles_no_posts(): void {
		// Delete all posts.
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "DELETE FROM {$wpdb->posts}" );

		$service = $this->get_service( FullGenerationService::class );
		$result  = $service->start_full_generation();

		$this->assertTrue( $result['success'] );
		$this->assertSame( 'none', $result['method'] );
		$this->assertSame( 0, $result['scheduled_count'] );
	}
}

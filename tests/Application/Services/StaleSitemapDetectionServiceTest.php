<?php
/**
 * Tests for Stale Sitemap Detection Service
 *
 * @package Automattic\MSM_Sitemap\Tests\Application\Services
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Application\Services;

use Automattic\MSM_Sitemap\Tests\TestCase;
use Automattic\MSM_Sitemap\Application\Services\StaleSitemapDetectionService;
use Automattic\MSM_Sitemap\Application\Services\MissingSitemapDetectionService;
use Automattic\MSM_Sitemap\Application\Services\SitemapService;

/**
 * Test class for Stale Sitemap Detection Service.
 */
class StaleSitemapDetectionServiceTest extends TestCase {

	/**
	 * Set up the test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		// Clear any existing last_run timestamp
		delete_option( 'msm_sitemap_update_last_run' );
	}

	/**
	 * Clean up after tests.
	 */
	public function tearDown(): void {
		delete_option( 'msm_sitemap_update_last_run' );
		parent::tearDown();
	}

	/**
	 * Test that stale detection returns empty when no last_run timestamp exists.
	 */
	public function test_get_dates_returns_empty_when_no_last_run(): void {
		$service = $this->get_service( StaleSitemapDetectionService::class );

		// Create a post and sitemap
		$this->create_dummy_post( '2024-01-15' );
		$this->build_sitemaps();

		// Without last_run, stale detection should return empty
		$dates = $service->get_dates();
		$this->assertEmpty( $dates );
	}

	/**
	 * Test that stale detection finds posts modified after last_run.
	 */
	public function test_get_dates_finds_stale_sitemaps(): void {
		$service = $this->get_service( StaleSitemapDetectionService::class );

		// Create a post and sitemap
		$post_id = $this->create_dummy_post( '2024-01-15' );
		$this->build_sitemaps();

		// Set last_run to 1 hour ago
		$last_run = time() - 3600;
		update_option( 'msm_sitemap_update_last_run', $last_run );

		// Simulate modifying the post after last_run
		global $wpdb;
		$now = gmdate( 'Y-m-d H:i:s' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$wpdb->posts,
			array( 'post_modified_gmt' => $now ),
			array( 'ID' => $post_id )
		);
		clean_post_cache( $post_id );

		// Stale detection should find this date
		$dates = $service->get_dates();
		$this->assertContains( '2024-01-15', $dates );
	}

	/**
	 * Test that newly published posts on dates with existing sitemaps are detected as stale.
	 *
	 * This is a key edge case: when a new post is published on a date that already
	 * has a sitemap, it should be detected as needing an update.
	 */
	public function test_newly_published_post_on_existing_sitemap_date_is_detected(): void {
		$service = $this->get_service( StaleSitemapDetectionService::class );

		// Create first post and generate sitemap
		$this->create_dummy_post( '2024-01-15' );
		$this->build_sitemaps();

		// Set last_run to simulate sitemap was generated 1 hour ago
		$last_run = time() - 3600;
		update_option( 'msm_sitemap_update_last_run', $last_run );

		// Now create another post on the same date (simulating a new publish)
		$post_id = $this->factory->post->create(
			array(
				'post_status' => 'publish',
				'post_type'   => 'post',
				'post_date'   => '2024-01-15 12:00:00',
			)
		);

		// Factory sets post_modified to match post_date, so we need to update it
		// to simulate a real "just published" scenario where modified_gmt is now
		global $wpdb;
		$now = gmdate( 'Y-m-d H:i:s' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$wpdb->posts,
			array( 'post_modified_gmt' => $now ),
			array( 'ID' => $post_id )
		);
		clean_post_cache( $post_id );

		// Stale detection should find this date
		$dates = $service->get_dates();
		$this->assertContains( '2024-01-15', $dates );
	}

	/**
	 * Test that MissingSitemapDetectionService includes stale dates in its summary.
	 */
	public function test_missing_detection_includes_stale_dates(): void {
		$service = $this->get_service( MissingSitemapDetectionService::class );

		// Create a post and sitemap
		$post_id = $this->create_dummy_post( '2024-01-15' );
		$this->build_sitemaps();

		// Set last_run to 1 hour ago
		$last_run = time() - 3600;
		update_option( 'msm_sitemap_update_last_run', $last_run );

		// Simulate modifying the post after last_run
		global $wpdb;
		$now = gmdate( 'Y-m-d H:i:s' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$wpdb->posts,
			array( 'post_modified_gmt' => $now ),
			array( 'ID' => $post_id )
		);
		clean_post_cache( $post_id );

		// Missing detection should include stale dates
		$data = $service->get_missing_sitemaps();
		$this->assertContains( '2024-01-15', $data['dates_needing_updates'] );
		$this->assertGreaterThan( 0, $data['all_dates_count'] );
	}

	/**
	 * Test that has_missing is true when there are stale sitemaps.
	 */
	public function test_summary_has_missing_when_stale(): void {
		$service = $this->get_service( MissingSitemapDetectionService::class );

		// Create a post and sitemap
		$post_id = $this->create_dummy_post( '2024-01-15' );
		$this->build_sitemaps();

		// Set last_run to 1 hour ago
		$last_run = time() - 3600;
		update_option( 'msm_sitemap_update_last_run', $last_run );

		// Simulate modifying the post after last_run
		global $wpdb;
		$now = gmdate( 'Y-m-d H:i:s' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$wpdb->posts,
			array( 'post_modified_gmt' => $now ),
			array( 'ID' => $post_id )
		);
		clean_post_cache( $post_id );

		// Summary should indicate missing/stale sitemaps
		$summary = $service->get_missing_content_summary();
		$this->assertTrue( $summary['has_missing'] );
	}

	/**
	 * Test that summary shows "All sitemaps up to date" when everything is current.
	 */
	public function test_summary_shows_up_to_date_when_current(): void {
		$service = $this->get_service( MissingSitemapDetectionService::class );

		// Create a post and sitemap
		$this->create_dummy_post( '2024-01-15' );
		$this->build_sitemaps();

		// Set last_run to now (after everything was generated)
		update_option( 'msm_sitemap_update_last_run', time() );

		// Summary should indicate all up to date
		$summary = $service->get_missing_content_summary();
		$this->assertFalse( $summary['has_missing'] );
		$this->assertStringContainsString( 'All sitemaps up to date', $summary['message'] );
	}

	/**
	 * Test that unpublishing a post (moving to draft) is detected as stale.
	 *
	 * When a post is moved from published to draft, the sitemap still contains
	 * its URL but the post is no longer published. This should be detected.
	 */
	public function test_unpublished_post_detected_as_stale(): void {
		$service = $this->get_service( StaleSitemapDetectionService::class );

		// Create a post and generate sitemap
		$post_id = $this->create_dummy_post( '2024-01-15' );
		$this->build_sitemaps();

		// Verify sitemap has URL count of 1
		$sitemap_id = $this->get_sitemap_post_id( 2024, 1, 15 );
		$this->assertEquals( 1, (int) get_post_meta( $sitemap_id, 'msm_indexed_url_count', true ) );

		// Set last_run to now (no stale posts by modification time)
		update_option( 'msm_sitemap_update_last_run', time() );

		// Move post to draft
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'draft',
			)
		);

		// Stale detection should find this date due to URL count mismatch
		$dates = $service->get_dates();
		$this->assertContains( '2024-01-15', $dates );
	}

	/**
	 * Test that trashing a post is detected as stale.
	 */
	public function test_trashed_post_detected_as_stale(): void {
		$service = $this->get_service( StaleSitemapDetectionService::class );

		// Create a post and generate sitemap
		$post_id = $this->create_dummy_post( '2024-01-15' );
		$this->build_sitemaps();

		// Set last_run to now
		update_option( 'msm_sitemap_update_last_run', time() );

		// Trash the post
		wp_trash_post( $post_id );

		// Stale detection should find this date
		$dates = $service->get_dates();
		$this->assertContains( '2024-01-15', $dates );
	}

	/**
	 * Test that deleting a post is detected as stale.
	 */
	public function test_deleted_post_detected_as_stale(): void {
		$service = $this->get_service( StaleSitemapDetectionService::class );

		// Create a post and generate sitemap
		$post_id = $this->create_dummy_post( '2024-01-15' );
		$this->build_sitemaps();

		// Set last_run to now
		update_option( 'msm_sitemap_update_last_run', time() );

		// Delete the post permanently
		wp_delete_post( $post_id, true );

		// Stale detection should find this date
		$dates = $service->get_dates();
		$this->assertContains( '2024-01-15', $dates );
	}

	/**
	 * Test that get_dates_with_count_mismatch returns empty when counts match.
	 */
	public function test_count_mismatch_returns_empty_when_counts_match(): void {
		$service = $this->get_service( StaleSitemapDetectionService::class );

		// Create a post and generate sitemap
		$this->create_dummy_post( '2024-01-15' );
		$this->build_sitemaps();

		// Counts should match - no mismatch
		$dates = $service->get_dates_with_count_mismatch();
		$this->assertEmpty( $dates );
	}

	/**
	 * Test that custom post types are detected as missing sitemaps and can be generated.
	 *
	 * This is a regression test for a bug where custom post types like 'articles'
	 * were detected as needing sitemaps, but the SitemapQueryService was hardcoded
	 * to only look for 'post' and 'page' post types when expanding date queries.
	 */
	public function test_custom_post_type_detected_and_generated(): void {
		// Register a custom post type for testing
		register_post_type(
			'article',
			array(
				'public'       => true,
				'has_archive'  => true,
				'rewrite'      => array( 'slug' => 'articles' ),
				'show_in_rest' => true,
			)
		);

		// Enable the custom post type in settings
		$settings_service = $this->get_service( \Automattic\MSM_Sitemap\Application\Services\SettingsService::class );
		$settings_service->update_setting( 'enabled_post_types', array( 'post', 'article' ) );

		// Re-initialize the DI container to pick up the new settings
		$this->container = \Automattic\MSM_Sitemap\Infrastructure\DI\msm_sitemap_container( true );

		// Create an article on a specific date
		$post_id = $this->create_dummy_post( '2024-01-23', 'publish', 'article' );

		// Get the missing sitemap detection service
		$missing_service = $this->get_service( MissingSitemapDetectionService::class );

		// Verify the date is detected as needing a sitemap
		$missing_data = $missing_service->get_missing_sitemaps();
		$this->assertContains( '2024-01-23', $missing_data['missing_dates'], 'Custom post type date should be detected as missing' );

		// Now try to generate the sitemap using the incremental generation service
		$incremental_service = $this->get_service( \Automattic\MSM_Sitemap\Application\Services\IncrementalGenerationService::class );
		$result              = $incremental_service->generate();

		// Verify generation succeeded
		$this->assertTrue( $result['success'], 'Generation should succeed' );
		$this->assertGreaterThan( 0, $result['generated_count'], 'At least one sitemap should be generated' );

		// Verify sitemap now exists for the date
		$sitemap_id = $this->get_sitemap_post_id( 2024, 1, 23 );
		$this->assertNotEmpty( $sitemap_id, 'Sitemap should now exist for the date' );

		// Clean up
		unregister_post_type( 'article' );
	}
}

<?php
/**
 * WP_Test_Sitemap_Stats
 *
 * @package Metro_Sitemap/unit_tests
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests;

/**
 * Unit Tests to validate stats are properly generated by Metro_Sitemap.
 */
class StatsTest extends TestCase {

	/**
	 * Number of Posts to Create (1 per year).
	 *
	 * @var int
	 */
	private int $num_years_data = 3;

	/**
	 * Generate posts and build initial sitemaps
	 */
	public function setUp(): void {
		parent::setUp();
		for ( $i = 0; $i < $this->num_years_data; $i++ ) {
			$this->add_a_post_for_a_day_x_years_ago( $i );
		}

		$this->assertPostCount( $this->num_years_data );
		$this->build_sitemaps();
	}

	/**
	 * Verify that indexed URL count is calculated correctly
	 */
	public function test_site_stats_creation(): void {
		// Check that we've indexed the proper total number of URLs.
		$this->assertIndexedUrlCount( $this->num_years_data );

		// Check specific stats.
		$this->assertStatsForCreatedPosts();
	}

	/**
	 * Checks that site stats are correct after inserting a new post on a day
	 * that already has a sitemap.
	 */
	public function test_site_stats_for_new_post(): void {
		$today_str = date( 'Y-m-d' );

		// Insert a new post for today.
		$this->create_dummy_posts( array( $today_str . ' 00:00:00' ) );

		// Build sitemaps.
		$this->build_sitemaps();

		// Check stats.
		$this->assertIndexedUrlCount( $this->num_years_data + 1 );

		// Check specific stats.
		$this->assertStatsForCreatedPosts();
	}

	/**
	 * Validate that Indexed URL Count is updated properly as posts are removed
	 */
	public function test_site_stats_for_deleted_post(): void {

		// Delete all posts (going backwards in time).
		$post_count = count( $this->posts );
		while ( $post_count ) {
			$last_post = array_pop( $this->posts );
			$post      = wp_delete_post( $last_post['ID'], true );
			--$post_count;

			if ( $post instanceof \WP_Post ) {
				$this->update_sitemap_by_post( $post );
				$this->assertIndexedUrlCount( $post_count );
				$this->assertStatsForCreatedPosts();
			}
		}

		$this->assertSitemapCount( 0 );
		$this->assertIndexedUrlCount( 0 );
	}
}

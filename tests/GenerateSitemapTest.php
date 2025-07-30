<?php
/**
 * Tests for Metro_Sitemap::generate_sitemap_for_date, delete_sitemap_for_date, and multi-day sitemap creation.
 *
 * @package Metro_Sitemap/unit_tests
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests;

use Metro_Sitemap;
use WP_Post;

/**
 * Tests for Metro_Sitemap::generate_sitemap_for_date, delete_sitemap_for_date, and multi-day sitemap creation.
 */
class GenerateSitemapTest extends TestCase {

	/**
	 * Test that generate_sitemap_for_date creates a sitemap post with the correct URL.
	 */
	public function test_generate_sitemap_for_date_with_posts_creates_sitemap_post(): void {
		$date = '2018-01-01';
		$this->create_dummy_post( $date . ' 00:00:00', 'publish' );
		Metro_Sitemap::generate_sitemap_for_date( $date );
		$sitemap_id = Metro_Sitemap::get_sitemap_post_id( 2018, 1, 1 );
		$this->assertNotFalse( $sitemap_id );
		$xml = get_post_meta( $sitemap_id, 'msm_sitemap_xml', true );
		$this->assertIsString( $xml );
		$this->assertStringContainsString( '<urlset', $xml );
		$count = get_post_meta( $sitemap_id, 'msm_indexed_url_count', true );
		$this->assertEquals( 1, (int) $count );
	}

	/**
	 * Test that generate_sitemap_for_date does not create a sitemap post if there are no posts.
	 */
	public function test_generate_sitemap_for_date_with_no_posts_does_not_create_sitemap_post(): void {
		$date = '2018-01-02';
		Metro_Sitemap::generate_sitemap_for_date( $date );
		$sitemap_id = Metro_Sitemap::get_sitemap_post_id( 2018, 1, 2 );
		$this->assertFalse( $sitemap_id );
	}

	/**
	 * Test that delete_sitemap_for_date removes the sitemap post and updates the count.
	 */
	public function test_delete_sitemap_for_date_removes_post_and_updates_count(): void {
		$date = '2018-01-03';
		$this->create_dummy_post( $date . ' 00:00:00', 'publish' );
		Metro_Sitemap::generate_sitemap_for_date( $date );
		$sitemap_id = Metro_Sitemap::get_sitemap_post_id( 2018, 1, 3 );
		$this->assertNotFalse( $sitemap_id );
		Metro_Sitemap::delete_sitemap_for_date( $date );
		$sitemap_id2 = Metro_Sitemap::get_sitemap_post_id( 2018, 1, 3 );
		$this->assertFalse( $sitemap_id2 );
	}

	/**
	 * Test that generate_sitemap_for_date, delete_sitemap_for_date, and regenerate_sitemap_for_date work as expected.
	 */
	public function test_generate_delete_regenerate_sitemap_for_date(): void {
		$date = '2018-01-04';
		$this->create_dummy_post( $date . ' 00:00:00', 'publish' );
		Metro_Sitemap::generate_sitemap_for_date( $date );
		$sitemap_id = Metro_Sitemap::get_sitemap_post_id( 2018, 1, 4 );
		$this->assertNotFalse( $sitemap_id );
		Metro_Sitemap::delete_sitemap_for_date( $date );
		$sitemap_id2 = Metro_Sitemap::get_sitemap_post_id( 2018, 1, 4 );
		$this->assertFalse( $sitemap_id2 );
		// Regenerate
		$this->create_dummy_post( $date . ' 00:00:00', 'publish' );
		Metro_Sitemap::generate_sitemap_for_date( $date );
		$sitemap_id3 = Metro_Sitemap::get_sitemap_post_id( 2018, 1, 4 );
		$this->assertNotFalse( $sitemap_id3 );
	}

	/**
	 * Test that generate_sitemap_for_date does not create a sitemap post if all posts are skipped by filter.
	 */
	public function test_generate_sitemap_for_date_all_posts_skipped_by_filter(): void {
		$date = '2018-01-05';
		$this->create_dummy_post( $date . ' 00:00:00', 'publish' );
		add_filter( 'msm_sitemap_skip_post', '__return_true', 10, 2 );
		Metro_Sitemap::generate_sitemap_for_date( $date );
		$sitemap_id = Metro_Sitemap::get_sitemap_post_id( 2018, 1, 5 );
		$this->assertFalse( $sitemap_id );
		remove_all_filters( 'msm_sitemap_skip_post' );
	}

	/**
	 * Test that generate_sitemap_for_date updates when posts change.
	 */
	public function test_generate_sitemap_for_date_updates_when_posts_change(): void {
		$date = '2018-01-06';
		$this->create_dummy_post( $date . ' 00:00:00', 'publish' );
		Metro_Sitemap::generate_sitemap_for_date( $date );
		$sitemap_id = Metro_Sitemap::get_sitemap_post_id( 2018, 1, 6 );
		$this->assertNotFalse( $sitemap_id );
		$count1 = get_post_meta( $sitemap_id, 'msm_indexed_url_count', true );
		$this->assertEquals( 1, (int) $count1 );
		// Add another post for the same date
		$this->create_dummy_post( $date . ' 12:00:00', 'publish' );
		Metro_Sitemap::generate_sitemap_for_date( $date );
		$sitemap_id2 = Metro_Sitemap::get_sitemap_post_id( 2018, 1, 6 );
		$this->assertNotFalse( $sitemap_id2 );
		$count2 = get_post_meta( $sitemap_id2, 'msm_indexed_url_count', true );
		$this->assertEquals( 2, (int) $count2 );
	}

	/**
	 * Multi-day bulk creation and XML structure/content validation.
	 */
	public function test_bulk_sitemap_posts_were_created_and_xml_is_valid(): void {
		$this->add_a_post_for_each_of_the_last_x_days_before_today( 4 );
		$this->assertPostCount( 4 );
		$this->build_sitemaps();

		$this->assertSitemapCount( 4 );
		
		$sitemaps = get_posts(
			array(
				'post_type'      => Metro_Sitemap::SITEMAP_CPT,
				'fields'         => 'ids',
				'posts_per_page' => -1,
			) 
		);

		$created_posts_ids = get_posts(
			array(
				'post_type'      => 'post',
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DEC',
			) 
		);

		foreach ( $sitemaps as $i => $map_id ) {
			$xml = get_post_meta( $map_id, 'msm_sitemap_xml', true );
			// Get the corresponding created post ID (assuming same order)
			$post_id = $created_posts_ids[ $i ];
			$this->assertStringContainsString( 'p=' . $post_id, $xml );

			$xml_struct = simplexml_load_string( $xml );
			$this->assertNotEmpty( $xml_struct->url );
			$this->assertNotEmpty( $xml_struct->url->loc );
			$this->assertNotEmpty( $xml_struct->url->lastmod );
			$this->assertNotEmpty( $xml_struct->url->changefreq );
			$this->assertNotEmpty( $xml_struct->url->priority );
			$this->assertStringContainsString( 'p=' . $post_id, (string) $xml_struct->url->loc );
		}
	}

	/**
	 * Validate that get_sitemap_post_id function returns the expected Sitemap.
	 */
	public function test_get_sitemap_post_id_returns_expected_sitemap(): void {
		$this->add_a_post_for_each_of_the_last_x_days_before_today( 4 );

		$this->assertPostCount( 4 );
		$this->build_sitemaps();

		$date          = strtotime( '-1 day' );
		$sitemap_year  = date( 'Y', $date );
		$sitemap_month = date( 'm', $date );
		$sitemap_day   = date( 'd', $date );
		$sitemap_ymd   = sprintf( '%s-%s-%s', $sitemap_year, $sitemap_month, $sitemap_day );

		$sitemap_post_id = Metro_Sitemap::get_sitemap_post_id( $sitemap_year, $sitemap_month, $sitemap_day );
		$sitemap_post    = get_post( $sitemap_post_id );

		$this->assertInstanceOf( WP_Post::class, $sitemap_post, 'get_sitemap_post_id returned non-WP_Post value' );
		$this->assertEquals( $sitemap_ymd, $sitemap_post->post_title );
	}

	/**
	 * Validate that Metro Sitemap CPTs are deleted when all posts are removed for a date.
	 */
	public function test_delete_empty_sitemap_removes_cpt_and_updates_count(): void {
		global $wpdb;

		$this->add_a_post_for_each_of_the_last_x_days_before_today( 4 );

		$this->assertPostCount( 4 );
		$this->build_sitemaps();

		list( $sitemap ) = get_posts(
			array(
				'post_type'      => Metro_Sitemap::SITEMAP_CPT,
				'posts_per_page' => 1,
			) 
		);

		$sitemap_date               = date( 'Y-m-d', strtotime( $sitemap->post_date ) );
		list( $year, $month, $day ) = explode( '-', $sitemap_date );
		$start_date                 = $sitemap_date . ' 00:00:00';
		$end_date                   = $sitemap_date . ' 23:59:59';
		$post_ids                   = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_status = 'publish' AND post_date >= %s AND post_date <= %s AND post_type = 'post' LIMIT 1", $start_date, $end_date ) );

		$expected_total_urls = Metro_Sitemap::get_total_indexed_url_count() - count( $post_ids );

		foreach ( $post_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}

		// Use the cron builder to trigger deletion logic
		\MSM_Sitemap_Builder_Cron::generate_sitemap_for_year_month_day(
			array(
				'year'  => $year,
				'month' => $month,
				'day'   => $day,
			) 
		);

		$this->assertEmpty( get_post( $sitemap->ID ), 'Sitemap with no posts was not deleted' );
		$this->assertEquals( $expected_total_urls, Metro_Sitemap::get_total_indexed_url_count(), 'Mismatch in total indexed URLs' );
	}

	/**
	 * Validate that sitemaps are deleted if all posts are skipped by filter.
	 */
	public function test_sitemap_deleted_if_all_posts_skipped(): void {
		// Create a post for today.
		$today = date( 'Y-m-d' ) . ' 00:00:00';
		$this->create_dummy_post( $today );

		// Build the sitemap for today (should create a sitemap post).
		$this->build_sitemaps();

		// Get the sitemap post ID for today.
		list( $year, $month, $day ) = explode( '-', date( 'Y-m-d' ) );
		$sitemap_id                 = Metro_Sitemap::get_sitemap_post_id( $year, $month, $day );
		$this->assertNotFalse( $sitemap_id, 'Sitemap post should exist before skipping.' );

		// Add a filter to skip all posts.
		add_filter( 'msm_sitemap_skip_post', '__return_true', 10, 2 );

		// Rebuild the sitemap for today (should delete the sitemap post).
		Metro_Sitemap::generate_sitemap_for_date( date( 'Y-m-d' ) );

		// The sitemap post should now be deleted.
		$sitemap_id_after = Metro_Sitemap::get_sitemap_post_id( $year, $month, $day );
		$this->assertFalse( $sitemap_id_after, 'Sitemap post should be deleted if all posts are skipped.' );

		// Clean up filter.
		remove_all_filters( 'msm_sitemap_skip_post' );
	}
} 

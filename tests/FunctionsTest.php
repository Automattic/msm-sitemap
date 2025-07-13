<?php
/**
 * WP_Test_Sitemap_Functions
 *
 * @package Metro_Sitemap/unit_tests
 */

namespace Automattic\MSM_Sitemap\Tests;

use Metro_Sitemap;

/**
 * Unit Tests to confirm Sitemaps are generated.
 *
 * @author Matthew Denton (mdbitz)
 */
class FunctionsTest extends TestCase {
	/**
	 * Custom post_status setup.
	 */
	public function custom_post_status_set_up(): void {
		register_post_status(
			$this->custom_post_status(),
			array(
				'public' => true,
			)
		);

		$this->add_test_filter( 'msm_sitemap_post_status', array( $this, 'custom_post_status' ) );
	}

	/**
	 * Custom post_status teardown.
	 */
	public function custom_post_status_tear_down(): void {
		remove_filter( 'msm_sitemap_post_status', array( $this, 'custom_post_status' ) );
	}

	/**
	 * Add post status to msm_sitemap.
	 *
	 * @return string Post status.
	 */
	public function custom_post_status(): string {
		return 'live';
	}

	/**
	 * Data provider providing map of recent variable and expected URL count.
	 *
	 * @return iterable<string, array<string, int|string>> Test parameters.
	 */
	public function recent_sitemap_url_count_data_provider(): iterable {
		yield '1 day' => array(
			'days' => 1,
			'URL count' => 2,
		);

		yield '7 days' => array(
			'days' => 7,
			'URL count' => 8,
		);
		
		yield '31 days' => array(
			'days' => 31,
			'URL count' => 11,
		);
	}

	/**
	 * Verify get_recent_sitemap_url_counts() returns correct count.
	 *
	 * @dataProvider recent_sitemap_url_count_data_provider
	 *
	 * @param int $days     Days.
	 * @param int $expected Expected URLs to be counted.
	 */
	public function test_get_recent_sitemap_url_counts( int $days, int $expected ): void {

		// Create Multiple Posts across various Dates.
		$date = time();
		
		// 3 for Today, 1 in "draft" status
		$cur_day = date( 'Y', $date ) . '-' . date( 'm', $date ) . '-' . date( 'd', $date ) . ' 00:00:00';
		$this->create_dummy_post( $cur_day );
		$this->create_dummy_post( $cur_day );
		$this->create_dummy_post( $cur_day, 'draft' );

		// 1  for each day in last week
		for ( $i = 0; $i < 6; $i++ ) {
			$date = strtotime( '-1 day', $date );
			$cur_day = date( 'Y', $date ) . '-' . date( 'm', $date ) . '-' . date( 'd', $date ) . ' 00:00:00';
			$this->create_dummy_post( $cur_day );
		}

		// 1 per week for previous 3 weeks
		for ( $i = 0; $i < 3; $i++ ) {
			$date = strtotime( '-7 day', $date );
			$cur_day = date( 'Y', $date ) . '-' . date( 'm', $date ) . '-' . date( 'd', $date ) . ' 00:00:00';
			$this->create_dummy_post( $cur_day );
		}
		$this->assertPostCount( 12 );
		$this->build_sitemaps();

		$stats     = Metro_Sitemap::get_recent_sitemap_url_counts( $days );
		$tot_count = array_sum( $stats );

		// Verify Stat returned for each day in $days.
		$this->assertCount( $days, $stats );
		// Verify total Stats are per post count.
		$this->assertEquals( $expected, $tot_count );
	}

	/**
	 * Data Provider for post year ranges
	 *
	 * @return iterable<string, array<string, int|string>> Array of Test parameters.
	 */
	public function post_year_range_data_provider(): iterable {
		yield 'no years' => array(
			'years' => 'none',
			'number_of_years_in_range' => 0,
		);

		yield 'zero years' => array(
			'years' => 0,
			'number_of_years_in_range' => 1,
		);
		
		yield 'one year' => array(
			'years' => 1,
			'number_of_years_in_range' => 2,
		);
		
		yield 'ten years' => array(
			'years' => 10,
			'number_of_years_in_range' => 11,
		);
	}

	/**
	 * Verify get_post_year_range() returns proper year ranges
	 *
	 * @dataProvider post_year_range_data_provider
	 *
	 * @param mixed $years                             Number of years or "none".
	 * @param int   $expected_number_of_years_in_range Expected number of years in range.
	 */
	public function test_get_post_year_range( $years, int $expected_number_of_years_in_range ): void {
		if ( 'none' !== $years ) {
			$this->add_a_post_for_a_day_x_years_ago( $years );
		}

		$year_range = Metro_Sitemap::get_post_year_range();
		$this->assertCount( $expected_number_of_years_in_range, $year_range, 'Expected ' . $expected_number_of_years_in_range . ' years in range, got ' . count( $year_range ) );
	}

	/**
	 * Verify get_post_year_range returns proper year ranges with custom status hook
	 *
	 * @dataProvider post_year_range_data_provider
	 *
	 * @param mixed $years                             Number of years of "none".
	 * @param int   $expected_number_of_years_in_range Expected number of years in range.
	 */
	public function test_get_post_year_range_with_custom_status_posts( $years, int $expected_number_of_years_in_range ): void {

		// set msm_sitemap_post_status filter to custom_status.
		$this->custom_post_status_set_up();

		if ( 'none' !== $years ) {
			$this->add_a_post_for_a_day_x_years_ago( $years, 'live' );
		}

		$year_range = Metro_Sitemap::get_post_year_range();
		// var_dump( $year_range );
		$this->assertCount( $expected_number_of_years_in_range, $year_range, 'Expected ' . $expected_number_of_years_in_range . ' years in range, got ' . count( $year_range ) );
	}

	/**
	 * Verify that get_post_year_range() calculates and caches the oldest post year.
	 */
	public function test_get_post_year_range_caches_oldest_post_year(): void {
		wp_cache_delete( 'oldest_post_date_year', 'msm_sitemap' );

		// Create posts in two different years.
		$this->create_dummy_post( '2010-01-01 00:00:00' );
		$this->create_dummy_post( '2015-01-01 00:00:00' );

		// Prime the cache by calling get_post_year_range.
		$years = Metro_Sitemap::get_post_year_range();
		$this->assertContains( 2010, $years );
		$this->assertContains( 2015, $years );

		// Now, delete all posts and check that the cached value is still used.
		_delete_all_posts();
		$cached_years = Metro_Sitemap::get_post_year_range();
		$this->assertEquals( $years, $cached_years );
	}

	/**
	 * Verify check_year_has_posts returns only years with posts
	 */
	public function test_check_year_has_posts(): void {
		$prev_year = (int) date( 'Y', strtotime('-1 year') );
		$this->add_a_post_for_a_day_x_years_ago( 1 );

		$prev5_year = (int) date( 'Y', strtotime('-5 year') );
		$this->add_a_post_for_a_day_x_years_ago( 5 );

		// Verify only Years for Posts are returned.
		$range_with_posts = Metro_Sitemap::check_year_has_posts();
		$this->assertContains( $prev_year, $range_with_posts );
		$this->assertContains( $prev5_year, $range_with_posts );
		$this->assertCount( 2, $range_with_posts );
	}

	/**
	 * Data Provider for get_date_stamp
	 *
	 * @return iterable<string, array<string, int|string>> Array of Test parameters.
	 */
	public function date_stamp_data_provider(): iterable {

		yield 'old date' => array(
			'year' => 1985,
			'month' => 11,
			'day' => 5,
			'expected_string' => '1985-11-05',
		);

		yield 'sensible date' => array(
			'year' => 2025,
			'month' => 7,
			'day' => 12,
			'expected_string' => '2025-07-12',
		);

		yield 'future date' => array(
			'year' => 2100,
			'month' => 10,
			'day' => 12,
			'expected_string' => '2100-10-12',
		);

		yield 'invalid date' => array(
			'year' => 0,
			'month' => 10,
			'day' => 12,
			'expected_string' => '0-10-12',
		);
	}

	/**
	 * Verify get_date_stamp returns proper formatted date string
	 *
	 * @dataProvider date_stamp_data_provider
	 *
	 * @param int    $year            Year.
	 * @param int    $month           Month.
	 * @param int    $day             Day.
	 * @param string $expected_string Expected DateStamp.
	 */
	public function test_get_date_stamp( int $year, int $month, int $day, string $expected_string ): void {
		$this->assertEquals( $expected_string, Metro_Sitemap::get_date_stamp( $year, $month, $day ) );
	}


	/**
	 * Data Provider for test_date_range_has_posts
	 *
	 * @return iterable<string, array<string, int|string>> Array of Test parameters.
	 */
	public function date_range_has_posts_data_provider(): iterable {
		yield 'no posts' => array(
			'start_date' => '2016-11-01',
			'end_date' => '2016-12-15',
			'has_post' => false,
		);

		yield 'no published posts' => array(
			'start_date' => '2016-10-01',
			'end_date' => '2016-10-15',
			'has_post' => false,
		);
		
		yield 'one published post on exact date' => array(
			'start_date' => '2016-01-01',
			'end_date' => '2016-01-01',
			'has_post' => true,
		);

		yield 'one published post at start of range' => array(
			'start_date' => '2016-01-01',
			'end_date' => '2016-01-10',
			'has_post' => true,
		);

		yield 'one published post at end of range' => array(
			'start_date' => '2015-12-28',
			'end_date' => '2016-01-01',
			'has_post' => true,
		);

		yield 'two published posts in range' => array(
			'start_date' => '2014-12-28',
			'end_date' => '2016-05-04',
			'has_post' => true,
		);
	}

	/**
	 * Verify date_range_has_posts returns expected value
	 *
	 * @dataProvider date_range_has_posts_data_provider
	 *
	 * @param string $start_date Start Date of Range in Y-M-D format.
	 * @param string $end_date  End Date of Range in Y-M-D format.
	 * @param boolean $has_post Does Range have Post.
	 */
	public function test_check_date_range_has_posts( string $start_date, string $end_date, bool $has_post ): void {

		// 1 for 2016-10-12 in "draft" status.
		$this->create_dummy_post( '2016-10-12 00:00:00', 'draft' );

		// 1 for 2016-01-01.
		$this->create_dummy_post( '2016-01-01 00:00:00' );

		// 1 for 2015-06-02.
		$this->create_dummy_post( '2015-06-02 00:00:00' );

		// Validate Range result.
		if ( $has_post ) {
			$this->assertNotNull( Metro_Sitemap::date_range_has_posts( $start_date, $end_date ) );
		} else {
			$this->assertNull( Metro_Sitemap::date_range_has_posts( $start_date, $end_date ) );
		}

	}

	/**
	 * Data Provider for test_date_range_has_posts_custom_status
	 *
	 * @return iterable<string, array<string, int|string>> Array of Test parameters.
	 */
	public function date_range_has_posts_custom_status_data_provider(): iterable {

		yield 'no live status posts' => array(
			'start_date' => '2015-12-01',
			'end_date' => '2016-12-15',
			'has_post' => false,
		);

		yield 'one live status post' => array(
			'start_date' => '2014-12-28',
			'end_date' => '2016-05-04',
			'has_post' => true,
		);
	}

	/**
	 * Verify date_range_has_posts returns expected value with custom status hook
	 *
	 * @dataProvider date_range_has_posts_custom_status_data_provider
	 *
	 * @param string $start_date Start Date of Range in Y-M-D format.
	 * @param string $end_date   End Date of Range in Y-M-D format.
	 * @param boolean $has_post   Does Range have Post.
	 */
	public function test_check_date_range_has_posts_custom_status( string $start_date, string $end_date, bool $has_post ): void {
		// set msm_sitemap_post_status filter to custom_status.
		$this->custom_post_status_set_up();

		// 1 for 2016-10-12 in "live" status.
		$this->create_dummy_post( '2015-10-12 00:00:00', 'live' );

		// 1 for 2016-01-01.
		$this->create_dummy_post( '2016-01-01 00:00:00' );

		// // 1 for 2015-06-02.
		$this->create_dummy_post( '2015-06-02 00:00:00' );

		// Validate Range result.
		if ( $has_post ) {
			$this->assertNotNull( Metro_Sitemap::date_range_has_posts( $start_date, $end_date ) );
		} else {
			$this->assertNull( Metro_Sitemap::date_range_has_posts( $start_date, $end_date ) );
		}
	}


	/**
	 * Data Provider for get_post_ids_for_date
	 *
	 * @return iterable<string, array<string, int|string>> Array of Test parameters.
	 */
	public function post_ids_for_date_data_provider(): iterable {
		yield 'no posts' => array(
			'sitemap_date' => '2016-10-01',
			'limit' => 500,
			'expected_count' => 0,
		);

		yield 'multiple posts for date' => array(
			'sitemap_date' => '2016-10-02',
			'limit' => 500,
			'expected_count' => 20,
		);

		yield 'multiple posts for date, but get limited number' => array(
			'sitemap_date' => '2016-10-02',
			'limit' => 10,
			'expected_count' => 10,
		);

		yield 'no published posts' => array(
			'sitemap_date' => '2016-10-03',
			'limit' => 500,
			'expected_count' => 0,
		);
	}

	/**
	 * Verify get_post_ids_for_date() returns expected value
	 *
	 * @dataProvider post_ids_for_date_data_provider
	 *
	 * @param string $sitemap_date Date in Y-M-D format.
	 * @param int $limit max number of posts to return.
	 * @param int $expected_count Number of posts expected to be returned.
	 */
	public function test_get_post_ids_for_date( string $sitemap_date, int $limit, int $expected_count ): void {
		// 1 for 2016-10-03 in "draft" status.
		$this->create_dummy_post( '2016-10-01 00:00:00', 'draft' );

		$created_post_ids = array();
		// 20 for 2016-10-02.
		for ( $i = 0; $i < 20; $i ++ ) {
			$hour = $i < 10 ? '0' . $i : $i;
			if ( '2016-10-02' === $sitemap_date ) {
				$created_post_ids[] = $this->create_dummy_post( '2016-10-02 ' . $hour . ':00:00' );
			}
		}

		$post_ids = Metro_Sitemap::get_post_ids_for_date( $sitemap_date, $limit );
		$this->assertCount($expected_count, $post_ids);
		$this->assertEquals( array_slice( $created_post_ids, 0, $limit ), $post_ids );
	}

	/**
	 * Verify get_post_ids_for_date returns expected value with custom status hook
	 *
	 * @dataProvider post_ids_for_date_data_provider
	 *
	 * @param string $sitemap_date   Date in Y-M-D format.
	 * @param int $limit          Max number of posts to return.
	 * @param int $expected_count Number of posts expected to be returned.
	 */
	public function test_get_post_ids_for_date_custom_status( string $sitemap_date, int $limit, int $expected_count ): void {

		// set msm_sitemap_post_status filter to custom_status.
		$this->custom_post_status_set_up();

		// 1 for 2016-10-03 in "draft" status.
		$this->create_dummy_post( '2016-10-01 00:00:00', 'draft' );

		$created_post_ids = array();
		// 20 for 2016-10-02.
		for ( $i = 0; $i < 20; $i ++ ) {
			$hour = $i < 10 ? '0' . $i : $i;
			if ( '2016-10-02' === $sitemap_date ) {
				$created_post_ids[] = $this->create_dummy_post( '2016-10-02 ' . $hour . ':00:00', 'live' );
			}
		}


		$post_ids = Metro_Sitemap::get_post_ids_for_date( $sitemap_date, $limit );
		$this->assertCount($expected_count, $post_ids);
		$this->assertEquals( array_slice( $created_post_ids, 0, $limit ), $post_ids );
	}

	/**
	 * Verify msm_sitemap_post_status filter returns expected value
	 */
	public function test_get_post_status(): void {

		// set msm_sitemap_post_status filter to custom_status.
		$this->custom_post_status_set_up();

		$this->assertEquals( 'live', Metro_Sitemap::get_post_status() );

		$this->add_test_filter( 'msm_sitemap_post_status', function() {
			return 'bad_status';
		} );
		$this->assertEquals( 'publish', Metro_Sitemap::get_post_status() );
	}

	public function test_get_last_modified_posts_filter_no_change(): void {
		$posts_before = Metro_Sitemap::get_last_modified_posts();
		$tag          = 'msm_pre_get_last_modified_posts';

		// Test no changes to query.
		$function = function ( $query ) {
			return $query;
		};
		add_filter( $tag, $function, 10, 3 );
		$posts_after = Metro_Sitemap::get_last_modified_posts();
		remove_filter( $tag, $function );

		$this->assertCount(count($posts_before), $posts_after);
	}

	public function test_get_last_modified_posts_filter_change_query(): void {
		// Create 6 posts modified within the last 3 months
		for ($i = 0; $i < 6; $i++) {
			$date = date('Y-m-d H:i:s', strtotime("-" . ($i * 10) . " days"));
			$post_id = $this->create_dummy_post($date);
			// Set post_modified_gmt to the same as post_date for simplicity
			wp_update_post([
				'ID' => $post_id,
				'post_modified_gmt' => get_gmt_from_date($date),
			]);
		}
		// Create 6 posts modified more than 3 months ago
		for ($i = 0; $i < 6; $i++) {
			$date = date('Y-m-d H:i:s', strtotime("-" . (100 + $i * 10) . " days"));
			$post_id = $this->create_dummy_post($date);
			wp_update_post([
				'ID' => $post_id,
				'post_modified_gmt' => get_gmt_from_date($date),
			]);
		}

		$posts_before = Metro_Sitemap::get_last_modified_posts();
		$tag          = 'msm_pre_get_last_modified_posts';

		// Modify query to fetch posts created in the last 3 months.
		$function = static function ($query, $post_types_in, $date ) {
			global $wpdb;
			return $wpdb->prepare( "SELECT ID, post_date FROM $wpdb->posts WHERE post_type IN ( {$post_types_in} ) AND post_date >= DATE_SUB(NOW(), INTERVAL 3 MONTH) AND post_modified_gmt >= %s LIMIT 1000", $date );
		};

		add_filter( $tag, $function, 10, 3 );
		$posts_after_date = Metro_Sitemap::get_last_modified_posts();
		remove_filter( $tag, $function );

		// Modify query as string to fetch only 10 posts.
		$limit    = 10;
		$function = static function ($query ) use ( $limit ) {
			return str_replace( 'LIMIT 1000', "LIMIT $limit", $query );
		};

		add_filter( $tag, $function );
		$posts_after = Metro_Sitemap::get_last_modified_posts();
		remove_filter( $tag, $function );

		$this->assertLessThan( count( $posts_before ), count( $posts_after_date ) );
		$this->assertEquals( count( $posts_after ), $limit );
	}
}

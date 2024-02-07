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
	 * Remove the sample posts and the sitemap posts
	 */
	public function teardown(): void {
		$this->posts = array();
		$sitemaps = get_posts( array(
			'post_type' => Metro_Sitemap::SITEMAP_CPT,
			'fields' => 'ids',
			'posts_per_page' => -1,
		) );
		update_option( 'msm_sitemap_indexed_url_count' , 0 );
		array_map( 'wp_delete_post', array_merge( $this->posts_created, $sitemaps ) );
	}

	/**
     * custom post_status setup
     */
    public function customPostStatusSetUp(): void
	{
        // register new post status.
		register_post_status( 'live', array(
			'public'                    => true,
		) );

		// add filter to return custom post status.
		add_filter( 'msm_sitemap_post_status', array( $this, 'add_post_status_to_msm_sitemap' ) );

    }

	/**
	 * custom post_status teardown
	 */
	public function customPostStatusTearDown(): void
	{
		remove_filter( 'msm_sitemap_post_status', array( $this, 'add_post_status_to_msm_sitemap' ) );
	}

	/**
	 * Data provider providing map of recent variable and expected URL count.
	 *
	 * @return array<array<int,int>> Array of Test parameters.
	 */
	public function recentSitemapURLCountDataProvider(): array
	{
		return array(
		    array( 1,2 ),
		    array( 7,8 ),
		    array( 31,11 ),
		);
	}

	/**
	 * Verify get_recent_sitemap_url_counts returns correct count
	 *
	 * @dataProvider recentSitemapURLCountDataProvider
	 *
	 * @param int $n Days.
	 * @param int  $expected_count Expected Urls to be counted.
	 */
	public function test_get_recent_sitemap_url_counts( int $n, int $expected_count ): void
	{

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
		$this->assertCount( 12, $this->posts );
		$this->build_sitemaps();

		$stats = Metro_Sitemap::get_recent_sitemap_url_counts( $n );
		$tot_count = array_sum( $stats );

		// Verify Stat returned for each day in n.
		$this->assertCount($n, $stats);
		// Verify total Stats are per post count.
		$this->assertEquals( $expected_count, $tot_count );
	}

	/**
	 * Data Provider for post year ranges
	 *
	 * @return array<array<int, mixed>> Array of Test parameters.
	 */
	public function postYearRangeDataProvider(): array
	{
		return array(
		    array( 'none', 0 ),
		    array( 0, 1 ),
		    array( 1, 2 ),
		    array( 10, 11 ),
		);
	}

	/**
	 * Verify get_post_year_range returns proper year ranges
	 *
	 * @dataProvider postYearRangeDataProvider
	 *
	 * @param mixed $years Number of Years or "none".
	 * @param int $range_values # of years in range.
	 */
	public function test_get_post_year_range( $years, int $range_values ): void
	{
		// Add a post for each day in the last x years.
		if ( 'none' !== $years ) {
			$date = strtotime("-$years year");
			$cur_day = date( 'Y', $date ) . '-' . date( 'm', $date ) . '-' . date( 'd', $date ) . ' 00:00:00';
			$this->create_dummy_post( $cur_day );
		}

		$year_range = Metro_Sitemap::get_post_year_range();
		$this->assertCount($range_values, $year_range);
	}

	/**
	 * Verify get_post_year_range returns proper year ranges with custom status hook
	 *
	 * @dataProvider postYearRangeDataProvider
	 *
	 * @param mixed $years Number of years of "none".
	 * @param int $range_values Number of years in range.
	 */
	public function test_get_post_year_range_custom_status_posts( $years, int $range_values ): void
	{

		// set msm_sitemap_post_status filter to custom_status.
		$this->customPostStatusSetUp();

		// Add a post for each day in the last x years.
		if ( 'none' !== $years ) {
			$date = strtotime("-$years year");
			$cur_day = date( 'Y', $date ) . '-' . date( 'm', $date ) . '-' . date( 'd', $date ) . ' 00:00:00';
			$this->create_dummy_post( $cur_day, 'live' );
		}

		$year_range = Metro_Sitemap::get_post_year_range();
		$this->assertCount($range_values, $year_range);

		// remove filter.
		$this->customPostStatusTearDown();
	}

	/**
	 * Verify check_year_has_posts returns only years with posts
	 */
	public function test_check_year_has_posts(): void
	{
		// Add a post for last year and 5 years ago.
		$date = strtotime('-1 year');
		$cur_day = date( 'Y', $date ) . '-' . date( 'm', $date ) . '-' . date( 'd', $date ) . ' 00:00:00';
		$prev_year = (int) date( 'Y', $date );
		$this->create_dummy_post( $cur_day );

		$date = strtotime( '-4 year', $date );
		$cur_day = date( 'Y', $date ) . '-' . date( 'm', $date ) . '-' . date( 'd', $date ) . ' 00:00:00';
		$prev5_year = (int) date( 'Y', $date );
		$this->create_dummy_post( $cur_day );

		// Verify only Years for Posts are returned.
		$range_with_posts = Metro_Sitemap::check_year_has_posts();
		$this->assertContains( $prev_year, $range_with_posts );
		$this->assertContains( $prev5_year, $range_with_posts );
		$this->assertCount(2, $range_with_posts);

	}

	/**
	 * Data Provider for get_date_stamp
	 *
	 * @return array<array<int, mixed>> Array of Test parameters.
	 */
	public function dateStampDataProvider(): array
	{
		return array(
		    array( 2016, 1, 7, '2016-01-07' ),
		    array( 2010, 8, 22, '2010-08-22' ),
		    array( 1985, 11, 5, '1985-11-05' ),
		    array( 100, 10, 12, '100-10-12' ),
		);
	}

	/**
	 * Verify get_date_stamp returns proper formatted date string
	 *
	 * @dataProvider dateStampDataProvider
	 *
	 * @param int $year Year.
	 * @param int $month Month.
	 * @param int $day Day.
	 * @param string $expected_string Expected DateStamp.
	 */
	public function test_get_date_stamp( int $year, int $month, int $day, string $expected_string ): void
	{
		$this->assertEquals( $expected_string, Metro_Sitemap::get_date_stamp( $year, $month, $day ) );
	}


	/**
	 * Data Provider for date_range_has_posts
	 *
	 * @return array<array<int, mixed>> Array of Test parameters.
	 */
	public function dateRangeHasPostsDataProvider(): array
	{
		return array(
		    array( '2016-11-01', '2016-12-15', false ),
		    array( '2016-10-01', '2016-10-15', false ),
		    array( '2016-01-01', '2016-01-01', true ),
		    array( '2016-01-01', '2016-01-10', true ),
		    array( '2015-12-28', '2016-01-01', true ),
		    array( '2014-12-28', '2016-05-04', true ),
		);
	}

	/**
	 * Data Provider for date_range_has_posts
	 *
	 * @return array<array<int, mixed>> Array of Test parameters.
	 */
	public function dateRangeHasPostsCustomStatusDataProvider(): array
	{
		return array(
		    array( '2016-11-01', '2016-12-15', false ),
		    array( '2014-12-28', '2016-05-04', true ),
		);
	}

	/**
	 * Verify date_range_has_posts returns expected value
	 *
	 * @dataProvider dateRangeHasPostsDataProvider
	 *
	 * @param string $start_date Start Date of Range in Y-M-D format.
	 * @param string $end_date  End Date of Range in Y-M-D format.
	 * @param boolean $has_post Does Range have Post.
	 */
	public function test_date_range_has_posts( string $start_date, string $end_date, bool $has_post ): void
	{

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
	 * Verify date_range_has_posts returns expected value with custom status hook
	 *
	 * @dataProvider dateRangeHasPostsCustomStatusDataProvider
	 *
	 * @param string $start_date Start Date of Range in Y-M-D format.
	 * @param string $end_date   End Date of Range in Y-M-D format.
	 * @param boolean $has_post   Does Range have Post.
	 */
	public function test_date_range_has_posts_custom_status( string $start_date, string $end_date, bool $has_post ): void
	{
		// set msm_sitemap_post_status filter to custom_status.
		$this->customPostStatusSetUp();

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

		$this->customPostStatusTearDown();

	}


	/**
	 * Data Provider for get_post_ids_for_date
	 *
	 * @return array<array<int, mixed>> Array of Test parameters.
	 */
	public function postIdsForDateDataProvider(): array
	{
		return array(
		    array( '2016-10-01', 500, 0 ),
		    array( '2016-10-02', 500, 20 ),
		    array( '2016-10-02', 10, 10 ),
		    array( '2016-10-03', 500, 0 ),
		);
	}

	/**
	 * Verify get_post_ids_for_date returns expected value
	 *
	 * @dataProvider postIdsForDateDataProvider
	 *
	 * @param string $sitemap_date Date in Y-M-D format.
	 * @param int $limit max number of posts to return.
	 * @param int $expected_count Number of posts expected to be returned.
	 */
	public function test_get_post_ids_for_date( string $sitemap_date, int $limit, int $expected_count ): void
	{

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
	 * @dataProvider postIdsForDateDataProvider
	 *
	 * @param string $sitemap_date   Date in Y-M-D format.
	 * @param int $limit          Max number of posts to return.
	 * @param int $expected_count Number of posts expected to be returned.
	 */
	public function test_get_post_ids_for_date_custom_status( string $sitemap_date, int $limit, int $expected_count ): void
	{

		// set msm_sitemap_post_status filter to custom_status.
		$this->customPostStatusSetUp();

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

		$this->customPostStatusTearDown();
	}

	/**
	 * Verify msm_sitemap_post_status filter returns expected value
	 */
	public function test_get_post_status(): void
	{

		// set msm_sitemap_post_status filter to custom_status.
		$this->customPostStatusSetUp();

		$this->assertEquals( 'live', Metro_Sitemap::get_post_status() );

		add_filter( 'msm_sitemap_post_status', function() {
			return 'bad_status';
		} );
		$this->assertEquals( 'publish', Metro_Sitemap::get_post_status() );

		// remove filter.
		remove_filter( 'msm_sitemap_post_status', static function() {
			return 'bad_status';
		} );

		$this->customPostStatusTearDown();

	}

	 public function add_post_status_to_msm_sitemap(): string
	 {
		return 'live';
	}

	public function test_get_last_modified_posts_filter_no_change(): void
	{
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

	public function test_get_last_modified_posts_filter_change_query(): void
	{
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

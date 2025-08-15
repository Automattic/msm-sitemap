<?php
/**
 * Tests for PostRepository
 *
 * @package Automattic\MSM_Sitemap\Tests
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests;

use Automattic\MSM_Sitemap\Infrastructure\Repositories\PostRepository;
use Automattic\MSM_Sitemap\Tests\Includes\CustomPostStatusTestTrait;

/**
 * Tests for PostRepository functionality.
 */
class PostRepositoryTest extends TestCase {

	use CustomPostStatusTestTrait;

	/**
	 * The post repository instance.
	 *
	 * @var PostRepository
	 */
	private PostRepository $repository;

	/**
	 * Set up the test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->repository = new PostRepository();
	}

	/**
	 * Tear down test state.
	 */
	public function tearDown(): void {
		// Ensure any custom status filter is removed between tests
		if ( method_exists( $this, 'custom_post_status_tear_down' ) ) {
			$this->custom_post_status_tear_down();
		}
		parent::tearDown();
	}

	/**
	 * Test get_post_status returns default status.
	 */
	public function test_get_post_status_default(): void {
		$this->assertEquals( 'publish', $this->repository->get_post_status() );
	}

	/**
	 * Test get_post_status with custom status filter.
	 */
	public function test_get_post_status_with_custom_filter(): void {
		$this->custom_post_status_set_up();
		$this->assertEquals( 'live', $this->repository->get_post_status() );
	}

	/**
	 * Test get_post_status with invalid status falls back to default.
	 */
	public function test_get_post_status_with_invalid_status_fallback(): void {
		$this->add_test_filter(
			'msm_sitemap_post_status',
			function () {
				return 'invalid_status';
			}
		);
		$this->assertEquals( 'publish', $this->repository->get_post_status() );
	}

	/**
	 * Test get_supported_post_types returns default types.
	 */
	public function test_get_supported_post_types_default(): void {
		$this->assertEquals( array( 'post' ), $this->repository->get_supported_post_types() );
	}

	/**
	 * Test get_supported_post_types with custom filter.
	 */
	public function test_get_supported_post_types_with_filter(): void {
		$this->add_test_filter(
			'msm_sitemap_entry_post_type',
			function () {
				return array( 'post', 'page', 'custom' );
			}
		);
		$this->assertEquals( array( 'post', 'page', 'custom' ), $this->repository->get_supported_post_types() );
	}

	/**
	 * Test get_supported_post_types_in returns properly formatted SQL.
	 */
	public function test_get_supported_post_types_in(): void {
		$result = $this->repository->get_supported_post_types_in();
		$this->assertStringContainsString( "'post'", $result );
	}

	/**
	 * Data provider for post year ranges.
	 *
	 * @return iterable<string, array<string, int|string>> Array of test parameters.
	 */
	public function post_year_range_data_provider(): iterable {
		yield 'no years' => array(
			'years'                    => 'none',
			'number_of_years_in_range' => 0,
		);

		yield 'zero years' => array(
			'years'                    => 0,
			'number_of_years_in_range' => 1,
		);
		
		yield 'one year' => array(
			'years'                    => 1,
			'number_of_years_in_range' => 2,
		);
		
		yield 'ten years' => array(
			'years'                    => 10,
			'number_of_years_in_range' => 11,
		);
	}

	/**
	 * Test get_post_year_range returns proper year ranges.
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

		$year_range = $this->repository->get_post_year_range();
		$this->assertCount( $expected_number_of_years_in_range, $year_range );
	}

	/**
	 * Test get_post_year_range with custom status posts.
	 *
	 * @dataProvider post_year_range_data_provider
	 *
	 * @param mixed $years                             Number of years of "none".
	 * @param int   $expected_number_of_years_in_range Expected number of years in range.
	 */
	public function test_get_post_year_range_with_custom_status_posts( $years, int $expected_number_of_years_in_range ): void {
		$this->custom_post_status_set_up();

		if ( 'none' !== $years ) {
			$this->add_a_post_for_a_day_x_years_ago( $years, 'live' );
		}

		$year_range = $this->repository->get_post_year_range();
		$this->assertCount( $expected_number_of_years_in_range, $year_range );
	}

	/**
	 * Test get_post_year_range caches the oldest post year.
	 */
	public function test_get_post_year_range_caches_oldest_post_year(): void {
		wp_cache_delete( 'oldest_post_date_year', 'msm_sitemap' );

		// Create posts in two different years.
		$this->create_dummy_post( '2010-01-01 00:00:00' );
		$this->create_dummy_post( '2015-01-01 00:00:00' );

		// Prime the cache by calling get_post_year_range.
		$years = $this->repository->get_post_year_range();
		$this->assertContains( 2010, $years );
		$this->assertContains( 2015, $years );

		// Now, delete all posts and check that the cached value is still used.
		\_delete_all_posts();
		$cached_years = $this->repository->get_post_year_range();
		$this->assertEquals( $years, $cached_years );
	}

	/**
	 * Test get_years_with_posts returns only years with posts.
	 */
	public function test_get_years_with_posts(): void {
		$prev_year = (int) date( 'Y', strtotime( '-1 year' ) );
		$this->add_a_post_for_a_day_x_years_ago( 1 );

		$prev5_year = (int) date( 'Y', strtotime( '-5 year' ) );
		$this->add_a_post_for_a_day_x_years_ago( 5 );

		// Verify only years for posts are returned.
		$range_with_posts = $this->repository->get_years_with_posts();
		$this->assertContains( $prev_year, $range_with_posts );
		$this->assertContains( $prev5_year, $range_with_posts );
		$this->assertCount( 2, $range_with_posts );
	}

	/**
	 * Data Provider for date_range_has_posts tests.
	 *
	 * @return iterable<string, array<string, int|string>> Array of test parameters.
	 */
	public function date_range_has_posts_data_provider(): iterable {
		yield 'no posts' => array(
			'start_date' => '2016-11-01',
			'end_date'   => '2016-12-15',
			'has_post'   => false,
		);

		yield 'no published posts' => array(
			'start_date' => '2016-10-01',
			'end_date'   => '2016-10-15',
			'has_post'   => false,
		);
		
		yield 'one published post on exact date' => array(
			'start_date' => '2016-01-01',
			'end_date'   => '2016-01-01',
			'has_post'   => true,
		);

		yield 'one published post at start of range' => array(
			'start_date' => '2016-01-01',
			'end_date'   => '2016-01-10',
			'has_post'   => true,
		);

		yield 'one published post at end of range' => array(
			'start_date' => '2015-12-28',
			'end_date'   => '2016-01-01',
			'has_post'   => true,
		);

		yield 'two published posts in range' => array(
			'start_date' => '2014-12-28',
			'end_date'   => '2016-05-04',
			'has_post'   => true,
		);
	}

	/**
	 * Test date_range_has_posts returns expected value.
	 *
	 * @dataProvider date_range_has_posts_data_provider
	 *
	 * @param string $start_date Start date of range in Y-M-D format.
	 * @param string $end_date   End date of range in Y-M-D format.
	 * @param bool   $has_post   Does range have post.
	 */
	public function test_date_range_has_posts( string $start_date, string $end_date, bool $has_post ): void {
		// 1 post for 2016-10-12 in "draft" status.
		$this->create_dummy_post( '2016-10-12 00:00:00', 'draft' );

		// 1 post for 2016-01-01.
		$this->create_dummy_post( '2016-01-01 00:00:00' );

		// 1 post for 2015-06-02.
		$this->create_dummy_post( '2015-06-02 00:00:00' );

		// Validate range result.
		if ( $has_post ) {
			$this->assertNotNull( $this->repository->date_range_has_posts( $start_date, $end_date ) );
		} else {
			$this->assertNull( $this->repository->date_range_has_posts( $start_date, $end_date ) );
		}
	}

	/**
	 * Test date_range_has_posts with custom status.
	 *
	 * @dataProvider date_range_has_posts_data_provider
	 *
	 * @param string $start_date Start date of range in Y-M-D format.
	 * @param string $end_date   End date of range in Y-M-D format.
	 * @param bool   $has_post   Does range have post.
	 */
	public function test_date_range_has_posts_custom_status( string $start_date, string $end_date, bool $has_post ): void {
		// Set msm_sitemap_post_status filter to custom_status.
		$this->custom_post_status_set_up();

		// 1 post for 2015-10-12 in "live" status.
		$this->create_dummy_post( '2015-10-12 00:00:00', 'live' );

		// 1 post for 2016-01-01 in "live" status (to match custom status filter).
		$this->create_dummy_post( '2016-01-01 00:00:00', 'live' );

		// 1 post for 2015-06-02 in "live" status.
		$this->create_dummy_post( '2015-06-02 00:00:00', 'live' );

		// Validate range result.
		if ( $has_post ) {
			$this->assertNotNull( $this->repository->date_range_has_posts( $start_date, $end_date ) );
		} else {
			$this->assertNull( $this->repository->date_range_has_posts( $start_date, $end_date ) );
		}
	}

	/**
	 * Test date_range_has_posts excludes unsupported post types.
	 */
	public function test_date_range_has_posts_excludes_unsupported_post_types(): void {
		register_post_type( 'not_included', array( 'public' => true ) );
		$this->create_dummy_post( '2017-01-01 00:00:00', 'publish', 'not_included' );
		$this->create_dummy_post( '2017-01-01 00:00:00', 'publish', 'post' );
		// Only the 'post' type should be counted.
		$this->assertNotNull( $this->repository->date_range_has_posts( '2017-01-01', '2017-01-01' ) );
	}

	/**
	 * Test date_range_has_posts excludes non-publish statuses.
	 */
	public function test_date_range_has_posts_excludes_other_statuses(): void {
		foreach ( array( 'draft', 'pending', 'private', 'trash' ) as $status ) {
			$this->create_dummy_post( '2017-02-01 00:00:00', $status );
		}
		// Only 'publish' should be counted.
		$this->create_dummy_post( '2017-02-01 00:00:00', 'publish' );
		$this->assertNotNull( $this->repository->date_range_has_posts( '2017-02-01', '2017-02-01' ) );
	}

	/**
	 * Test date_range_has_posts with invalid dates returns null.
	 */
	public function test_date_range_has_posts_with_invalid_dates(): void {
		$this->assertNull( $this->repository->date_range_has_posts( 'not-a-date', '2017-01-01' ) );
		$this->assertNull( $this->repository->date_range_has_posts( '2017-01-01', 'not-a-date' ) );
	}

	/**
	 * Test date_range_has_posts with non-existent date returns null.
	 */
	public function test_date_range_has_posts_with_nonexistent_date(): void {
		$this->assertNull( $this->repository->date_range_has_posts( '2017-02-30', '2017-02-30' ) );
	}

	/**
	 * Data provider for get_post_ids_for_date tests.
	 *
	 * @return iterable<string, array<string, int|string>> Array of test parameters.
	 */
	public function get_post_ids_for_date_data_provider(): iterable {
		yield 'no posts' => array(
			'sitemap_date'   => '2016-11-01',
			'limit'          => 10,
			'expected_count' => 0,
		);

		yield 'one post' => array(
			'sitemap_date'   => '2016-01-01',
			'limit'          => 10,
			'expected_count' => 1,
		);
		
		yield 'multiple posts, unlimited' => array(
			'sitemap_date'   => '2016-01-02',
			'limit'          => 500,
			'expected_count' => 3,
		);
		
		yield 'multiple posts, limited' => array(
			'sitemap_date'   => '2016-01-02',
			'limit'          => 2,
			'expected_count' => 2,
		);
	}

	/**
	 * Test get_post_ids_for_date returns expected post IDs.
	 *
	 * @dataProvider get_post_ids_for_date_data_provider
	 *
	 * @param string $sitemap_date   Date in Y-m-d format.
	 * @param int    $limit          Limit of post IDs to return.
	 * @param int    $expected_count Expected number of post IDs.
	 */
	public function test_get_post_ids_for_date( string $sitemap_date, int $limit, int $expected_count ): void {
		// Create test posts.
		$this->create_dummy_post( '2016-01-01 00:00:00' );
		$this->create_dummy_post( '2016-01-02 08:00:00' );
		$this->create_dummy_post( '2016-01-02 12:00:00' );
		$this->create_dummy_post( '2016-01-02 18:00:00' );
		$this->create_dummy_post( '2016-01-02 22:00:00', 'draft' ); // Should be excluded.

		$post_ids = $this->repository->get_post_ids_for_date( $sitemap_date, $limit );
		$this->assertCount( $expected_count, $post_ids );
	}

	/**
	 * Test get_post_ids_for_date with custom post status.
	 */
	public function test_get_post_ids_for_date_with_custom_status(): void {
		$this->custom_post_status_set_up();

		$this->create_dummy_post( '2016-10-04 00:00:00', 'live' );
		$this->create_dummy_post( '2016-10-05 00:00:00', 'live' );

		$post_ids = $this->repository->get_post_ids_for_date( '2016-10-04', 10 );
		$this->assertCount( 1, $post_ids );

		$post_ids = $this->repository->get_post_ids_for_date( '2016-10-05', 10 );
		$this->assertCount( 1, $post_ids );
	}

	/**
	 * Test get_post_ids_for_date with zero or negative limit.
	 */
	public function test_get_post_ids_for_date_with_invalid_limit(): void {
		$this->create_dummy_post( '2016-10-06 00:00:00' );

		$post_ids_zero     = $this->repository->get_post_ids_for_date( '2016-10-06', 0 );
		$post_ids_negative = $this->repository->get_post_ids_for_date( '2016-10-06', -5 );
		$this->assertCount( 0, $post_ids_zero );
		$this->assertCount( 0, $post_ids_negative );
	}

	/**
	 * Test get_post_ids_for_date with invalid date.
	 */
	public function test_get_post_ids_for_date_with_invalid_date(): void {
		$post_ids = $this->repository->get_post_ids_for_date( '2016-02-30', 10 );
		$this->assertIsArray( $post_ids );
		$this->assertCount( 0, $post_ids );
	}

	/**
	 * Test order_by_post_date sorts posts correctly.
	 */
	public function test_order_by_post_date(): void {
		$post_a = (object) array( 'post_date' => '2020-01-01 00:00:00' );
		$post_b = (object) array( 'post_date' => '2020-01-02 00:00:00' );
		$post_c = (object) array( 'post_date' => '2020-01-01 00:00:00' );

		// Post A is earlier than Post B.
		$this->assertEquals( -1, $this->repository->order_by_post_date( $post_a, $post_b ) );
		
		// Post B is later than Post A.
		$this->assertEquals( 1, $this->repository->order_by_post_date( $post_b, $post_a ) );
		
		// Post A and Post C are the same.
		$this->assertEquals( 0, $this->repository->order_by_post_date( $post_a, $post_c ) );
	}

	/**
	 * Test get_modified_posts_since returns posts modified since given timestamp.
	 */
	public function test_get_modified_posts_since(): void {
		// Create a post.
		$post_id = $this->create_dummy_post( '2020-01-01 00:00:00' );
		
		// Update the post to change its modified time.
		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => 'Updated Title',
			) 
		);
		// Ensure modified_gmt is definitely within range for deterministic test
		global $wpdb;
		$wpdb->update(
			$wpdb->posts,
			array(
				'post_modified_gmt' => gmdate( 'Y-m-d H:i:s' ),
				'post_modified'     => date( 'Y-m-d H:i:s' ),
			),
			array( 'ID' => $post_id )
		);
		clean_post_cache( $post_id );
		
		// Get posts modified in the last hour
		$modified_posts = $this->repository->get_modified_posts_since( time() - 3600 );
		
		// Should include our updated post.
		$modified_post_ids = array_map( 'intval', wp_list_pluck( $modified_posts, 'ID' ) );
		$this->assertContains( (int) $post_id, $modified_post_ids );
	}

	/**
	 * Test get_modified_posts_since with specific timestamp.
	 */
	public function test_get_modified_posts_since_with_timestamp(): void {
		$timestamp = time() - 3600; // last hour
		
		// Create a post.
		$post_id = $this->create_dummy_post( '2020-01-01 00:00:00' );
		
		// Update the post to change its modified time.
		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => 'Updated Title',
			) 
		);
		// Ensure modified_gmt is definitely within range for deterministic test
		global $wpdb;
		$wpdb->update(
			$wpdb->posts,
			array(
				'post_modified_gmt' => gmdate( 'Y-m-d H:i:s' ),
				'post_modified'     => date( 'Y-m-d H:i:s' ),
			),
			array( 'ID' => $post_id )
		);
		clean_post_cache( $post_id );
		
		// Get posts modified since timestamp.
		$modified_posts = $this->repository->get_modified_posts_since( $timestamp );
		
		// Should include our updated post.
		$modified_post_ids = array_map( 'intval', wp_list_pluck( $modified_posts, 'ID' ) );
		$this->assertContains( (int) $post_id, $modified_post_ids );
	}
}

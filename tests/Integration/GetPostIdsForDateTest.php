<?php
/**
 * Tests for Metro_Sitemap::get_post_ids_for_date edge cases
 *
 * @package Metro_Sitemap/unit_tests
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Integration;

use Metro_Sitemap;
use Automattic\MSM_Sitemap\Tests\Integration\Includes\CustomPostStatusTestTrait;

/**
 * Tests for Metro_Sitemap::get_post_ids_for_date edge cases.
 */
class GetPostIdsForDateTest extends TestCase {

	use CustomPostStatusTestTrait;

	/**
	 * Data Provider for get_post_ids_for_date
	 *
	 * @return iterable<string, array<string, int|string>> Array of Test parameters.
	 */
	public function post_ids_for_date_data_provider(): iterable {
		yield 'no posts' => array(
			'sitemap_date'   => '2016-10-01',
			'limit'          => 500,
			'expected_count' => 0,
		);

		yield 'multiple posts for date' => array(
			'sitemap_date'   => '2016-10-02',
			'limit'          => 500,
			'expected_count' => 20,
		);

		yield 'multiple posts for date, but get limited number' => array(
			'sitemap_date'   => '2016-10-02',
			'limit'          => 10,
			'expected_count' => 10,
		);

		yield 'no published posts' => array(
			'sitemap_date'   => '2016-10-03',
			'limit'          => 500,
			'expected_count' => 0,
		);
	}

	/**
	 * Verify get_post_ids_for_date() returns expected value
	 *
	 * @dataProvider post_ids_for_date_data_provider
	 *
	 * @param string $sitemap_date Date in Y-M-D format.
	 * @param int    $limit max number of posts to return.
	 * @param int    $expected_count Number of posts expected to be returned.
	 */
	public function test_get_post_ids_for_date( string $sitemap_date, int $limit, int $expected_count ): void {
		// 1 for 2016-10-03 in "draft" status.
		$this->create_dummy_post( '2016-10-01 00:00:00', 'draft' );

		$created_post_ids = array();
		// 20 posts for 2016-10-02.
		for ( $i = 0; $i < 20; $i++ ) {
			$hour = $i < 10 ? '0' . $i : $i;
			if ( '2016-10-02' === $sitemap_date ) {
				$created_post_ids[] = $this->create_dummy_post( '2016-10-02 ' . $hour . ':00:00' );
			}
		}

		$post_ids = Metro_Sitemap::get_post_ids_for_date( $sitemap_date, $limit );
		$this->assertCount( $expected_count, $post_ids );
		$this->assertEquals( array_slice( $created_post_ids, 0, $limit ), $post_ids );
	}

	/**
	 * Verify get_post_ids_for_date returns expected value with custom status hook
	 *
	 * @dataProvider post_ids_for_date_data_provider
	 *
	 * @param string $sitemap_date   Date in Y-M-D format.
	 * @param int    $limit          Max number of posts to return.
	 * @param int    $expected_count Number of posts expected to be returned.
	 */
	public function test_get_post_ids_for_date_custom_status( string $sitemap_date, int $limit, int $expected_count ): void {
		// set msm_sitemap_post_status filter to custom_status.
		$this->custom_post_status_set_up();

		// 1 for 2016-10-03 in "draft" status.
		$this->create_dummy_post( '2016-10-01 00:00:00', 'draft' );

		$created_post_ids = array();
		// 20 posts for 2016-10-02.
		for ( $i = 0; $i < 20; $i++ ) {
			$hour = $i < 10 ? '0' . $i : $i;
			if ( '2016-10-02' === $sitemap_date ) {
				$created_post_ids[] = $this->create_dummy_post( '2016-10-02 ' . $hour . ':00:00', 'live' );
			}
		}

		$post_ids = Metro_Sitemap::get_post_ids_for_date( $sitemap_date, $limit );
		$this->assertCount( $expected_count, $post_ids );
		$this->assertEquals( array_slice( $created_post_ids, 0, $limit ), $post_ids );
	}

	/**
	 * Posts with different post types should be excluded unless supported.
	 */
	public function test_get_post_ids_for_date_excludes_unsupported_post_types(): void {
		// Create a post of a custom type not included by default.
		register_post_type( 'not_included', array( 'public' => true ) );
		$this->create_dummy_post( '2016-10-04 00:00:00', 'publish', 'not_included' );
		// Create a normal post for the same date.
		$this->create_dummy_post( '2016-10-04 00:00:00', 'publish', 'post' );
		$post_ids = Metro_Sitemap::get_post_ids_for_date( '2016-10-04', 10 );
		$this->assertCount( 1, $post_ids );
	}

	/**
	 * Posts with various statuses should be excluded unless status is supported.
	 * 
	 * We don't include a `future` status because we're using a date in the past, and
	 * WordPress immediately changes them to `publish`.
	 */
	public function test_get_post_ids_for_date_excludes_other_statuses(): void {
		foreach ( array( 'draft', 'pending', 'private', 'publish', 'trash' ) as $status ) {
			$this->create_dummy_post( '2016-10-05 00:00:00', $status );
		}
		// Only 'publish' should be included by default.
		$post_ids = Metro_Sitemap::get_post_ids_for_date( '2016-10-05', 10 );
		$this->assertCount( 1, $post_ids );
	}

	/**
	 * Posts with future dates should be excluded.
	 */
	public function test_get_post_ids_for_date_excludes_future_posts(): void {
		$future_date = wp_date( 'Y-m-d', strtotime( '+1 year' ) );
		$this->create_dummy_post( $future_date . ' 00:00:00', 'publish' );
		$post_ids = Metro_Sitemap::get_post_ids_for_date( $future_date, 10 );
		$this->assertCount( 0, $post_ids );
	}

	/**
	 * Posts with malformed/invalid dates should be excluded.
	 */
	public function test_get_post_ids_for_date_excludes_invalid_dates(): void {
		// Should not throw DB error, should just return empty array
		$post_ids = Metro_Sitemap::get_post_ids_for_date( '0000-00-00', 10 );
		$this->assertIsArray( $post_ids );
		$this->assertCount( 0, $post_ids );
	}

	/**
	 * Limit = 0 or negative should return no posts.
	 */
	public function test_get_post_ids_for_date_with_zero_and_negative_limit(): void {
		$this->create_dummy_post( '2016-10-06 00:00:00', 'publish' );
		$post_ids_zero     = Metro_Sitemap::get_post_ids_for_date( '2016-10-06', 0 );
		$post_ids_negative = Metro_Sitemap::get_post_ids_for_date( '2016-10-06', -5 );
		$this->assertCount( 0, $post_ids_zero );
		$this->assertCount( 0, $post_ids_negative );
	}

	/**
	 * Non-existent date (e.g., Feb 30) should return no posts.
	 */
	public function test_get_post_ids_for_date_with_nonexistent_date(): void {
		// Suppress expected DB error output
		ob_start();
		$post_ids = Metro_Sitemap::get_post_ids_for_date( '2016-02-30', 10 );
		ob_end_clean();
		$this->assertCount( 0, $post_ids );
	}

	/**
	 * All posts excluded by filter should return no posts.
	 */
	public function test_get_post_ids_for_date_all_posts_excluded_by_filter(): void {
		// Exclude all posts by filtering to a non-existent post type
		add_filter(
			'msm_sitemap_entry_post_type',
			function () {
				return array( 'nonexistent_type' );
			} 
		);
		$this->create_dummy_post( '2016-10-07 00:00:00', 'publish' );
		$post_ids = Metro_Sitemap::get_post_ids_for_date( '2016-10-07', 10 );
		if ( count( $post_ids ) !== 0 ) {
			$posts    = array_map( 'get_post', $post_ids );
			$statuses = array_map(
				function ( $p ) {
					return $p->post_status;
				},
				$posts 
			);
			$this->fail( 'Expected 0 posts, got IDs: ' . implode( ', ', $post_ids ) . ', statuses: ' . implode( ', ', $statuses ) );
		}
		$this->assertCount( 0, $post_ids );
		remove_all_filters( 'msm_sitemap_entry_post_type' );
	}
} 

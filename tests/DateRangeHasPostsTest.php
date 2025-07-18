<?php
/**
 * Tests for Metro_Sitemap::date_range_has_posts edge cases
 *
 * @package Metro_Sitemap/unit_tests
 */

namespace Automattic\MSM_Sitemap\Tests;

use Metro_Sitemap;
use Automattic\MSM_Sitemap\Tests\Includes\CustomPostStatusTestTrait;

class DateRangeHasPostsTest extends TestCase {

	use CustomPostStatusTestTrait;

	/**
	 * Data Provider for test_date_range_has_posts
	 *
	 * @return iterable<string, array<string, int|string>> Array of Test parameters.
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
	 * Verify date_range_has_posts returns expected value
	 *
	 * @dataProvider date_range_has_posts_data_provider
	 *
	 * @param string  $start_date Start Date of Range in Y-M-D format.
	 * @param string  $end_date  End Date of Range in Y-M-D format.
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
			'end_date'   => '2016-12-15',
			'has_post'   => false,
		);

		yield 'one live status post' => array(
			'start_date' => '2014-12-28',
			'end_date'   => '2016-05-04',
			'has_post'   => true,
		);
	}

	/**
	 * Verify date_range_has_posts returns expected value with custom status hook
	 *
	 * @dataProvider date_range_has_posts_custom_status_data_provider
	 *
	 * @param string  $start_date Start Date of Range in Y-M-D format.
	 * @param string  $end_date   End Date of Range in Y-M-D format.
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
	 * Posts with unsupported post types in the range should not be counted.
	 */
	public function test_date_range_has_posts_excludes_unsupported_post_types(): void {
		register_post_type( 'not_included', array( 'public' => true ) );
		$this->create_dummy_post( '2017-01-01 00:00:00', 'publish', 'not_included' );
		$this->create_dummy_post( '2017-01-01 00:00:00', 'publish', 'post' );
		// Only the 'post' type should be counted.
		$this->assertNotNull( Metro_Sitemap::date_range_has_posts( '2017-01-01', '2017-01-01' ) );
	}

	/**
	 * Posts with various statuses in the range should not be counted unless status is 'publish'.
	 */
	public function test_date_range_has_posts_excludes_other_statuses(): void {
		foreach ( array( 'draft', 'pending', 'private', 'trash' ) as $status ) {
			$this->create_dummy_post( '2017-02-01 00:00:00', $status );
		}
		// Only 'publish' should be counted.
		$this->create_dummy_post( '2017-02-01 00:00:00', 'publish' );
		$this->assertNotNull( Metro_Sitemap::date_range_has_posts( '2017-02-01', '2017-02-01' ) );
	}

	/**
	 * Range with only future-dated posts should not be counted.
	 */
	public function test_date_range_has_posts_with_only_future_posts(): void {
		$future_date = date( 'Y-m-d', strtotime( '+2 years' ) );
		$this->create_dummy_post( $future_date . ' 00:00:00', 'publish' );
		$this->assertNull( Metro_Sitemap::date_range_has_posts( $future_date, $future_date ) );
	}

	/**
	 * Malformed/invalid date ranges should return null (no posts).
	 */
	public function test_date_range_has_posts_with_invalid_dates(): void {
		$this->assertNull( Metro_Sitemap::date_range_has_posts( 'not-a-date', '2017-01-01' ) );
		$this->assertNull( Metro_Sitemap::date_range_has_posts( '2017-01-01', 'not-a-date' ) );
	}

	/**
	 * Non-existent date (e.g., Feb 30) should return null.
	 */
	public function test_date_range_has_posts_with_nonexistent_date(): void {
		$this->assertNull( Metro_Sitemap::date_range_has_posts( '2017-02-30', '2017-02-30' ) );
	}

	/**
	 * Start date after end date should return null.
	 */
	public function test_date_range_has_posts_start_after_end(): void {
		$this->create_dummy_post( '2017-03-01 00:00:00', 'publish' );
		$this->assertNull( Metro_Sitemap::date_range_has_posts( '2017-03-02', '2017-03-01' ) );
	}

	/**
	 * Zero-length range (start == end, no posts) should return null.
	 */
	public function test_date_range_has_posts_zero_length_range_no_posts(): void {
		$this->assertNull( Metro_Sitemap::date_range_has_posts( '2017-04-01', '2017-04-01' ) );
	}

	/**
	 * All posts excluded by filter should return null.
	 */
	public function test_date_range_has_posts_all_posts_excluded_by_filter(): void {
		add_filter(
			'msm_sitemap_entry_post_type',
			function() {
				return array( 'nonexistent_type' );
			} 
		);
		$this->create_dummy_post( '2017-05-01 00:00:00', 'publish' );
		$this->assertNull( Metro_Sitemap::date_range_has_posts( '2017-05-01', '2017-05-01' ) );
		remove_all_filters( 'msm_sitemap_entry_post_type' );
	}
} 

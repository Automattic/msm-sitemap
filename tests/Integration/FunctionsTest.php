<?php
/**
 * WP_Test_Sitemap_Functions
 *
 * @package Automattic\MSM_Sitemap\Tests
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests;

use Automattic\MSM_Sitemap\Tests\Includes\CustomPostStatusTestTrait;

/**
 * Unit Tests to confirm Sitemaps are generated.
 *
 * @author Matthew Denton (mdbitz)
 */
class FunctionsTest extends TestCase {
	use CustomPostStatusTestTrait;

	/**
	 * Data provider providing map of recent variable and expected URL count.
	 *
	 * @return iterable<string, array<string, int|string>> Test parameters.
	 */
	public function recent_sitemap_url_count_data_provider(): iterable {
		yield '1 day' => array(
			'days'      => 1,
			'URL count' => 2,
		);

		yield '7 days' => array(
			'days'      => 7,
			'URL count' => 8,
		);
		
		yield '31 days' => array(
			'days'      => 31,
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
			$date    = strtotime( '-1 day', $date );
			$cur_day = date( 'Y', $date ) . '-' . date( 'm', $date ) . '-' . date( 'd', $date ) . ' 00:00:00';
			$this->create_dummy_post( $cur_day );
		}

		// 1 per week for previous 3 weeks
		for ( $i = 0; $i < 3; $i++ ) {
			$date    = strtotime( '-7 day', $date );
			$cur_day = date( 'Y', $date ) . '-' . date( 'm', $date ) . '-' . date( 'd', $date ) . ' 00:00:00';
			$this->create_dummy_post( $cur_day );
		}
		$this->assertPostCount( 12 );
		$this->build_sitemaps();

		$stats_service = $this->get_service( \Automattic\MSM_Sitemap\Application\Services\SitemapStatsService::class );
		$stats         = $stats_service->get_recent_url_counts( $days );
		$tot_count     = array_sum( $stats );

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
	 * Data Provider for get_date_stamp
	 *
	 * @return iterable<string, array<string, int|string>> Array of Test parameters.
	 */
	public function date_stamp_data_provider(): iterable {
		yield 'old date' => array(
			'year'            => 1985,
			'month'           => 11,
			'day'             => 5,
			'expected_string' => '1985-11-05',
		);

		yield 'sensible date' => array(
			'year'            => 2025,
			'month'           => 7,
			'day'             => 12,
			'expected_string' => '2025-07-12',
		);

		yield 'future date' => array(
			'year'            => 2100,
			'month'           => 10,
			'day'             => 12,
			'expected_string' => '2100-10-12',
		);

		yield 'invalid date' => array(
			'year'            => 0,
			'month'           => 10,
			'day'             => 12,
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
		$this->assertEquals( $expected_string, \Automattic\MSM_Sitemap\Domain\Utilities\DateUtility::format_date_stamp( $year, $month, $day ) );
	}

	// Removed tests for get_post_year_range, check_year_has_posts, and get_post_status.
	// These are now covered by PostRepositoryTest.
}

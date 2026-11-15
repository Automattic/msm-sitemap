<?php
/**
 * Tests for monthly sitemaps (msm_sitemap_index_by_month).
 *
 * @package Metro_Sitemap/unit_tests
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Integration;

use Metro_Sitemap;

/**
 * Tests for the monthly sitemap index and monthly sitemap output.
 */
class MonthlySitemapTest extends TestCase {

	/**
	 * Create posts on two days in August 2019 and one day in September 2019, then build their sitemaps.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->create_dummy_posts( array( '2019-08-01 10:00:00', '2019-08-15 10:00:00', '2019-09-03 10:00:00' ) );
		Metro_Sitemap::generate_sitemap_for_date( '2019-08-01' );
		Metro_Sitemap::generate_sitemap_for_date( '2019-08-15' );
		Metro_Sitemap::generate_sitemap_for_date( '2019-09-03' );
	}

	/**
	 * The index lists one sitemap per day when the filter is off.
	 */
	public function test_index_lists_days_by_default(): void {
		$xml = Metro_Sitemap::build_root_sitemap_xml();

		$this->assertSame( 3, substr_count( $xml, '<sitemap>' ) );
		$this->assertStringContainsString( 'dd=15', $xml );
	}

	/**
	 * The index lists one sitemap per month when the filter is on.
	 */
	public function test_index_lists_months_when_enabled(): void {
		$this->add_test_filter( 'msm_sitemap_index_by_month', '__return_true' );

		$xml = Metro_Sitemap::build_root_sitemap_xml();

		$this->assertSame( 2, substr_count( $xml, '<sitemap>' ) );
		$this->assertStringContainsString( 'yyyy=2019&amp;mm=08<', $xml );
		$this->assertStringContainsString( 'yyyy=2019&amp;mm=09<', $xml );
		$this->assertStringNotContainsString( 'dd=', $xml );
	}

	/**
	 * Monthly URLs use the yearly index URL when both filters are on.
	 */
	public function test_index_by_month_and_year(): void {
		$this->add_test_filter( 'msm_sitemap_index_by_month', '__return_true' );
		$this->add_test_filter( 'msm_sitemap_index_by_year', '__return_true' );

		$xml = Metro_Sitemap::build_root_sitemap_xml( 2019 );

		$this->assertSame( 2, substr_count( $xml, '<sitemap>' ) );
		$this->assertStringContainsString( 'sitemap-2019.xml?mm=08<', $xml );
		$this->assertStringContainsString( 'sitemap-2019.xml?mm=09<', $xml );
		$this->assertStringNotContainsString( 'dd=', $xml );
	}

	/**
	 * The lastmod of a month is the newest modified time of its days.
	 */
	public function test_month_lastmod_uses_newest_day(): void {
		global $wpdb;

		$this->add_test_filter( 'msm_sitemap_index_by_month', '__return_true' );

		$sitemap_id = Metro_Sitemap::get_sitemap_post_id( 2019, 8, 15 );
		$wpdb->update( $wpdb->posts, array( 'post_modified' => '2030-01-02 03:04:05' ), array( 'ID' => $sitemap_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		clean_post_cache( $sitemap_id );

		$xml = Metro_Sitemap::build_root_sitemap_xml();

		$this->assertStringContainsString( 'yyyy=2019&amp;mm=08</loc><lastmod>2030-01-02T03:04:05', $xml );
		$this->assertSame( 1, substr_count( $xml, '<lastmod>2030-01-02' ) );
	}

	/**
	 * A month request returns the URLs of every day in that month.
	 */
	public function test_monthly_sitemap_joins_days(): void {
		$this->add_test_filter( 'msm_sitemap_index_by_month', '__return_true' );

		$xml = Metro_Sitemap::build_xml(
			array(
				'year'  => 2019,
				'month' => 8,
				'day'   => false,
			)
		);

		$this->assertIsString( $xml );
		$this->assertNotFalse( simplexml_load_string( $xml ) );
		$this->assertSame( 1, substr_count( $xml, '<urlset' ) );
		$this->assertSame( 2, substr_count( $xml, '<url>' ) );
		$this->assertStringContainsString( get_permalink( $this->posts[0]['ID'] ), $xml );
		$this->assertStringContainsString( get_permalink( $this->posts[1]['ID'] ), $xml );
		$this->assertStringNotContainsString( get_permalink( $this->posts[2]['ID'] ), $xml );
	}

	/**
	 * Only the first sitemap of a day is used, the same one the daily URL serves.
	 */
	public function test_monthly_sitemap_skips_duplicate_day(): void {
		$duplicate_id = wp_insert_post(
			array(
				'post_name'   => '2019-08-01',
				'post_title'  => '2019-08-01',
				'post_type'   => Metro_Sitemap::SITEMAP_CPT,
				'post_status' => 'publish',
				'post_date'   => '2019-08-01 00:00:00',
			)
		);
		update_post_meta( $duplicate_id, 'msm_sitemap_xml', '<?xml version="1.0"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>https://duplicate.example/</loc></url></urlset>' );

		$xml = Metro_Sitemap::build_monthly_sitemap_xml( 2019, 8 );

		$this->assertSame( 2, substr_count( $xml, '<url>' ) );
		$this->assertStringNotContainsString( 'duplicate.example', $xml );
	}

	/**
	 * Days removed with the msm_sitemap_index filter are left out of the monthly sitemap.
	 */
	public function test_monthly_sitemap_respects_index_filter(): void {
		$this->add_test_filter(
			'msm_sitemap_index',
			function ( $sitemaps ) {
				return array_values( array_diff( $sitemaps, array( '2019-08-15 00:00:00' ) ) );
			}
		);

		$xml = Metro_Sitemap::build_monthly_sitemap_xml( 2019, 8 );

		$this->assertSame( 1, substr_count( $xml, '<url>' ) );
		$this->assertStringContainsString( get_permalink( $this->posts[0]['ID'] ), $xml );
		$this->assertStringNotContainsString( get_permalink( $this->posts[1]['ID'] ), $xml );
	}

	/**
	 * A month with no sitemaps returns false, so the template sends a 404.
	 */
	public function test_empty_month_returns_false(): void {
		$this->add_test_filter( 'msm_sitemap_index_by_month', '__return_true' );

		$this->assertFalse(
			Metro_Sitemap::build_xml(
				array(
					'year'  => 2019,
					'month' => 10,
					'day'   => false,
				)
			)
		);
	}

	/**
	 * A month request returns false when the filter is off, so the template sends a 404.
	 */
	public function test_month_request_returns_false_when_disabled(): void {
		$this->assertFalse(
			Metro_Sitemap::build_xml(
				array(
					'year'  => 2019,
					'month' => 8,
					'day'   => false,
				)
			)
		);
	}

	/**
	 * A month outside 1 to 12 returns false.
	 */
	public function test_invalid_month_returns_false(): void {
		$this->assertFalse( Metro_Sitemap::build_monthly_sitemap_xml( 2019, 13 ) );
	}

	/**
	 * Daily sitemaps still work when the filter is on.
	 */
	public function test_daily_sitemap_still_works(): void {
		$this->add_test_filter( 'msm_sitemap_index_by_month', '__return_true' );

		$xml = Metro_Sitemap::build_xml(
			array(
				'year'  => 2019,
				'month' => 8,
				'day'   => 15,
			)
		);

		$this->assertSame( 1, substr_count( $xml, '<url>' ) );
		$this->assertStringContainsString( get_permalink( $this->posts[1]['ID'] ), $xml );
	}
}

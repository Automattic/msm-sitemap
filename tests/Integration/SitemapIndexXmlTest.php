<?php
/**
 * Tests for Metro_Sitemap::build_root_sitemap_xml (sitemap index XML and filters)
 *
 * @package Metro_Sitemap/unit_tests
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Integration;

use Metro_Sitemap;

/**
 * Tests for Metro_Sitemap::build_root_sitemap_xml (sitemap index XML and filters).
 */
class SitemapIndexXmlTest extends TestCase {

	/**
	 * Test that build_root_sitemap_xml returns the correct XML.
	 */
	public function test_build_root_sitemap_xml_returns_correct_xml(): void {
		$date = '2019-01-01';
		$this->create_dummy_post( $date . ' 00:00:00', 'publish' );
		Metro_Sitemap::generate_sitemap_for_date( $date );
		$xml = Metro_Sitemap::build_root_sitemap_xml();
		$this->assertIsString( $xml );
		$this->assertStringContainsString( '<sitemapindex', $xml );
		$this->assertStringContainsString( '<sitemap>', $xml );
		$this->assertStringContainsString( '<loc>', $xml );
	}

	/**
	 * Test that build_root_sitemap_xml respects year filtering.
	 */
	public function test_build_root_sitemap_xml_respects_year_filtering(): void {
		$date1 = '2020-01-01';
		$date2 = '2021-01-01';
		$this->create_dummy_post( $date1 . ' 00:00:00', 'publish' );
		$this->create_dummy_post( $date2 . ' 00:00:00', 'publish' );
		Metro_Sitemap::generate_sitemap_for_date( $date1 );
		Metro_Sitemap::generate_sitemap_for_date( $date2 );
		$xml_2020 = Metro_Sitemap::build_root_sitemap_xml( 2020 );
		$xml_2021 = Metro_Sitemap::build_root_sitemap_xml( 2021 );
		$this->assertStringContainsString( '2020', $xml_2020 );
		$this->assertStringContainsString( '2021', $xml_2021 );
		$this->assertStringNotContainsString( '2021', $xml_2020 );
		$this->assertStringNotContainsString( '2020', $xml_2021 );
	}

	/**
	 * Test that build_root_sitemap_xml filters work.
	 */
	public function test_build_root_sitemap_xml_filters_work(): void {
		$date = '2022-01-01';
		$this->create_dummy_post( $date . ' 00:00:00', 'publish' );
		Metro_Sitemap::generate_sitemap_for_date( $date );
		add_filter(
			'msm_sitemap_index',
			function ( $sitemaps ) {
				return array_reverse( $sitemaps );
			},
			10,
			1 
		);
		add_filter(
			'msm_sitemap_index_appended_xml',
			function ( $appended, $year, $sitemaps ) {
				return '<!-- appended -->';
			},
			10,
			3 
		);
		add_filter(
			'msm_sitemap_index_xml',
			function ( $xml, $year, $sitemaps ) {
				return str_replace( '<sitemapindex', '<sitemapindex test="1"', $xml );
			},
			10,
			3 
		);
		$xml = Metro_Sitemap::build_root_sitemap_xml();
		$this->assertStringContainsString( '<sitemapindex test="1"', $xml );
		$this->assertStringContainsString( '<!-- appended -->', $xml );
		remove_all_filters( 'msm_sitemap_index' );
		remove_all_filters( 'msm_sitemap_index_appended_xml' );
		remove_all_filters( 'msm_sitemap_index_xml' );
	}
} 

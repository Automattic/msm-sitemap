<?php
/**
 * Tests for sitemap index XML generation using SitemapXmlRequestHandler
 *
 * @package Automattic\MSM_Sitemap\Tests
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests;

use Automattic\MSM_Sitemap\Infrastructure\REST\SitemapXmlRequestHandler;

/**
 * Tests for sitemap index XML generation using the new DDD services.
 */
class SitemapIndexXmlTest extends TestCase {

	/**
	 * Test that sitemap index generation returns the correct XML.
	 */
	public function test_sitemap_index_generation_returns_correct_xml(): void {
		$date = '2019-01-01';
		$this->create_dummy_post( $date . ' 00:00:00', 'publish' );
		$this->generate_sitemap_for_date( $date );
		
		$sitemap_endpoint_handler = $this->get_service( SitemapXmlRequestHandler::class );
		$xml                      = $sitemap_endpoint_handler->get_sitemap_index_xml();
		
		$this->assertIsString( $xml );
		$this->assertStringContainsString( '<sitemapindex', $xml );
		$this->assertStringContainsString( '<sitemap>', $xml );
		$this->assertStringContainsString( '<loc>', $xml );
	}

	/**
	 * Test that sitemap index generation respects year filtering.
	 */
	public function test_sitemap_index_generation_respects_year_filtering(): void {
		$date1 = '2020-01-01';
		$date2 = '2021-01-01';
		$this->create_dummy_post( $date1 . ' 00:00:00', 'publish' );
		$this->create_dummy_post( $date2 . ' 00:00:00', 'publish' );
		$this->generate_sitemap_for_date( $date1 );
		$this->generate_sitemap_for_date( $date2 );
		
		$sitemap_endpoint_handler = $this->get_service( SitemapXmlRequestHandler::class );
		$xml_2020                 = $sitemap_endpoint_handler->get_sitemap_index_xml( 2020 );
		$xml_2021                 = $sitemap_endpoint_handler->get_sitemap_index_xml( 2021 );
		
		$this->assertStringContainsString( '2020', $xml_2020 );
		$this->assertStringContainsString( '2021', $xml_2021 );
		$this->assertStringNotContainsString( '2021', $xml_2020 );
		$this->assertStringNotContainsString( '2020', $xml_2021 );
	}

	/**
	 * Test that sitemap index generation filters work.
	 */
	public function test_sitemap_index_generation_filters_work(): void {
		$date = '2022-01-01';
		$this->create_dummy_post( $date . ' 00:00:00', 'publish' );
		$this->generate_sitemap_for_date( $date );
		
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
		
		$sitemap_endpoint_handler = $this->get_service( SitemapXmlRequestHandler::class );
		$xml                      = $sitemap_endpoint_handler->get_sitemap_index_xml();
		
		$this->assertStringContainsString( '<sitemapindex test="1"', $xml );
		$this->assertStringContainsString( '<!-- appended -->', $xml );
		
		remove_all_filters( 'msm_sitemap_index' );
		remove_all_filters( 'msm_sitemap_index_appended_xml' );
		remove_all_filters( 'msm_sitemap_index_xml' );
	}
} 

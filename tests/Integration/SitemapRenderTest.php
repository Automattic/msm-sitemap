<?php
/**
 * Tests for rendering sitemap XML from stored entry data.
 *
 * @package Automattic\MSM_Sitemap\Tests
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Integration;

use Metro_Sitemap;
use SimpleXMLElement;

/**
 * Sitemaps store entry data and build the XML when served.
 */
class SitemapRenderTest extends TestCase {

	/**
	 * Generate a sitemap for a single post and return the sitemap ID.
	 *
	 * @return int
	 */
	private function generate_sitemap(): int {
		$this->create_dummy_post( '2018-01-01 00:00:00' );
		Metro_Sitemap::generate_sitemap_for_date( '2018-01-01' );

		return (int) Metro_Sitemap::get_sitemap_post_id( 2018, 1, 1 );
	}

	/**
	 * Entry data is stored instead of the full XML.
	 */
	public function test_generate_stores_entry_data_not_xml(): void {
		$sitemap_id = $this->generate_sitemap();

		$entries = json_decode( get_post_meta( $sitemap_id, 'msm_sitemap_data', true ), true );

		$this->assertIsArray( $entries );
		$this->assertCount( 1, $entries );
		$this->assertStringContainsString( 'http', $entries[0]['loc'] );
		$this->assertArrayHasKey( 'lastmod', $entries[0] );
		$this->assertSame( '', get_post_meta( $sitemap_id, 'msm_sitemap_xml', true ) );
	}

	/**
	 * Served XML matches the stored entries.
	 */
	public function test_served_xml_is_built_from_entry_data(): void {
		$sitemap_id = $this->generate_sitemap();
		$entries    = json_decode( get_post_meta( $sitemap_id, 'msm_sitemap_data', true ), true );

		$xml = simplexml_load_string( Metro_Sitemap::build_individual_sitemap_xml( 2018, 1, 1 ) );

		$this->assertCount( 1, $xml->url );
		$this->assertSame( $entries[0]['loc'], (string) $xml->url->loc );
		$this->assertSame( $entries[0]['lastmod'], (string) $xml->url->lastmod );
	}

	/**
	 * A msm_sitemap_entry filter added after generation shows up without regenerating.
	 */
	public function test_entry_filter_applies_without_regeneration(): void {
		$this->generate_sitemap();

		$this->add_test_filter(
			'msm_sitemap_entry',
			function ( SimpleXMLElement $url ) {
				$url->addChild( 'news', 'added-later' );
				return $url;
			}
		);

		$this->assertStringContainsString( '<news>added-later</news>', Metro_Sitemap::build_individual_sitemap_xml( 2018, 1, 1 ) );
	}

	/**
	 * A msm_sitemap_namespace filter added after generation shows up without regenerating.
	 */
	public function test_namespace_filter_applies_without_regeneration(): void {
		$this->generate_sitemap();

		$this->add_test_filter(
			'msm_sitemap_namespace',
			function ( array $namespaces ) {
				$namespaces['xmlns:video'] = 'http://www.google.com/schemas/sitemap-video/1.1';
				return $namespaces;
			}
		);

		$this->assertStringContainsString( 'xmlns:video=', Metro_Sitemap::build_individual_sitemap_xml( 2018, 1, 1 ) );
	}

	/**
	 * Sitemaps from older versions still serve their stored XML.
	 */
	public function test_legacy_stored_xml_is_served(): void {
		$sitemap_id = $this->generate_sitemap();
		delete_post_meta( $sitemap_id, 'msm_sitemap_data' );
		update_post_meta( $sitemap_id, 'msm_sitemap_xml', '<urlset><url><loc>https://example.com/legacy</loc></url></urlset>' );

		$this->assertSame( '<urlset><url><loc>https://example.com/legacy</loc></url></urlset>', Metro_Sitemap::build_individual_sitemap_xml( 2018, 1, 1 ) );
	}

	/**
	 * Regenerating a legacy sitemap swaps the stored XML for entry data.
	 */
	public function test_regeneration_removes_legacy_xml(): void {
		$sitemap_id = $this->generate_sitemap();
		delete_post_meta( $sitemap_id, 'msm_sitemap_data' );
		update_post_meta( $sitemap_id, 'msm_sitemap_xml', '<urlset></urlset>' );

		Metro_Sitemap::generate_sitemap_for_date( '2018-01-01' );

		$this->assertSame( '', get_post_meta( $sitemap_id, 'msm_sitemap_xml', true ) );
		$this->assertNotEmpty( get_post_meta( $sitemap_id, 'msm_sitemap_data', true ) );
	}

	/**
	 * The update action still gets the generated XML.
	 */
	public function test_update_action_receives_xml(): void {
		$this->generate_sitemap();

		$received = null;
		add_action(
			'msm_update_sitemap_post',
			function ( $sitemap_id, $year, $month, $day, $xml ) use ( &$received ) {
				$received = $xml;
			},
			10,
			5
		);

		Metro_Sitemap::generate_sitemap_for_date( '2018-01-01' );

		$this->assertIsString( $received );
		$this->assertStringContainsString( '<urlset', $received );
		$this->assertStringContainsString( '<loc>', $received );
	}
}

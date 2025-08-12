<?php
/**
 * WP_Test_Sitemap_SitemapIndexXmlFormatter
 *
 * @package Automattic\MSM_Sitemap\Tests
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests;

use Automattic\MSM_Sitemap\Infrastructure\Formatters\SitemapIndexXmlFormatter;
use Automattic\MSM_Sitemap\Infrastructure\Factories\SitemapIndexCollectionFactory;
use Automattic\MSM_Sitemap\Infrastructure\Factories\SitemapIndexEntryFactory;

/**
 * Unit Tests for SitemapIndexXmlFormatter.
 *
 * @author Gary Jones
 */
class SitemapIndexXmlFormatterTest extends TestCase {

	/**
	 * Skip all tests for SitemapIndexXmlFormatter as it's not being used in production.
	 */
	public function setUp(): void {
		// $this->markTestSkipped( 'SitemapIndexXmlFormatter is not being used in production.' );
	}

	/**
	 * Test formatting a single sitemap index entry.
	 */
	public function test_format(): void {
		$formatter = new SitemapIndexXmlFormatter();
		$entry = SitemapIndexEntryFactory::from_data( 'https://example.com/sitemap.xml', '2024-01-15T00:00:00+00:00' );
		$collection = SitemapIndexCollectionFactory::from_entries( array( $entry ) );

		$xml = $formatter->format( $collection );

		$this->assertStringContainsString( '<?xml version="1.0" encoding="UTF-8"?>', $xml );
		$this->assertStringContainsString( '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $xml );
		$this->assertStringContainsString( '<sitemap>', $xml );
		$this->assertStringContainsString( '<loc>https://example.com/sitemap.xml</loc>', $xml );
		$this->assertStringContainsString( '<lastmod>2024-01-15T00:00:00+00:00</lastmod>', $xml );
		$this->assertStringContainsString( '</sitemap>', $xml );
		$this->assertStringContainsString( '</sitemapindex>', $xml );
	}

	/**
	 * Test formatting a sitemap index entry without lastmod.
	 */
	public function test_format_without_lastmod(): void {
		$formatter = new SitemapIndexXmlFormatter();
		$entry = SitemapIndexEntryFactory::from_data( 'https://example.com/sitemap.xml' );
		$collection = SitemapIndexCollectionFactory::from_entries( array( $entry ) );

		$xml = $formatter->format( $collection );

		$this->assertStringContainsString( '<loc>https://example.com/sitemap.xml</loc>', $xml );
		$this->assertStringNotContainsString( '<lastmod>', $xml );
	}

	/**
	 * Test XML escaping.
	 */
	public function test_xml_escaping(): void {
		$formatter = new SitemapIndexXmlFormatter();
		$entry = SitemapIndexEntryFactory::from_data( 'https://example.com/sitemap.xml?param=value&other=test', '2024-01-15T00:00:00+00:00' );
		$collection = SitemapIndexCollectionFactory::from_entries( array( $entry ) );

		$xml = $formatter->format( $collection );

		$this->assertStringContainsString( '<loc>https://example.com/sitemap.xml?param=value&amp;other=test</loc>', $xml );
	}

	/**
	 * Test formatting multiple sitemap index entries.
	 */
	public function test_format_multiple_entries(): void {
		$formatter = new SitemapIndexXmlFormatter();
		$entry1 = SitemapIndexEntryFactory::from_data( 'https://example.com/sitemap1.xml', '2024-01-15T00:00:00+00:00' );
		$entry2 = SitemapIndexEntryFactory::from_data( 'https://example.com/sitemap2.xml', '2024-01-16T00:00:00+00:00' );
		$collection = SitemapIndexCollectionFactory::from_entries( array( $entry1, $entry2 ) );

		$xml = $formatter->format( $collection );

		$this->assertStringContainsString( '<loc>https://example.com/sitemap1.xml</loc>', $xml );
		$this->assertStringContainsString( '<lastmod>2024-01-15T00:00:00+00:00</lastmod>', $xml );
		$this->assertStringContainsString( '<loc>https://example.com/sitemap2.xml</loc>', $xml );
		$this->assertStringContainsString( '<lastmod>2024-01-16T00:00:00+00:00</lastmod>', $xml );
		$this->assertEquals( 2, substr_count( $xml, '<sitemap>' ) );
		$this->assertEquals( 2, substr_count( $xml, '</sitemap>' ) );
	}

	/**
	 * Test formatting empty collection.
	 */
	public function test_format_empty_collection(): void {
		$formatter = new SitemapIndexXmlFormatter();
		$collection = SitemapIndexCollectionFactory::create_empty();

		$xml = $formatter->format( $collection );

		$this->assertStringContainsString( '<?xml version="1.0" encoding="UTF-8"?>', $xml );
		$this->assertStringContainsString( '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $xml );
		$this->assertStringContainsString( '</sitemapindex>', $xml );
		$this->assertStringNotContainsString( '<sitemap>', $xml );
	}

	/**
	 * Test formatting with special characters.
	 */
	public function test_format_with_special_characters(): void {
		$formatter = new SitemapIndexXmlFormatter();
		$entry = SitemapIndexEntryFactory::from_data( 'https://example.com/sitemap.xml?param=value&other=test', '2024-01-15T00:00:00+00:00' );
		$collection = SitemapIndexCollectionFactory::from_entries( array( $entry ) );

		$xml = $formatter->format( $collection );

		$this->assertStringContainsString( '&amp;', $xml );
		$this->assertStringNotContainsString( 'param=value&other=test', $xml );
		$this->assertStringContainsString( 'param=value&amp;other=test', $xml );
	}

	/**
	 * Test formatting with different lastmod formats.
	 */
	public function test_format_with_different_lastmod_formats(): void {
		$formatter = new SitemapIndexXmlFormatter();
		$entry = SitemapIndexEntryFactory::from_data( 'https://example.com/sitemap.xml', '2024-01-15T12:30:45Z' );
		$collection = SitemapIndexCollectionFactory::from_entries( array( $entry ) );

		$xml = $formatter->format( $collection );

		$this->assertStringContainsString( '<lastmod>2024-01-15T12:30:45Z</lastmod>', $xml );
	}
}

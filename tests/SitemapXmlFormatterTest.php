<?php
/**
 * SitemapXmlFormatterTest
 *
 * @package Automattic\MSM_Sitemap\Tests
 */

declare(strict_types=1);

namespace Automattic\MSM_Sitemap\Tests;

use Automattic\MSM_Sitemap\Domain\ValueObjects\SitemapContent;
use Automattic\MSM_Sitemap\Domain\ValueObjects\UrlEntry;
use Automattic\MSM_Sitemap\Infrastructure\Formatters\SitemapXmlFormatter;

/**
 * Unit Tests for SitemapXmlFormatter.
 */
class SitemapXmlFormatterTest extends TestCase {

	/**
	 * Test that the formatter includes XSL stylesheet reference.
	 */
	public function test_format_includes_xsl_stylesheet(): void {
		$formatter = new SitemapXmlFormatter();
		$content   = new SitemapContent();
		$entry     = new UrlEntry( 'https://example.com/test' );
		$content   = $content->add( $entry );

		$xml = $formatter->format( $content );

		$this->assertStringContainsString( '<?xml-stylesheet type="text/xsl"', $xml );
		$this->assertStringContainsString( '/msm-sitemap.xsl', $xml );
	}

	/**
	 * Test that the formatter includes all required namespaces.
	 */
	public function test_format_includes_namespaces(): void {
		$formatter = new SitemapXmlFormatter();
		$content   = new SitemapContent();
		$entry     = new UrlEntry( 'https://example.com/test' );
		$content   = $content->add( $entry );

		$xml = $formatter->format( $content );

		$this->assertStringContainsString( 'xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"', $xml );
		$this->assertStringContainsString( 'xmlns:news="http://www.google.com/schemas/sitemap-news/0.9"', $xml );
		$this->assertStringContainsString( 'xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"', $xml );
		$this->assertStringContainsString( 'xmlns:video="http://www.google.com/schemas/sitemap-video/1.1"', $xml );
	}

	/**
	 * Test that the formatter produces valid XML structure.
	 */
	public function test_format_produces_valid_xml_structure(): void {
		$formatter = new SitemapXmlFormatter();
		$content   = new SitemapContent();
		$entry     = new UrlEntry( 'https://example.com/test', '2024-01-15T00:00:00+00:00', 'daily', 0.8 );
		$content   = $content->add( $entry );

		$xml = $formatter->format( $content );

		$this->assertStringContainsString( '<?xml version="1.0" encoding="UTF-8"?>', $xml );
		$this->assertStringContainsString( '<urlset', $xml );
		$this->assertStringContainsString( '</urlset>', $xml );
		$this->assertStringContainsString( '<url>', $xml );
		$this->assertStringContainsString( '</url>', $xml );
		$this->assertStringContainsString( '<loc>https://example.com/test</loc>', $xml );
		$this->assertStringContainsString( '<lastmod>2024-01-15T00:00:00+00:00</lastmod>', $xml );
		$this->assertStringContainsString( '<changefreq>daily</changefreq>', $xml );
		$this->assertStringContainsString( '<priority>0.8</priority>', $xml );
	}

	/**
	 * Test that the formatter handles empty content correctly.
	 */
	public function test_format_handles_empty_content(): void {
		$formatter = new SitemapXmlFormatter();
		$content   = new SitemapContent();

		$xml = $formatter->format( $content );

		$this->assertStringContainsString( '<?xml version="1.0" encoding="UTF-8"?>', $xml );
		$this->assertStringContainsString( '<urlset', $xml );
		$this->assertStringContainsString( '</urlset>', $xml );
		$this->assertStringNotContainsString( '<url>', $xml );
	}
}

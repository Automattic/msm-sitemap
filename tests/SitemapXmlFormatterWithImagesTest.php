<?php
/**
 * SitemapXmlFormatterWithImagesTest
 *
 * @package Automattic\MSM_Sitemap\Tests
 */

declare(strict_types=1);

namespace Automattic\MSM_Sitemap\Tests;

use Automattic\MSM_Sitemap\Domain\ValueObjects\ImageEntry;
use Automattic\MSM_Sitemap\Domain\ValueObjects\SitemapContent;
use Automattic\MSM_Sitemap\Domain\ValueObjects\UrlEntry;
use Automattic\MSM_Sitemap\Infrastructure\Formatters\SitemapXmlFormatter;

/**
 * Unit Tests for SitemapXmlFormatter with images.
 *
 * Per Google's Image Sitemap specification, only the loc element is required.
 * Other elements (caption, geo_location, title, license) have been deprecated by Google.
 *
 * @see https://developers.google.com/search/docs/crawling-indexing/sitemaps/image-sitemaps
 */
class SitemapXmlFormatterWithImagesTest extends TestCase {

	/**
	 * Test that the formatter includes image elements in XML.
	 */
	public function test_format_includes_image_elements(): void {
		$formatter = new SitemapXmlFormatter();
		$content   = new SitemapContent();

		// Create an image entry (only loc is supported per Google spec).
		$image = new ImageEntry( 'https://example.com/image.jpg' );

		// Create a URL entry with images.
		$entry = new UrlEntry(
			'https://example.com/test-post',
			'2024-01-15T00:00:00+00:00',
			'daily',
			0.8,
			array( $image )
		);

		$content = $content->add( $entry );

		$xml = $formatter->format( $content );

		// Check that image elements are included.
		$this->assertStringContainsString( '<image:image>', $xml );
		$this->assertStringContainsString( '<image:loc>https://example.com/image.jpg</image:loc>', $xml );
		$this->assertStringContainsString( '</image:image>', $xml );

		// Check that deprecated elements are NOT included.
		$this->assertStringNotContainsString( '<image:title>', $xml );
		$this->assertStringNotContainsString( '<image:caption>', $xml );
		$this->assertStringNotContainsString( '<image:geo_location>', $xml );
		$this->assertStringNotContainsString( '<image:license>', $xml );
	}

	/**
	 * Test that the formatter handles URLs without images correctly.
	 */
	public function test_format_handles_urls_without_images(): void {
		$formatter = new SitemapXmlFormatter();
		$content   = new SitemapContent();

		// Create a URL entry without images.
		$entry = new UrlEntry(
			'https://example.com/test-post',
			'2024-01-15T00:00:00+00:00',
			'daily',
			0.8
		);

		$content = $content->add( $entry );

		$xml = $formatter->format( $content );

		// Check that image elements are not included.
		$this->assertStringNotContainsString( '<image:image>', $xml );
		$this->assertStringNotContainsString( '</image:image>', $xml );

		// Check that URL elements are still present.
		$this->assertStringContainsString( '<url>', $xml );
		$this->assertStringContainsString( '</url>', $xml );
		$this->assertStringContainsString( '<loc>https://example.com/test-post</loc>', $xml );
	}

	/**
	 * Test that the formatter handles multiple images per URL.
	 */
	public function test_format_handles_multiple_images_per_url(): void {
		$formatter = new SitemapXmlFormatter();
		$content   = new SitemapContent();

		// Create multiple image entries (only loc is supported per Google spec).
		$image1 = new ImageEntry( 'https://example.com/image1.jpg' );
		$image2 = new ImageEntry( 'https://example.com/image2.jpg' );

		// Create a URL entry with multiple images.
		$entry = new UrlEntry(
			'https://example.com/test-post',
			'2024-01-15T00:00:00+00:00',
			'daily',
			0.8,
			array( $image1, $image2 )
		);

		$content = $content->add( $entry );

		$xml = $formatter->format( $content );

		// Check that both image elements are included.
		$this->assertStringContainsString( '<image:loc>https://example.com/image1.jpg</image:loc>', $xml );
		$this->assertStringContainsString( '<image:loc>https://example.com/image2.jpg</image:loc>', $xml );

		// Count image elements.
		$image_count = substr_count( $xml, '<image:image>' );
		$this->assertEquals( 2, $image_count );
	}

	/**
	 * Test that the formatter includes proper XML structure.
	 */
	public function test_format_produces_valid_xml_structure_with_images(): void {
		$formatter = new SitemapXmlFormatter();
		$content   = new SitemapContent();

		// Create an image entry.
		$image = new ImageEntry( 'https://example.com/image.jpg' );

		// Create a URL entry with image.
		$entry = new UrlEntry(
			'https://example.com/test-post',
			'2024-01-15T00:00:00+00:00',
			'daily',
			0.8,
			array( $image )
		);

		$content = $content->add( $entry );

		$xml = $formatter->format( $content );

		// Check XML structure.
		$this->assertStringContainsString( '<?xml version="1.0" encoding="UTF-8"?>', $xml );
		$this->assertStringContainsString( '<urlset', $xml );
		$this->assertStringContainsString( '</urlset>', $xml );
		$this->assertStringContainsString( '<url>', $xml );
		$this->assertStringContainsString( '</url>', $xml );

		// Check that image namespace is included.
		$this->assertStringContainsString( 'xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"', $xml );
	}
}

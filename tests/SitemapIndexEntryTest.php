<?php
/**
 * WP_Test_Sitemap_SitemapIndexEntry
 *
 * @package Metro_Sitemap/unit_tests
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests;

use Automattic\MSM_Sitemap\Domain\ValueObjects\SitemapIndexEntry;
use InvalidArgumentException;

/**
 * Unit Tests for SitemapIndexEntry value object.
 *
 * @author Gary Jones
 */
class SitemapIndexEntryTest extends TestCase {

	/**
	 * Test creating a valid sitemap index entry.
	 */
	public function test_create_valid_sitemap_index_entry(): void {
		$entry = new SitemapIndexEntry( 'https://example.com/sitemap-2024-01-15.xml' );

		$this->assertEquals( 'https://example.com/sitemap-2024-01-15.xml', $entry->loc() );
		$this->assertNull( $entry->lastmod() );
	}

	/**
	 * Test creating a sitemap index entry with lastmod.
	 */
	public function test_create_sitemap_index_entry_with_lastmod(): void {
		$entry = new SitemapIndexEntry( 'https://example.com/sitemap-2024-01-15.xml', '2024-01-15T00:00:00+00:00' );

		$this->assertEquals( 'https://example.com/sitemap-2024-01-15.xml', $entry->loc() );
		$this->assertEquals( '2024-01-15T00:00:00+00:00', $entry->lastmod() );
	}

	/**
	 * Test URL validation with invalid URL.
	 */
	public function test_url_validation_with_invalid_url(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Sitemap URL must be a valid URL.' );

		new SitemapIndexEntry( 'not-a-valid-url' );
	}

	/**
	 * Test URL validation with empty URL.
	 */
	public function test_url_validation_with_empty_url(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Sitemap URL cannot be empty.' );

		new SitemapIndexEntry( '' );
	}

	/**
	 * Test URL validation with URL exceeding max length.
	 */
	public function test_url_validation_with_url_exceeding_max_length(): void {
		$long_url = 'https://example.com/' . str_repeat( 'a', 2048 );

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Sitemap URL cannot exceed 2048 characters.' );

		new SitemapIndexEntry( $long_url );
	}

	/**
	 * Test lastmod validation with empty string.
	 */
	public function test_lastmod_validation_with_empty_string(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Last modified date cannot be empty if provided.' );

		new SitemapIndexEntry( 'https://example.com/sitemap.xml', '' );
	}

	/**
	 * Test to array method.
	 */
	public function test_to_array(): void {
		$entry = new SitemapIndexEntry( 'https://example.com/sitemap.xml' );
		$array = $entry->to_array();

		$this->assertEquals( 'https://example.com/sitemap.xml', $array['loc'] );
		$this->assertArrayNotHasKey( 'lastmod', $array );
	}

	/**
	 * Test to array method with lastmod.
	 */
	public function test_to_array_with_lastmod(): void {
		$entry = new SitemapIndexEntry( 'https://example.com/sitemap.xml', '2024-01-15T00:00:00+00:00' );
		$array = $entry->to_array();

		$this->assertEquals( 'https://example.com/sitemap.xml', $array['loc'] );
		$this->assertEquals( '2024-01-15T00:00:00+00:00', $array['lastmod'] );
	}

	/**
	 * Test equals method with identical entries.
	 */
	public function test_equals_with_identical_entries(): void {
		$entry1 = new SitemapIndexEntry( 'https://example.com/sitemap.xml', '2024-01-15T00:00:00+00:00' );
		$entry2 = new SitemapIndexEntry( 'https://example.com/sitemap.xml', '2024-01-15T00:00:00+00:00' );

		$this->assertTrue( $entry1->equals( $entry2 ) );
	}

	/**
	 * Test equals method with different entries.
	 */
	public function test_equals_with_different_entries(): void {
		$entry1 = new SitemapIndexEntry( 'https://example.com/sitemap1.xml' );
		$entry2 = new SitemapIndexEntry( 'https://example.com/sitemap2.xml' );

		$this->assertFalse( $entry1->equals( $entry2 ) );
	}

	/**
	 * Test equals method with different lastmod.
	 */
	public function test_equals_with_different_lastmod(): void {
		$entry1 = new SitemapIndexEntry( 'https://example.com/sitemap.xml', '2024-01-15T00:00:00+00:00' );
		$entry2 = new SitemapIndexEntry( 'https://example.com/sitemap.xml', '2024-01-16T00:00:00+00:00' );

		$this->assertFalse( $entry1->equals( $entry2 ) );
	}

	/**
	 * Test equals method with one entry having lastmod and the other not.
	 */
	public function test_equals_with_one_having_lastmod(): void {
		$entry1 = new SitemapIndexEntry( 'https://example.com/sitemap.xml', '2024-01-15T00:00:00+00:00' );
		$entry2 = new SitemapIndexEntry( 'https://example.com/sitemap.xml' );

		$this->assertFalse( $entry1->equals( $entry2 ) );
	}

	/**
	 * Test valid URL formats.
	 */
	public function test_valid_url_formats(): void {
		$valid_urls = array(
			'https://example.com/sitemap.xml',
			'http://example.com/sitemap.xml',
			'https://example.com/sitemap-2024-01-15.xml',
			'https://example.com/sitemaps/sitemap.xml',
		);

		foreach ( $valid_urls as $url ) {
			$entry = new SitemapIndexEntry( $url );
			$this->assertEquals( $url, $entry->loc() );
		}
	}

	/**
	 * Test valid lastmod formats.
	 */
	public function test_valid_lastmod_formats(): void {
		$valid_lastmods = array(
			'2024-01-15T00:00:00+00:00',
			'2024-01-15T12:30:45Z',
			'2024-01-15',
			'2024-01-15T12:30:45-05:00',
		);

		foreach ( $valid_lastmods as $lastmod ) {
			$entry = new SitemapIndexEntry( 'https://example.com/sitemap.xml', $lastmod );
			$this->assertEquals( $lastmod, $entry->lastmod() );
		}
	}
}

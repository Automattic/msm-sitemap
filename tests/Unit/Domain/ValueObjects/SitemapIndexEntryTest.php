<?php
/**
 * Tests for SitemapIndexEntry Value Object.
 *
 * @package Automattic\MSM_Sitemap\Tests\Unit\Domain\ValueObjects
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Unit\Domain\ValueObjects;

use Automattic\MSM_Sitemap\Domain\ValueObjects\SitemapIndexEntry;
use Automattic\MSM_Sitemap\Tests\Unit\TestCase;
use InvalidArgumentException;

/**
 * SitemapIndexEntry Value Object test case.
 *
 * Tests validation logic including:
 * - URL validation (empty, invalid format, length limits)
 * - lastmod validation (cannot be empty if provided)
 * - Equality comparison
 */
class SitemapIndexEntryTest extends TestCase {

	/**
	 * Test that a valid URL is accepted with minimal parameters.
	 */
	public function test_accepts_valid_url_with_minimal_params(): void {
		$entry = new SitemapIndexEntry( 'https://example.com/sitemap-2024-01.xml' );

		$this->assertSame( 'https://example.com/sitemap-2024-01.xml', $entry->loc() );
		$this->assertNull( $entry->lastmod() );
	}

	/**
	 * Test that a valid URL with lastmod is accepted.
	 */
	public function test_accepts_valid_url_with_lastmod(): void {
		$entry = new SitemapIndexEntry( 'https://example.com/sitemap.xml', '2024-01-15' );

		$this->assertSame( 'https://example.com/sitemap.xml', $entry->loc() );
		$this->assertSame( '2024-01-15', $entry->lastmod() );
	}

	// ===========================================
	// URL (loc) validation tests
	// ===========================================

	/**
	 * Test that empty URL throws InvalidArgumentException.
	 */
	public function test_rejects_empty_url(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Sitemap URL cannot be empty.' );

		new SitemapIndexEntry( '' );
	}

	/**
	 * Test that invalid URL format throws InvalidArgumentException.
	 */
	public function test_rejects_invalid_url_format(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Sitemap URL must be a valid URL.' );

		new SitemapIndexEntry( 'not-a-valid-url' );
	}

	/**
	 * Test that relative URL is rejected.
	 */
	public function test_rejects_relative_url(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Sitemap URL must be a valid URL.' );

		new SitemapIndexEntry( '/sitemap.xml' );
	}

	/**
	 * Test that URL exceeding 2048 characters throws InvalidArgumentException.
	 */
	public function test_rejects_url_exceeding_max_length(): void {
		$long_path = str_repeat( 'a', 2049 );
		$long_url  = 'https://example.com/' . $long_path;

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Sitemap URL cannot exceed 2048 characters.' );

		new SitemapIndexEntry( $long_url );
	}

	/**
	 * Test that URL at exactly 2048 characters is accepted.
	 */
	public function test_accepts_url_at_max_length(): void {
		$path     = str_repeat( 'a', 2028 );
		$long_url = 'https://example.com/' . $path;

		$this->assertSame( 2048, strlen( $long_url ) );

		$entry = new SitemapIndexEntry( $long_url );
		$this->assertSame( $long_url, $entry->loc() );
	}

	// ===========================================
	// lastmod validation tests
	// ===========================================

	/**
	 * Test that null lastmod is accepted.
	 */
	public function test_accepts_null_lastmod(): void {
		$entry = new SitemapIndexEntry( 'https://example.com/sitemap.xml', null );

		$this->assertNull( $entry->lastmod() );
	}

	/**
	 * Test that empty string lastmod throws InvalidArgumentException.
	 */
	public function test_rejects_empty_string_lastmod(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Last modified date cannot be empty if provided.' );

		new SitemapIndexEntry( 'https://example.com/sitemap.xml', '' );
	}

	/**
	 * Test that any non-empty lastmod string is accepted.
	 *
	 * Note: The current implementation accepts any non-empty string.
	 * When proper date validation is added, this test may need updating.
	 */
	public function test_accepts_any_non_empty_lastmod_string(): void {
		$entry = new SitemapIndexEntry( 'https://example.com/sitemap.xml', '2024-01-15T10:30:00+00:00' );

		$this->assertSame( '2024-01-15T10:30:00+00:00', $entry->lastmod() );
	}

	// ===========================================
	// to_array tests
	// ===========================================

	/**
	 * Test to_array with only loc.
	 */
	public function test_to_array_with_only_loc(): void {
		$entry = new SitemapIndexEntry( 'https://example.com/sitemap.xml' );

		$this->assertSame(
			array( 'loc' => 'https://example.com/sitemap.xml' ),
			$entry->to_array()
		);
	}

	/**
	 * Test to_array with loc and lastmod.
	 */
	public function test_to_array_with_loc_and_lastmod(): void {
		$entry = new SitemapIndexEntry( 'https://example.com/sitemap.xml', '2024-01-15' );

		$this->assertSame(
			array(
				'loc'     => 'https://example.com/sitemap.xml',
				'lastmod' => '2024-01-15',
			),
			$entry->to_array()
		);
	}

	/**
	 * Test to_array excludes null lastmod.
	 */
	public function test_to_array_excludes_null_lastmod(): void {
		$entry = new SitemapIndexEntry( 'https://example.com/sitemap.xml', null );

		$result = $entry->to_array();

		$this->assertArrayHasKey( 'loc', $result );
		$this->assertArrayNotHasKey( 'lastmod', $result );
	}

	// ===========================================
	// equals tests
	// ===========================================

	/**
	 * Test equals returns true for identical entries.
	 */
	public function test_equals_returns_true_for_identical_entries(): void {
		$entry1 = new SitemapIndexEntry( 'https://example.com/sitemap.xml', '2024-01-15' );
		$entry2 = new SitemapIndexEntry( 'https://example.com/sitemap.xml', '2024-01-15' );

		$this->assertTrue( $entry1->equals( $entry2 ) );
	}

	/**
	 * Test equals returns false when loc differs.
	 */
	public function test_equals_returns_false_when_loc_differs(): void {
		$entry1 = new SitemapIndexEntry( 'https://example.com/sitemap1.xml' );
		$entry2 = new SitemapIndexEntry( 'https://example.com/sitemap2.xml' );

		$this->assertFalse( $entry1->equals( $entry2 ) );
	}

	/**
	 * Test equals returns false when lastmod differs.
	 */
	public function test_equals_returns_false_when_lastmod_differs(): void {
		$entry1 = new SitemapIndexEntry( 'https://example.com/sitemap.xml', '2024-01-15' );
		$entry2 = new SitemapIndexEntry( 'https://example.com/sitemap.xml', '2024-01-16' );

		$this->assertFalse( $entry1->equals( $entry2 ) );
	}

	/**
	 * Test equals returns false when one has lastmod and other does not.
	 */
	public function test_equals_returns_false_when_lastmod_null_vs_set(): void {
		$entry1 = new SitemapIndexEntry( 'https://example.com/sitemap.xml', '2024-01-15' );
		$entry2 = new SitemapIndexEntry( 'https://example.com/sitemap.xml', null );

		$this->assertFalse( $entry1->equals( $entry2 ) );
	}

	/**
	 * Test equals returns true for both null lastmod.
	 */
	public function test_equals_returns_true_for_both_null_lastmod(): void {
		$entry1 = new SitemapIndexEntry( 'https://example.com/sitemap.xml' );
		$entry2 = new SitemapIndexEntry( 'https://example.com/sitemap.xml' );

		$this->assertTrue( $entry1->equals( $entry2 ) );
	}

	// ===========================================
	// Edge cases
	// ===========================================

	/**
	 * Test that HTTP URLs are accepted.
	 */
	public function test_accepts_http_urls(): void {
		$entry = new SitemapIndexEntry( 'http://example.com/sitemap.xml' );

		$this->assertSame( 'http://example.com/sitemap.xml', $entry->loc() );
	}

	/**
	 * Test that URLs with query strings are accepted.
	 */
	public function test_accepts_urls_with_query_strings(): void {
		$url   = 'https://example.com/sitemap.xml?year=2024';
		$entry = new SitemapIndexEntry( $url );

		$this->assertSame( $url, $entry->loc() );
	}
}

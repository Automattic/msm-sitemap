<?php
/**
 * Unit tests for the SitemapIndexEntry value object.
 *
 * @package Automattic\MSM_Sitemap\Tests\Unit
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Unit;

use Automattic\MSM_Sitemap\Domain\ValueObjects\SitemapIndexEntry;
use Brain\Monkey\Functions;
use InvalidArgumentException;

/**
 * Test case for SitemapIndexEntry.
 */
class SitemapIndexEntryTest extends TestCase {

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Stub the translation function to return the first argument.
		Functions\stubs( [ '__' => null ] );
	}

	/**
	 * Test creating entry with just a URL.
	 */
	public function testCreateWithLocOnly(): void {
		$entry = new SitemapIndexEntry( 'https://example.com/sitemap.xml' );

		$this->assertSame( 'https://example.com/sitemap.xml', $entry->loc() );
		$this->assertNull( $entry->lastmod() );
	}

	/**
	 * Test creating entry with URL and lastmod.
	 */
	public function testCreateWithLocAndLastmod(): void {
		$entry = new SitemapIndexEntry( 'https://example.com/sitemap.xml', '2024-06-15' );

		$this->assertSame( 'https://example.com/sitemap.xml', $entry->loc() );
		$this->assertSame( '2024-06-15', $entry->lastmod() );
	}

	/**
	 * Test that empty URL throws exception.
	 */
	public function testEmptyUrlThrowsException(): void {
		$this->expectException( InvalidArgumentException::class );

		new SitemapIndexEntry( '' );
	}

	/**
	 * Test that invalid URL format throws exception.
	 */
	public function testInvalidUrlFormatThrowsException(): void {
		$this->expectException( InvalidArgumentException::class );

		new SitemapIndexEntry( 'not-a-valid-url' );
	}

	/**
	 * Test that URL exceeding max length throws exception.
	 */
	public function testUrlExceedingMaxLengthThrowsException(): void {
		$this->expectException( InvalidArgumentException::class );

		// Create a URL longer than 2048 characters.
		$long_url = 'https://example.com/' . str_repeat( 'a', 2050 );
		new SitemapIndexEntry( $long_url );
	}

	/**
	 * Test to_array with minimal data.
	 */
	public function testToArrayMinimal(): void {
		$entry = new SitemapIndexEntry( 'https://example.com/sitemap.xml' );

		$expected = [ 'loc' => 'https://example.com/sitemap.xml' ];

		$this->assertSame( $expected, $entry->to_array() );
	}

	/**
	 * Test to_array with all data.
	 */
	public function testToArrayFull(): void {
		$entry = new SitemapIndexEntry( 'https://example.com/sitemap.xml', '2024-06-15' );

		$expected = [
			'loc'     => 'https://example.com/sitemap.xml',
			'lastmod' => '2024-06-15',
		];

		$this->assertSame( $expected, $entry->to_array() );
	}

	/**
	 * Test equals method returns true for identical entries.
	 */
	public function testEqualsReturnsTrueForIdentical(): void {
		$entry1 = new SitemapIndexEntry( 'https://example.com/sitemap.xml', '2024-06-15' );
		$entry2 = new SitemapIndexEntry( 'https://example.com/sitemap.xml', '2024-06-15' );

		$this->assertTrue( $entry1->equals( $entry2 ) );
	}

	/**
	 * Test equals method returns false for different URLs.
	 */
	public function testEqualsReturnsFalseForDifferentUrls(): void {
		$entry1 = new SitemapIndexEntry( 'https://example.com/sitemap1.xml' );
		$entry2 = new SitemapIndexEntry( 'https://example.com/sitemap2.xml' );

		$this->assertFalse( $entry1->equals( $entry2 ) );
	}

	/**
	 * Test equals method returns false for different lastmod.
	 */
	public function testEqualsReturnsFalseForDifferentLastmod(): void {
		$entry1 = new SitemapIndexEntry( 'https://example.com/sitemap.xml', '2024-06-15' );
		$entry2 = new SitemapIndexEntry( 'https://example.com/sitemap.xml', '2024-06-16' );

		$this->assertFalse( $entry1->equals( $entry2 ) );
	}

	/**
	 * Test equals with null vs non-null lastmod.
	 */
	public function testEqualsWithNullVsNonNullLastmod(): void {
		$entry1 = new SitemapIndexEntry( 'https://example.com/sitemap.xml' );
		$entry2 = new SitemapIndexEntry( 'https://example.com/sitemap.xml', '2024-06-15' );

		$this->assertFalse( $entry1->equals( $entry2 ) );
	}
}

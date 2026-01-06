<?php
/**
 * Tests for ImageEntry Value Object.
 *
 * @package Automattic\MSM_Sitemap\Tests\Unit\Domain\ValueObjects
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Unit\Domain\ValueObjects;

use Automattic\MSM_Sitemap\Domain\ValueObjects\ImageEntry;
use Automattic\MSM_Sitemap\Tests\Unit\TestCase;
use InvalidArgumentException;

/**
 * ImageEntry Value Object test case.
 *
 * Tests URL validation logic including:
 * - Empty URL rejection
 * - Invalid URL format rejection
 * - URL length limits (2048 characters max)
 * - Equality comparison
 */
class ImageEntryTest extends TestCase {

	/**
	 * Test that a valid URL is accepted.
	 */
	public function test_accepts_valid_url(): void {
		$entry = new ImageEntry( 'https://example.com/image.jpg' );

		$this->assertSame( 'https://example.com/image.jpg', $entry->loc() );
	}

	/**
	 * Test that empty URL throws InvalidArgumentException.
	 */
	public function test_rejects_empty_url(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Image URL cannot be empty.' );

		new ImageEntry( '' );
	}

	/**
	 * Test that invalid URL format throws InvalidArgumentException.
	 */
	public function test_rejects_invalid_url_format(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Image URL must be a valid URL.' );

		new ImageEntry( 'not-a-valid-url' );
	}

	/**
	 * Test that relative URL is rejected.
	 */
	public function test_rejects_relative_url(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Image URL must be a valid URL.' );

		new ImageEntry( '/images/photo.jpg' );
	}

	/**
	 * Test that URL without protocol is rejected.
	 */
	public function test_rejects_url_without_protocol(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Image URL must be a valid URL.' );

		new ImageEntry( 'example.com/image.jpg' );
	}

	/**
	 * Test that URL exceeding maximum length throws InvalidArgumentException.
	 */
	public function test_rejects_url_exceeding_max_length(): void {
		// Create a URL that exceeds 2048 characters.
		$long_path = str_repeat( 'a', 2049 );
		$long_url  = 'https://example.com/' . $long_path;

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Image URL cannot exceed 2048 characters.' );

		new ImageEntry( $long_url );
	}

	/**
	 * Test that URL at exactly max length is accepted.
	 */
	public function test_accepts_url_at_max_length(): void {
		// Create a URL that is exactly 2048 characters.
		// 'https://example.com/' is 20 characters, so we need 2028 more.
		$path     = str_repeat( 'a', 2028 );
		$long_url = 'https://example.com/' . $path;

		$this->assertSame( 2048, strlen( $long_url ) );

		$entry = new ImageEntry( $long_url );
		$this->assertSame( $long_url, $entry->loc() );
	}

	/**
	 * Test to_array returns correct structure.
	 */
	public function test_to_array_returns_expected_structure(): void {
		$entry = new ImageEntry( 'https://example.com/image.png' );

		$this->assertSame(
			array( 'loc' => 'https://example.com/image.png' ),
			$entry->to_array()
		);
	}

	/**
	 * Test equals returns true for same URL.
	 */
	public function test_equals_returns_true_for_identical_urls(): void {
		$entry1 = new ImageEntry( 'https://example.com/same.jpg' );
		$entry2 = new ImageEntry( 'https://example.com/same.jpg' );

		$this->assertTrue( $entry1->equals( $entry2 ) );
	}

	/**
	 * Test equals returns false for different URLs.
	 */
	public function test_equals_returns_false_for_different_urls(): void {
		$entry1 = new ImageEntry( 'https://example.com/first.jpg' );
		$entry2 = new ImageEntry( 'https://example.com/second.jpg' );

		$this->assertFalse( $entry1->equals( $entry2 ) );
	}

	/**
	 * Test that HTTP URLs are accepted.
	 */
	public function test_accepts_http_urls(): void {
		$entry = new ImageEntry( 'http://example.com/image.jpg' );

		$this->assertSame( 'http://example.com/image.jpg', $entry->loc() );
	}

	/**
	 * Test that URLs with query strings are accepted.
	 */
	public function test_accepts_urls_with_query_strings(): void {
		$url   = 'https://example.com/image.jpg?size=large&format=webp';
		$entry = new ImageEntry( $url );

		$this->assertSame( $url, $entry->loc() );
	}

	/**
	 * Test that URLs with special characters are handled.
	 */
	public function test_accepts_urls_with_encoded_characters(): void {
		$url   = 'https://example.com/images/my%20photo.jpg';
		$entry = new ImageEntry( $url );

		$this->assertSame( $url, $entry->loc() );
	}
}

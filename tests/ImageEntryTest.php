<?php
/**
 * ImageEntry Test
 *
 * @package Automattic\MSM_Sitemap\Tests
 */

declare(strict_types=1);

namespace Automattic\MSM_Sitemap\Tests;

use Automattic\MSM_Sitemap\Domain\ValueObjects\ImageEntry;
use InvalidArgumentException;

/**
 * Unit Tests for ImageEntry.
 *
 * Per Google's Image Sitemap specification, only the loc (URL) element is required.
 * Other elements (caption, geo_location, title, license) have been deprecated by Google.
 *
 * @see https://developers.google.com/search/docs/crawling-indexing/sitemaps/image-sitemaps
 */
class ImageEntryTest extends TestCase {

	/**
	 * Test that ImageEntry can be created with a valid URL.
	 */
	public function test_create_image_entry_with_valid_url(): void {
		$image = new ImageEntry( 'https://example.com/image.jpg' );

		$this->assertEquals( 'https://example.com/image.jpg', $image->loc() );
	}

	/**
	 * Test that ImageEntry throws exception for empty URL.
	 */
	public function test_create_image_entry_with_empty_url_throws_exception(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Image URL cannot be empty.' );

		new ImageEntry( '' );
	}

	/**
	 * Test that ImageEntry throws exception for invalid URL.
	 */
	public function test_create_image_entry_with_invalid_url_throws_exception(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Image URL must be a valid URL.' );

		new ImageEntry( 'not-a-url' );
	}

	/**
	 * Test that ImageEntry throws exception for URL that is too long.
	 */
	public function test_create_image_entry_with_url_too_long_throws_exception(): void {
		$long_url = 'https://example.com/' . str_repeat( 'a', 2048 );

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Image URL cannot exceed 2048 characters.' );

		new ImageEntry( $long_url );
	}

	/**
	 * Test that ImageEntry to_array method works correctly.
	 */
	public function test_image_entry_to_array(): void {
		$image = new ImageEntry( 'https://example.com/image.jpg' );

		$expected = array(
			'loc' => 'https://example.com/image.jpg',
		);

		$this->assertEquals( $expected, $image->to_array() );
	}

	/**
	 * Test that ImageEntry equals method works correctly.
	 */
	public function test_image_entry_equals(): void {
		$image1 = new ImageEntry( 'https://example.com/image.jpg' );
		$image2 = new ImageEntry( 'https://example.com/image.jpg' );
		$image3 = new ImageEntry( 'https://example.com/different.jpg' );

		$this->assertTrue( $image1->equals( $image2 ) );
		$this->assertFalse( $image1->equals( $image3 ) );
	}

	/**
	 * Test that ImageEntry accepts valid URL schemes.
	 *
	 * @dataProvider valid_url_schemes_data_provider
	 */
	public function test_image_entry_accepts_valid_url_schemes( string $url ): void {
		$image = new ImageEntry( $url );

		$this->assertEquals( $url, $image->loc() );
	}

	/**
	 * Data provider for valid URL schemes.
	 */
	public function valid_url_schemes_data_provider(): iterable {
		yield 'https' => array( 'url' => 'https://example.com/image.jpg' );
		yield 'http' => array( 'url' => 'http://example.com/image.jpg' );
	}
}

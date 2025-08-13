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
 */
class ImageEntryTest extends TestCase {

	/**
	 * Test that ImageEntry can be created with valid data.
	 */
	public function test_create_image_entry_with_valid_data(): void {
		$image = new ImageEntry(
			'https://example.com/image.jpg',
			'Test caption',
			'New York, NY',
			'Test Image Title',
			'https://creativecommons.org/licenses/by/4.0/'
		);

		$this->assertEquals( 'https://example.com/image.jpg', $image->loc() );
		$this->assertEquals( 'Test caption', $image->caption() );
		$this->assertEquals( 'New York, NY', $image->geo_location() );
		$this->assertEquals( 'Test Image Title', $image->title() );
		$this->assertEquals( 'https://creativecommons.org/licenses/by/4.0/', $image->license() );
	}

	/**
	 * Test that ImageEntry can be created with minimal data.
	 */
	public function test_create_image_entry_with_minimal_data(): void {
		$image = new ImageEntry( 'https://example.com/image.jpg' );

		$this->assertEquals( 'https://example.com/image.jpg', $image->loc() );
		$this->assertNull( $image->caption() );
		$this->assertNull( $image->geo_location() );
		$this->assertNull( $image->title() );
		$this->assertNull( $image->license() );
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
	 * Test that ImageEntry throws exception for caption that is too long.
	 */
	public function test_create_image_entry_with_caption_too_long_throws_exception(): void {
		$long_caption = str_repeat( 'a', 2049 );
		
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Image caption cannot exceed 2048 characters.' );

		new ImageEntry( 'https://example.com/image.jpg', $long_caption );
	}

	/**
	 * Test that ImageEntry throws exception for invalid license URL.
	 */
	public function test_create_image_entry_with_invalid_license_throws_exception(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'License URL must be a valid URL.' );

		new ImageEntry( 'https://example.com/image.jpg', null, null, null, 'not-a-url' );
	}

	/**
	 * Test that ImageEntry to_array method works correctly.
	 */
	public function test_image_entry_to_array(): void {
		$image = new ImageEntry(
			'https://example.com/image.jpg',
			'Test caption',
			'New York, NY',
			'Test Image Title',
			'https://creativecommons.org/licenses/by/4.0/'
		);

		$expected = array(
			'loc'          => 'https://example.com/image.jpg',
			'caption'      => 'Test caption',
			'geo_location' => 'New York, NY',
			'title'        => 'Test Image Title',
			'license'      => 'https://creativecommons.org/licenses/by/4.0/',
		);

		$this->assertEquals( $expected, $image->to_array() );
	}

	/**
	 * Test that ImageEntry to_array method works with minimal data.
	 */
	public function test_image_entry_to_array_with_minimal_data(): void {
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
		$image1 = new ImageEntry(
			'https://example.com/image.jpg',
			'Test caption',
			'New York, NY',
			'Test Image Title',
			'https://creativecommons.org/licenses/by/4.0/'
		);

		$image2 = new ImageEntry(
			'https://example.com/image.jpg',
			'Test caption',
			'New York, NY',
			'Test Image Title',
			'https://creativecommons.org/licenses/by/4.0/'
		);

		$image3 = new ImageEntry( 'https://example.com/different.jpg' );

		$this->assertTrue( $image1->equals( $image2 ) );
		$this->assertFalse( $image1->equals( $image3 ) );
	}
}

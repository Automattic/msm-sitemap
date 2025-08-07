<?php
/**
 * WP_Test_Sitemap_UrlEntry
 *
 * @package Metro_Sitemap/unit_tests
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests;

use Automattic\MSM_Sitemap\Domain\ValueObjects\UrlEntry;

/**
 * Unit Tests for UrlEntry value object.
 *
 * @author Gary Jones
 */
class UrlEntryTest extends TestCase {

	/**
	 * Test creating a valid URL entry.
	 */
	public function test_create_valid_url_entry(): void {
		$url_entry = new UrlEntry(
			'https://example.com/my-post/',
			'2024-01-15T10:30:00+00:00',
			'weekly',
			0.8
		);

		$this->assertEquals( 'https://example.com/my-post/', $url_entry->loc() );
		$this->assertEquals( '2024-01-15T10:30:00+00:00', $url_entry->lastmod() );
		$this->assertEquals( 'weekly', $url_entry->changefreq() );
		$this->assertEquals( 0.8, $url_entry->priority() );
	}

	/**
	 * Test creating URL entry with only required parameters.
	 */
	public function test_create_url_entry_with_only_required_parameters(): void {
		$url_entry = new UrlEntry( 'https://example.com/simple-post/' );

		$this->assertEquals( 'https://example.com/simple-post/', $url_entry->loc() );
		$this->assertNull( $url_entry->lastmod() );
		$this->assertNull( $url_entry->changefreq() );
		$this->assertNull( $url_entry->priority() );
	}

	/**
	 * Test URL validation with invalid URL.
	 */
	public function test_url_validation_with_invalid_url(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid URL format: not-a-valid-url' );

		new UrlEntry( 'not-a-valid-url' );
	}

	/**
	 * Test URL validation with empty URL.
	 */
	public function test_url_validation_with_empty_url(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'URL cannot be empty.' );

		new UrlEntry( '' );
	}

	/**
	 * Test URL validation with URL exceeding maximum length.
	 */
	public function test_url_validation_with_url_exceeding_max_length(): void {
		$long_url = 'https://example.com/' . str_repeat( 'a', 2048 );

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'URL exceeds maximum length of 2048 characters' );

		new UrlEntry( $long_url );
	}

	/**
	 * Test lastmod validation with invalid format.
	 */
	public function test_lastmod_validation_with_invalid_format(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid lastmod format: invalid-date' );

		new UrlEntry(
			'https://example.com/post/',
			'invalid-date'
		);
	}

	/**
	 * Test lastmod validation with invalid date.
	 */
	public function test_lastmod_validation_with_invalid_date(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid date in lastmod: 2024-02-30T10:30:00+00:00' );

		new UrlEntry(
			'https://example.com/post/',
			'2024-02-30T10:30:00+00:00'
		);
	}

	/**
	 * Test changefreq validation with invalid value.
	 */
	public function test_changefreq_validation_with_invalid_value(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid changefreq value: invalid-changefreq' );

		new UrlEntry(
			'https://example.com/post/',
			null,
			'invalid-changefreq'
		);
	}

	/**
	 * Test priority validation with value below minimum.
	 */
	public function test_priority_validation_with_value_below_minimum(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid priority value: -0.1' );

		new UrlEntry(
			'https://example.com/post/',
			null,
			null,
			-0.1
		);
	}

	/**
	 * Test priority validation with value above maximum.
	 */
	public function test_priority_validation_with_value_above_maximum(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid priority value: 1.1' );

		new UrlEntry(
			'https://example.com/post/',
			null,
			null,
			1.1
		);
	}

	/**
	 * Test to_array method.
	 */
	public function test_to_array(): void {
		$url_entry = new UrlEntry(
			'https://example.com/my-post/',
			'2024-01-15T10:30:00+00:00',
			'weekly',
			0.8
		);

		$array = $url_entry->to_array();

		$this->assertEquals( 'https://example.com/my-post/', $array['loc'] );
		$this->assertEquals( '2024-01-15T10:30:00+00:00', $array['lastmod'] );
		$this->assertEquals( 'weekly', $array['changefreq'] );
		$this->assertEquals( 0.8, $array['priority'] );
	}

	/**
	 * Test to_array method with optional parameters.
	 */
	public function test_to_array_with_optional_parameters(): void {
		$url_entry = new UrlEntry( 'https://example.com/simple-post/' );

		$array = $url_entry->to_array();

		$this->assertEquals( 'https://example.com/simple-post/', $array['loc'] );
		$this->assertArrayNotHasKey( 'lastmod', $array );
		$this->assertArrayNotHasKey( 'changefreq', $array );
		$this->assertArrayNotHasKey( 'priority', $array );
	}



	/**
	 * Test equals method with identical entries.
	 */
	public function test_equals_with_identical_entries(): void {
		$url_entry1 = new UrlEntry(
			'https://example.com/same-post/',
			'2024-01-15T10:30:00+00:00',
			'weekly',
			0.8
		);

		$url_entry2 = new UrlEntry(
			'https://example.com/same-post/',
			'2024-01-15T10:30:00+00:00',
			'weekly',
			0.8
		);

		$this->assertTrue( $url_entry1->equals( $url_entry2 ) );
	}

	/**
	 * Test equals method with different entries.
	 */
	public function test_equals_with_different_entries(): void {
		$url_entry1 = new UrlEntry(
			'https://example.com/post-1/',
			'2024-01-15T10:30:00+00:00',
			'weekly',
			0.8
		);

		$url_entry2 = new UrlEntry(
			'https://example.com/post-2/',
			'2024-01-15T10:30:00+00:00',
			'weekly',
			0.8
		);

		$this->assertFalse( $url_entry1->equals( $url_entry2 ) );
	}

	/**
	 * Test equals method with different lastmod.
	 */
	public function test_equals_with_different_lastmod(): void {
		$url_entry1 = new UrlEntry(
			'https://example.com/same-post/',
			'2024-01-15T10:30:00+00:00',
			'weekly',
			0.8
		);

		$url_entry2 = new UrlEntry(
			'https://example.com/same-post/',
			'2024-01-16T10:30:00+00:00',
			'weekly',
			0.8
		);

		$this->assertFalse( $url_entry1->equals( $url_entry2 ) );
	}

	/**
	 * Test valid changefreq values.
	 */
	public function test_valid_changefreq_values(): void {
		$valid_values = array( 'always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never' );

		foreach ( $valid_values as $changefreq ) {
			$url_entry = new UrlEntry(
				'https://example.com/post/',
				null,
				$changefreq
			);

			$this->assertEquals( $changefreq, $url_entry->changefreq() );
		}
	}

	/**
	 * Test valid priority values.
	 */
	public function test_valid_priority_values(): void {
		$valid_values = array( 0.0, 0.1, 0.5, 0.9, 1.0 );

		foreach ( $valid_values as $priority ) {
			$url_entry = new UrlEntry(
				'https://example.com/post/',
				null,
				null,
				$priority
			);

			$this->assertEquals( $priority, $url_entry->priority() );
		}
	}

	/**
	 * Test valid lastmod formats.
	 */
	public function test_valid_lastmod_formats(): void {
		$valid_formats = array(
			'2024-01-15',
			'2024-01-15T10:30:00+00:00',
			'2024-01-15T10:30:00-05:00',
		);

		foreach ( $valid_formats as $lastmod ) {
			$url_entry = new UrlEntry(
				'https://example.com/post/',
				$lastmod
			);

			$this->assertEquals( $lastmod, $url_entry->lastmod() );
		}
	}
} 

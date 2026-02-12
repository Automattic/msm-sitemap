<?php
/**
 * Tests for UrlEntry Value Object.
 *
 * @package Automattic\MSM_Sitemap\Tests\Unit\Domain\ValueObjects
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Unit\Domain\ValueObjects;

use Automattic\MSM_Sitemap\Domain\ValueObjects\ImageEntry;
use Automattic\MSM_Sitemap\Domain\ValueObjects\UrlEntry;
use Automattic\MSM_Sitemap\Tests\Generators\ValidUrlGenerator;
use Automattic\MSM_Sitemap\Tests\Unit\TestCase;
use Eris\Generators;
use Eris\TestTrait;
use InvalidArgumentException;

/**
 * UrlEntry Value Object test case.
 *
 * Tests validation logic including:
 * - URL validation (empty, invalid format, length limits)
 * - lastmod format validation (W3C Datetime)
 * - changefreq validation (must be valid sitemap protocol value)
 * - priority validation (must be between 0.0 and 1.0)
 * - images validation (must be ImageEntry objects, max 1000)
 */
class UrlEntryTest extends TestCase {

	use TestTrait;

	/**
	 * Test that a valid URL is accepted with minimal parameters.
	 */
	public function test_accepts_valid_url_with_minimal_params(): void {
		$entry = new UrlEntry( 'https://example.com/page' );

		$this->assertSame( 'https://example.com/page', $entry->loc() );
		$this->assertNull( $entry->lastmod() );
		$this->assertNull( $entry->changefreq() );
		$this->assertNull( $entry->priority() );
		$this->assertSame( array(), $entry->images() );
	}

	/**
	 * Test that a valid URL with all parameters is accepted.
	 */
	public function test_accepts_valid_url_with_all_params(): void {
		$image = new ImageEntry( 'https://example.com/image.jpg' );
		$entry = new UrlEntry(
			'https://example.com/page',
			'2024-01-15',
			'weekly',
			0.8,
			array( $image )
		);

		$this->assertSame( 'https://example.com/page', $entry->loc() );
		$this->assertSame( '2024-01-15', $entry->lastmod() );
		$this->assertSame( 'weekly', $entry->changefreq() );
		$this->assertSame( 0.8, $entry->priority() );
		$this->assertCount( 1, $entry->images() );
	}

	// ===========================================
	// URL (loc) validation tests
	// ===========================================

	/**
	 * Test that empty URL throws InvalidArgumentException.
	 */
	public function test_rejects_empty_url(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'URL cannot be empty.' );

		new UrlEntry( '' );
	}

	/**
	 * Test that invalid URL format throws InvalidArgumentException.
	 */
	public function test_rejects_invalid_url_format(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid URL format:' );

		new UrlEntry( 'not-a-valid-url' );
	}

	/**
	 * Test that URL exceeding 2048 characters throws InvalidArgumentException.
	 */
	public function test_rejects_url_exceeding_max_length(): void {
		$long_path = str_repeat( 'a', 2049 );
		$long_url  = 'https://example.com/' . $long_path;

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'URL exceeds maximum length of 2048 characters' );

		new UrlEntry( $long_url );
	}

	/**
	 * Test that URL at exactly 2048 characters is accepted.
	 */
	public function test_accepts_url_at_max_length(): void {
		$path     = str_repeat( 'a', 2028 );
		$long_url = 'https://example.com/' . $path;

		$this->assertSame( 2048, strlen( $long_url ) );

		$entry = new UrlEntry( $long_url );
		$this->assertSame( $long_url, $entry->loc() );
	}

	// ===========================================
	// lastmod validation tests
	// ===========================================

	/**
	 * Test that valid date-only lastmod is accepted.
	 */
	public function test_accepts_valid_date_only_lastmod(): void {
		$entry = new UrlEntry( 'https://example.com/page', '2024-01-15' );

		$this->assertSame( '2024-01-15', $entry->lastmod() );
	}

	/**
	 * Test that valid datetime lastmod with timezone is accepted.
	 */
	public function test_accepts_valid_datetime_with_timezone(): void {
		$entry = new UrlEntry( 'https://example.com/page', '2024-01-15T10:30:00+00:00' );

		$this->assertSame( '2024-01-15T10:30:00+00:00', $entry->lastmod() );
	}

	/**
	 * Test that valid datetime with negative timezone offset is accepted.
	 */
	public function test_accepts_datetime_with_negative_timezone(): void {
		$entry = new UrlEntry( 'https://example.com/page', '2024-06-20T15:45:30-05:00' );

		$this->assertSame( '2024-06-20T15:45:30-05:00', $entry->lastmod() );
	}

	/**
	 * Test that invalid lastmod format throws InvalidArgumentException.
	 */
	public function test_rejects_invalid_lastmod_format(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid lastmod format:' );

		new UrlEntry( 'https://example.com/page', '15-01-2024' );
	}

	/**
	 * Test that lastmod without timezone separator is rejected.
	 */
	public function test_rejects_datetime_without_timezone(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid lastmod format:' );

		new UrlEntry( 'https://example.com/page', '2024-01-15T10:30:00' );
	}

	/**
	 * Test that lastmod with invalid date throws InvalidArgumentException.
	 */
	public function test_rejects_invalid_calendar_date_in_lastmod(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid date in lastmod:' );

		// February 31st is not a valid date.
		new UrlEntry( 'https://example.com/page', '2024-02-31' );
	}

	/**
	 * Test that lastmod with invalid month throws InvalidArgumentException.
	 */
	public function test_rejects_invalid_month_in_lastmod(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid date in lastmod:' );

		// Month 13 does not exist.
		new UrlEntry( 'https://example.com/page', '2024-13-15' );
	}

	// ===========================================
	// changefreq validation tests
	// ===========================================

	/**
	 * @dataProvider valid_changefreq_provider
	 *
	 * Test that all valid changefreq values are accepted.
	 *
	 * @param string $changefreq The changefreq value to test.
	 */
	public function test_accepts_valid_changefreq_values( string $changefreq ): void {
		$entry = new UrlEntry( 'https://example.com/page', null, $changefreq );

		$this->assertSame( $changefreq, $entry->changefreq() );
	}

	/**
	 * Data provider for valid changefreq values.
	 *
	 * @return array<string, array<string>>
	 */
	public static function valid_changefreq_provider(): array {
		return array(
			'always'  => array( 'always' ),
			'hourly'  => array( 'hourly' ),
			'daily'   => array( 'daily' ),
			'weekly'  => array( 'weekly' ),
			'monthly' => array( 'monthly' ),
			'yearly'  => array( 'yearly' ),
			'never'   => array( 'never' ),
		);
	}

	/**
	 * Test that invalid changefreq value throws InvalidArgumentException.
	 */
	public function test_rejects_invalid_changefreq(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid changefreq value: biweekly' );

		new UrlEntry( 'https://example.com/page', null, 'biweekly' );
	}

	/**
	 * Test that uppercase changefreq is rejected (case-sensitive).
	 */
	public function test_rejects_uppercase_changefreq(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid changefreq value: DAILY' );

		new UrlEntry( 'https://example.com/page', null, 'DAILY' );
	}

	// ===========================================
	// priority validation tests
	// ===========================================

	/**
	 * Test that priority of 0.0 is accepted.
	 */
	public function test_accepts_priority_at_minimum(): void {
		$entry = new UrlEntry( 'https://example.com/page', null, null, 0.0 );

		$this->assertSame( 0.0, $entry->priority() );
	}

	/**
	 * Test that priority of 1.0 is accepted.
	 */
	public function test_accepts_priority_at_maximum(): void {
		$entry = new UrlEntry( 'https://example.com/page', null, null, 1.0 );

		$this->assertSame( 1.0, $entry->priority() );
	}

	/**
	 * Test that priority of 0.5 is accepted.
	 */
	public function test_accepts_priority_in_middle(): void {
		$entry = new UrlEntry( 'https://example.com/page', null, null, 0.5 );

		$this->assertSame( 0.5, $entry->priority() );
	}

	/**
	 * Test that negative priority throws InvalidArgumentException.
	 */
	public function test_rejects_negative_priority(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid priority value:' );

		new UrlEntry( 'https://example.com/page', null, null, -0.1 );
	}

	/**
	 * Test that priority greater than 1.0 throws InvalidArgumentException.
	 */
	public function test_rejects_priority_above_maximum(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid priority value:' );

		new UrlEntry( 'https://example.com/page', null, null, 1.1 );
	}

	// ===========================================
	// images validation tests
	// ===========================================

	/**
	 * Test that valid images array is accepted.
	 */
	public function test_accepts_valid_images(): void {
		$images = array(
			new ImageEntry( 'https://example.com/image1.jpg' ),
			new ImageEntry( 'https://example.com/image2.png' ),
		);

		$entry = new UrlEntry( 'https://example.com/page', null, null, null, $images );

		$this->assertCount( 2, $entry->images() );
		$this->assertTrue( $entry->has_images() );
		$this->assertSame( 2, $entry->image_count() );
	}

	/**
	 * Test that empty images array results in no images.
	 */
	public function test_accepts_empty_images_array(): void {
		$entry = new UrlEntry( 'https://example.com/page', null, null, null, array() );

		$this->assertSame( array(), $entry->images() );
		$this->assertFalse( $entry->has_images() );
		$this->assertSame( 0, $entry->image_count() );
	}

	/**
	 * Test that null images results in empty array.
	 */
	public function test_null_images_results_in_empty_array(): void {
		$entry = new UrlEntry( 'https://example.com/page', null, null, null, null );

		$this->assertSame( array(), $entry->images() );
		$this->assertFalse( $entry->has_images() );
	}

	/**
	 * Test that non-ImageEntry objects in images array throws InvalidArgumentException.
	 */
	public function test_rejects_non_image_entry_in_images(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'All images must be ImageEntry objects.' );

		// @phpstan-ignore-next-line
		new UrlEntry( 'https://example.com/page', null, null, null, array( 'not-an-image' ) );
	}

	/**
	 * Test that exceeding 1000 images throws InvalidArgumentException.
	 */
	public function test_rejects_more_than_1000_images(): void {
		$images = array();
		for ( $i = 0; $i < 1001; $i++ ) {
			$images[] = new ImageEntry( "https://example.com/image{$i}.jpg" );
		}

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Too many images: 1001 provided, maximum is 1000' );

		new UrlEntry( 'https://example.com/page', null, null, null, $images );
	}

	/**
	 * Test that exactly 1000 images is accepted.
	 */
	public function test_accepts_exactly_1000_images(): void {
		$images = array();
		for ( $i = 0; $i < 1000; $i++ ) {
			$images[] = new ImageEntry( "https://example.com/image{$i}.jpg" );
		}

		$entry = new UrlEntry( 'https://example.com/page', null, null, null, $images );

		$this->assertSame( 1000, $entry->image_count() );
	}

	// ===========================================
	// to_array() tests
	// ===========================================

	/**
	 * Test to_array returns only loc for minimal entry.
	 */
	public function test_to_array_with_minimal_entry(): void {
		$entry = new UrlEntry( 'https://example.com/page' );

		$this->assertSame(
			array( 'loc' => 'https://example.com/page' ),
			$entry->to_array()
		);
	}

	/**
	 * Test to_array includes all set properties.
	 */
	public function test_to_array_with_all_properties(): void {
		$image = new ImageEntry( 'https://example.com/image.jpg' );
		$entry = new UrlEntry(
			'https://example.com/page',
			'2024-01-15',
			'weekly',
			0.8,
			array( $image )
		);

		$result = $entry->to_array();

		$this->assertSame( 'https://example.com/page', $result['loc'] );
		$this->assertSame( '2024-01-15', $result['lastmod'] );
		$this->assertSame( 'weekly', $result['changefreq'] );
		$this->assertSame( 0.8, $result['priority'] );
		$this->assertArrayHasKey( 'images', $result );
		$this->assertCount( 1, $result['images'] );
	}

	/**
	 * Test to_array excludes null values.
	 */
	public function test_to_array_excludes_null_values(): void {
		$entry = new UrlEntry( 'https://example.com/page', '2024-01-15' );

		$result = $entry->to_array();

		$this->assertArrayHasKey( 'loc', $result );
		$this->assertArrayHasKey( 'lastmod', $result );
		$this->assertArrayNotHasKey( 'changefreq', $result );
		$this->assertArrayNotHasKey( 'priority', $result );
		$this->assertArrayNotHasKey( 'images', $result );
	}

	// ===========================================
	// equals() tests
	// ===========================================

	/**
	 * Test equals returns true for identical entries.
	 */
	public function test_equals_returns_true_for_identical_entries(): void {
		$entry1 = new UrlEntry( 'https://example.com/page', '2024-01-15', 'weekly', 0.8 );
		$entry2 = new UrlEntry( 'https://example.com/page', '2024-01-15', 'weekly', 0.8 );

		$this->assertTrue( $entry1->equals( $entry2 ) );
	}

	/**
	 * Test equals returns false when loc differs.
	 */
	public function test_equals_returns_false_when_loc_differs(): void {
		$entry1 = new UrlEntry( 'https://example.com/page1' );
		$entry2 = new UrlEntry( 'https://example.com/page2' );

		$this->assertFalse( $entry1->equals( $entry2 ) );
	}

	/**
	 * Test equals returns false when lastmod differs.
	 */
	public function test_equals_returns_false_when_lastmod_differs(): void {
		$entry1 = new UrlEntry( 'https://example.com/page', '2024-01-15' );
		$entry2 = new UrlEntry( 'https://example.com/page', '2024-01-16' );

		$this->assertFalse( $entry1->equals( $entry2 ) );
	}

	/**
	 * Test equals returns false when changefreq differs.
	 */
	public function test_equals_returns_false_when_changefreq_differs(): void {
		$entry1 = new UrlEntry( 'https://example.com/page', null, 'daily' );
		$entry2 = new UrlEntry( 'https://example.com/page', null, 'weekly' );

		$this->assertFalse( $entry1->equals( $entry2 ) );
	}

	/**
	 * Test equals returns false when priority differs.
	 */
	public function test_equals_returns_false_when_priority_differs(): void {
		$entry1 = new UrlEntry( 'https://example.com/page', null, null, 0.5 );
		$entry2 = new UrlEntry( 'https://example.com/page', null, null, 0.6 );

		$this->assertFalse( $entry1->equals( $entry2 ) );
	}

	/**
	 * Test that equals does not compare images (by design).
	 */
	public function test_equals_ignores_images(): void {
		$entry1 = new UrlEntry(
			'https://example.com/page',
			null,
			null,
			null,
			array( new ImageEntry( 'https://example.com/img1.jpg' ) )
		);
		$entry2 = new UrlEntry(
			'https://example.com/page',
			null,
			null,
			null,
			array( new ImageEntry( 'https://example.com/img2.jpg' ) )
		);

		// Note: equals() does not compare images - this is by design per the implementation.
		$this->assertTrue( $entry1->equals( $entry2 ) );
	}

	// ===========================================
	// Property-based tests (Eris)
	// ===========================================

	/**
	 * Property: any float in [0.0, 1.0] is a valid priority value.
	 *
	 * The sitemap protocol specifies priority as a value between 0.0 and 1.0.
	 * Any float within this range should be accepted without exception.
	 */
	public function test_property_any_priority_in_valid_range_is_accepted(): void {
		$this
			->forAll(
				Generators::choose( 0, 1000 )
			)
			->then( function ( int $int_value ): void {
				// Map integer 0-1000 to float 0.0-1.0 for precise control.
				$priority = $int_value / 1000.0;
				$entry    = new UrlEntry( 'https://example.com/page', null, null, $priority );

				$this->assertSame( $priority, $entry->priority() );
				$this->assertTrue(
					$entry->priority() >= 0.0 && $entry->priority() <= 1.0,
					sprintf( 'Priority %f should be within [0.0, 1.0]', $entry->priority() )
				);
			} );
	}

	/**
	 * Property: any float outside [0.0, 1.0] is rejected as a priority value.
	 *
	 * Floats below 0.0 or above 1.0 must always throw InvalidArgumentException.
	 */
	public function test_property_any_priority_outside_valid_range_is_rejected(): void {
		$this
			->forAll(
				Generators::choose( 1, 10000 )
			)
			->then( function ( int $offset ): void {
				// Generate a value above 1.0 (from 1.001 to 11.0).
				$above = 1.0 + ( $offset / 1000.0 );
				$caught_above = false;
				try {
					new UrlEntry( 'https://example.com/page', null, null, $above );
				} catch ( InvalidArgumentException $e ) {
					$caught_above = true;
				}
				$this->assertTrue(
					$caught_above,
					sprintf( 'Priority %f (above 1.0) should be rejected', $above )
				);

				// Generate a value below 0.0 (from -0.001 to -10.0).
				$below = -1.0 * ( $offset / 1000.0 );
				$caught_below = false;
				try {
					new UrlEntry( 'https://example.com/page', null, null, $below );
				} catch ( InvalidArgumentException $e ) {
					$caught_below = true;
				}
				$this->assertTrue(
					$caught_below,
					sprintf( 'Priority %f (below 0.0) should be rejected', $below )
				);
			} );
	}

	/**
	 * Property: reflexive equality holds for any UrlEntry.
	 *
	 * Every UrlEntry must be equal to itself: $x->equals($x) is always true.
	 */
	public function test_property_reflexive_equality(): void {
		$this
			->forAll(
				new ValidUrlGenerator(),
				Generators::elements( 'always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never' ),
				Generators::choose( 0, 100 )
			)
			->then( function ( string $url, string $changefreq, int $priority_int ): void {
				$priority = $priority_int / 100.0;
				$entry    = new UrlEntry( $url, '2024-01-15', $changefreq, $priority );

				$this->assertTrue(
					$entry->equals( $entry ),
					'UrlEntry must be equal to itself (reflexive equality)'
				);
			} );
	}

	/**
	 * Property: to_array always contains the loc key.
	 *
	 * The 'loc' field is required by the sitemap protocol and must always
	 * appear in the array representation, regardless of other optional fields.
	 */
	public function test_property_to_array_always_contains_loc(): void {
		$this
			->forAll(
				new ValidUrlGenerator()
			)
			->then( function ( string $url ): void {
				$entry = new UrlEntry( $url );
				$array = $entry->to_array();

				$this->assertArrayHasKey( 'loc', $array );
				$this->assertSame( $url, $array['loc'] );
			} );
	}

	/**
	 * Property: to_array includes priority when set, excludes when null.
	 *
	 * The priority key should only appear in to_array output when a
	 * priority value was provided during construction.
	 */
	public function test_property_to_array_priority_presence_matches_construction(): void {
		$this
			->forAll(
				Generators::choose( 0, 100 )
			)
			->then( function ( int $priority_int ): void {
				$priority = $priority_int / 100.0;
				$entry    = new UrlEntry( 'https://example.com/page', null, null, $priority );
				$array    = $entry->to_array();

				$this->assertArrayHasKey( 'priority', $array );
				$this->assertSame( $priority, $array['priority'] );
			} );
	}
}

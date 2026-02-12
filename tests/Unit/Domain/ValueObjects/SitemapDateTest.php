<?php
/**
 * Tests for SitemapDate Value Object.
 *
 * @package Automattic\MSM_Sitemap\Tests\Unit\Domain\ValueObjects
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Unit\Domain\ValueObjects;

use Automattic\MSM_Sitemap\Domain\ValueObjects\SitemapDate;
use Automattic\MSM_Sitemap\Tests\Generators\SitemapDateGenerator;
use Automattic\MSM_Sitemap\Tests\Unit\TestCase;
use Eris\TestTrait;
use InvalidArgumentException;

/**
 * SitemapDate Value Object test case.
 *
 * Tests:
 * - Date validation (invalid month/day/year combinations)
 * - String parsing (Y-m-d and MySQL datetime formats)
 * - Date comparison (isBefore, isAfter, equals)
 * - Formatted output methods
 * - Immutability
 */
class SitemapDateTest extends TestCase {

	use TestTrait;

	// ===========================================
	// Constructor validation tests
	// ===========================================

	/**
	 * Test that valid date is accepted.
	 */
	public function test_accepts_valid_date(): void {
		$date = new SitemapDate( 2024, 1, 15 );

		$this->assertSame( 2024, $date->year() );
		$this->assertSame( 1, $date->month() );
		$this->assertSame( 15, $date->day() );
	}

	/**
	 * Test that February 29 is accepted in leap year.
	 */
	public function test_accepts_feb_29_in_leap_year(): void {
		$date = new SitemapDate( 2024, 2, 29 ); // 2024 is a leap year.

		$this->assertSame( 29, $date->day() );
	}

	/**
	 * Test that February 29 is rejected in non-leap year.
	 */
	public function test_rejects_feb_29_in_non_leap_year(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid date: 2023-2-29' );

		new SitemapDate( 2023, 2, 29 ); // 2023 is not a leap year.
	}

	/**
	 * Test that invalid month throws exception.
	 */
	public function test_rejects_invalid_month(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid date: 2024-13-15' );

		new SitemapDate( 2024, 13, 15 );
	}

	/**
	 * Test that month 0 throws exception.
	 */
	public function test_rejects_month_zero(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid date: 2024-0-15' );

		new SitemapDate( 2024, 0, 15 );
	}

	/**
	 * Test that invalid day throws exception.
	 */
	public function test_rejects_invalid_day(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid date: 2024-4-31' );

		new SitemapDate( 2024, 4, 31 ); // April has only 30 days.
	}

	/**
	 * Test that day 0 throws exception.
	 */
	public function test_rejects_day_zero(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid date: 2024-1-0' );

		new SitemapDate( 2024, 1, 0 );
	}

	/**
	 * Test that day 32 throws exception.
	 */
	public function test_rejects_day_32(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid date: 2024-1-32' );

		new SitemapDate( 2024, 1, 32 );
	}

	// ===========================================
	// fromString() tests
	// ===========================================

	/**
	 * Test that valid Y-m-d string is parsed correctly.
	 */
	public function test_from_string_parses_ymd_format(): void {
		$date = SitemapDate::fromString( '2024-01-15' );

		$this->assertSame( 2024, $date->year() );
		$this->assertSame( 1, $date->month() );
		$this->assertSame( 15, $date->day() );
	}

	/**
	 * Test that MySQL datetime format is parsed correctly.
	 */
	public function test_from_string_parses_mysql_datetime(): void {
		$date = SitemapDate::fromString( '2024-01-15 10:30:00' );

		$this->assertSame( 2024, $date->year() );
		$this->assertSame( 1, $date->month() );
		$this->assertSame( 15, $date->day() );
	}

	/**
	 * Test that D-M-Y format is parsed (but produces unexpected results).
	 *
	 * Note: The implementation doesn't validate Y-m-d format explicitly.
	 * '15-01-2024' parses as year=15, month=1, day=20 (first 2 chars of '2024').
	 * This documents the actual behavior.
	 */
	public function test_from_string_parses_dmy_as_short_year(): void {
		$date = SitemapDate::fromString( '15-01-2024' );

		// This is parsed as year 15, month 01, day 20 (first 2 chars of '2024')
		$this->assertSame( 15, $date->year() );
		$this->assertSame( 1, $date->month() );
		$this->assertSame( 20, $date->day() );
	}

	/**
	 * Test that string with only year-month throws exception.
	 */
	public function test_from_string_rejects_incomplete_date(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid date format: 2024-01. Expected Y-m-d.' );

		SitemapDate::fromString( '2024-01' );
	}

	/**
	 * Test that empty string throws exception.
	 */
	public function test_from_string_rejects_empty_string(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid date format: . Expected Y-m-d.' );

		SitemapDate::fromString( '' );
	}

	/**
	 * Test that fromString with invalid date throws exception.
	 */
	public function test_from_string_validates_date(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid date: 2024-2-30' );

		SitemapDate::fromString( '2024-02-30' );
	}

	// ===========================================
	// today() tests
	// ===========================================

	/**
	 * Test that today returns current date.
	 */
	public function test_today_returns_current_date(): void {
		$today    = SitemapDate::today();
		$expected = gmdate( 'Y-m-d' );

		$this->assertSame( $expected, $today->toString() );
	}

	// ===========================================
	// Formatted output tests
	// ===========================================

	/**
	 * Test toString returns Y-m-d format with zero-padding.
	 */
	public function test_to_string_returns_padded_ymd(): void {
		$date = new SitemapDate( 2024, 1, 5 );

		$this->assertSame( '2024-01-05', $date->toString() );
	}

	/**
	 * Test __toString magic method.
	 */
	public function test_magic_to_string(): void {
		$date = new SitemapDate( 2024, 12, 25 );

		$this->assertSame( '2024-12-25', (string) $date );
	}

	/**
	 * Test yearString returns 4-digit year.
	 */
	public function test_year_string_returns_padded_year(): void {
		$date = new SitemapDate( 2024, 1, 1 );

		$this->assertSame( '2024', $date->yearString() );
	}

	/**
	 * Test monthString returns 2-digit month.
	 */
	public function test_month_string_returns_padded_month(): void {
		$date = new SitemapDate( 2024, 5, 1 );

		$this->assertSame( '05', $date->monthString() );
	}

	/**
	 * Test dayString returns 2-digit day.
	 */
	public function test_day_string_returns_padded_day(): void {
		$date = new SitemapDate( 2024, 1, 8 );

		$this->assertSame( '08', $date->dayString() );
	}

	/**
	 * Test toUrlParams returns correct structure.
	 */
	public function test_to_url_params_returns_correct_structure(): void {
		$date   = new SitemapDate( 2024, 3, 15 );
		$params = $date->toUrlParams();

		$this->assertSame(
			array(
				'yyyy' => '2024',
				'mm'   => '03',
				'dd'   => '15',
			),
			$params
		);
	}

	/**
	 * Test toMysqlDatetime with default time.
	 */
	public function test_to_mysql_datetime_with_default_time(): void {
		$date = new SitemapDate( 2024, 6, 20 );

		$this->assertSame( '2024-06-20 00:00:00', $date->toMysqlDatetime() );
	}

	/**
	 * Test toMysqlDatetime with custom time.
	 */
	public function test_to_mysql_datetime_with_custom_time(): void {
		$date = new SitemapDate( 2024, 6, 20 );

		$this->assertSame( '2024-06-20 23:59:59', $date->toMysqlDatetime( '23:59:59' ) );
	}

	// ===========================================
	// Comparison tests
	// ===========================================

	/**
	 * Test equals returns true for same date.
	 */
	public function test_equals_returns_true_for_same_date(): void {
		$date1 = new SitemapDate( 2024, 1, 15 );
		$date2 = new SitemapDate( 2024, 1, 15 );

		$this->assertTrue( $date1->equals( $date2 ) );
	}

	/**
	 * Test equals returns false when year differs.
	 */
	public function test_equals_returns_false_when_year_differs(): void {
		$date1 = new SitemapDate( 2024, 1, 15 );
		$date2 = new SitemapDate( 2023, 1, 15 );

		$this->assertFalse( $date1->equals( $date2 ) );
	}

	/**
	 * Test equals returns false when month differs.
	 */
	public function test_equals_returns_false_when_month_differs(): void {
		$date1 = new SitemapDate( 2024, 1, 15 );
		$date2 = new SitemapDate( 2024, 2, 15 );

		$this->assertFalse( $date1->equals( $date2 ) );
	}

	/**
	 * Test equals returns false when day differs.
	 */
	public function test_equals_returns_false_when_day_differs(): void {
		$date1 = new SitemapDate( 2024, 1, 15 );
		$date2 = new SitemapDate( 2024, 1, 16 );

		$this->assertFalse( $date1->equals( $date2 ) );
	}

	/**
	 * Test isBefore returns true when date is before.
	 */
	public function test_is_before_returns_true_when_before(): void {
		$earlier = new SitemapDate( 2024, 1, 1 );
		$later   = new SitemapDate( 2024, 1, 15 );

		$this->assertTrue( $earlier->isBefore( $later ) );
	}

	/**
	 * Test isBefore returns false when date is after.
	 */
	public function test_is_before_returns_false_when_after(): void {
		$earlier = new SitemapDate( 2024, 1, 1 );
		$later   = new SitemapDate( 2024, 1, 15 );

		$this->assertFalse( $later->isBefore( $earlier ) );
	}

	/**
	 * Test isBefore returns false when dates are equal.
	 */
	public function test_is_before_returns_false_when_equal(): void {
		$date1 = new SitemapDate( 2024, 1, 15 );
		$date2 = new SitemapDate( 2024, 1, 15 );

		$this->assertFalse( $date1->isBefore( $date2 ) );
	}

	/**
	 * Test isBefore compares years correctly.
	 */
	public function test_is_before_compares_years(): void {
		$earlier = new SitemapDate( 2023, 12, 31 );
		$later   = new SitemapDate( 2024, 1, 1 );

		$this->assertTrue( $earlier->isBefore( $later ) );
	}

	/**
	 * Test isBefore compares months correctly.
	 */
	public function test_is_before_compares_months(): void {
		$earlier = new SitemapDate( 2024, 1, 31 );
		$later   = new SitemapDate( 2024, 2, 1 );

		$this->assertTrue( $earlier->isBefore( $later ) );
	}

	/**
	 * Test isAfter returns true when date is after.
	 */
	public function test_is_after_returns_true_when_after(): void {
		$earlier = new SitemapDate( 2024, 1, 1 );
		$later   = new SitemapDate( 2024, 1, 15 );

		$this->assertTrue( $later->isAfter( $earlier ) );
	}

	/**
	 * Test isAfter returns false when date is before.
	 */
	public function test_is_after_returns_false_when_before(): void {
		$earlier = new SitemapDate( 2024, 1, 1 );
		$later   = new SitemapDate( 2024, 1, 15 );

		$this->assertFalse( $earlier->isAfter( $later ) );
	}

	/**
	 * Test isAfter returns false when dates are equal.
	 */
	public function test_is_after_returns_false_when_equal(): void {
		$date1 = new SitemapDate( 2024, 1, 15 );
		$date2 = new SitemapDate( 2024, 1, 15 );

		$this->assertFalse( $date1->isAfter( $date2 ) );
	}

	// ===========================================
	// Edge cases
	// ===========================================

	/**
	 * Test first day of year.
	 */
	public function test_first_day_of_year(): void {
		$date = new SitemapDate( 2024, 1, 1 );

		$this->assertSame( '2024-01-01', $date->toString() );
	}

	/**
	 * Test last day of year.
	 */
	public function test_last_day_of_year(): void {
		$date = new SitemapDate( 2024, 12, 31 );

		$this->assertSame( '2024-12-31', $date->toString() );
	}

	/**
	 * Test various month end days.
	 *
	 * @dataProvider month_end_days_provider
	 *
	 * @param int $month       The month.
	 * @param int $last_day    The last day of the month.
	 */
	public function test_month_end_days( int $month, int $last_day ): void {
		$date = new SitemapDate( 2024, $month, $last_day );

		$this->assertSame( $last_day, $date->day() );
	}

	/**
	 * Data provider for month end days.
	 *
	 * @return array<string, array{int, int}>
	 */
	public static function month_end_days_provider(): array {
		return array(
			'January 31'   => array( 1, 31 ),
			'February 29'  => array( 2, 29 ), // 2024 is a leap year.
			'March 31'     => array( 3, 31 ),
			'April 30'     => array( 4, 30 ),
			'May 31'       => array( 5, 31 ),
			'June 30'      => array( 6, 30 ),
			'July 31'      => array( 7, 31 ),
			'August 31'    => array( 8, 31 ),
			'September 30' => array( 9, 30 ),
			'October 31'   => array( 10, 31 ),
			'November 30'  => array( 11, 30 ),
			'December 31'  => array( 12, 31 ),
		);
	}

	// ===========================================
	// Property-based tests (Eris)
	// ===========================================

	/**
	 * Property: trichotomy holds for any two SitemapDates.
	 *
	 * For any two dates A and B, exactly one of these is true:
	 * - A->isBefore(B)
	 * - A->equals(B)
	 * - A->isAfter(B)
	 *
	 * This is a fundamental ordering property that ensures the comparison
	 * methods form a total order over the date domain.
	 */
	public function test_property_trichotomy_exactly_one_comparison_holds(): void {
		$this
			->forAll(
				new SitemapDateGenerator(),
				new SitemapDateGenerator()
			)
			->then( function ( SitemapDate $a, SitemapDate $b ): void {
				$is_before = $a->isBefore( $b );
				$is_equal  = $a->equals( $b );
				$is_after  = $a->isAfter( $b );

				$true_count = (int) $is_before + (int) $is_equal + (int) $is_after;

				$this->assertSame(
					1,
					$true_count,
					sprintf(
						'Exactly one of isBefore/equals/isAfter should hold for %s vs %s. Got: before=%s, equal=%s, after=%s',
						$a->toString(),
						$b->toString(),
						$is_before ? 'true' : 'false',
						$is_equal ? 'true' : 'false',
						$is_after ? 'true' : 'false'
					)
				);
			} );
	}

	/**
	 * Property: isBefore implies isAfter in reverse (anti-symmetry).
	 *
	 * If A->isBefore(B), then B->isAfter(A) must also hold.
	 * This ensures the ordering is consistent in both directions.
	 */
	public function test_property_is_before_implies_is_after_in_reverse(): void {
		$this
			->forAll(
				new SitemapDateGenerator(),
				new SitemapDateGenerator()
			)
			->then( function ( SitemapDate $a, SitemapDate $b ): void {
				if ( $a->isBefore( $b ) ) {
					$this->assertTrue(
						$b->isAfter( $a ),
						sprintf(
							'If %s isBefore %s, then %s should be isAfter %s',
							$a->toString(),
							$b->toString(),
							$b->toString(),
							$a->toString()
						)
					);
				}

				if ( $a->isAfter( $b ) ) {
					$this->assertTrue(
						$b->isBefore( $a ),
						sprintf(
							'If %s isAfter %s, then %s should be isBefore %s',
							$a->toString(),
							$b->toString(),
							$b->toString(),
							$a->toString()
						)
					);
				}
			} );
	}

	/**
	 * Property: toString roundtrip preserves the date.
	 *
	 * For any valid SitemapDate, converting to string and back via
	 * fromString should produce an equal date. This guarantees that
	 * the serialisation format is lossless.
	 */
	public function test_property_to_string_roundtrip(): void {
		$this
			->forAll(
				new SitemapDateGenerator()
			)
			->then( function ( SitemapDate $date ): void {
				$roundtripped = SitemapDate::fromString( $date->toString() );

				$this->assertTrue(
					$date->equals( $roundtripped ),
					sprintf(
						'SitemapDate::fromString(%s)->equals(original) should be true, got %s',
						$date->toString(),
						$roundtripped->toString()
					)
				);
			} );
	}

	/**
	 * Property: reflexive equality holds for any SitemapDate.
	 *
	 * Every date must be equal to itself.
	 */
	public function test_property_reflexive_equality(): void {
		$this
			->forAll(
				new SitemapDateGenerator()
			)
			->then( function ( SitemapDate $date ): void {
				$this->assertTrue(
					$date->equals( $date ),
					sprintf(
						'SitemapDate %s must be equal to itself',
						$date->toString()
					)
				);
			} );
	}

	/**
	 * Property: equal dates are never before or after each other.
	 *
	 * If A->equals(B), then both isBefore and isAfter must be false.
	 */
	public function test_property_equal_dates_are_not_before_or_after(): void {
		$this
			->forAll(
				new SitemapDateGenerator()
			)
			->then( function ( SitemapDate $date ): void {
				$same = SitemapDate::fromString( $date->toString() );

				$this->assertTrue( $date->equals( $same ) );
				$this->assertFalse(
					$date->isBefore( $same ),
					sprintf( 'Equal date %s should not be isBefore itself', $date->toString() )
				);
				$this->assertFalse(
					$date->isAfter( $same ),
					sprintf( 'Equal date %s should not be isAfter itself', $date->toString() )
				);
			} );
	}

	/**
	 * Property: toString always produces YYYY-MM-DD format with zero-padding.
	 *
	 * The output format must match the pattern with 4-digit year, 2-digit
	 * month, and 2-digit day separated by hyphens.
	 */
	public function test_property_to_string_format_is_consistent(): void {
		$this
			->forAll(
				new SitemapDateGenerator()
			)
			->then( function ( SitemapDate $date ): void {
				$string = $date->toString();

				$this->assertMatchesRegularExpression(
					'/^\d{4}-\d{2}-\d{2}$/',
					$string,
					sprintf( 'toString() output "%s" should match YYYY-MM-DD format', $string )
				);
			} );
	}
}

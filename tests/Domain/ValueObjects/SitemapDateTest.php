<?php
/**
 * SitemapDateTest.php
 *
 * @package Automattic\MSM_Sitemap\Tests\Domain\ValueObjects
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Domain\ValueObjects;

use Automattic\MSM_Sitemap\Domain\ValueObjects\SitemapDate;
use Automattic\MSM_Sitemap\Tests\TestCase;
use InvalidArgumentException;

/**
 * Tests for SitemapDate value object.
 */
class SitemapDateTest extends TestCase {

	/**
	 * Test creating a valid date.
	 */
	public function test_create_valid_date(): void {
		$date = new SitemapDate( 2024, 1, 15 );

		$this->assertSame( 2024, $date->year() );
		$this->assertSame( 1, $date->month() );
		$this->assertSame( 15, $date->day() );
	}

	/**
	 * Test creating from string.
	 */
	public function test_from_string(): void {
		$date = SitemapDate::fromString( '2024-01-15' );

		$this->assertSame( 2024, $date->year() );
		$this->assertSame( 1, $date->month() );
		$this->assertSame( 15, $date->day() );
	}

	/**
	 * Test creating from MySQL datetime string.
	 */
	public function test_from_mysql_datetime_string(): void {
		$date = SitemapDate::fromString( '2024-01-15 12:30:45' );

		$this->assertSame( 2024, $date->year() );
		$this->assertSame( 1, $date->month() );
		$this->assertSame( 15, $date->day() );
	}

	/**
	 * Test creating today's date.
	 */
	public function test_today(): void {
		$date     = SitemapDate::today();
		$expected = gmdate( 'Y-m-d' );

		$this->assertSame( $expected, $date->toString() );
	}

	/**
	 * Test toString output.
	 */
	public function test_to_string(): void {
		$date = new SitemapDate( 2024, 1, 5 );

		$this->assertSame( '2024-01-05', $date->toString() );
		$this->assertSame( '2024-01-05', (string) $date );
	}

	/**
	 * Test padded string getters.
	 */
	public function test_padded_string_getters(): void {
		$date = new SitemapDate( 2024, 3, 7 );

		$this->assertSame( '2024', $date->yearString() );
		$this->assertSame( '03', $date->monthString() );
		$this->assertSame( '07', $date->dayString() );
	}

	/**
	 * Test URL params.
	 */
	public function test_to_url_params(): void {
		$date   = new SitemapDate( 2024, 3, 7 );
		$params = $date->toUrlParams();

		$this->assertSame( '2024', $params['yyyy'] );
		$this->assertSame( '03', $params['mm'] );
		$this->assertSame( '07', $params['dd'] );
	}

	/**
	 * Test MySQL datetime output.
	 */
	public function test_to_mysql_datetime(): void {
		$date = new SitemapDate( 2024, 1, 15 );

		$this->assertSame( '2024-01-15 00:00:00', $date->toMysqlDatetime() );
		$this->assertSame( '2024-01-15 12:30:45', $date->toMysqlDatetime( '12:30:45' ) );
	}

	/**
	 * Test equality.
	 */
	public function test_equals(): void {
		$date1 = new SitemapDate( 2024, 1, 15 );
		$date2 = new SitemapDate( 2024, 1, 15 );
		$date3 = new SitemapDate( 2024, 1, 16 );

		$this->assertTrue( $date1->equals( $date2 ) );
		$this->assertFalse( $date1->equals( $date3 ) );
	}

	/**
	 * Test isBefore.
	 */
	public function test_is_before(): void {
		$earlier = new SitemapDate( 2024, 1, 15 );
		$later   = new SitemapDate( 2024, 1, 16 );

		$this->assertTrue( $earlier->isBefore( $later ) );
		$this->assertFalse( $later->isBefore( $earlier ) );
		$this->assertFalse( $earlier->isBefore( $earlier ) );
	}

	/**
	 * Test isAfter.
	 */
	public function test_is_after(): void {
		$earlier = new SitemapDate( 2024, 1, 15 );
		$later   = new SitemapDate( 2024, 1, 16 );

		$this->assertTrue( $later->isAfter( $earlier ) );
		$this->assertFalse( $earlier->isAfter( $later ) );
		$this->assertFalse( $earlier->isAfter( $earlier ) );
	}

	/**
	 * Test isBefore with different years.
	 */
	public function test_is_before_different_years(): void {
		$earlier = new SitemapDate( 2023, 12, 31 );
		$later   = new SitemapDate( 2024, 1, 1 );

		$this->assertTrue( $earlier->isBefore( $later ) );
	}

	/**
	 * Test isBefore with different months.
	 */
	public function test_is_before_different_months(): void {
		$earlier = new SitemapDate( 2024, 1, 31 );
		$later   = new SitemapDate( 2024, 2, 1 );

		$this->assertTrue( $earlier->isBefore( $later ) );
	}

	/**
	 * Test invalid date throws exception.
	 */
	public function test_invalid_date_throws_exception(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid date' );

		new SitemapDate( 2024, 2, 30 ); // Feb 30 doesn't exist
	}

	/**
	 * Test invalid string format throws exception.
	 */
	public function test_invalid_string_format_throws_exception(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid date format' );

		SitemapDate::fromString( '2024-01' ); // Missing day
	}

	/**
	 * Test leap year handling.
	 */
	public function test_leap_year_handling(): void {
		// 2024 is a leap year
		$leapDay = new SitemapDate( 2024, 2, 29 );
		$this->assertSame( '2024-02-29', $leapDay->toString() );

		// 2023 is not a leap year
		$this->expectException( InvalidArgumentException::class );
		new SitemapDate( 2023, 2, 29 );
	}
}

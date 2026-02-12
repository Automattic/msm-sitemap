<?php
/**
 * Custom Eris generator for valid SitemapDate objects.
 *
 * Generates SitemapDate instances with valid year/month/day combinations.
 * Uses checkdate() to guarantee that generated dates are real calendar dates.
 *
 * @package Automattic\MSM_Sitemap\Tests\Generators
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Generators;

use Automattic\MSM_Sitemap\Domain\ValueObjects\SitemapDate;
use Eris\Generator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Random\RandomRange;

/**
 * Generates valid SitemapDate instances for property-based testing.
 */
class SitemapDateGenerator implements Generator {

	/**
	 * Minimum year to generate.
	 *
	 * @var int
	 */
	private int $min_year;

	/**
	 * Maximum year to generate.
	 *
	 * @var int
	 */
	private int $max_year;

	/**
	 * Days per month (non-leap year maximums, adjusted at generation time).
	 *
	 * @var array<int, int>
	 */
	private const DAYS_IN_MONTH = array(
		1  => 31,
		2  => 29,
		3  => 31,
		4  => 30,
		5  => 31,
		6  => 30,
		7  => 31,
		8  => 31,
		9  => 30,
		10 => 31,
		11 => 30,
		12 => 31,
	);

	/**
	 * Constructor.
	 *
	 * @param int $min_year Minimum year to generate (default 2000).
	 * @param int $max_year Maximum year to generate (default 2030).
	 */
	public function __construct( int $min_year = 2000, int $max_year = 2030 ) {
		$this->min_year = $min_year;
		$this->max_year = $max_year;
	}

	/**
	 * Generate a valid SitemapDate.
	 *
	 * @param int         $_size The generation size.
	 * @param RandomRange $rand  The random number generator.
	 * @return GeneratedValueSingle The generated SitemapDate value.
	 */
	public function __invoke( $_size, RandomRange $rand ) {
		$year  = $rand->rand( $this->min_year, $this->max_year );
		$month = $rand->rand( 1, 12 );

		$max_day = self::DAYS_IN_MONTH[ $month ];
		// Adjust for February in non-leap years.
		if ( 2 === $month && ! checkdate( 2, 29, $year ) ) {
			$max_day = 28;
		}

		$day = $rand->rand( 1, $max_day );

		$date = new SitemapDate( $year, $month, $day );

		return GeneratedValueSingle::fromJustValue( $date, 'sitemap-date' );
	}

	/**
	 * Shrink a generated SitemapDate towards a simpler form.
	 *
	 * @param GeneratedValue $element The value to shrink.
	 * @return GeneratedValueSingle The shrunk value.
	 */
	public function shrink( GeneratedValue $element ) {
		$date = $element->unbox();

		if ( ! $date instanceof SitemapDate ) {
			return GeneratedValueSingle::fromJustValue(
				new SitemapDate( 2024, 1, 1 ),
				'sitemap-date'
			);
		}

		// Shrink towards 2024-01-01 by reducing each component.
		$year  = $date->year();
		$month = $date->month();
		$day   = $date->day();

		if ( $day > 1 ) {
			return GeneratedValueSingle::fromJustValue(
				new SitemapDate( $year, $month, $day - 1 ),
				'sitemap-date'
			);
		}

		if ( $month > 1 ) {
			return GeneratedValueSingle::fromJustValue(
				new SitemapDate( $year, $month - 1, 1 ),
				'sitemap-date'
			);
		}

		if ( $year > $this->min_year ) {
			return GeneratedValueSingle::fromJustValue(
				new SitemapDate( $year - 1, 1, 1 ),
				'sitemap-date'
			);
		}

		return $element;
	}
}

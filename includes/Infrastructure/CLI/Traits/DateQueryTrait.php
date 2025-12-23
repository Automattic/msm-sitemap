<?php
/**
 * Date Query Trait
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\CLI\Traits
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Infrastructure\CLI\Traits;

use WP_CLI;

/**
 * Trait DateQueryTrait
 *
 * Provides date parsing utilities for CLI commands.
 */
trait DateQueryTrait {

	/**
	 * Parse a flexible date string (YYYY, YYYY-MM, YYYY-MM-DD) or --all into a date_query array.
	 *
	 * @param string|null $date The date string to parse.
	 * @param bool        $all  Whether to return an empty array for --all flag.
	 * @return array The parsed date query array.
	 */
	protected function parse_date_query( ?string $date = null, bool $all = false ): array {
		if ( $all ) {
			return array(); // Empty array for --all, will be handled by caller
		}
		if ( empty( $date ) ) {
			return array();
		}

		$parts = explode( '-', $date );

		if ( count( $parts ) === 3 ) {
			$year  = (int) $parts[0];
			$month = (int) $parts[1];
			$day   = (int) $parts[2];

			if ( ! checkdate( $month, $day, $year ) ) {
				WP_CLI::error( __( 'Invalid date. Please provide a real calendar date (e.g., 2024-02-29).', 'msm-sitemap' ) );
			}

			return array(
				array(
					'year'  => $year,
					'month' => $month,
					'day'   => $day,
				),
			);
		}

		if ( count( $parts ) === 2 ) {
			$year  = (int) $parts[0];
			$month = (int) $parts[1];

			if ( $month < 1 || $month > 12 ) {
				WP_CLI::error( __( 'Invalid month. Please specify a month between 1 and 12.', 'msm-sitemap' ) );
			}

			if ( $year < 1970 || $year > (int) gmdate( 'Y' ) ) {
				WP_CLI::error( __( 'Invalid year. Please specify a year between 1970 and the current year.', 'msm-sitemap' ) );
			}

			return array(
				array(
					'year'  => $year,
					'month' => $month,
				),
			);
		}

		if ( count( $parts ) === 1 && strlen( $parts[0] ) === 4 ) {
			$year = (int) $parts[0];

			if ( $year < 1970 || $year > (int) gmdate( 'Y' ) ) {
				WP_CLI::error( __( 'Invalid year. Please specify a year between 1970 and the current year.', 'msm-sitemap' ) );
			}

			return array( array( 'year' => $year ) );
		}

		WP_CLI::error( __( 'Invalid date format. Use YYYY, YYYY-MM, or YYYY-MM-DD.', 'msm-sitemap' ) );
		return array(); // Unreachable, but satisfies static analysis
	}
}

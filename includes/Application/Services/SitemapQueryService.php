<?php
/**
 * SitemapQueryService
 *
 * @package Automattic\MSM_Sitemap\Application\Services
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Application\Services;

/**
 * Service for handling sitemap date queries and matching logic.
 */
class SitemapQueryService {

	/**
	 * Parse a date string into query format.
	 *
	 * @param string|null $date Date string (YYYY, YYYY-MM, or YYYY-MM-DD) or null for all.
	 * @param bool $all Whether to get all dates.
	 * @return array|null Array of date queries or null for all.
	 */
	public function parse_date_query( ?string $date, bool $all = false ): ?array {
		if ( $all || empty( $date ) ) {
			return null;
		}
		
		$parts = explode( '-', $date );
		$query = array();
		
		if ( count( $parts ) === 3 ) {
			// YYYY-MM-DD
			$query = array(
				'year'  => (int) $parts[0],
				'month' => (int) $parts[1],
				'day'   => (int) $parts[2],
			);
		} elseif ( count( $parts ) === 2 ) {
			// YYYY-MM
			$query = array(
				'year'  => (int) $parts[0],
				'month' => (int) $parts[1],
			);
		} elseif ( count( $parts ) === 1 ) {
			// YYYY
			$query = array(
				'year' => (int) $parts[0],
			);
		}
		
		return empty( $query ) ? null : array( $query );
	}

	/**
	 * Get matching dates for a date query from available dates.
	 *
	 * @param array $query Date query with year, month, day keys.
	 * @param array $available_dates Array of available sitemap dates.
	 * @return array Array of matching dates.
	 */
	public function get_matching_dates_for_query( array $query, array $available_dates ): array {
		$matching_dates = array();
		
		if ( isset( $query['year'], $query['month'], $query['day'] ) ) {
			$date_str = sprintf( '%04d-%02d-%02d', $query['year'], $query['month'], $query['day'] );
			if ( in_array( $date_str, $available_dates, true ) ) {
				$matching_dates[] = $date_str;
			}
		} elseif ( isset( $query['year'], $query['month'] ) ) {
			$year = $query['year'];
			$month = $query['month'];
			$max_day = ( $year == date( 'Y' ) && $month == date( 'n' ) ) ? date( 'j' ) : cal_days_in_month( CAL_GREGORIAN, $month, $year );
			
			for ( $day = 1; $day <= $max_day; $day++ ) {
				$date_str = sprintf( '%04d-%02d-%02d', $year, $month, $day );
				if ( in_array( $date_str, $available_dates, true ) ) {
					$matching_dates[] = $date_str;
				}
			}
		} elseif ( isset( $query['year'] ) ) {
			$year = $query['year'];
			$max_month = ( $year == date( 'Y' ) ) ? date( 'n' ) : 12;
			
			for ( $month = 1; $month <= $max_month; $month++ ) {
				$max_day = ( $year == date( 'Y' ) && $month == date( 'n' ) ) ? date( 'j' ) : cal_days_in_month( CAL_GREGORIAN, $month, $year );
				
				for ( $day = 1; $day <= $max_day; $day++ ) {
					$date_str = sprintf( '%04d-%02d-%02d', $year, $month, $day );
					if ( in_array( $date_str, $available_dates, true ) ) {
						$matching_dates[] = $date_str;
					}
				}
			}
		}
		
		return $matching_dates;
	}

	/**
	 * Expand date queries into all potential dates they represent.
	 *
	 * @param array $date_queries Array of date queries.
	 * @return array Array of all potential dates.
	 */
	public function expand_date_queries( array $date_queries ): array {
		$all_dates = array();
		
		foreach ( $date_queries as $query ) {
			if ( isset( $query['year'], $query['month'], $query['day'] ) ) {
				$all_dates[] = sprintf( '%04d-%02d-%02d', $query['year'], $query['month'], $query['day'] );
			} elseif ( isset( $query['year'], $query['month'] ) ) {
				$year = $query['year'];
				$month = $query['month'];
				$max_day = ( $year == date( 'Y' ) && $month == date( 'n' ) ) ? date( 'j' ) : cal_days_in_month( CAL_GREGORIAN, $month, $year );
				
				for ( $day = 1; $day <= $max_day; $day++ ) {
					$all_dates[] = sprintf( '%04d-%02d-%02d', $year, $month, $day );
				}
			} elseif ( isset( $query['year'] ) ) {
				$year = $query['year'];
				$max_month = ( $year == date( 'Y' ) ) ? date( 'n' ) : 12;
				
				for ( $month = 1; $month <= $max_month; $month++ ) {
					$max_day = ( $year == date( 'Y' ) && $month == date( 'n' ) ) ? date( 'j' ) : cal_days_in_month( CAL_GREGORIAN, $month, $year );
					
					for ( $day = 1; $day <= $max_day; $day++ ) {
						$all_dates[] = sprintf( '%04d-%02d-%02d', $year, $month, $day );
					}
				}
			}
		}
		
		return array_unique( $all_dates );
	}

	/**
	 * Count how many dates would match the given queries from available dates.
	 *
	 * @param array $date_queries Array of date queries.
	 * @param array $available_dates Array of available sitemap dates.
	 * @return int Number of matching dates.
	 */
	public function count_matching_dates( array $date_queries, array $available_dates ): int {
		$matching_dates = array();
		
		foreach ( $date_queries as $query ) {
			$query_matches = $this->get_matching_dates_for_query( $query, $available_dates );
			$matching_dates = array_merge( $matching_dates, $query_matches );
		}
		
		return count( array_unique( $matching_dates ) );
	}

	/**
	 * Get all dates that would match the given queries, whether they exist or not.
	 *
	 * @param array $date_queries Array of date queries.
	 * @param array $available_dates Array of available sitemap dates.
	 * @return array Array with 'matched' and 'potential' date arrays.
	 */
	public function get_date_analysis( array $date_queries, array $available_dates ): array {
		$potential_dates = $this->expand_date_queries( $date_queries );
		$matched_dates = array();
		
		foreach ( $date_queries as $query ) {
			$query_matches = $this->get_matching_dates_for_query( $query, $available_dates );
			$matched_dates = array_merge( $matched_dates, $query_matches );
		}
		
		return array(
			'matched' => array_unique( $matched_dates ),
			'potential' => $potential_dates,
		);
	}
}

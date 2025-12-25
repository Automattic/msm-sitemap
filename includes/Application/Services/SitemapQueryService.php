<?php
/**
 * SitemapQueryService
 *
 * @package Automattic\MSM_Sitemap\Application\Services
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Application\Services;

use Automattic\MSM_Sitemap\Domain\Utilities\DateUtility;
use Automattic\MSM_Sitemap\Infrastructure\Repositories\PostRepository;

/**
 * Service for handling sitemap date queries and matching logic.
 */
class SitemapQueryService {

	/**
	 * The post repository.
	 *
	 * @var PostRepository|null
	 */
	private ?PostRepository $post_repository;

	/**
	 * Constructor.
	 *
	 * @param PostRepository|null $post_repository The post repository (optional for backwards compatibility).
	 */
	public function __construct( ?PostRepository $post_repository = null ) {
		$this->post_repository = $post_repository;
	}

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
			$year  = $query['year'];
			$month = $query['month'];
			
			// Validate month to avoid ValueError in PHP 8.0+
			if ( $month < 1 || $month > 12 ) {
				// Invalid month - return empty array
				return array();
			}

			$max_day = ( gmdate( 'Y' ) == $year && gmdate( 'n' ) == $month ) ? (int) gmdate( 'j' ) : DateUtility::get_days_in_month( $year, $month );
			
			for ( $day = 1; $day <= $max_day; $day++ ) {
				$date_str = sprintf( '%04d-%02d-%02d', $year, $month, $day );
				if ( in_array( $date_str, $available_dates, true ) ) {
					$matching_dates[] = $date_str;
				}
			}
		} elseif ( isset( $query['year'] ) ) {
			$year      = $query['year'];
			$max_month = ( gmdate( 'Y' ) == $year ) ? (int) gmdate( 'n' ) : 12;

			for ( $month = 1; $month <= $max_month; $month++ ) {
				$max_day = ( gmdate( 'Y' ) == $year && gmdate( 'n' ) == $month ) ? (int) gmdate( 'j' ) : DateUtility::get_days_in_month( $year, $month );

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
				// Validate the complete date before adding it
				if ( checkdate( $query['month'], $query['day'], $query['year'] ) ) {
					$all_dates[] = sprintf( '%04d-%02d-%02d', $query['year'], $query['month'], $query['day'] );
				}
				// Skip invalid dates silently
			} elseif ( isset( $query['year'], $query['month'] ) ) {
				$year  = $query['year'];
				$month = $query['month'];

				// Validate month to avoid ValueError in PHP 8.0+
				if ( $month < 1 || $month > 12 ) {
					// Invalid month - skip this query
					continue;
				}

				$max_day = ( gmdate( 'Y' ) == $year && gmdate( 'n' ) == $month ) ? (int) gmdate( 'j' ) : DateUtility::get_days_in_month( $year, $month );

				for ( $day = 1; $day <= $max_day; $day++ ) {
					$all_dates[] = sprintf( '%04d-%02d-%02d', $year, $month, $day );
				}
			} elseif ( isset( $query['year'] ) ) {
				$year      = $query['year'];
				$max_month = ( gmdate( 'Y' ) == $year ) ? (int) gmdate( 'n' ) : 12;

				for ( $month = 1; $month <= $max_month; $month++ ) {
					$max_day = ( gmdate( 'Y' ) == $year && gmdate( 'n' ) == $month ) ? (int) gmdate( 'j' ) : DateUtility::get_days_in_month( $year, $month );

					for ( $day = 1; $day <= $max_day; $day++ ) {
						$all_dates[] = sprintf( '%04d-%02d-%02d', $year, $month, $day );
					}
				}
			}
		}
		
		return array_unique( $all_dates );
	}

	/**
	 * Expand date queries into dates that have posts.
	 *
	 * @param array $date_queries Array of date queries.
	 * @return array Array of dates that have posts.
	 */
	public function expand_date_queries_with_posts( array $date_queries ): array {
		// First expand to all potential dates
		$all_potential_dates = $this->expand_date_queries( $date_queries );

		if ( empty( $all_potential_dates ) ) {
			return array();
		}

		// Get post types and status from repository if available, otherwise use defaults
		if ( $this->post_repository ) {
			$post_types_in = $this->post_repository->get_supported_post_types_in();
			$post_status   = $this->post_repository->get_post_status();
		} else {
			// Fallback for backwards compatibility (when no repository injected)
			$post_types_in = "'post', 'page'";
			$post_status   = 'publish';
		}

		// Get all dates that actually have posts
		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $all_potential_dates ), '%s' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$dates_with_posts = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT DATE(post_date) as post_date
				FROM {$wpdb->posts}
				WHERE post_type IN ({$post_types_in})
				AND post_status = %s
				AND DATE(post_date) IN ($placeholders)
				ORDER BY post_date ASC",
				array_merge( array( $post_status ), $all_potential_dates )
			)
		);
		// phpcs:enable

		// Return only the dates that have posts and are in our query range
		return array_intersect( $all_potential_dates, is_array( $dates_with_posts ) ? $dates_with_posts : array() );
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
			$query_matches  = $this->get_matching_dates_for_query( $query, $available_dates );
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
		$matched_dates   = array();
		
		foreach ( $date_queries as $query ) {
			$query_matches = $this->get_matching_dates_for_query( $query, $available_dates );
			$matched_dates = array_merge( $matched_dates, $query_matches );
		}
		
		return array(
			'matched'   => array_unique( $matched_dates ),
			'potential' => $potential_dates,
		);
	}
}

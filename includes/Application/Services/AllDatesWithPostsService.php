<?php
/**
 * All Dates With Posts Service
 *
 * @package Automattic\MSM_Sitemap\Application\Services
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Application\Services;

use Automattic\MSM_Sitemap\Domain\Contracts\SitemapDateProviderInterface;

/**
 * Service for providing all dates that have published posts.
 *
 * Used for full sitemap regeneration where all dates should be processed
 * regardless of whether sitemaps already exist.
 */
class AllDatesWithPostsService implements SitemapDateProviderInterface {

	/**
	 * Get all dates that have published posts.
	 *
	 * @return array<string> Array of dates in YYYY-MM-DD format.
	 */
	public function get_dates(): array {
		global $wpdb;

		// Get all unique post dates that have published content
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$dates = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT DATE(post_date) as post_date
				FROM {$wpdb->posts}
				WHERE post_type IN (%s, %s)
				AND post_status = %s
				ORDER BY post_date ASC",
				'post',
				'page',
				'publish'
			)
		);

		return $dates ?: array();
	}

	/**
	 * Get the provider type identifier.
	 *
	 * @return string The provider type.
	 */
	public function get_type(): string {
		return 'all';
	}

	/**
	 * Get a human-readable description of what this provider detects.
	 *
	 * @return string The description.
	 */
	public function get_description(): string {
		return __( 'All dates with published posts (for full regeneration)', 'msm-sitemap' );
	}

	/**
	 * Get the count of dates with posts.
	 *
	 * @return int The count of dates.
	 */
	public function get_count(): int {
		return count( $this->get_dates() );
	}

	/**
	 * Get dates for a specific year.
	 *
	 * @param int $year The year to filter by.
	 * @return array<string> Array of dates in YYYY-MM-DD format.
	 */
	public function get_dates_for_year( int $year ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$dates = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT DATE(post_date) as post_date
				FROM {$wpdb->posts}
				WHERE post_type IN (%s, %s)
				AND post_status = %s
				AND YEAR(post_date) = %d
				ORDER BY post_date ASC",
				'post',
				'page',
				'publish',
				$year
			)
		);

		return $dates ?: array();
	}

	/**
	 * Get all years that have published posts.
	 *
	 * @return array<int> Array of years.
	 */
	public function get_years_with_posts(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$years = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT YEAR(post_date) as post_year
				FROM {$wpdb->posts}
				WHERE post_type IN (%s, %s)
				AND post_status = %s
				ORDER BY post_year ASC",
				'post',
				'page',
				'publish'
			)
		);

		return array_map( 'intval', $years ?: array() );
	}
}

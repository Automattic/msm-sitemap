<?php
/**
 * All Dates With Posts Service
 *
 * @package Automattic\MSM_Sitemap\Application\Services
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Application\Services;

use Automattic\MSM_Sitemap\Domain\Contracts\SitemapDateProviderInterface;
use Automattic\MSM_Sitemap\Infrastructure\Repositories\PostRepository;

/**
 * Service for providing all dates that have published posts.
 *
 * Used for full sitemap regeneration where all dates should be processed
 * regardless of whether sitemaps already exist.
 */
class AllDatesWithPostsService implements SitemapDateProviderInterface {

	/**
	 * Post repository for getting enabled post types.
	 *
	 * @var PostRepository
	 */
	private PostRepository $post_repository;

	/**
	 * Constructor.
	 *
	 * @param PostRepository $post_repository Post repository.
	 */
	public function __construct( PostRepository $post_repository ) {
		$this->post_repository = $post_repository;
	}

	/**
	 * Get all dates that have published posts.
	 *
	 * @return array<string> Array of dates in YYYY-MM-DD format.
	 */
	public function get_dates(): array {
		global $wpdb;

		$post_types = $this->post_repository->get_supported_post_types();

		if ( empty( $post_types ) ) {
			return array();
		}

		$post_types_in = $this->post_repository->get_supported_post_types_in();
		$post_status   = $this->post_repository->get_post_status();

		// Get all unique post dates that have published content
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$dates = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT DATE(post_date) as post_date
				FROM {$wpdb->posts}
				WHERE post_type IN ({$post_types_in})
				AND post_status = %s
				ORDER BY post_date ASC",
				$post_status
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

		$post_types = $this->post_repository->get_supported_post_types();

		if ( empty( $post_types ) ) {
			return array();
		}

		$post_types_in = $this->post_repository->get_supported_post_types_in();
		$post_status   = $this->post_repository->get_post_status();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$dates = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT DATE(post_date) as post_date
				FROM {$wpdb->posts}
				WHERE post_type IN ({$post_types_in})
				AND post_status = %s
				AND YEAR(post_date) = %d
				ORDER BY post_date ASC",
				$post_status,
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

		$post_types = $this->post_repository->get_supported_post_types();

		if ( empty( $post_types ) ) {
			return array();
		}

		$post_types_in = $this->post_repository->get_supported_post_types_in();
		$post_status   = $this->post_repository->get_post_status();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$years = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT YEAR(post_date) as post_year
				FROM {$wpdb->posts}
				WHERE post_type IN ({$post_types_in})
				AND post_status = %s
				ORDER BY post_year ASC",
				$post_status
			)
		);

		return array_map( 'intval', $years ?: array() );
	}
}

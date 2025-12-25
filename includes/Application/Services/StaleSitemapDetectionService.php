<?php
/**
 * Stale Sitemap Detection Service
 *
 * @package Automattic\MSM_Sitemap\Application\Services
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Application\Services;

use Automattic\MSM_Sitemap\Domain\Contracts\SitemapDateProviderInterface;
use Automattic\MSM_Sitemap\Domain\Contracts\SitemapRepositoryInterface;
use Automattic\MSM_Sitemap\Infrastructure\Repositories\PostRepository;

/**
 * Service for detecting stale sitemaps that need regeneration.
 *
 * A sitemap is considered stale when posts on that date have been modified
 * since the last sitemap update run.
 */
class StaleSitemapDetectionService implements SitemapDateProviderInterface {

	/**
	 * The sitemap repository.
	 *
	 * @var SitemapRepositoryInterface
	 */
	private SitemapRepositoryInterface $sitemap_repository;

	/**
	 * The post repository.
	 *
	 * @var PostRepository
	 */
	private PostRepository $post_repository;

	/**
	 * Constructor.
	 *
	 * @param SitemapRepositoryInterface $sitemap_repository The sitemap repository.
	 * @param PostRepository             $post_repository    The post repository.
	 */
	public function __construct(
		SitemapRepositoryInterface $sitemap_repository,
		PostRepository $post_repository
	) {
		$this->sitemap_repository = $sitemap_repository;
		$this->post_repository    = $post_repository;
	}

	/**
	 * Get dates with stale sitemaps.
	 *
	 * Returns dates where:
	 * - A sitemap already exists for the date
	 * - Posts on that date have been modified since the last sitemap update
	 * - OR the URL count in the sitemap doesn't match the actual published post count
	 *   (indicating posts were unpublished, deleted, or moved)
	 *
	 * @return array<string> Array of dates in YYYY-MM-DD format.
	 */
	public function get_dates(): array {
		global $wpdb;

		$post_types = $this->post_repository->get_supported_post_types();

		// If no post types are enabled, there can be no stale sitemaps
		if ( empty( $post_types ) ) {
			return array();
		}

		// Get the last sitemap update time
		$last_update = get_option( 'msm_sitemap_update_last_run' );
		if ( ! $last_update ) {
			return array();
		}

		// Ensure $last_update is an integer timestamp
		$last_update_timestamp = is_numeric( $last_update ) ? (int) $last_update : strtotime( $last_update );

		// Get all existing sitemap dates
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$sitemap_dates = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_title
				FROM {$wpdb->posts}
				WHERE post_type = %s
				AND post_status = %s",
				'msm_sitemap',
				'publish'
			)
		);

		if ( empty( $sitemap_dates ) ) {
			return array();
		}

		// Find dates that have posts modified since the last sitemap update
		$placeholders  = implode( ',', array_fill( 0, count( $sitemap_dates ), '%s' ) );
		$post_types_in = $this->post_repository->get_supported_post_types_in();
		$post_status   = $this->post_repository->get_post_status();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders
		$stale_dates = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT DATE(post_date) as post_date
				FROM {$wpdb->posts}
				WHERE post_type IN ({$post_types_in})
				AND post_status = %s
				AND DATE(post_date) IN ($placeholders)
				AND post_modified_gmt > %s",
				array_merge(
					array( $post_status ),
					$sitemap_dates,
					array( gmdate( 'Y-m-d H:i:s', $last_update_timestamp ) )
				)
			)
		);
		// phpcs:enable

		$stale_dates = is_array( $stale_dates ) ? $stale_dates : array();

		// Also include dates where URL count doesn't match (posts unpublished, deleted, or moved)
		$count_mismatch_dates = $this->get_dates_with_count_mismatch();

		// Merge and dedupe
		return array_values( array_unique( array_merge( $stale_dates, $count_mismatch_dates ) ) );
	}

	/**
	 * Get dates where the sitemap URL count doesn't match the actual published post count.
	 *
	 * This detects when posts have been unpublished, deleted, or moved to different dates
	 * since the sitemap was generated.
	 *
	 * @return array<string> Array of dates in YYYY-MM-DD format.
	 */
	public function get_dates_with_count_mismatch(): array {
		global $wpdb;

		$post_types = $this->post_repository->get_supported_post_types();

		// If no post types are enabled, there can be no mismatches
		if ( empty( $post_types ) ) {
			return array();
		}

		$post_types_in = $this->post_repository->get_supported_post_types_in();
		$post_status   = $this->post_repository->get_post_status();

		// Get all sitemaps with their stored URL counts
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$sitemaps = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title as sitemap_date, pm.meta_value as stored_count
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'msm_indexed_url_count'
				WHERE p.post_type = %s
				AND p.post_status = %s",
				'msm_sitemap',
				'publish'
			)
		);

		if ( empty( $sitemaps ) ) {
			return array();
		}

		$mismatched_dates = array();

		foreach ( $sitemaps as $sitemap ) {
			$stored_count = (int) $sitemap->stored_count;
			$sitemap_date = $sitemap->sitemap_date;

			// Get the actual count of published posts for this date
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$actual_count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*)
					FROM {$wpdb->posts}
					WHERE post_type IN ({$post_types_in})
					AND post_status = %s
					AND DATE(post_date) = %s",
					$post_status,
					$sitemap_date
				)
			);
			// phpcs:enable

			if ( $stored_count !== $actual_count ) {
				$mismatched_dates[] = $sitemap_date;
			}
		}

		return $mismatched_dates;
	}

	/**
	 * Get the provider type identifier.
	 *
	 * @return string The provider type.
	 */
	public function get_type(): string {
		return 'stale';
	}

	/**
	 * Get a human-readable description of what this provider detects.
	 *
	 * @return string The description.
	 */
	public function get_description(): string {
		return __( 'Dates with sitemaps that need updating due to recent post modifications', 'msm-sitemap' );
	}

	/**
	 * Get the count of stale sitemaps.
	 *
	 * @return int The count of stale dates.
	 */
	public function get_count(): int {
		return count( $this->get_dates() );
	}

	/**
	 * Check if there are any stale sitemaps.
	 *
	 * @return bool True if there are stale sitemaps.
	 */
	public function has_stale_sitemaps(): bool {
		return $this->get_count() > 0;
	}
}

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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$stale_dates = $wpdb->get_col(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
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

		return $stale_dates ?: array();
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

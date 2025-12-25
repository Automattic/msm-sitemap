<?php
/**
 * Missing Sitemap Detection Service
 *
 * @package Automattic\MSM_Sitemap\Application\Services
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Application\Services;

use Automattic\MSM_Sitemap\Domain\Contracts\SitemapDateProviderInterface;
use Automattic\MSM_Sitemap\Domain\Contracts\SitemapRepositoryInterface;
use Automattic\MSM_Sitemap\Infrastructure\Repositories\PostRepository;

/**
 * Service for detecting missing sitemaps.
 *
 * A sitemap is considered missing when there are published posts on a date
 * but no corresponding sitemap exists.
 */
class MissingSitemapDetectionService implements SitemapDateProviderInterface {

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
	 * The stale sitemap detection service.
	 *
	 * @var StaleSitemapDetectionService|null
	 */
	private ?StaleSitemapDetectionService $stale_detection_service;

	/**
	 * Constructor.
	 *
	 * @param SitemapRepositoryInterface        $sitemap_repository       The sitemap repository.
	 * @param PostRepository                    $post_repository          The post repository.
	 * @param StaleSitemapDetectionService|null $stale_detection_service  The stale detection service (optional).
	 */
	public function __construct(
		SitemapRepositoryInterface $sitemap_repository,
		PostRepository $post_repository,
		?StaleSitemapDetectionService $stale_detection_service = null
	) {
		$this->sitemap_repository       = $sitemap_repository;
		$this->post_repository          = $post_repository;
		$this->stale_detection_service  = $stale_detection_service;
	}

	/**
	 * Get dates with missing sitemaps.
	 *
	 * Implements SitemapDateProviderInterface.
	 *
	 * @return array<string> Array of dates in YYYY-MM-DD format.
	 */
	public function get_dates(): array {
		global $wpdb;

		$post_types = $this->post_repository->get_supported_post_types();

		// If no post types are enabled, there can be no missing sitemaps
		if ( empty( $post_types ) ) {
			return array();
		}

		$post_types_in = $this->post_repository->get_supported_post_types_in();
		$post_status   = $this->post_repository->get_post_status();

		// Get all unique post dates that should have sitemaps
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$post_dates = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT DATE(post_date) as post_date
				FROM {$wpdb->posts}
				WHERE post_type IN ({$post_types_in})
				AND post_status = %s
				ORDER BY post_date ASC",
				$post_status
			)
		);

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

		// Find missing dates (dates with posts but no sitemaps)
		$missing_dates = array_diff( $post_dates ?: array(), $sitemap_dates ?: array() );

		return array_values( $missing_dates ); // Re-index array
	}

	/**
	 * Get the provider type identifier.
	 *
	 * @return string The provider type.
	 */
	public function get_type(): string {
		return 'missing';
	}

	/**
	 * Get a human-readable description of what this provider detects.
	 *
	 * @return string The description.
	 */
	public function get_description(): string {
		return __( 'Dates with posts but no sitemap', 'msm-sitemap' );
	}

	/**
	 * Get comprehensive missing sitemap data including stale sitemaps.
	 *
	 * This method provides backward compatibility and combines data from
	 * both missing and stale detection services.
	 *
	 * @return array{
	 *     missing_dates: array<string>,
	 *     dates_needing_updates: array<string>,
	 *     all_dates_to_generate: array<string>,
	 *     missing_dates_count: int,
	 *     dates_needing_updates_count: int,
	 *     all_dates_count: int,
	 *     total_posts_count: int,
	 *     recently_modified_count: int
	 * }
	 */
	public function get_missing_sitemaps(): array {
		global $wpdb;

		$missing_dates = $this->get_dates();

		// Get stale dates from the stale detection service if available
		$dates_needing_updates = array();
		if ( $this->stale_detection_service ) {
			$dates_needing_updates = $this->stale_detection_service->get_dates();
		}

		// Combine missing dates and dates needing updates
		$all_dates_to_generate = array_unique( array_merge( $missing_dates, $dates_needing_updates ) );

		// Count posts for all dates that need generation
		$total_posts_count = 0;
		$post_types        = $this->post_repository->get_supported_post_types();

		if ( ! empty( $all_dates_to_generate ) && ! empty( $post_types ) ) {
			$placeholders  = implode( ',', array_fill( 0, count( $all_dates_to_generate ), '%s' ) );
			$post_types_in = $this->post_repository->get_supported_post_types_in();
			$post_status   = $this->post_repository->get_post_status();

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$total_posts_count = $wpdb->get_var(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
					"SELECT COUNT(*)
					FROM {$wpdb->posts}
					WHERE post_type IN ({$post_types_in})
					AND post_status = %s
					AND DATE(post_date) IN ($placeholders)",
					array_merge( array( $post_status ), $all_dates_to_generate )
				)
			);
		}

		// Get recently modified posts count
		$last_run                = get_option( 'msm_sitemap_update_last_run' );
		$recently_modified_count = 0;

		if ( $last_run && ! empty( $post_types ) ) {
			// Ensure $last_run is an integer timestamp
			$last_run_timestamp = is_numeric( $last_run ) ? (int) $last_run : strtotime( $last_run );
			$post_types_in      = $this->post_repository->get_supported_post_types_in();
			$post_status        = $this->post_repository->get_post_status();

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$recently_modified_count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*)
					FROM {$wpdb->posts}
					WHERE post_type IN ({$post_types_in})
					AND post_status = %s
					AND post_modified_gmt > %s",
					$post_status,
					gmdate( 'Y-m-d H:i:s', $last_run_timestamp )
				)
			);
		}

		return array(
			'missing_dates'               => $missing_dates,
			'dates_needing_updates'       => $dates_needing_updates,
			'all_dates_to_generate'       => $all_dates_to_generate,
			'missing_dates_count'         => count( $missing_dates ),
			'dates_needing_updates_count' => count( $dates_needing_updates ),
			'all_dates_count'             => count( $all_dates_to_generate ),
			'total_posts_count'           => (int) $total_posts_count,
			'recently_modified_count'     => (int) $recently_modified_count,
		);
	}

	/**
	 * Check if there are any missing sitemaps or recently modified content.
	 *
	 * @return bool True if there are missing sitemaps or recent content.
	 */
	public function has_missing_content(): bool {
		$missing_data = $this->get_missing_sitemaps();
		return $missing_data['all_dates_count'] > 0 || $missing_data['recently_modified_count'] > 0;
	}

	/**
	 * Get a summary of missing content for display.
	 *
	 * @return array{has_missing: bool, message: string, counts: array<string>}
	 */
	public function get_missing_content_summary(): array {
		$missing_data = $this->get_missing_sitemaps();

		$summary = array(
			'has_missing' => false,
			'message'     => '',
			'counts'      => array(),
		);

		if ( $missing_data['all_dates_count'] > 0 || $missing_data['recently_modified_count'] > 0 ) {
			$summary['has_missing'] = true;

			// Build a simple message focused on sitemaps needing attention
			$total_sitemaps = $missing_data['all_dates_count'];

			$summary['message'] = sprintf(
				/* translators: %d is the number of sitemaps */
				_n(
					'%d sitemap needs generating',
					'%d sitemaps need generating',
					$total_sitemaps,
					'msm-sitemap'
				),
				$total_sitemaps
			);
		} else {
			$summary['message'] = __( 'All sitemaps up to date', 'msm-sitemap' );
		}

		return $summary;
	}

	/**
	 * Get message parts for success notifications.
	 *
	 * @return array<string> Array of message parts for display.
	 */
	public function get_success_message_parts(): array {
		$missing_data  = $this->get_missing_sitemaps();
		$message_parts = array();

		if ( $missing_data['missing_dates_count'] > 0 ) {
			$message_parts[] = sprintf(
				/* translators: %d is the number of missing sitemaps */
				_n( '%d missing sitemap', '%d missing sitemaps', $missing_data['missing_dates_count'], 'msm-sitemap' ),
				$missing_data['missing_dates_count']
			);
		}

		if ( $missing_data['dates_needing_updates_count'] > 0 ) {
			$message_parts[] = sprintf(
				/* translators: %d is the number of sitemaps needing updates */
				_n( '%d sitemap that needs updating', '%d sitemaps that need updating', $missing_data['dates_needing_updates_count'], 'msm-sitemap' ),
				$missing_data['dates_needing_updates_count']
			);
		}

		return $message_parts;
	}
}

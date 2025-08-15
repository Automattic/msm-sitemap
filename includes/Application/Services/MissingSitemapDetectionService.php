<?php
/**
 * Missing Sitemap Detection Service
 *
 * @package Automattic\MSM_Sitemap\Application\Services
 */

declare(strict_types=1);

namespace Automattic\MSM_Sitemap\Application\Services;

use Automattic\MSM_Sitemap\Domain\Contracts\SitemapRepositoryInterface;
use Automattic\MSM_Sitemap\Infrastructure\Repositories\PostRepository;

/**
 * Service for detecting missing sitemaps and providing counts for generation
 */
class MissingSitemapDetectionService {

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
	 * @param PostRepository $post_repository The post repository.
	 */
	public function __construct( SitemapRepositoryInterface $sitemap_repository, PostRepository $post_repository ) {
		$this->sitemap_repository = $sitemap_repository;
		$this->post_repository    = $post_repository;
	}

	/**
	 * Get missing sitemap dates and counts using optimized queries
	 *
	 * @return array Array with missing dates and counts
	 */
	public function get_missing_sitemaps(): array {
		global $wpdb;

		// Get all unique post dates that should have sitemaps
		$post_dates = $wpdb->get_col(
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

		// Find missing dates (dates with posts but no sitemaps)
		$missing_dates = array_diff( $post_dates, $sitemap_dates );
		$missing_dates = array_values( $missing_dates ); // Re-index array

		// Find dates that need updates (have sitemaps but posts were modified recently)
		$dates_needing_updates = $this->get_dates_needing_updates( $sitemap_dates );

		// Combine missing dates and dates needing updates
		$all_dates_to_generate = array_unique( array_merge( $missing_dates, $dates_needing_updates ) );

		// Count posts for all dates that need generation
		$total_posts_count = 0;
		if ( ! empty( $all_dates_to_generate ) ) {
			$placeholders      = implode( ',', array_fill( 0, count( $all_dates_to_generate ), '%s' ) );
			$total_posts_count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*)
					FROM {$wpdb->posts}
					WHERE post_type IN (%s, %s)
					AND post_status = %s
					AND DATE(post_date) IN ($placeholders)",
					array_merge( array( 'post', 'page', 'publish' ), $all_dates_to_generate )
				)
			);
		}

		// Get recently modified posts count
		$last_run                = get_option( 'msm_sitemap_update_last_run' );
		$recently_modified_count = 0;

		if ( $last_run ) {
			// Ensure $last_run is an integer timestamp
			$last_run_timestamp = is_numeric( $last_run ) ? (int) $last_run : strtotime( $last_run );
			
			$recently_modified_count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*)
					FROM {$wpdb->posts}
					WHERE post_type IN (%s, %s)
					AND post_status = %s
					AND post_modified_gmt > %s",
					'post',
					'page',
					'publish',
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
	 * Get dates that need updates due to recent post modifications
	 *
	 * @param array $sitemap_dates Array of existing sitemap dates.
	 * @return array Array of dates that need updates.
	 */
	private function get_dates_needing_updates( array $sitemap_dates ): array {
		global $wpdb;

		if ( empty( $sitemap_dates ) ) {
			return $sitemap_dates;
		}

		// Get the last sitemap update time
		$last_update = get_option( 'msm_sitemap_update_last_run' );
		if ( ! $last_update ) {
			return $sitemap_dates;
		}

		// Ensure $last_update is an integer timestamp
		$last_update_timestamp = is_numeric( $last_update ) ? (int) $last_update : strtotime( $last_update );

		// Find dates that have posts modified since the last sitemap update
		$placeholders                    = implode( ',', array_fill( 0, count( $sitemap_dates ), '%s' ) );
		$dates_with_recent_modifications = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT DATE(post_date) as post_date
				FROM {$wpdb->posts}
				WHERE post_type IN (%s, %s)
				AND post_status = %s
				AND DATE(post_date) IN ($placeholders)
				AND post_modified_gmt > %s",
				array_merge( array( 'post', 'page', 'publish' ), $sitemap_dates, array( gmdate( 'Y-m-d H:i:s', $last_update_timestamp ) ) )
			)
		);

		return $dates_with_recent_modifications;
	}

	/**
	 * Check if there are any missing sitemaps or recently modified content
	 *
	 * @return bool True if there are missing sitemaps or recent content
	 */
	public function has_missing_content(): bool {
		$missing_data = $this->get_missing_sitemaps();
		return $missing_data['all_dates_count'] > 0 || $missing_data['recently_modified_count'] > 0;
	}

	/**
	 * Get a summary of missing content for display
	 *
	 * @return array Summary data for UI display
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
			
			// Use the centralized method for message parts
			$counts = $this->get_success_message_parts();

			// Add recently modified posts count if any
			if ( $missing_data['recently_modified_count'] > 0 ) {
				$counts[] = sprintf(
					/* translators: %d is the number of recently modified posts */
					_n( '%d recently modified post', '%d recently modified posts', $missing_data['recently_modified_count'], 'msm-sitemap' ),
					$missing_data['recently_modified_count']
				);
			}

			$summary['counts']  = $counts;
			$summary['message'] = implode( '; ', $counts );
		} else {
			$summary['message'] = __( 'No missing sitemaps detected', 'msm-sitemap' );
		}

		return $summary;
	}

	/**
	 * Get message parts for success notifications
	 *
	 * @return array Array of message parts for display
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

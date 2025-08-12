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
	 * Get missing sitemap dates and counts using optimized queries
	 *
	 * @return array Array with missing dates and counts
	 */
	public static function get_missing_sitemaps(): array {
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

		// Find missing dates
		$missing_dates = array_diff($post_dates, $sitemap_dates);
		$missing_dates = array_values($missing_dates); // Re-index array

		// Count posts for missing dates
		$missing_posts_count = 0;
		if ( ! empty( $missing_dates ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $missing_dates ), '%s' ) );
			$missing_posts_count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*)
					FROM {$wpdb->posts}
					WHERE post_type IN (%s, %s)
					AND post_status = %s
					AND DATE(post_date) IN ($placeholders)",
					array_merge( array( 'post', 'page', 'publish' ), $missing_dates )
				)
			);
		}

		// Get recently modified posts count
		$last_run = get_option( 'msm_sitemap_update_last_run' );
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
					gmdate('Y-m-d H:i:s', $last_run_timestamp)
				)
			);
		}

		return array(
			'missing_dates'        => $missing_dates,
			'missing_dates_count'  => count($missing_dates),
			'missing_posts_count'  => (int) $missing_posts_count,
			'recently_modified_count' => (int) $recently_modified_count,
			'last_run'            => $last_run,
		);
	}

	/**
	 * Check if there are any missing sitemaps or recently modified content
	 *
	 * @return bool True if there are missing sitemaps or recent content
	 */
	public static function has_missing_content(): bool {
		$missing_data = self::get_missing_sitemaps();
		return $missing_data['missing_dates_count'] > 0 || $missing_data['recently_modified_count'] > 0;
	}

	/**
	 * Get a summary of missing content for display
	 *
	 * @return array Summary data for UI display
	 */
	public static function get_missing_content_summary(): array {
		$missing_data = self::get_missing_sitemaps();
		
		$summary = array(
			'has_missing' => false,
			'message'     => '',
			'counts'      => array(),
		);

		if ( $missing_data['missing_dates_count'] > 0 || $missing_data['recently_modified_count'] > 0 ) {
			$summary['has_missing'] = true;
			$counts = array();

			if ( $missing_data['missing_dates_count'] > 0 ) {
				$counts[] = sprintf(
					/* translators: %d is the number of missing sitemap dates */
					_n( '%d missing sitemap date', '%d missing sitemap dates', $missing_data['missing_dates_count'], 'msm-sitemap' ),
					$missing_data['missing_dates_count']
				);
			}

			if ( $missing_data['recently_modified_count'] > 0 ) {
				$counts[] = sprintf(
					/* translators: %d is the number of recently modified posts */
					_n( '%d recently modified post', '%d recently modified posts', $missing_data['recently_modified_count'], 'msm-sitemap' ),
					$missing_data['recently_modified_count']
				);
			}

			$summary['counts'] = $counts;
			$summary['message'] = implode( '; ', $counts );
		} else {
			$summary['message'] = __( 'No missing sitemaps detected', 'msm-sitemap' );
		}

		return $summary;
	}
}

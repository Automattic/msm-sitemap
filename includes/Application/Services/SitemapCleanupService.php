<?php
/**
 * SitemapCleanupService
 *
 * @package Automattic\MSM_Sitemap\Application\Services
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Application\Services;

use Automattic\MSM_Sitemap\Domain\Contracts\SitemapRepositoryInterface;
use Automattic\MSM_Sitemap\Infrastructure\Repositories\PostRepository;

/**
 * Service for cleaning up orphaned sitemaps.
 */
class SitemapCleanupService {

	/**
	 * The sitemap repository.
	 *
	 * @var SitemapRepositoryInterface
	 */
	private SitemapRepositoryInterface $repository;

	/**
	 * The post repository.
	 *
	 * @var PostRepository
	 */
	private PostRepository $post_repository;

	/**
	 * Constructor.
	 *
	 * @param SitemapRepositoryInterface $repository The sitemap repository.
	 * @param PostRepository|null $post_repository The post repository (optional, will create if not provided).
	 */
	public function __construct(
		SitemapRepositoryInterface $repository,
		?PostRepository $post_repository = null
	) {
		$this->repository = $repository;
		$this->post_repository = $post_repository ?? new PostRepository();
	}

	/**
	 * Clean up orphaned sitemaps that no longer have posts.
	 *
	 * @param array $date_queries Array of date queries to check.
	 * @return int Number of orphaned sitemaps deleted.
	 */
	public function cleanup_orphaned_sitemaps( array $date_queries ): int {
		$deleted_count = 0;
		$all_sitemap_dates = $this->repository->get_all_sitemap_dates();
		
		// Get all dates that should be checked based on queries
		$dates_to_check = $this->expand_date_queries( $date_queries );
		
		// Check each existing sitemap date to see if it should be cleaned up
		foreach ( $all_sitemap_dates as $sitemap_date ) {
			// Only check dates that are in our query range
			if ( ! in_array( $sitemap_date, $dates_to_check, true ) ) {
				continue;
			}
			
			// Check if this date still has posts
			if ( null === $this->post_repository->date_range_has_posts( $sitemap_date, $sitemap_date ) ) {
				// No posts for this date, delete the sitemap
				if ( $this->repository->delete_by_date( $sitemap_date ) ) {
					++$deleted_count;
				}
			}
		}
		
		return $deleted_count;
	}

	/**
	 * Clean up ALL orphaned sitemaps (sitemaps that exist but have no posts).
	 * 
	 * This method checks every existing sitemap date against actual post counts
	 * and removes sitemaps for dates that no longer have any published posts.
	 * Used primarily by the incremental cron job.
	 * 
	 * @return int Number of orphaned sitemaps deleted.
	 */
	public function cleanup_all_orphaned_sitemaps(): int {
		// Get all sitemap dates
		$all_sitemap_dates = $this->repository->get_all_sitemap_dates();
		
		// Find orphaned dates (sitemaps that exist but have no posts)
		$orphaned_dates = array();
		foreach ( $all_sitemap_dates as $sitemap_date ) {
			$date_key = gmdate( 'Y-m-d', strtotime( $sitemap_date ) );
			
			// Check if this date has any posts
			$post_count = $this->post_repository->get_post_ids_for_date( $date_key, 1 );
			
			if ( empty( $post_count ) ) {
				$orphaned_dates[] = $date_key;
			}
		}

		// Delete orphaned sitemaps
		$deleted_count = 0;
		foreach ( $orphaned_dates as $date ) {
			if ( $this->repository->delete_by_date( $date ) ) {
				++$deleted_count;
			}
		}
		
		return $deleted_count;
	}

	/**
	 * Expand date queries into actual dates.
	 *
	 * @param array $date_queries Array of date queries.
	 * @return array Array of actual dates.
	 */
	private function expand_date_queries( array $date_queries ): array {
		$dates = array();
		
		foreach ( $date_queries as $query ) {
			if ( isset( $query['year'], $query['month'], $query['day'] ) ) {
				$dates[] = sprintf( '%04d-%02d-%02d', $query['year'], $query['month'], $query['day'] );
			} elseif ( isset( $query['year'], $query['month'] ) ) {
				$year = $query['year'];
				$month = $query['month'];
				
				// Validate month before calling cal_days_in_month to avoid ValueError in PHP 8.0+
				if ( 1 > $month || 12 < $month ) {
					// Invalid month - skip this query
					continue;
				}
				
				$max_day = ( (int) gmdate( 'Y' ) === $year && (int) gmdate( 'n' ) === $month ) ? (int) gmdate( 'j' ) : \cal_days_in_month( \CAL_GREGORIAN, $month, $year );
				
				for ( $day = 1; $day <= $max_day; $day++ ) {
					$dates[] = sprintf( '%04d-%02d-%02d', $year, $month, $day );
				}
			} elseif ( isset( $query['year'] ) ) {
				$year = $query['year'];
				$max_month = ( (int) gmdate( 'Y' ) === $year ) ? (int) gmdate( 'n' ) : 12;
				
				for ( $month = 1; $month <= $max_month; $month++ ) {
					$max_day = ( (int) gmdate( 'Y' ) === $year && (int) gmdate( 'n' ) === $month ) ? (int) gmdate( 'j' ) : \cal_days_in_month( \CAL_GREGORIAN, $month, $year );
					
					for ( $day = 1; $day <= $max_day; $day++ ) {
						$dates[] = sprintf( '%04d-%02d-%02d', $year, $month, $day );
					}
				}
			}
		}
		
		return array_unique( $dates );
	}
}

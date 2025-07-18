<?php
/**
 * Permalinks handler
 *
 * @package Automattic\MSM_Sitemap
 */

declare(strict_types=1);

namespace Automattic\MSM_Sitemap;

/**
 * Permalinks handler for msm_sitemap posts.
 *
 * Ensures that get_permalink() and related tooling return the correct public-facing URL for each sitemap.
 * Handles both daily and year-based index URLs.
 */
class Permalinks {
	/**
	 * Register the permalink filter.
	 */
	public static function register(): void {
		add_filter( 'post_type_link', array( __CLASS__, 'filter_post_type_link' ), 10, 2 );
	}

	/**
	 * Filter the permalink for msm_sitemap posts to match the actual sitemap endpoint.
	 *
	 * @param string   $permalink The default permalink.
	 * @param \WP_Post $post      The post object.
	 * @return string The corrected sitemap URL.
	 */
	public static function filter_post_type_link( string $permalink, \WP_Post $post ): string {
		if ( 'msm_sitemap' !== $post->post_type ) {
			return $permalink;
		}
		
		$date = $post->post_name; // e.g., '2022-11-11' or '2022'.
		
		// Check if we should index by year (via filter).
		$index_by_year = apply_filters( 'msm_sitemap_index_by_year', false );

		if ( $index_by_year && preg_match( '/^(\d{4})$/', $date, $matches ) ) {
			$year = $matches[1];
			return home_url( "/sitemap-$year.xml" );
		}
		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $matches ) ) {
			$year  = $matches[1];
			$month = $matches[2];
			$day   = $matches[3];
			return home_url( "/sitemap.xml?yyyy=$year&mm=$month&dd=$day" );
		}
		// Fallback: return the default permalink if the date format is unexpected.
		return $permalink;
	}
} 

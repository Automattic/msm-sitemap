<?php
/**
 * Sitemap Index Entry Factory
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\Factories
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Infrastructure\Factories;

use Automattic\MSM_Sitemap\Domain\ValueObjects\Site;
use Automattic\MSM_Sitemap\Domain\ValueObjects\SitemapIndexEntry;
use InvalidArgumentException;

/**
 * Factory for creating SitemapIndexEntry objects from WordPress data.
 *
 * Handles WordPress-specific concerns while keeping the domain layer pure.
 */
class SitemapIndexEntryFactory {

	/**
	 * Create a sitemap index entry from a sitemap post.
	 *
	 * @param \WP_Post $sitemap_post The sitemap post.
	 * @return SitemapIndexEntry The sitemap index entry.
	 */
	public static function from_post( \WP_Post $sitemap_post ): SitemapIndexEntry {
		$loc     = get_permalink( $sitemap_post );
		$lastmod = get_post_modified_time( 'c', true, $sitemap_post );

		return new SitemapIndexEntry( $loc, $lastmod );
	}

	/**
	 * Create sitemap index entries from an array of sitemap posts.
	 *
	 * @param array<\WP_Post> $sitemap_posts Array of sitemap posts.
	 * @return array<SitemapIndexEntry> Array of sitemap index entries.
	 */
	public static function from_posts( array $sitemap_posts ): array {
		$entries = array();

		foreach ( $sitemap_posts as $sitemap_post ) {
			$entries[] = self::from_post( $sitemap_post );
		}

		return $entries;
	}

	/**
	 * Create a sitemap index entry from raw data (for testing or non-WordPress contexts).
	 *
	 * @param string      $loc     The URL of the sitemap.
	 * @param string|null $lastmod The last modified date (optional).
	 * @return SitemapIndexEntry The sitemap index entry.
	 * @throws InvalidArgumentException If any data is invalid.
	 */
	public static function from_data( string $loc, ?string $lastmod = null ): SitemapIndexEntry {
		return new SitemapIndexEntry( $loc, $lastmod );
	}

	/**
	 * Create sitemap index entries from an array of post IDs.
	 *
	 * @param array<int> $post_ids Array of sitemap post IDs.
	 * @return array<SitemapIndexEntry> Array of sitemap index entries.
	 */
	public static function from_post_ids( array $post_ids ): array {
		$entries = array();

		foreach ( $post_ids as $post_id ) {
			$sitemap_post = get_post( $post_id );
			if ( $sitemap_post && 'msm_sitemap' === $sitemap_post->post_type ) {
				$entries[] = self::from_post( $sitemap_post );
			}
		}

		return $entries;
	}

	/**
	 * Create sitemap index entries from an array of sitemap dates.
	 *
	 * Uses WordPress 5.3+ date/time functions for proper timezone handling.
	 *
	 * @see https://make.wordpress.org/core/2019/09/23/date-time-improvements-wp-5-3/
	 *
	 * @param array<string> $sitemap_dates Array of sitemap dates in MySQL DATETIME format.
	 * @return array<SitemapIndexEntry> Array of sitemap index entries.
	 */
	public static function from_sitemap_dates( array $sitemap_dates ): array {
		$entries  = array();
		$timezone = wp_timezone();

		foreach ( $sitemap_dates as $sitemap_date ) {
			$loc = self::build_sitemap_url( $sitemap_date );

			// Parse the date in the site's timezone.
			// The sitemap date (from post_date) is stored in local time, so we
			// need to interpret it in the site's timezone to get the correct offset.
			// Using DateTimeImmutable per WordPress 5.3+ best practices.
			$datetime = new \DateTimeImmutable( $sitemap_date, $timezone );

			// Format using wp_date() for consistency with WordPress conventions.
			$lastmod = wp_date( 'c', $datetime->getTimestamp(), $timezone );

			$entries[] = new SitemapIndexEntry( $loc, $lastmod );
		}

		return $entries;
	}

	/**
	 * Build the sitemap URL for a given date.
	 *
	 * @param string $sitemap_date The sitemap date in MySQL DATETIME format.
	 * @return string The sitemap URL.
	 */
	private static function build_sitemap_url( string $sitemap_date ): string {
		// Parse the date in the site's timezone.
		// The sitemap date (from post_date) is stored in local time.
		// Using DateTimeImmutable per WordPress 5.3+ best practices.
		$datetime = new \DateTimeImmutable( $sitemap_date, wp_timezone() );
		$year     = $datetime->format( 'Y' );
		$month    = $datetime->format( 'm' );
		$day      = $datetime->format( 'd' );

		if ( Site::is_indexed_by_year() ) {
			$query_args = array(
				'mm' => $month,
				'dd' => $day,
			);
			$base_url   = Site::get_sitemap_index_url( (int) $year );
		} else {
			$query_args = array(
				'yyyy' => $year,
				'mm'   => $month,
				'dd'   => $day,
			);
			$base_url   = Site::get_sitemap_index_url();
		}

		return add_query_arg( $query_args, $base_url );
	}
}

<?php
/**
 * URL Entry Factory
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\Factories
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Infrastructure\Factories;

use Automattic\MSM_Sitemap\Domain\ValueObjects\UrlEntry;

/**
 * Factory for creating UrlEntry objects from WordPress data.
 *
 * Handles WordPress-specific concerns like post retrieval, permalinks,
 * and WordPress filters while keeping the domain layer pure.
 */
class UrlEntryFactory {

	/**
	 * Create a UrlEntry from a WordPress post ID.
	 *
	 * @param int $post_id The post ID.
	 * @return \Automattic\MSM_Sitemap\Domain\ValueObjects\UrlEntry|null The URL entry or null if post should be skipped.
	 */
	public static function from_post( int $post_id ): ?UrlEntry {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return null;
		}

		// Check if post should be skipped
		if ( apply_filters( 'msm_sitemap_skip_post', false, $post_id ) ) {
			return null;
		}

		$permalink = get_permalink( $post );
		if ( ! $permalink ) {
			return null;
		}

		$lastmod = get_post_modified_time( 'c', true, $post );
		$changefreq = apply_filters( 'msm_sitemap_changefreq', 'monthly', $post );
		$priority = apply_filters( 'msm_sitemap_priority', 0.7, $post );

		try {
			return new UrlEntry(
				$permalink,
				$lastmod,
				$changefreq,
				$priority
			);
		} catch ( \InvalidArgumentException $e ) {
			// Log the error but don't break sitemap generation
			error_log( 'MSM Sitemap: Invalid URL entry for post ' . $post_id . ': ' . $e->getMessage() );
			return null;
		}
	}

	/**
	 * Create UrlEntry objects from an array of post IDs.
	 *
	 * @param array<int> $post_ids Array of post IDs.
	 * @return array<\Automattic\MSM_Sitemap\Domain\ValueObjects\UrlEntry> Array of URL entries.
	 */
	public static function from_posts( array $post_ids ): array {
		$url_entries = array();
		foreach ( $post_ids as $post_id ) {
			$url_entry = self::from_post( $post_id );
			if ( $url_entry ) {
				$url_entries[] = $url_entry;
			}
		}
		return $url_entries;
	}

	/**
	 * Create a UrlEntry from raw data (for testing or non-WordPress contexts).
	 *
	 * @param string      $loc        The URL of the page.
	 * @param string|null $lastmod    The date of last modification.
	 * @param string|null $changefreq How frequently the page changes.
	 * @param float|null  $priority   The priority of this URL.
	 * @return \Automattic\MSM_Sitemap\Domain\ValueObjects\UrlEntry The URL entry.
	 * @throws \InvalidArgumentException If any parameter is invalid.
	 */
	public static function from_data(
		string $loc,
		?string $lastmod = null,
		?string $changefreq = null,
		?float $priority = null
	): UrlEntry {
		return new UrlEntry( $loc, $lastmod, $changefreq, $priority );
	}
}

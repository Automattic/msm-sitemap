<?php
/**
 * URL Set Factory
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\Factories
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Infrastructure\Factories;

use Automattic\MSM_Sitemap\Domain\ValueObjects\UrlSet;

/**
 * Factory for creating UrlSet objects from WordPress data.
 *
 * Handles WordPress-specific concerns while keeping the domain layer pure.
 */
class UrlSetFactory {

	/**
	 * Create a UrlSet from an array of post IDs.
	 *
	 * @param array<int> $post_ids Array of post IDs.
	 * @param int        $max_entries Maximum number of entries allowed (default: 50000).
	 * @return \Automattic\MSM_Sitemap\Domain\ValueObjects\UrlSet The URL set.
	 */
	public static function from_posts( array $post_ids, int $max_entries = UrlSet::DEFAULT_MAX_ENTRIES ): UrlSet {
		$url_entries = UrlEntryFactory::from_posts( $post_ids );
		return new UrlSet( $url_entries, $max_entries );
	}

	/**
	 * Create a UrlSet from an array of URL entries.
	 *
	 * @param array<\Automattic\MSM_Sitemap\Domain\ValueObjects\UrlEntry> $url_entries Array of URL entries.
	 * @param int                                                          $max_entries Maximum number of entries allowed (default: 50000).
	 * @return \Automattic\MSM_Sitemap\Domain\ValueObjects\UrlSet The URL set.
	 */
	public static function from_entries( array $url_entries, int $max_entries = UrlSet::DEFAULT_MAX_ENTRIES ): UrlSet {
		return new UrlSet( $url_entries, $max_entries );
	}

	/**
	 * Create an empty UrlSet.
	 *
	 * @param int $max_entries Maximum number of entries allowed (default: 50000).
	 * @return \Automattic\MSM_Sitemap\Domain\ValueObjects\UrlSet An empty URL set.
	 */
	public static function create_empty( int $max_entries = UrlSet::DEFAULT_MAX_ENTRIES ): UrlSet {
		return new UrlSet( array(), $max_entries );
	}

	/**
	 * Create a UrlSet from raw data (for testing or non-WordPress contexts).
	 *
	 * @param array<array<string, mixed>> $entries_data Array of entry data arrays.
	 * @param int                        $max_entries Maximum number of entries allowed (default: 50000).
	 * @return \Automattic\MSM_Sitemap\Domain\ValueObjects\UrlSet The URL set.
	 * @throws \InvalidArgumentException If any entry data is invalid.
	 */
	public static function from_data( array $entries_data, int $max_entries = UrlSet::DEFAULT_MAX_ENTRIES ): UrlSet {
		$url_entries = array();
		foreach ( $entries_data as $entry_data ) {
			$url_entries[] = UrlEntryFactory::from_data(
				$entry_data['loc'],
				$entry_data['lastmod'] ?? null,
				$entry_data['changefreq'] ?? null,
				$entry_data['priority'] ?? null
			);
		}
		return new UrlSet( $url_entries, $max_entries );
	}
}

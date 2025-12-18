<?php
/**
 * Sitemap Index Collection Factory
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\Factories
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Infrastructure\Factories;

use Automattic\MSM_Sitemap\Domain\ValueObjects\SitemapIndexCollection;
use Automattic\MSM_Sitemap\Domain\ValueObjects\SitemapIndexEntry;
use InvalidArgumentException;

/**
 * Factory for creating SitemapIndexCollection objects.
 *
 * Handles collection creation while keeping the domain layer pure.
 */
class SitemapIndexCollectionFactory {

	/**
	 * Create a sitemap index collection from an array of entries.
	 *
	 * @param array<SitemapIndexEntry> $entries Array of sitemap index entries.
	 * @param int                      $max_entries Maximum number of entries allowed (default: 50000).
	 * @return SitemapIndexCollection The sitemap index collection.
	 */
	public static function from_entries( array $entries, int $max_entries = SitemapIndexCollection::DEFAULT_MAX_ENTRIES ): SitemapIndexCollection {
		return new SitemapIndexCollection( $entries, $max_entries );
	}

	/**
	 * Create an empty sitemap index collection.
	 *
	 * @param int $max_entries Maximum number of entries allowed (default: 50000).
	 * @return SitemapIndexCollection The empty sitemap index collection.
	 */
	public static function create_empty( int $max_entries = SitemapIndexCollection::DEFAULT_MAX_ENTRIES ): SitemapIndexCollection {
		return new SitemapIndexCollection( array(), $max_entries );
	}

	/**
	 * Merge multiple sitemap index collections into one.
	 *
	 * @param array<SitemapIndexCollection> $collections Array of collections to merge.
	 * @param int                           $max_entries Maximum number of entries allowed (default: 50000).
	 * @return SitemapIndexCollection The merged sitemap index collection.
	 */
	public static function merge( array $collections, int $max_entries = SitemapIndexCollection::DEFAULT_MAX_ENTRIES ): SitemapIndexCollection {
		$all_entries = array();

		foreach ( $collections as $collection ) {
			if ( ! $collection instanceof SitemapIndexCollection ) {
				throw new InvalidArgumentException(
					__( 'All collections must be SitemapIndexCollection instances.', 'msm-sitemap' )
				);
			}
			$all_entries = array_merge( $all_entries, $collection->get_entries() );
		}

		return new SitemapIndexCollection( $all_entries, $max_entries );
	}
}

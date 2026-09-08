<?php
/**
 * Sitemap Index Collection Value Object
 *
 * @package Automattic\MSM_Sitemap\Domain\ValueObjects
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Sitemap Index Collection Value Object
 *
 * Represents a collection of sitemap index entries.
 * Follows the sitemap protocol specification from sitemaps.org.
 */
class SitemapIndexCollection implements \Countable {

	/**
	 * Array of sitemap index entries.
	 *
	 * @var array<SitemapIndexEntry>
	 */
	private array $entries;

	/**
	 * Maximum number of entries allowed per sitemap index according to protocol.
	 *
	 * @var int
	 */
	private int $max_entries;

	/**
	 * Default maximum number of entries allowed per sitemap index according to protocol.
	 *
	 * @var int
	 */
	public const DEFAULT_MAX_ENTRIES = 50000;

	/**
	 * Constructor.
	 *
	 * @param array<SitemapIndexEntry> $entries Array of sitemap index entries.
	 * @param int                      $max_entries Maximum number of entries allowed (default: 50000).
	 * @throws InvalidArgumentException If the number of entries exceeds the maximum allowed or if max_entries exceeds the protocol limit.
	 */
	public function __construct( array $entries = array(), int $max_entries = self::DEFAULT_MAX_ENTRIES ) {
		if ( $max_entries > self::DEFAULT_MAX_ENTRIES ) {
			throw new InvalidArgumentException(
				sprintf(
					/* translators: %d is the maximum number of entries allowed by the sitemap protocol. */
					__( 'Maximum entries cannot exceed the sitemap index protocol limit of %d.', 'msm-sitemap' ),
					self::DEFAULT_MAX_ENTRIES
				)
			);
		}
		$this->max_entries = $max_entries;
		$this->validate_entries( $entries );
		$this->entries = $entries;
	}

	/**
	 * Add a sitemap index entry to the collection.
	 *
	 * @param SitemapIndexEntry $entry The sitemap index entry to add.
	 * @throws InvalidArgumentException If adding the entry would exceed the maximum allowed.
	 */
	public function add( SitemapIndexEntry $entry ): void {
		if ( count( $this->entries ) >= $this->max_entries ) {
			throw new InvalidArgumentException(
				sprintf(
					/* translators: %d is the maximum number of entries allowed. */
					__( 'Cannot add entry: sitemap index already contains the maximum of %d entries.', 'msm-sitemap' ),
					$this->max_entries
				)
			);
		}

		$this->entries[] = $entry;
	}

	/**
	 * Remove a sitemap index entry from the collection.
	 *
	 * @param SitemapIndexEntry $entry The sitemap index entry to remove.
	 */
	public function remove( SitemapIndexEntry $entry ): void {
		$key = array_search( $entry, $this->entries, true );
		if ( false !== $key ) {
			unset( $this->entries[ $key ] );
			$this->entries = array_values( $this->entries );
		}
	}

	/**
	 * Get all sitemap index entries.
	 *
	 * @return array<SitemapIndexEntry> Array of sitemap index entries.
	 */
	public function get_entries(): array {
		return $this->entries;
	}

	/**
	 * Check if the collection is empty.
	 *
	 * @return bool True if empty, false otherwise.
	 */
	public function is_empty(): bool {
		return empty( $this->entries );
	}

	/**
	 * Check if the collection is full.
	 *
	 * @return bool True if full, false otherwise.
	 */
	public function is_full(): bool {
		return count( $this->entries ) >= $this->max_entries;
	}

	/**
	 * Check if the collection contains a specific entry.
	 *
	 * @param SitemapIndexEntry $entry The entry to check for.
	 * @return bool True if contains the entry, false otherwise.
	 */
	public function contains( SitemapIndexEntry $entry ): bool {
		return in_array( $entry, $this->entries, true );
	}

	/**
	 * Get the number of entries in the collection.
	 *
	 * @return int The number of entries.
	 */
	public function count(): int {
		return count( $this->entries );
	}

	/**
	 * Convert the collection to an array.
	 *
	 * @return array<SitemapIndexEntry> Array representation of the collection.
	 */
	public function to_array(): array {
		return $this->entries;
	}

	/**
	 * Check if this collection equals another collection.
	 *
	 * @param SitemapIndexCollection $other The other collection to compare.
	 * @return bool True if equal, false otherwise.
	 */
	public function equals( SitemapIndexCollection $other ): bool {
		if ( count( $this->entries ) !== count( $other->get_entries() ) ) {
			return false;
		}

		foreach ( $this->entries as $entry ) {
			if ( ! $other->contains( $entry ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Validate the entries array.
	 *
	 * @param array<SitemapIndexEntry> $entries The entries to validate.
	 * @throws InvalidArgumentException If the entries are invalid.
	 */
	private function validate_entries( array $entries ): void {
		foreach ( $entries as $entry ) {
			if ( ! $entry instanceof SitemapIndexEntry ) {
				throw new InvalidArgumentException(
					__( 'All entries must be SitemapIndexEntry instances.', 'msm-sitemap' )
				);
			}
		}

		if ( count( $entries ) > $this->max_entries ) {
			throw new InvalidArgumentException(
				sprintf(
					/* translators: %d is the maximum number of entries allowed. */
					__( 'Cannot create sitemap index collection: exceeds maximum of %d entries.', 'msm-sitemap' ),
					$this->max_entries
				)
			);
		}
	}
}

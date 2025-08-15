<?php
/**
 * SitemapContent Value Object
 *
 * @package Automattic\MSM_Sitemap\Domain\ValueObjects
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * SitemapContent represents a collection of URL entries for a sitemap.
 * This is an immutable value object that implements the Countable interface.
 */
class SitemapContent implements \Countable {

	/**
	 * Maximum number of entries allowed by the sitemap protocol.
	 */
	public const DEFAULT_MAX_ENTRIES = 50000;

	/**
	 * The URL entries in this sitemap content.
	 *
	 * @var UrlEntry[]
	 */
	private array $entries;

	/**
	 * Maximum number of entries allowed in this sitemap content.
	 *
	 * @var int
	 */
	private int $max_entries;

	/**
	 * Constructor.
	 *
	 * @param UrlEntry[] $entries The URL entries.
	 * @param int        $max_entries Maximum number of entries allowed.
	 *
	 * @throws InvalidArgumentException If max_entries exceeds the protocol limit or if entries contain invalid types.
	 */
	public function __construct( array $entries = array(), int $max_entries = self::DEFAULT_MAX_ENTRIES ) {
		// Validate max_entries doesn't exceed protocol limit
		if ( $max_entries > self::DEFAULT_MAX_ENTRIES ) {
			throw new InvalidArgumentException(
				sprintf(
					'Max entries (%d) cannot exceed the sitemap protocol limit (%d)',
					$max_entries,
					self::DEFAULT_MAX_ENTRIES
				)
			);
		}

		// Validate that all entries are UrlEntry objects
		foreach ( $entries as $entry ) {
			if ( ! $entry instanceof UrlEntry ) {
				throw new InvalidArgumentException(
					sprintf(
						'All entries must be UrlEntry objects, got %s',
						gettype( $entry )
					)
				);
			}
		}

		// Limit entries to max_entries
		$entries = array_slice( $entries, 0, $max_entries );

		$this->entries     = $entries;
		$this->max_entries = $max_entries;
	}

	/**
	 * Add a URL entry to this sitemap content.
	 *
	 * @param UrlEntry $entry The URL entry to add.
	 * @return SitemapContent A new instance with the entry added.
	 */
	public function add( UrlEntry $entry ): SitemapContent {
		if ( $this->is_full() ) {
			return $this; // Cannot add more entries
		}

		$new_entries   = $this->entries;
		$new_entries[] = $entry;

		return new self( $new_entries, $this->max_entries );
	}

	/**
	 * Remove a URL entry from this sitemap content.
	 *
	 * @param UrlEntry $entry The URL entry to remove.
	 * @return SitemapContent A new instance with the entry removed, or the same instance if entry not found.
	 */
	public function remove( UrlEntry $entry ): SitemapContent {
		$new_entries = array_filter(
			$this->entries,
			function ( UrlEntry $existing_entry ) use ( $entry ) {
				return ! $existing_entry->equals( $entry );
			}
		);

		// If no entries were removed, return the same instance
		if ( count( $new_entries ) === count( $this->entries ) ) {
			return $this;
		}

		return new self( array_values( $new_entries ), $this->max_entries );
	}

	/**
	 * Get all URL entries in this sitemap content.
	 *
	 * @return UrlEntry[]
	 */
	public function get_entries(): array {
		return $this->entries;
	}

	/**
	 * Count the number of URL entries in this sitemap content.
	 *
	 * @return int The number of entries.
	 */
	public function count(): int {
		return count( $this->entries );
	}

	/**
	 * Check if this sitemap content is empty.
	 *
	 * @return bool True if empty, false otherwise.
	 */
	public function is_empty(): bool {
		return empty( $this->entries );
	}

	/**
	 * Check if this sitemap content is full.
	 *
	 * @return bool True if full, false otherwise.
	 */
	public function is_full(): bool {
		return count( $this->entries ) >= $this->max_entries;
	}

	/**
	 * Check if this sitemap content contains a specific URL entry.
	 *
	 * @param UrlEntry $entry The URL entry to check for.
	 * @return bool True if found, false otherwise.
	 */
	public function contains( UrlEntry $entry ): bool {
		foreach ( $this->entries as $existing_entry ) {
			if ( $existing_entry->equals( $entry ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Convert this sitemap content to an array.
	 *
	 * @return array The sitemap content as an array.
	 */
	public function to_array(): array {
		return array_map(
			function ( UrlEntry $entry ) {
				return $entry->to_array();
			},
			$this->entries
		);
	}

	/**
	 * Check if this sitemap content equals another.
	 *
	 * @param SitemapContent $other The other sitemap content to compare with.
	 * @return bool True if equal, false otherwise.
	 */
	public function equals( SitemapContent $other ): bool {
		if ( $this->count() !== $other->count() ) {
			return false;
		}

		foreach ( $this->entries as $index => $entry ) {
			if ( ! $entry->equals( $other->entries[ $index ] ) ) {
				return false;
			}
		}

		return true;
	}
}

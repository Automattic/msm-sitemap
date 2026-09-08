<?php
/**
 * URL Set Value Object
 *
 * @package Automattic\MSM_Sitemap\Domain\ValueObjects
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Domain\ValueObjects;

use Countable;
use InvalidArgumentException;

/**
 * URL Set Value Object
 *
 * Represents a collection of URL entries for a sitemap.
 * Follows the sitemap protocol specification from sitemaps.org.
 */
class UrlSet implements Countable {

	/**
	 * Array of URL entries.
	 *
	 * @var array<UrlEntry>
	 */
	private array $entries;

	/**
	 * Maximum number of entries allowed per sitemap according to protocol.
	 *
	 * @var int
	 */
	private int $max_entries;

	/**
	 * Default maximum number of entries allowed per sitemap according to protocol.
	 *
	 * @var int
	 */
	public const DEFAULT_MAX_ENTRIES = 50000;

	/**
	 * Constructor.
	 *
	 * @param array<UrlEntry> $entries Array of URL entries.
	 * @param int            $max_entries Maximum number of entries allowed (default: 50000).
	 * @throws InvalidArgumentException If the number of entries exceeds the maximum allowed or if max_entries exceeds the protocol limit.
	 */
	public function __construct( array $entries = array(), int $max_entries = self::DEFAULT_MAX_ENTRIES ) {
		if ( $max_entries > self::DEFAULT_MAX_ENTRIES ) {
			throw new InvalidArgumentException(
				sprintf(
					/* translators: %d is the maximum number of entries allowed by the sitemap protocol. */
					__( 'Maximum entries cannot exceed the sitemap protocol limit of %d.', 'msm-sitemap' ),
					self::DEFAULT_MAX_ENTRIES
				)
			);
		}
		
		$this->max_entries = $max_entries;
		$this->validate_entries( $entries );
		$this->entries = $entries;
	}

	/**
	 * Add a URL entry to the set.
	 *
	 * @param UrlEntry $entry The URL entry to add.
	 * @throws InvalidArgumentException If adding the entry would exceed the maximum allowed.
	 */
	public function add( UrlEntry $entry ): void {
		if ( count( $this->entries ) >= $this->max_entries ) {
			throw new InvalidArgumentException(
				sprintf(
					/* translators: %d is the maximum number of entries allowed. */
					__( 'Cannot add entry: sitemap already contains the maximum of %d entries.', 'msm-sitemap' ),
					$this->max_entries
				)
			);
		}

		$this->entries[] = $entry;
	}

	/**
	 * Remove a URL entry from the set.
	 *
	 * @param UrlEntry $entry The URL entry to remove.
	 * @return bool True if the entry was removed, false if it was not found.
	 */
	public function remove( UrlEntry $entry ): bool {
		$key = array_search( $entry, $this->entries, true );
		if ( false !== $key ) {
			unset( $this->entries[ $key ] );
			$this->entries = array_values( $this->entries ); // Re-index array
			return true;
		}

		return false;
	}

	/**
	 * Get all URL entries.
	 *
	 * @return array<UrlEntry> Array of URL entries.
	 */
	public function get_entries(): array {
		return $this->entries;
	}

	/**
	 * Check if the set is empty.
	 *
	 * @return bool True if empty, false otherwise.
	 */
	public function is_empty(): bool {
		return empty( $this->entries );
	}

	/**
	 * Check if the set is full (at maximum capacity).
	 *
	 * @return bool True if full, false otherwise.
	 */
	public function is_full(): bool {
		return count( $this->entries ) >= $this->max_entries;
	}

	/**
	 * Check if the set contains a specific URL entry.
	 *
	 * @param UrlEntry $entry The URL entry to check for.
	 * @return bool True if the entry is found, false otherwise.
	 */
	public function contains( UrlEntry $entry ): bool {
		return in_array( $entry, $this->entries, true );
	}

	/**
	 * Get the number of entries in the set.
	 *
	 * @return int The number of entries.
	 */
	public function count(): int {
		return count( $this->entries );
	}

	/**
	 * Convert the URL set to an array representation.
	 *
	 * @return array<array<string, mixed>> Array representation of the URL set.
	 */
	public function to_array(): array {
		$array = array();
		foreach ( $this->entries as $entry ) {
			$array[] = $entry->to_array();
		}
		return $array;
	}



	/**
	 * Check if this URL set is equal to another.
	 *
	 * @param UrlSet $other The other URL set to compare with.
	 * @return bool True if equal, false otherwise.
	 */
	public function equals( UrlSet $other ): bool {
		if ( count( $this->entries ) !== count( $other->entries ) ) {
			return false;
		}

		// Compare entries (order doesn't matter for equality)
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
	 * @param array<UrlEntry> $entries The entries to validate.
	 * @throws InvalidArgumentException If the entries are invalid.
	 */
	private function validate_entries( array $entries ): void {
		foreach ( $entries as $entry ) {
			if ( ! $entry instanceof UrlEntry ) {
				throw new InvalidArgumentException(
					__( 'All entries must be UrlEntry instances.', 'msm-sitemap' )
				);
			}
		}

		if ( count( $entries ) > $this->max_entries ) {
			throw new InvalidArgumentException(
				sprintf(
					/* translators: %d is the maximum number of entries allowed. */
					__( 'Cannot create URL set: exceeds maximum of %d entries.', 'msm-sitemap' ),
					$this->max_entries
				)
			);
		}
	}


}

<?php
/**
 * Sitemap Index Entry Value Object
 *
 * @package Automattic\MSM_Sitemap\Domain\ValueObjects
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Sitemap Index Entry Value Object
 *
 * Represents a single entry in a sitemap index.
 * Follows the sitemap protocol specification from sitemaps.org.
 */
class SitemapIndexEntry {

	/**
	 * URL of the sitemap (required).
	 *
	 * @var string
	 */
	private string $loc;

	/**
	 * Last modified date of the sitemap (optional).
	 *
	 * @var string|null
	 */
	private ?string $lastmod;

	/**
	 * Maximum URL length according to sitemap protocol.
	 *
	 * @var int
	 */
	private const MAX_URL_LENGTH = 2048;

	/**
	 * Constructor.
	 *
	 * @param string      $loc     The URL of the sitemap (required).
	 * @param string|null $lastmod The last modified date (optional).
	 * @throws InvalidArgumentException If the URL is invalid or exceeds maximum length.
	 */
	public function __construct( string $loc, ?string $lastmod = null ) {
		$this->validate_loc( $loc );
		$this->validate_lastmod( $lastmod );

		$this->loc     = $loc;
		$this->lastmod = $lastmod;
	}

	/**
	 * Get the URL of the sitemap.
	 *
	 * @return string The URL.
	 */
	public function loc(): string {
		return $this->loc;
	}

	/**
	 * Get the last modified date.
	 *
	 * @return string|null The last modified date, or null if not set.
	 */
	public function lastmod(): ?string {
		return $this->lastmod;
	}

	/**
	 * Convert the entry to an array.
	 *
	 * @return array<string, mixed> Array representation of the entry.
	 */
	public function to_array(): array {
		$array = array(
			'loc' => $this->loc,
		);

		if ( $this->lastmod ) {
			$array['lastmod'] = $this->lastmod;
		}

		return $array;
	}

	/**
	 * Check if this entry equals another entry.
	 *
	 * @param SitemapIndexEntry $other The other entry to compare.
	 * @return bool True if equal, false otherwise.
	 */
	public function equals( SitemapIndexEntry $other ): bool {
		return $this->loc === $other->loc() && $this->lastmod === $other->lastmod();
	}

	/**
	 * Validate the URL.
	 *
	 * @param string $loc The URL to validate.
	 * @throws InvalidArgumentException If the URL is invalid.
	 */
	private function validate_loc( string $loc ): void {
		if ( empty( $loc ) ) {
			throw new InvalidArgumentException(
				__( 'Sitemap URL cannot be empty.', 'msm-sitemap' )
			);
		}

		if ( ! filter_var( $loc, FILTER_VALIDATE_URL ) ) {
			throw new InvalidArgumentException(
				__( 'Sitemap URL must be a valid URL.', 'msm-sitemap' )
			);
		}

		if ( strlen( $loc ) > self::MAX_URL_LENGTH ) {
			throw new InvalidArgumentException(
				sprintf(
					/* translators: %d is the maximum URL length allowed. */
					__( 'Sitemap URL cannot exceed %d characters.', 'msm-sitemap' ),
					self::MAX_URL_LENGTH
				)
			);
		}
	}

	/**
	 * Validate the last modified date.
	 *
	 * @param string|null $lastmod The last modified date to validate.
	 * @throws InvalidArgumentException If the date is invalid.
	 */
	private function validate_lastmod( ?string $lastmod ): void {
		if ( null === $lastmod ) {
			return;
		}

		// For now, we'll accept any non-empty string
		// When the timezone PR is merged, we can add proper date validation
		if ( empty( $lastmod ) ) {
			throw new InvalidArgumentException(
				__( 'Last modified date cannot be empty if provided.', 'msm-sitemap' )
			);
		}
	}
}

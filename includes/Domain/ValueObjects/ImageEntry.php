<?php
/**
 * Image Entry Value Object
 *
 * @package Automattic\MSM_Sitemap\Domain\ValueObjects
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Domain\ValueObjects;

/**
 * Image Entry Value Object
 *
 * Represents a single image entry in a sitemap following the Google Image Sitemap protocol.
 * Only the loc (URL) element is supported as Google has deprecated all other image sitemap tags.
 *
 * @see https://developers.google.com/search/docs/crawling-indexing/sitemaps/image-sitemaps
 */
class ImageEntry {

	/**
	 * The URL of the image (required).
	 *
	 * @var string
	 */
	private string $loc;

	/**
	 * Maximum URL length according to sitemap protocol.
	 *
	 * @var int
	 */
	private const MAX_URL_LENGTH = 2048;

	/**
	 * Constructor.
	 *
	 * @param string $loc The URL of the image (required).
	 *
	 * @throws \InvalidArgumentException If the URL is invalid.
	 */
	public function __construct( string $loc ) {
		$this->validate_loc( $loc );
		$this->loc = $loc;
	}

	/**
	 * Get the URL of the image.
	 *
	 * @return string The image URL.
	 */
	public function loc(): string {
		return $this->loc;
	}

	/**
	 * Convert the image entry to an array representation.
	 *
	 * @return array<string, string> Array representation of the image entry.
	 */
	public function to_array(): array {
		return array(
			'loc' => $this->loc,
		);
	}

	/**
	 * Check if this image entry is equal to another.
	 *
	 * @param ImageEntry $other The other image entry to compare with.
	 * @return bool True if equal, false otherwise.
	 */
	public function equals( ImageEntry $other ): bool {
		return $this->loc === $other->loc;
	}

	/**
	 * Validate the loc (URL) parameter.
	 *
	 * @param string $loc The URL to validate.
	 * @throws \InvalidArgumentException If the URL is invalid.
	 */
	private function validate_loc( string $loc ): void {
		if ( empty( $loc ) ) {
			throw new \InvalidArgumentException( 'Image URL cannot be empty.' );
		}

		$max_length = self::MAX_URL_LENGTH;
		if ( strlen( $loc ) > $max_length ) {
			throw new \InvalidArgumentException(
				sprintf(
					'Image URL cannot exceed %d characters.',
					$max_length
				)
			);
		}

		if ( ! filter_var( $loc, FILTER_VALIDATE_URL ) ) {
			throw new \InvalidArgumentException( 'Image URL must be a valid URL.' );
		}
	}
}

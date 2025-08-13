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
 * Based on https://developers.google.com/search/docs/crawling-indexing/sitemaps/image-sitemaps
 */
class ImageEntry {

	/**
	 * The URL of the image (required).
	 *
	 * @var string
	 */
	private string $loc;

	/**
	 * The caption of the image (optional).
	 *
	 * @var string|null
	 */
	private ?string $caption;

	/**
	 * The geographic location of the image (optional).
	 *
	 * @var string|null
	 */
	private ?string $geo_location;

	/**
	 * The title of the image (optional).
	 *
	 * @var string|null
	 */
	private ?string $title;

	/**
	 * The license URL of the image (optional).
	 *
	 * @var string|null
	 */
	private ?string $license;

	/**
	 * Maximum URL length according to sitemap protocol.
	 *
	 * @var int
	 */
	private const MAX_URL_LENGTH = 2048;

	/**
	 * Constructor.
	 *
	 * @param string      $loc          The URL of the image (required).
	 * @param string|null $caption      The caption of the image (optional).
	 * @param string|null $geo_location The geographic location of the image (optional).
	 * @param string|null $title        The title of the image (optional).
	 * @param string|null $license      The license URL of the image (optional).
	 *
	 * @throws \InvalidArgumentException If any parameter is invalid.
	 */
	public function __construct(
		string $loc,
		?string $caption = null,
		?string $geo_location = null,
		?string $title = null,
		?string $license = null
	) {
		$this->validate_loc( $loc );
		$this->validate_caption( $caption );
		$this->validate_geo_location( $geo_location );
		$this->validate_title( $title );
		$this->validate_license( $license );

		$this->loc          = $loc;
		$this->caption      = $caption;
		$this->geo_location = $geo_location;
		$this->title        = $title;
		$this->license      = $license;
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
	 * Get the caption of the image.
	 *
	 * @return string|null The image caption or null if not set.
	 */
	public function caption(): ?string {
		return $this->caption;
	}

	/**
	 * Get the geographic location of the image.
	 *
	 * @return string|null The geographic location or null if not set.
	 */
	public function geo_location(): ?string {
		return $this->geo_location;
	}

	/**
	 * Get the title of the image.
	 *
	 * @return string|null The image title or null if not set.
	 */
	public function title(): ?string {
		return $this->title;
	}

	/**
	 * Get the license URL of the image.
	 *
	 * @return string|null The license URL or null if not set.
	 */
	public function license(): ?string {
		return $this->license;
	}

	/**
	 * Convert the image entry to an array representation.
	 *
	 * @return array<string, mixed> Array representation of the image entry.
	 */
	public function to_array(): array {
		$array = array(
			'loc' => $this->loc,
		);

		if ( null !== $this->caption ) {
			$array['caption'] = $this->caption;
		}

		if ( null !== $this->geo_location ) {
			$array['geo_location'] = $this->geo_location;
		}

		if ( null !== $this->title ) {
			$array['title'] = $this->title;
		}

		if ( null !== $this->license ) {
			$array['license'] = $this->license;
		}

		return $array;
	}

	/**
	 * Check if this image entry is equal to another.
	 *
	 * @param ImageEntry $other The other image entry to compare with.
	 * @return bool True if equal, false otherwise.
	 */
	public function equals( ImageEntry $other ): bool {
		return $this->loc === $other->loc &&
			$this->caption === $other->caption &&
			$this->geo_location === $other->geo_location &&
			$this->title === $other->title &&
			$this->license === $other->license;
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

	/**
	 * Validate the caption parameter.
	 *
	 * @param string|null $caption The caption to validate.
	 * @throws \InvalidArgumentException If the caption is invalid.
	 */
	private function validate_caption( ?string $caption ): void {
		if ( null !== $caption && strlen( $caption ) > 2048 ) {
			throw new \InvalidArgumentException( 'Image caption cannot exceed 2048 characters.' );
		}
	}

	/**
	 * Validate the geographic location parameter.
	 *
	 * @param string|null $geo_location The geographic location to validate.
	 * @throws \InvalidArgumentException If the geographic location is invalid.
	 */
	private function validate_geo_location( ?string $geo_location ): void {
		if ( null !== $geo_location && strlen( $geo_location ) > 2048 ) {
			throw new \InvalidArgumentException( 'Geographic location cannot exceed 2048 characters.' );
		}
	}

	/**
	 * Validate the title parameter.
	 *
	 * @param string|null $title The title to validate.
	 * @throws \InvalidArgumentException If the title is invalid.
	 */
	private function validate_title( ?string $title ): void {
		if ( null !== $title && strlen( $title ) > 2048 ) {
			throw new \InvalidArgumentException( 'Image title cannot exceed 2048 characters.' );
		}
	}

	/**
	 * Validate the license parameter.
	 *
	 * @param string|null $license The license URL to validate.
	 * @throws \InvalidArgumentException If the license URL is invalid.
	 */
	private function validate_license( ?string $license ): void {
		if ( null !== $license ) {
			$max_length = self::MAX_URL_LENGTH;
			if ( strlen( $license ) > $max_length ) {
				throw new \InvalidArgumentException(
					sprintf(
						'License URL cannot exceed %d characters.',
						$max_length
					)
				);
			}

			if ( ! filter_var( $license, FILTER_VALIDATE_URL ) ) {
				throw new \InvalidArgumentException( 'License URL must be a valid URL.' );
			}
		}
	}
}

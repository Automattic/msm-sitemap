<?php
/**
 * URL Entry Value Object
 *
 * @package Automattic\MSM_Sitemap\Domain\ValueObjects
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Domain\ValueObjects;

/**
 * URL Entry Value Object
 *
 * Represents a single URL entry in a sitemap with its associated metadata.
 * Follows the sitemap protocol specification from sitemaps.org.
 */
class UrlEntry {

	/**
	 * The URL of the page (required).
	 *
	 * @var string
	 */
	private string $loc;

	/**
	 * The date of last modification of the page (optional).
	 *
	 * @var string|null
	 */
	private ?string $lastmod;

	/**
	 * How frequently the page is likely to change (optional).
	 *
	 * @var string|null
	 */
	private ?string $changefreq;

	/**
	 * The priority of this URL relative to other URLs on the site (optional).
	 *
	 * @var float|null
	 */
	private ?float $priority;

	/**
	 * Array of image entries associated with this URL (optional).
	 *
	 * @var array<ImageEntry>
	 */
	private array $images;

	/**
	 * Valid changefreq values according to sitemap protocol.
	 *
	 * @var array<string>
	 */
	private const VALID_CHANGEFREQ_VALUES = array(
		'always',
		'hourly',
		'daily',
		'weekly',
		'monthly',
		'yearly',
		'never',
	);

	/**
	 * Maximum URL length according to sitemap protocol.
	 *
	 * @var int
	 */
	private const MAX_URL_LENGTH = 2048;

	/**
	 * Minimum priority value.
	 *
	 * @var float
	 */
	private const MIN_PRIORITY = 0.0;

	/**
	 * Maximum priority value.
	 *
	 * @var float
	 */
	private const MAX_PRIORITY = 1.0;

	/**
	 * Constructor.
	 *
	 * @param string                    $loc        The URL of the page (required).
	 * @param string|null               $lastmod    The date of last modification (optional).
	 * @param string|null               $changefreq How frequently the page changes (optional).
	 * @param float|null                $priority   The priority of this URL (optional).
	 * @param array<ImageEntry>|null    $images     Array of image entries (optional).
	 *
	 * @throws \InvalidArgumentException If any parameter is invalid.
	 */
	public function __construct(
		string $loc,
		?string $lastmod = null,
		?string $changefreq = null,
		?float $priority = null,
		?array $images = null
	) {
		$this->validate_loc( $loc );
		$this->validate_lastmod( $lastmod );
		$this->validate_changefreq( $changefreq );
		$this->validate_priority( $priority );
		$this->validate_images( $images );

		$this->loc        = $loc;
		$this->lastmod    = $lastmod;
		$this->changefreq = $changefreq;
		$this->priority   = $priority;
		$this->images     = $images ?? array();
	}

	/**
	 * Get the URL of the page.
	 *
	 * @return string The URL.
	 */
	public function loc(): string {
		return $this->loc;
	}

	/**
	 * Get the last modification date.
	 *
	 * @return string|null The last modification date or null if not set.
	 */
	public function lastmod(): ?string {
		return $this->lastmod;
	}

	/**
	 * Get the change frequency.
	 *
	 * @return string|null The change frequency or null if not set.
	 */
	public function changefreq(): ?string {
		return $this->changefreq;
	}

	/**
	 * Get the priority.
	 *
	 * @return float|null The priority or null if not set.
	 */
	public function priority(): ?float {
		return $this->priority;
	}

	/**
	 * Get the images associated with this URL.
	 *
	 * @return array<ImageEntry> Array of image entries.
	 */
	public function images(): array {
		return $this->images;
	}

	/**
	 * Check if this URL has any images.
	 *
	 * @return bool True if has images, false otherwise.
	 */
	public function has_images(): bool {
		return ! empty( $this->images );
	}

	/**
	 * Get the number of images associated with this URL.
	 *
	 * @return int The number of images.
	 */
	public function image_count(): int {
		return count( $this->images );
	}

	/**
	 * Convert the URL entry to an array representation.
	 *
	 * @return array<string, mixed> Array representation of the URL entry.
	 */
	public function to_array(): array {
		$array = array(
			'loc' => $this->loc,
		);

		if ( null !== $this->lastmod ) {
			$array['lastmod'] = $this->lastmod;
		}

		if ( null !== $this->changefreq ) {
			$array['changefreq'] = $this->changefreq;
		}

		if ( null !== $this->priority ) {
			$array['priority'] = $this->priority;
		}

		if ( ! empty( $this->images ) ) {
			$array['images'] = array_map( fn( ImageEntry $image ) => $image->to_array(), $this->images );
		}

		return $array;
	}

	/**
	 * Check if this URL entry is equal to another.
	 *
	 * @param UrlEntry $other The other URL entry to compare with.
	 * @return bool True if equal, false otherwise.
	 */
	public function equals( UrlEntry $other ): bool {
		return $this->loc === $other->loc &&
			$this->lastmod === $other->lastmod &&
			$this->changefreq === $other->changefreq &&
			$this->priority === $other->priority;
	}

	/**
	 * Validate the loc (URL) parameter.
	 *
	 * @param string $loc The URL to validate.
	 * @throws \InvalidArgumentException If the URL is invalid.
	 */
	private function validate_loc( string $loc ): void {
		if ( empty( $loc ) ) {
			throw new \InvalidArgumentException( __( 'URL cannot be empty.', 'msm-sitemap' ) );
		}

		if ( ! filter_var( $loc, FILTER_VALIDATE_URL ) ) {
			throw new \InvalidArgumentException(
				sprintf(
					/* translators: %s is the invalid URL. */
					__( 'Invalid URL format: %s', 'msm-sitemap' ),
					$loc
				)
			);
		}

		if ( strlen( $loc ) > self::MAX_URL_LENGTH ) {
			throw new \InvalidArgumentException(
				sprintf(
					/* translators: %1$d is the maximum URL length, %2$s is the URL. */
					__( 'URL exceeds maximum length of %1$d characters: %2$s', 'msm-sitemap' ),
					self::MAX_URL_LENGTH,
					$loc
				)
			);
		}
	}

	/**
	 * Validate the lastmod parameter.
	 *
	 * @param string|null $lastmod The last modification date to validate.
	 * @throws \InvalidArgumentException If the date is invalid.
	 */
	private function validate_lastmod( ?string $lastmod ): void {
		if ( null === $lastmod ) {
			return;
		}

		// Check for W3C Datetime format (YYYY-MM-DD or YYYY-MM-DDTHH:MM:SS+00:00).
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2})?$/', $lastmod ) ) {
			throw new \InvalidArgumentException(
				sprintf(
					/* translators: %s is the invalid date. */
					__( 'Invalid lastmod format: %s. Expected YYYY-MM-DD or YYYY-MM-DDTHH:MM:SS+00:00', 'msm-sitemap' ),
					$lastmod
				)
			);
		}

		// Validate that it's a real date.
		$date_parts                 = explode( 'T', $lastmod );
		$date_only                  = $date_parts[0];
		list( $year, $month, $day ) = explode( '-', $date_only );

		if ( ! checkdate( (int) $month, (int) $day, (int) $year ) ) {
			throw new \InvalidArgumentException(
				sprintf(
					/* translators: %s is the invalid date. */
					__( 'Invalid date in lastmod: %s', 'msm-sitemap' ),
					$lastmod
				)
			);
		}
	}

	/**
	 * Validate the changefreq parameter.
	 *
	 * @param string|null $changefreq The change frequency to validate.
	 * @throws \InvalidArgumentException If the change frequency is invalid.
	 */
	private function validate_changefreq( ?string $changefreq ): void {
		if ( null === $changefreq ) {
			return;
		}

		if ( ! in_array( $changefreq, self::VALID_CHANGEFREQ_VALUES, true ) ) {
			throw new \InvalidArgumentException(
				sprintf(
					/* translators: %1$s is the invalid changefreq, %2$s is the list of valid values. */
					__( 'Invalid changefreq value: %1$s. Valid values are: %2$s', 'msm-sitemap' ),
					$changefreq,
					implode( ', ', self::VALID_CHANGEFREQ_VALUES )
				)
			);
		}
	}

	/**
	 * Validate the priority parameter.
	 *
	 * @param float|null $priority The priority to validate.
	 * @throws \InvalidArgumentException If the priority is invalid.
	 */
	private function validate_priority( ?float $priority ): void {
		if ( null === $priority ) {
			return;
		}

		if ( $priority < self::MIN_PRIORITY || $priority > self::MAX_PRIORITY ) {
			throw new \InvalidArgumentException(
				sprintf(
					/* translators: %1$f is the invalid priority, %2$f is the minimum, %3$f is the maximum. */
					__( 'Invalid priority value: %1$f. Must be between %2$f and %3$f', 'msm-sitemap' ),
					$priority,
					self::MIN_PRIORITY,
					self::MAX_PRIORITY
				)
			);
		}
	}

	/**
	 * Validate the images parameter.
	 *
	 * @param array<ImageEntry>|null $images The images array to validate.
	 * @throws \InvalidArgumentException If the images array is invalid.
	 */
	private function validate_images( ?array $images ): void {
		if ( null === $images ) {
			return;
		}

		foreach ( $images as $image ) {
			if ( ! $image instanceof ImageEntry ) {
				throw new \InvalidArgumentException( 'All images must be ImageEntry objects.' );
			}
		}
	}
} 

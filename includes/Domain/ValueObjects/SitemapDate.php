<?php
/**
 * SitemapDate Value Object
 *
 * Immutable value object representing a date for sitemap operations.
 * Replaces scattered date string parsing and formatting throughout the codebase.
 *
 * @package Automattic\MSM_Sitemap\Domain\ValueObjects
 */

declare(strict_types=1);

namespace Automattic\MSM_Sitemap\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Represents a date for sitemap operations.
 *
 * This value object encapsulates date handling, providing:
 * - Validation on construction
 * - Multiple construction methods (from string, from parts, today)
 * - Formatted output for various contexts
 * - Immutability and equality comparison
 */
final class SitemapDate {

	/**
	 * The year.
	 */
	private int $year;

	/**
	 * The month (1-12).
	 */
	private int $month;

	/**
	 * The day (1-31).
	 */
	private int $day;

	/**
	 * Constructor.
	 *
	 * @param int $year  The year (e.g., 2024).
	 * @param int $month The month (1-12).
	 * @param int $day   The day (1-31).
	 * @throws InvalidArgumentException If the date is invalid.
	 */
	public function __construct( int $year, int $month, int $day ) {
		if ( ! checkdate( $month, $day, $year ) ) {
			throw new InvalidArgumentException(
				sprintf( 'Invalid date: %d-%d-%d', $year, $month, $day )
			);
		}

		$this->year  = $year;
		$this->month = $month;
		$this->day   = $day;
	}

	/**
	 * Create from a date string.
	 *
	 * Accepts formats like:
	 * - "2024-01-15"
	 * - "2024-01-15 00:00:00" (MySQL datetime)
	 *
	 * @param string $date Date string in Y-m-d format (time portion ignored).
	 * @return self
	 * @throws InvalidArgumentException If the date string is invalid.
	 */
	public static function fromString( string $date ): self {
		$parts = explode( '-', $date, 3 );

		if ( count( $parts ) < 3 ) {
			throw new InvalidArgumentException(
				sprintf( 'Invalid date format: %s. Expected Y-m-d.', $date )
			);
		}

		// Handle MySQL datetime format by taking only first 2 chars of day portion
		$day = (int) substr( $parts[2], 0, 2 );

		return new self( (int) $parts[0], (int) $parts[1], $day );
	}

	/**
	 * Create for today's date (UTC).
	 *
	 * @return self
	 */
	public static function today(): self {
		return self::fromString( gmdate( 'Y-m-d' ) );
	}

	/**
	 * Get the year.
	 *
	 * @return int
	 */
	public function year(): int {
		return $this->year;
	}

	/**
	 * Get the month.
	 *
	 * @return int
	 */
	public function month(): int {
		return $this->month;
	}

	/**
	 * Get the day.
	 *
	 * @return int
	 */
	public function day(): int {
		return $this->day;
	}

	/**
	 * Get as formatted string (Y-m-d).
	 *
	 * @return string Date in YYYY-MM-DD format.
	 */
	public function toString(): string {
		return sprintf( '%04d-%02d-%02d', $this->year, $this->month, $this->day );
	}

	/**
	 * Magic method for string conversion.
	 *
	 * @return string
	 */
	public function __toString(): string {
		return $this->toString();
	}

	/**
	 * Get padded year string.
	 *
	 * @return string Year as 4-digit string.
	 */
	public function yearString(): string {
		return sprintf( '%04d', $this->year );
	}

	/**
	 * Get padded month string.
	 *
	 * @return string Month as 2-digit string.
	 */
	public function monthString(): string {
		return sprintf( '%02d', $this->month );
	}

	/**
	 * Get padded day string.
	 *
	 * @return string Day as 2-digit string.
	 */
	public function dayString(): string {
		return sprintf( '%02d', $this->day );
	}

	/**
	 * Get URL query parameters for this date.
	 *
	 * @return array{yyyy: string, mm: string, dd: string}
	 */
	public function toUrlParams(): array {
		return array(
			'yyyy' => $this->yearString(),
			'mm'   => $this->monthString(),
			'dd'   => $this->dayString(),
		);
	}

	/**
	 * Get as MySQL datetime string.
	 *
	 * @param string $time Optional time portion (default: '00:00:00').
	 * @return string MySQL datetime format.
	 */
	public function toMysqlDatetime( string $time = '00:00:00' ): string {
		return $this->toString() . ' ' . $time;
	}

	/**
	 * Check equality with another SitemapDate.
	 *
	 * @param SitemapDate $other The other date to compare.
	 * @return bool True if equal.
	 */
	public function equals( SitemapDate $other ): bool {
		return $this->year === $other->year
			&& $this->month === $other->month
			&& $this->day === $other->day;
	}

	/**
	 * Check if this date is before another.
	 *
	 * @param SitemapDate $other The other date to compare.
	 * @return bool True if this date is before the other.
	 */
	public function isBefore( SitemapDate $other ): bool {
		if ( $this->year !== $other->year ) {
			return $this->year < $other->year;
		}
		if ( $this->month !== $other->month ) {
			return $this->month < $other->month;
		}
		return $this->day < $other->day;
	}

	/**
	 * Check if this date is after another.
	 *
	 * @param SitemapDate $other The other date to compare.
	 * @return bool True if this date is after the other.
	 */
	public function isAfter( SitemapDate $other ): bool {
		return $other->isBefore( $this );
	}
}

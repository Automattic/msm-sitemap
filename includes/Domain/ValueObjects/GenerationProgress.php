<?php
/**
 * GenerationProgress Value Object
 *
 * Immutable value object representing the progress of sitemap generation.
 * Replaces array-based progress tracking with a type-safe approach.
 *
 * @package Automattic\MSM_Sitemap\Domain\ValueObjects
 */

declare(strict_types=1);

namespace Automattic\MSM_Sitemap\Domain\ValueObjects;

/**
 * Represents the progress state of sitemap generation.
 *
 * This value object encapsulates generation progress, providing:
 * - Type-safe access to progress metrics
 * - Computed properties (percent complete, is complete)
 * - Immutability
 * - Array conversion for backwards compatibility
 */
final class GenerationProgress {

	/**
	 * Whether generation is currently in progress.
	 */
	private bool $in_progress;

	/**
	 * Total number of dates to generate.
	 */
	private int $total;

	/**
	 * Number of dates remaining to generate.
	 */
	private int $remaining;

	/**
	 * The current date being processed (optional).
	 */
	private ?SitemapDate $current_date;

	/**
	 * Constructor.
	 *
	 * @param bool              $in_progress  Whether generation is in progress.
	 * @param int               $total        Total number of dates to generate.
	 * @param int               $remaining    Number of dates remaining.
	 * @param SitemapDate|null  $current_date The current date being processed (optional).
	 */
	public function __construct(
		bool $in_progress,
		int $total,
		int $remaining,
		?SitemapDate $current_date = null
	) {
		$this->in_progress  = $in_progress;
		$this->total        = max( 0, $total );
		$this->remaining    = max( 0, min( $remaining, $this->total ) );
		$this->current_date = $current_date;
	}

	/**
	 * Create a progress instance representing no active generation.
	 *
	 * @return self
	 */
	public static function notStarted(): self {
		return new self( false, 0, 0 );
	}

	/**
	 * Create a progress instance for a newly started generation.
	 *
	 * @param int $total_dates Total number of dates to generate.
	 * @return self
	 */
	public static function started( int $total_dates ): self {
		return new self( true, $total_dates, $total_dates );
	}

	/**
	 * Check if generation is in progress.
	 *
	 * @return bool
	 */
	public function isInProgress(): bool {
		return $this->in_progress;
	}

	/**
	 * Get the total number of dates to generate.
	 *
	 * @return int
	 */
	public function total(): int {
		return $this->total;
	}

	/**
	 * Get the number of dates remaining.
	 *
	 * @return int
	 */
	public function remaining(): int {
		return $this->remaining;
	}

	/**
	 * Get the number of dates completed.
	 *
	 * @return int
	 */
	public function completed(): int {
		return $this->total - $this->remaining;
	}

	/**
	 * Get the current date being processed.
	 *
	 * @return SitemapDate|null
	 */
	public function currentDate(): ?SitemapDate {
		return $this->current_date;
	}

	/**
	 * Get the completion percentage (0-100).
	 *
	 * @return float
	 */
	public function percentComplete(): float {
		if ( 0 === $this->total ) {
			return 0.0;
		}
		return round( ( $this->completed() / $this->total ) * 100, 1 );
	}

	/**
	 * Check if generation is complete.
	 *
	 * @return bool True if all dates have been processed.
	 */
	public function isComplete(): bool {
		return ! $this->in_progress && $this->total > 0 && 0 === $this->remaining;
	}

	/**
	 * Check if generation has not started or has no work.
	 *
	 * @return bool True if no generation work exists.
	 */
	public function isEmpty(): bool {
		return 0 === $this->total;
	}

	/**
	 * Create a new instance with one date marked as completed.
	 *
	 * @param SitemapDate|null $next_date The next date to process (optional).
	 * @return self
	 */
	public function withDateCompleted( ?SitemapDate $next_date = null ): self {
		$new_remaining = max( 0, $this->remaining - 1 );
		$still_in_progress = $new_remaining > 0;

		return new self(
			$still_in_progress,
			$this->total,
			$new_remaining,
			$next_date
		);
	}

	/**
	 * Create a new instance with generation marked as cancelled.
	 *
	 * @return self
	 */
	public function withCancelled(): self {
		return new self( false, $this->total, $this->remaining, null );
	}

	/**
	 * Convert to array for backwards compatibility.
	 *
	 * @return array{in_progress: bool, total: int, remaining: int, completed: int}
	 */
	public function toArray(): array {
		return array(
			'in_progress' => $this->in_progress,
			'total'       => $this->total,
			'remaining'   => $this->remaining,
			'completed'   => $this->completed(),
		);
	}

	/**
	 * Check equality with another GenerationProgress.
	 *
	 * @param GenerationProgress $other The other progress to compare.
	 * @return bool
	 */
	public function equals( GenerationProgress $other ): bool {
		return $this->in_progress === $other->in_progress
			&& $this->total === $other->total
			&& $this->remaining === $other->remaining;
	}
}

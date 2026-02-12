<?php
/**
 * Tests for GenerationProgress Value Object.
 *
 * @package Automattic\MSM_Sitemap\Tests\Unit\Domain\ValueObjects
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Unit\Domain\ValueObjects;

use Automattic\MSM_Sitemap\Domain\ValueObjects\GenerationProgress;
use Automattic\MSM_Sitemap\Domain\ValueObjects\SitemapDate;
use Automattic\MSM_Sitemap\Tests\Unit\TestCase;
use Eris\Generators;
use Eris\TestTrait;

/**
 * GenerationProgress Value Object test case.
 *
 * Tests:
 * - State transitions (started, progress, completion, cancellation)
 * - Computed properties (completed count, percent complete, isEmpty)
 * - Boundary conditions (negative values, remaining > total)
 * - Immutability (withDateCompleted, withCancelled return new instances)
 */
class GenerationProgressTest extends TestCase {

	use TestTrait;

	// ===========================================
	// Factory method tests
	// ===========================================

	/**
	 * Test notStarted factory creates correct state.
	 */
	public function test_not_started_factory(): void {
		$progress = GenerationProgress::notStarted();

		$this->assertFalse( $progress->isInProgress() );
		$this->assertSame( 0, $progress->total() );
		$this->assertSame( 0, $progress->remaining() );
		$this->assertSame( 0, $progress->completed() );
		$this->assertTrue( $progress->isEmpty() );
	}

	/**
	 * Test started factory creates correct state.
	 */
	public function test_started_factory(): void {
		$progress = GenerationProgress::started( 100 );

		$this->assertTrue( $progress->isInProgress() );
		$this->assertSame( 100, $progress->total() );
		$this->assertSame( 100, $progress->remaining() );
		$this->assertSame( 0, $progress->completed() );
		$this->assertFalse( $progress->isEmpty() );
	}

	// ===========================================
	// Constructor normalization tests
	// ===========================================

	/**
	 * Test that negative total is normalized to 0.
	 */
	public function test_negative_total_normalized_to_zero(): void {
		$progress = new GenerationProgress( true, -10, 5 );

		$this->assertSame( 0, $progress->total() );
	}

	/**
	 * Test that negative remaining is normalized to 0.
	 */
	public function test_negative_remaining_normalized_to_zero(): void {
		$progress = new GenerationProgress( true, 100, -10 );

		$this->assertSame( 0, $progress->remaining() );
	}

	/**
	 * Test that remaining is capped at total.
	 */
	public function test_remaining_capped_at_total(): void {
		$progress = new GenerationProgress( true, 100, 150 );

		$this->assertSame( 100, $progress->remaining() );
	}

	// ===========================================
	// Computed property tests
	// ===========================================

	/**
	 * Test completed calculation.
	 */
	public function test_completed_calculation(): void {
		$progress = new GenerationProgress( true, 100, 30 );

		$this->assertSame( 70, $progress->completed() );
	}

	/**
	 * Test percent complete at 0%.
	 */
	public function test_percent_complete_at_zero(): void {
		$progress = GenerationProgress::started( 100 );

		$this->assertSame( 0.0, $progress->percentComplete() );
	}

	/**
	 * Test percent complete at 50%.
	 */
	public function test_percent_complete_at_fifty(): void {
		$progress = new GenerationProgress( true, 100, 50 );

		$this->assertSame( 50.0, $progress->percentComplete() );
	}

	/**
	 * Test percent complete at 100%.
	 */
	public function test_percent_complete_at_hundred(): void {
		$progress = new GenerationProgress( false, 100, 0 );

		$this->assertSame( 100.0, $progress->percentComplete() );
	}

	/**
	 * Test percent complete with zero total returns 0.
	 */
	public function test_percent_complete_with_zero_total(): void {
		$progress = GenerationProgress::notStarted();

		$this->assertSame( 0.0, $progress->percentComplete() );
	}

	/**
	 * Test percent complete is rounded to one decimal.
	 */
	public function test_percent_complete_rounded(): void {
		$progress = new GenerationProgress( true, 3, 1 );

		// 2/3 = 66.666...% -> should round to 66.7%
		$this->assertSame( 66.7, $progress->percentComplete() );
	}

	// ===========================================
	// isComplete tests
	// ===========================================

	/**
	 * Test isComplete returns true when all conditions met.
	 */
	public function test_is_complete_when_finished(): void {
		$progress = new GenerationProgress( false, 100, 0 );

		$this->assertTrue( $progress->isComplete() );
	}

	/**
	 * Test isComplete returns false when in progress.
	 */
	public function test_is_complete_false_when_in_progress(): void {
		$progress = new GenerationProgress( true, 100, 0 );

		$this->assertFalse( $progress->isComplete() );
	}

	/**
	 * Test isComplete returns false when remaining > 0.
	 */
	public function test_is_complete_false_when_remaining(): void {
		$progress = new GenerationProgress( false, 100, 10 );

		$this->assertFalse( $progress->isComplete() );
	}

	/**
	 * Test isComplete returns false when total is 0.
	 */
	public function test_is_complete_false_when_empty(): void {
		$progress = GenerationProgress::notStarted();

		$this->assertFalse( $progress->isComplete() );
	}

	// ===========================================
	// isEmpty tests
	// ===========================================

	/**
	 * Test isEmpty returns true when total is 0.
	 */
	public function test_is_empty_when_total_zero(): void {
		$progress = GenerationProgress::notStarted();

		$this->assertTrue( $progress->isEmpty() );
	}

	/**
	 * Test isEmpty returns false when total > 0.
	 */
	public function test_is_empty_false_when_has_work(): void {
		$progress = GenerationProgress::started( 10 );

		$this->assertFalse( $progress->isEmpty() );
	}

	// ===========================================
	// currentDate tests
	// ===========================================

	/**
	 * Test currentDate returns provided date.
	 */
	public function test_current_date_returns_provided_date(): void {
		$date     = new SitemapDate( 2024, 1, 15 );
		$progress = new GenerationProgress( true, 100, 50, $date );

		$this->assertSame( $date, $progress->currentDate() );
	}

	/**
	 * Test currentDate returns null when not set.
	 */
	public function test_current_date_returns_null_when_not_set(): void {
		$progress = GenerationProgress::started( 100 );

		$this->assertNull( $progress->currentDate() );
	}

	// ===========================================
	// withDateCompleted tests (immutability)
	// ===========================================

	/**
	 * Test withDateCompleted returns new instance.
	 */
	public function test_with_date_completed_returns_new_instance(): void {
		$progress    = GenerationProgress::started( 100 );
		$newProgress = $progress->withDateCompleted();

		$this->assertNotSame( $progress, $newProgress );
	}

	/**
	 * Test withDateCompleted decrements remaining.
	 */
	public function test_with_date_completed_decrements_remaining(): void {
		$progress    = GenerationProgress::started( 100 );
		$newProgress = $progress->withDateCompleted();

		$this->assertSame( 99, $newProgress->remaining() );
		$this->assertSame( 1, $newProgress->completed() );
	}

	/**
	 * Test withDateCompleted sets new current date.
	 */
	public function test_with_date_completed_sets_next_date(): void {
		$progress    = GenerationProgress::started( 100 );
		$nextDate    = new SitemapDate( 2024, 1, 16 );
		$newProgress = $progress->withDateCompleted( $nextDate );

		$this->assertSame( $nextDate, $newProgress->currentDate() );
	}

	/**
	 * Test withDateCompleted marks not in progress when last date.
	 */
	public function test_with_date_completed_marks_finished_on_last(): void {
		$progress    = new GenerationProgress( true, 1, 1 );
		$newProgress = $progress->withDateCompleted();

		$this->assertFalse( $newProgress->isInProgress() );
		$this->assertSame( 0, $newProgress->remaining() );
	}

	/**
	 * Test withDateCompleted doesn't go below zero.
	 */
	public function test_with_date_completed_doesnt_go_negative(): void {
		$progress = new GenerationProgress( true, 1, 0 ); // Already at 0.
		$newProgress = $progress->withDateCompleted();

		$this->assertSame( 0, $newProgress->remaining() );
	}

	/**
	 * Test successive withDateCompleted calls track progress.
	 */
	public function test_successive_with_date_completed(): void {
		$progress = GenerationProgress::started( 5 );

		$progress = $progress->withDateCompleted();
		$this->assertSame( 4, $progress->remaining() );

		$progress = $progress->withDateCompleted();
		$this->assertSame( 3, $progress->remaining() );

		$progress = $progress->withDateCompleted();
		$this->assertSame( 2, $progress->remaining() );

		$progress = $progress->withDateCompleted();
		$this->assertSame( 1, $progress->remaining() );

		$progress = $progress->withDateCompleted();
		$this->assertSame( 0, $progress->remaining() );
		$this->assertFalse( $progress->isInProgress() );
	}

	// ===========================================
	// withCancelled tests (immutability)
	// ===========================================

	/**
	 * Test withCancelled returns new instance.
	 */
	public function test_with_cancelled_returns_new_instance(): void {
		$progress    = GenerationProgress::started( 100 );
		$newProgress = $progress->withCancelled();

		$this->assertNotSame( $progress, $newProgress );
	}

	/**
	 * Test withCancelled marks not in progress.
	 */
	public function test_with_cancelled_marks_not_in_progress(): void {
		$progress    = new GenerationProgress( true, 100, 50 );
		$newProgress = $progress->withCancelled();

		$this->assertFalse( $newProgress->isInProgress() );
	}

	/**
	 * Test withCancelled preserves total and remaining.
	 */
	public function test_with_cancelled_preserves_counts(): void {
		$progress    = new GenerationProgress( true, 100, 50 );
		$newProgress = $progress->withCancelled();

		$this->assertSame( 100, $newProgress->total() );
		$this->assertSame( 50, $newProgress->remaining() );
	}

	/**
	 * Test withCancelled clears current date.
	 */
	public function test_with_cancelled_clears_current_date(): void {
		$date        = new SitemapDate( 2024, 1, 15 );
		$progress    = new GenerationProgress( true, 100, 50, $date );
		$newProgress = $progress->withCancelled();

		$this->assertNull( $newProgress->currentDate() );
	}

	// ===========================================
	// toArray tests
	// ===========================================

	/**
	 * Test toArray returns expected structure.
	 */
	public function test_to_array_returns_expected_structure(): void {
		$progress = new GenerationProgress( true, 100, 30 );

		$array = $progress->toArray();

		$this->assertSame(
			array(
				'in_progress' => true,
				'total'       => 100,
				'remaining'   => 30,
				'completed'   => 70,
			),
			$array
		);
	}

	// ===========================================
	// equals tests
	// ===========================================

	/**
	 * Test equals returns true for identical progress.
	 */
	public function test_equals_returns_true_for_identical(): void {
		$progress1 = new GenerationProgress( true, 100, 50 );
		$progress2 = new GenerationProgress( true, 100, 50 );

		$this->assertTrue( $progress1->equals( $progress2 ) );
	}

	/**
	 * Test equals returns false when in_progress differs.
	 */
	public function test_equals_returns_false_when_in_progress_differs(): void {
		$progress1 = new GenerationProgress( true, 100, 50 );
		$progress2 = new GenerationProgress( false, 100, 50 );

		$this->assertFalse( $progress1->equals( $progress2 ) );
	}

	/**
	 * Test equals returns false when total differs.
	 */
	public function test_equals_returns_false_when_total_differs(): void {
		$progress1 = new GenerationProgress( true, 100, 50 );
		$progress2 = new GenerationProgress( true, 200, 50 );

		$this->assertFalse( $progress1->equals( $progress2 ) );
	}

	/**
	 * Test equals returns false when remaining differs.
	 */
	public function test_equals_returns_false_when_remaining_differs(): void {
		$progress1 = new GenerationProgress( true, 100, 50 );
		$progress2 = new GenerationProgress( true, 100, 60 );

		$this->assertFalse( $progress1->equals( $progress2 ) );
	}

	/**
	 * Test equals ignores current_date (by design).
	 */
	public function test_equals_ignores_current_date(): void {
		$date1     = new SitemapDate( 2024, 1, 15 );
		$date2     = new SitemapDate( 2024, 1, 16 );
		$progress1 = new GenerationProgress( true, 100, 50, $date1 );
		$progress2 = new GenerationProgress( true, 100, 50, $date2 );

		// Equals doesn't compare current_date.
		$this->assertTrue( $progress1->equals( $progress2 ) );
	}

	// ===========================================
	// Property-based tests (Eris)
	// ===========================================

	/**
	 * Property: completed equals total minus remaining.
	 *
	 * For any valid GenerationProgress, the completed() value must
	 * always equal total() - remaining(). This is the fundamental
	 * invariant of the progress calculation.
	 */
	public function test_property_completed_equals_total_minus_remaining(): void {
		$this
			->forAll(
				Generators::choose( 0, 10000 ),
				Generators::choose( 0, 10000 )
			)
			->then( function ( int $total, int $remaining ): void {
				$progress = new GenerationProgress( true, $total, $remaining );

				$this->assertSame(
					$progress->total() - $progress->remaining(),
					$progress->completed(),
					sprintf(
						'completed (%d) must equal total (%d) - remaining (%d)',
						$progress->completed(),
						$progress->total(),
						$progress->remaining()
					)
				);
			} );
	}

	/**
	 * Property: percentComplete is always in [0.0, 100.0].
	 *
	 * Regardless of the total and remaining values provided,
	 * the percentage must be a valid percentage value.
	 */
	public function test_property_percent_complete_in_valid_range(): void {
		$this
			->forAll(
				Generators::choose( 0, 10000 ),
				Generators::choose( 0, 10000 )
			)
			->then( function ( int $total, int $remaining ): void {
				$progress = new GenerationProgress( true, $total, $remaining );
				$percent  = $progress->percentComplete();

				$this->assertGreaterThanOrEqual(
					0.0,
					$percent,
					'percentComplete must be >= 0.0'
				);
				$this->assertLessThanOrEqual(
					100.0,
					$percent,
					'percentComplete must be <= 100.0'
				);
			} );
	}

	/**
	 * Property: remaining is always clamped to [0, total] after normalisation.
	 *
	 * The constructor normalises negative values to 0 and caps remaining
	 * at total. This property verifies the invariant holds for any inputs.
	 */
	public function test_property_remaining_always_in_zero_to_total_range(): void {
		$this
			->forAll(
				Generators::choose( -100, 10000 ),
				Generators::choose( -100, 10000 )
			)
			->then( function ( int $total, int $remaining ): void {
				$progress = new GenerationProgress( true, $total, $remaining );

				$this->assertGreaterThanOrEqual(
					0,
					$progress->remaining(),
					'remaining must be >= 0 after normalisation'
				);
				$this->assertLessThanOrEqual(
					$progress->total(),
					$progress->remaining(),
					'remaining must be <= total after normalisation'
				);
			} );
	}

	/**
	 * Property: total is always non-negative after normalisation.
	 *
	 * Negative total values are normalised to 0 by the constructor.
	 */
	public function test_property_total_always_non_negative(): void {
		$this
			->forAll(
				Generators::choose( -1000, 10000 )
			)
			->then( function ( int $total ): void {
				$progress = new GenerationProgress( true, $total, 0 );

				$this->assertGreaterThanOrEqual(
					0,
					$progress->total(),
					sprintf( 'total must be >= 0 after normalisation, got %d for input %d', $progress->total(), $total )
				);
			} );
	}

	/**
	 * Property: reflexive equality holds for any GenerationProgress.
	 *
	 * Every progress instance must be equal to itself.
	 */
	public function test_property_reflexive_equality(): void {
		$this
			->forAll(
				Generators::bool(),
				Generators::choose( 0, 10000 ),
				Generators::choose( 0, 10000 )
			)
			->then( function ( bool $in_progress, int $total, int $remaining ): void {
				$progress = new GenerationProgress( $in_progress, $total, $remaining );

				$this->assertTrue(
					$progress->equals( $progress ),
					'GenerationProgress must be equal to itself'
				);
			} );
	}

	/**
	 * Property: toArray completed value is consistent with completed().
	 *
	 * The 'completed' key in toArray() must always match the value
	 * returned by the completed() method.
	 */
	public function test_property_to_array_completed_matches_method(): void {
		$this
			->forAll(
				Generators::bool(),
				Generators::choose( 0, 10000 ),
				Generators::choose( 0, 10000 )
			)
			->then( function ( bool $in_progress, int $total, int $remaining ): void {
				$progress = new GenerationProgress( $in_progress, $total, $remaining );
				$array    = $progress->toArray();

				$this->assertSame(
					$progress->completed(),
					$array['completed'],
					'toArray completed must match completed() method'
				);
				$this->assertSame(
					$progress->total(),
					$array['total'],
					'toArray total must match total() method'
				);
				$this->assertSame(
					$progress->remaining(),
					$array['remaining'],
					'toArray remaining must match remaining() method'
				);
				$this->assertSame(
					$progress->isInProgress(),
					$array['in_progress'],
					'toArray in_progress must match isInProgress() method'
				);
			} );
	}

	/**
	 * Property: percentComplete is 0.0 when total is 0.
	 *
	 * A zero total should always produce 0.0% to avoid division by zero.
	 */
	public function test_property_percent_complete_zero_when_total_zero(): void {
		$this
			->forAll(
				Generators::choose( -100, 0 )
			)
			->then( function ( int $total ): void {
				$progress = new GenerationProgress( false, $total, 0 );

				// After normalisation, total will be 0 for non-positive inputs.
				if ( 0 === $progress->total() ) {
					$this->assertSame(
						0.0,
						$progress->percentComplete(),
						'percentComplete must be 0.0 when total is 0'
					);
				}
			} );
	}
}

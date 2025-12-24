<?php
/**
 * GenerationProgressTest.php
 *
 * @package Automattic\MSM_Sitemap\Tests\Domain\ValueObjects
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Domain\ValueObjects;

use Automattic\MSM_Sitemap\Domain\ValueObjects\GenerationProgress;
use Automattic\MSM_Sitemap\Domain\ValueObjects\SitemapDate;
use Automattic\MSM_Sitemap\Tests\TestCase;

/**
 * Tests for GenerationProgress value object.
 */
class GenerationProgressTest extends TestCase {

	/**
	 * Test creating progress with constructor.
	 */
	public function test_constructor_creates_valid_progress(): void {
		$progress = new GenerationProgress( true, 100, 75 );

		$this->assertTrue( $progress->isInProgress() );
		$this->assertSame( 100, $progress->total() );
		$this->assertSame( 75, $progress->remaining() );
		$this->assertSame( 25, $progress->completed() );
	}

	/**
	 * Test notStarted factory method.
	 */
	public function test_not_started_creates_empty_progress(): void {
		$progress = GenerationProgress::notStarted();

		$this->assertFalse( $progress->isInProgress() );
		$this->assertSame( 0, $progress->total() );
		$this->assertSame( 0, $progress->remaining() );
		$this->assertSame( 0, $progress->completed() );
		$this->assertTrue( $progress->isEmpty() );
	}

	/**
	 * Test started factory method.
	 */
	public function test_started_creates_initial_progress(): void {
		$progress = GenerationProgress::started( 50 );

		$this->assertTrue( $progress->isInProgress() );
		$this->assertSame( 50, $progress->total() );
		$this->assertSame( 50, $progress->remaining() );
		$this->assertSame( 0, $progress->completed() );
		$this->assertFalse( $progress->isEmpty() );
	}

	/**
	 * Test percent complete calculation.
	 */
	public function test_percent_complete(): void {
		$progress = new GenerationProgress( true, 100, 75 );
		$this->assertSame( 25.0, $progress->percentComplete() );

		$half_done = new GenerationProgress( true, 200, 100 );
		$this->assertSame( 50.0, $half_done->percentComplete() );

		$almost_done = new GenerationProgress( true, 1000, 1 );
		$this->assertSame( 99.9, $almost_done->percentComplete() );
	}

	/**
	 * Test percent complete with zero total.
	 */
	public function test_percent_complete_with_zero_total(): void {
		$progress = GenerationProgress::notStarted();
		$this->assertSame( 0.0, $progress->percentComplete() );
	}

	/**
	 * Test isComplete.
	 */
	public function test_is_complete(): void {
		// Not complete - still in progress
		$in_progress = new GenerationProgress( true, 100, 50 );
		$this->assertFalse( $in_progress->isComplete() );

		// Not complete - has remaining
		$has_remaining = new GenerationProgress( false, 100, 10 );
		$this->assertFalse( $has_remaining->isComplete() );

		// Not complete - empty
		$empty = GenerationProgress::notStarted();
		$this->assertFalse( $empty->isComplete() );

		// Complete
		$complete = new GenerationProgress( false, 100, 0 );
		$this->assertTrue( $complete->isComplete() );
	}

	/**
	 * Test isEmpty.
	 */
	public function test_is_empty(): void {
		$empty = GenerationProgress::notStarted();
		$this->assertTrue( $empty->isEmpty() );

		$not_empty = GenerationProgress::started( 10 );
		$this->assertFalse( $not_empty->isEmpty() );
	}

	/**
	 * Test withDateCompleted creates new instance.
	 */
	public function test_with_date_completed(): void {
		$progress = new GenerationProgress( true, 100, 50 );
		$updated  = $progress->withDateCompleted();

		// Original unchanged
		$this->assertSame( 50, $progress->remaining() );

		// New instance updated
		$this->assertSame( 49, $updated->remaining() );
		$this->assertSame( 51, $updated->completed() );
		$this->assertTrue( $updated->isInProgress() );
	}

	/**
	 * Test withDateCompleted on last date.
	 */
	public function test_with_date_completed_on_last_date(): void {
		$progress = new GenerationProgress( true, 100, 1 );
		$updated  = $progress->withDateCompleted();

		$this->assertSame( 0, $updated->remaining() );
		$this->assertSame( 100, $updated->completed() );
		$this->assertFalse( $updated->isInProgress() );
	}

	/**
	 * Test withDateCompleted with next date.
	 */
	public function test_with_date_completed_with_next_date(): void {
		$next_date = new SitemapDate( 2024, 1, 15 );
		$progress  = new GenerationProgress( true, 100, 50 );
		$updated   = $progress->withDateCompleted( $next_date );

		$this->assertNotNull( $updated->currentDate() );
		$this->assertTrue( $next_date->equals( $updated->currentDate() ) );
	}

	/**
	 * Test withCancelled.
	 */
	public function test_with_cancelled(): void {
		$progress  = new GenerationProgress( true, 100, 50 );
		$cancelled = $progress->withCancelled();

		// Original unchanged
		$this->assertTrue( $progress->isInProgress() );

		// Cancelled instance
		$this->assertFalse( $cancelled->isInProgress() );
		$this->assertSame( 100, $cancelled->total() );
		$this->assertSame( 50, $cancelled->remaining() );
		$this->assertNull( $cancelled->currentDate() );
	}

	/**
	 * Test toArray for backwards compatibility.
	 */
	public function test_to_array(): void {
		$progress = new GenerationProgress( true, 100, 75 );
		$array    = $progress->toArray();

		$this->assertSame(
			array(
				'in_progress' => true,
				'total'       => 100,
				'remaining'   => 75,
				'completed'   => 25,
			),
			$array
		);
	}

	/**
	 * Test equals.
	 */
	public function test_equals(): void {
		$progress1 = new GenerationProgress( true, 100, 50 );
		$progress2 = new GenerationProgress( true, 100, 50 );
		$progress3 = new GenerationProgress( true, 100, 49 );

		$this->assertTrue( $progress1->equals( $progress2 ) );
		$this->assertFalse( $progress1->equals( $progress3 ) );
	}

	/**
	 * Test currentDate.
	 */
	public function test_current_date(): void {
		$date     = new SitemapDate( 2024, 6, 15 );
		$progress = new GenerationProgress( true, 100, 50, $date );

		$this->assertNotNull( $progress->currentDate() );
		$this->assertTrue( $date->equals( $progress->currentDate() ) );
	}

	/**
	 * Test remaining cannot exceed total.
	 */
	public function test_remaining_capped_at_total(): void {
		$progress = new GenerationProgress( true, 50, 100 );

		$this->assertSame( 50, $progress->remaining() );
	}

	/**
	 * Test negative values are normalized to zero.
	 */
	public function test_negative_values_normalized(): void {
		$progress = new GenerationProgress( true, -10, -5 );

		$this->assertSame( 0, $progress->total() );
		$this->assertSame( 0, $progress->remaining() );
	}
}

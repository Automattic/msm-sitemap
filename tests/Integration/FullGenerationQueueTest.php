<?php
/**
 * Tests that single-day updates leave a running full generation alone.
 *
 * @package Metro_Sitemap/unit_tests
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Integration;

use MSM_Sitemap_Builder_Cron;

/**
 * Tests that single-day updates leave a running full generation alone.
 */
class FullGenerationQueueTest extends TestCase {

	/**
	 * Put a full generation part-way through 2020-03, with days 1-10 still to do.
	 */
	public function setUp(): void {
		parent::setUp();
		MSM_Sitemap_Builder_Cron::reset_sitemap_data();
		update_option( 'msm_years_to_process', array( 2019, 2020 ) );
		update_option( 'msm_months_to_process', array( 1, 2, 3 ) );
		update_option( 'msm_days_to_process', range( 1, 10 ) );
		update_option( 'msm_sitemap_create_in_progress', true );
	}

	/**
	 * Remove the queue options.
	 */
	public function tearDown(): void {
		MSM_Sitemap_Builder_Cron::reset_sitemap_data();
		parent::tearDown();
	}

	/**
	 * A day queued by a post change does not move the full generation queue on.
	 */
	public function test_single_day_update_leaves_queue_alone(): void {
		$this->create_dummy_post( '2018-02-05 10:00:00' );

		MSM_Sitemap_Builder_Cron::generate_sitemap_for_year_month_day(
			array(
				'year'  => 2018,
				'month' => 2,
				'day'   => 5,
			)
		);

		$this->assertSame( range( 1, 10 ), get_option( 'msm_days_to_process' ) );
		$this->assertSame( array( 1, 2, 3 ), get_option( 'msm_months_to_process' ) );
		$this->assertSame( array( 2019, 2020 ), get_option( 'msm_years_to_process' ) );
		$this->assertTrue( (bool) get_option( 'msm_sitemap_create_in_progress' ) );
		$this->assertFalse(
			wp_next_scheduled(
				'msm_cron_generate_sitemap_for_year_month_day',
				array(
					array(
						'year'  => 2018,
						'month' => 2,
						'day'   => 10,
					),
				)
			)
		);
	}

	/**
	 * A post change dated 1 January does not drop a year from the queue.
	 */
	public function test_first_of_january_update_leaves_years_alone(): void {
		MSM_Sitemap_Builder_Cron::generate_sitemap_for_year_month_day(
			array(
				'year'  => 2018,
				'month' => 1,
				'day'   => 1,
			)
		);

		$this->assertSame( array( 2019, 2020 ), get_option( 'msm_years_to_process' ) );
	}

	/**
	 * The day at the head of the queue still moves the full generation on.
	 */
	public function test_queue_head_moves_queue_on(): void {
		MSM_Sitemap_Builder_Cron::generate_sitemap_for_year_month_day(
			array(
				'year'  => 2020,
				'month' => 3,
				'day'   => 10,
			)
		);

		$this->assertSame( range( 1, 9 ), get_option( 'msm_days_to_process' ) );
		$this->assertNotFalse(
			wp_next_scheduled(
				'msm_cron_generate_sitemap_for_year_month_day',
				array(
					array(
						'year'  => 2020,
						'month' => 3,
						'day'   => 9,
					),
				)
			)
		);
	}

	/**
	 * Without a full generation running, a single-day update does not touch the progress flag.
	 */
	public function test_single_day_update_without_full_generation(): void {
		MSM_Sitemap_Builder_Cron::reset_sitemap_data();
		$this->create_dummy_post( '2018-02-05 10:00:00' );

		MSM_Sitemap_Builder_Cron::generate_sitemap_for_year_month_day(
			array(
				'year'  => 2018,
				'month' => 2,
				'day'   => 5,
			)
		);

		$this->assertNotFalse( \Metro_Sitemap::get_sitemap_post_id( 2018, 2, 5 ) );
		$this->assertFalse( get_option( 'msm_sitemap_create_in_progress' ) );
	}

	/**
	 * A head day with no event is rescheduled, but only when a second check still finds it missing.
	 */
	public function test_stalled_day_is_rescheduled_on_second_check(): void {
		add_filter( 'msm_sitemap_cron_enabled', '__return_true' );
		$args = $this->head_day_args();

		do_action( 'msm_cron_update_sitemap' );
		$this->assertFalse( wp_next_scheduled( 'msm_cron_generate_sitemap_for_year_month_day', $args ) );

		do_action( 'msm_cron_update_sitemap' );
		$this->assertNotFalse( wp_next_scheduled( 'msm_cron_generate_sitemap_for_year_month_day', $args ) );
	}

	/**
	 * A generation whose next event is still queued is left alone.
	 */
	public function test_queued_day_is_left_alone(): void {
		add_filter( 'msm_sitemap_cron_enabled', '__return_true' );
		$args = $this->head_day_args();
		wp_schedule_single_event( time() + 300, 'msm_cron_generate_sitemap_for_year_month_day', $args );

		MSM_Sitemap_Builder_Cron::resume_stalled_generation();
		MSM_Sitemap_Builder_Cron::resume_stalled_generation();

		$this->assertSame( time() + 300, wp_next_scheduled( 'msm_cron_generate_sitemap_for_year_month_day', $args ) );
		$this->assertFalse( get_option( 'msm_generation_stall_check' ) );
	}

	/**
	 * A stalled generation between months gets its month event back.
	 */
	public function test_stalled_month_is_rescheduled(): void {
		add_filter( 'msm_sitemap_cron_enabled', '__return_true' );
		delete_option( 'msm_days_to_process' );
		$args = array(
			array(
				'year'  => 2020,
				'month' => 3,
			),
		);

		MSM_Sitemap_Builder_Cron::resume_stalled_generation();
		MSM_Sitemap_Builder_Cron::resume_stalled_generation();

		$this->assertNotFalse( wp_next_scheduled( 'msm_cron_generate_sitemap_for_year_month', $args ) );
	}

	/**
	 * A halt requested while the generation is stalled completes once the head day runs again.
	 */
	public function test_halt_completes_after_stall(): void {
		add_filter( 'msm_sitemap_cron_enabled', '__return_true' );
		update_option( 'msm_stop_processing', true );

		MSM_Sitemap_Builder_Cron::resume_stalled_generation();
		MSM_Sitemap_Builder_Cron::resume_stalled_generation();
		do_action( 'msm_cron_generate_sitemap_for_year_month_day', $this->head_day_args()[0] );

		$this->assertFalse( get_option( 'msm_sitemap_create_in_progress' ) );
		$this->assertFalse( get_option( 'msm_stop_processing' ) );
		$this->assertSame( range( 1, 10 ), get_option( 'msm_days_to_process' ) );
	}

	/**
	 * A generation marked as running with nothing left to process is marked as finished.
	 */
	public function test_running_with_empty_queue_is_cleared(): void {
		add_filter( 'msm_sitemap_cron_enabled', '__return_true' );
		delete_option( 'msm_years_to_process' );

		MSM_Sitemap_Builder_Cron::resume_stalled_generation();

		$this->assertFalse( get_option( 'msm_sitemap_create_in_progress' ) );
	}

	/**
	 * Nothing is rescheduled while the plugin's cron is off.
	 */
	public function test_nothing_rescheduled_when_cron_disabled(): void {
		add_filter( 'msm_sitemap_cron_enabled', '__return_false' );

		MSM_Sitemap_Builder_Cron::resume_stalled_generation();
		MSM_Sitemap_Builder_Cron::resume_stalled_generation();

		$this->assertFalse( wp_next_scheduled( 'msm_cron_generate_sitemap_for_year_month_day', $this->head_day_args() ) );
	}

	/**
	 * Cron event arguments for the day the queue in setUp() is waiting on.
	 *
	 * @return array<int, array<string, int>>
	 */
	private function head_day_args(): array {
		return array(
			array(
				'year'  => 2020,
				'month' => 3,
				'day'   => 10,
			),
		);
	}
}

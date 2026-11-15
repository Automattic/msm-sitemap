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
}

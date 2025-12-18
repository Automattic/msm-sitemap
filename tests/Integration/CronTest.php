<?php
/**
 * WP_Test_Sitemap_Cron
 *
 * @package Metro_Sitemap/unit_tests
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Integration;

use MSM_Sitemap_Builder_Cron;

/**
 * Unit Tests to confirm Cron is populated as expected
 */
class CronTest extends TestCase {

	/**
	 * Number of years to create posts for.
	 *
	 * @var Integer
	 */
	private int $num_years_data = 2;

	/**
	 * Generate posts and build the sitemap
	 */
	public function setUp(): void {
		parent::setUp();
		MSM_Sitemap_Builder_Cron::setup();

		$this->add_a_post_for_each_of_the_last_x_years( $this->num_years_data );

		$this->assertPostCount( $this->num_years_data );
	}

	/**
	 * Validate that Cron Jobs are scheduled as expected.
	 */
	public function test_cron_jobs_scheduling(): void {

		// Reset Cron SitemapBuilder.
		MSM_Sitemap_Builder_Cron::reset_sitemap_data();
		delete_option( 'msm_stop_processing' );
		MSM_Sitemap_Builder_Cron::generate_full_sitemap();
		update_option( 'msm_sitemap_create_in_progress', true );

		$years_being_processed = (array) get_option( 'msm_years_to_process', array() );

		// Validate initial Options is set to years for Posts.
		$expected_years = array(
			wp_date( 'Y' ),
			wp_date( 'Y', strtotime( '-1 year' ) ),
		);

		// Validate initial option values.
		$this->assertSame( array_diff( $expected_years, $years_being_processed ), array_diff( $years_being_processed, $expected_years ), "Years Scheduled for Processing don't align with Posts." );

		// fake_cron.
		$this->fake_cron();

		$months_being_processed = (array) get_option( 'msm_months_to_process', array() );

		// Validate Current Month is added to months_to_process.
		$month = (int) wp_date( 'n' );
		$this->assertContains( $month, $months_being_processed, 'Initial Year Processing should use Current Month if same year' );

		// fake_cron.
		$this->fake_cron();

		$days_being_processed  = (array) get_option( 'msm_days_to_process', array() );
		$years_being_processed = (array) get_option( 'msm_years_to_process', array() );

		$expected_days = range( 1, wp_date( 'j' ) );

		// Validate Current Month only processes days that have passed and today.
		$this->assertSame( array_diff( $expected_days, $days_being_processed ), array_diff( $days_being_processed, $expected_days ), "Current Month shouldn't process days in future." );

		// Test that the cron system is working by verifying we can process multiple days
		$this->assertGreaterThan( 0, count( $days_being_processed ), 'Should have days to process' );
	}
}

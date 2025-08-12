<?php
/**
 * FullGenerationHandler Test
 *
 * @package Automattic\MSM_Sitemap\Tests\Infrastructure\Cron
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Infrastructure\Cron;

use Automattic\MSM_Sitemap\Infrastructure\Cron\CronSchedulingService;
use Automattic\MSM_Sitemap\Infrastructure\Cron\FullGenerationHandler;

/**
 * Unit Tests for FullGenerationHandler cron generation flow
 */
class FullGenerationHandlerTest extends \Automattic\MSM_Sitemap\Tests\TestCase {

	/**
	 * Number of years to create posts for.
	 *
	 * @var int
	 */
	private int $num_years_data = 2;

	/**
	 * Set up test environment with posts and handler setup
	 */
	public function setUp(): void {
		parent::setUp();
		FullGenerationHandler::setup();

		$this->add_a_post_for_each_of_the_last_x_years( $this->num_years_data );
		
		// Add a post for the current year to ensure current month processing works
		$this->add_a_post_for_today();

		$this->assertPostCount( $this->num_years_data + 1 );
	}

	/**
	 * Test that full generation handler schedules years correctly
	 */
	public function test_schedules_years_for_processing_when_full_generation_triggered(): void {
		// Reset sitemap data via service.
		$generator = msm_sitemap_plugin()->get_sitemap_generator();
		$repository = new \Automattic\MSM_Sitemap\Infrastructure\Repositories\SitemapPostRepository();
		$service = new \Automattic\MSM_Sitemap\Application\Services\SitemapService( $generator, $repository );
		$service->reset_all_data();
		delete_option( 'msm_stop_processing' );
		CronSchedulingService::handle_full_generation();

		$years_being_processed = (array) get_option( 'msm_years_to_process', array() );

		// Validate initial Options is set to years for Posts.
		$expected_years = array(
			gmdate( 'Y' ),
			gmdate( 'Y', strtotime( '-1 year' ) ),
		);

		// Validate initial option values.
		$this->assertSame( 
			array_diff( $expected_years, $years_being_processed ), 
			array_diff( $years_being_processed, $expected_years ), 
			"Years Scheduled for Processing don't align with Posts." 
		);
	}

	/**
	 * Test that year processing schedules months correctly
	 */
	public function test_schedules_months_for_processing_when_year_processed(): void {
		// Clear the generation flag to allow year processing to run
		delete_option( 'msm_generation_in_progress' );
		
		// Call year processing directly instead of waiting for cron
		FullGenerationHandler::generate_sitemap_for_year( 2024 );

		$months_being_processed = (array) get_option( 'msm_months_to_process', array() );

		// Validate Current Month is added to months_to_process.
		$month = (int) gmdate( 'n' );
		$this->assertContains( 
			$month, 
			$months_being_processed, 
			'Initial Year Processing should use Current Month if same year' 
		);
	}

	/**
	 * Test that month processing schedules days correctly
	 */
	public function test_schedules_days_for_processing_when_month_processed(): void {
		// Create posts for the days we expect to be processed (1 to current day) in the correct year
		$current_day = (int) gmdate( 'j' );
		for ( $day = 1; $day <= $current_day; $day++ ) {
			$date = '2024-' . gmdate( 'm' ) . '-' . sprintf( '%02d', $day ) . ' 10:00:00';
			$this->create_dummy_post( $date );
		}

		// Call month processing directly instead of using fake_cron
		FullGenerationHandler::generate_sitemap_for_year_month( 2024, 8 );

		$days_being_processed = (array) get_option( 'msm_days_to_process', array() );
		$expected_days = range( 1, gmdate( 'j' ) );

		// Validate Current Month only processes days that have passed and today.
		$this->assertSame( 
			array_diff( $expected_days, $days_being_processed ), 
			array_diff( $days_being_processed, $expected_days ), 
			"Current Month shouldn't process days in future." 
		);

		// Test that the cron system is working by verifying we can process multiple days
		$this->assertGreaterThan( 0, count( $days_being_processed ), 'Should have days to process' );
	}
}

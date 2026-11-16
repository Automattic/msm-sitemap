<?php
/**
 * CronServiceTest
 *
 * @package Metro_Sitemap/unit_tests
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Integration;

use Automattic\MSM_Sitemap\Cron_Service;

/**
 * Unit Tests for Cron_Service class
 */
class CronServiceTest extends TestCase {

	/**
	 * Start each test with cron disabled and nothing scheduled.
	 */
	public function setUp(): void {
		parent::setUp();
		delete_option( Cron_Service::CRON_ENABLED_OPTION );
		wp_unschedule_hook( 'msm_cron_update_sitemap' );
	}

	/**
	 * Test that enable_cron() sets the option and schedules the event.
	 */
	public function test_enable_cron(): void {
		$result = Cron_Service::enable_cron();

		$this->assertTrue( $result );
		$this->assertTrue( (bool) get_option( Cron_Service::CRON_ENABLED_OPTION ) );
		$this->assertNotFalse( wp_next_scheduled( 'msm_cron_update_sitemap' ) );
	}

	/**
	 * Test that enable_cron() returns false when already enabled.
	 */
	public function test_enable_cron_when_already_enabled(): void {
		Cron_Service::enable_cron();

		$result = Cron_Service::enable_cron();

		$this->assertFalse( $result );
		$this->assertTrue( (bool) get_option( Cron_Service::CRON_ENABLED_OPTION ) );
	}

	/**
	 * Test that disable_cron() unsets the option, unschedules every sitemap event
	 * and clears any generation in progress.
	 */
	public function test_disable_cron(): void {
		Cron_Service::enable_cron();
		$events = array(
			'msm_cron_generate_sitemap_for_year'           => array( 'year' => 2024 ),
			'msm_cron_generate_sitemap_for_year_month'     => array(
				'year'  => 2024,
				'month' => 1,
			),
			'msm_cron_generate_sitemap_for_year_month_day' => array(
				'year'  => 2024,
				'month' => 1,
				'day'   => 1,
			),
		);
		foreach ( $events as $hook => $args ) {
			wp_schedule_single_event( time(), $hook, array( $args ) );
		}
		update_option( 'msm_sitemap_create_in_progress', true );
		update_option( 'msm_years_to_process', array( 2024 ) );
		$this->assertNotFalse( wp_next_scheduled( 'msm_cron_update_sitemap' ), 'Precondition: enabling cron schedules the update event.' );

		$result = Cron_Service::disable_cron();

		$this->assertTrue( $result );
		$this->assertFalse( (bool) get_option( Cron_Service::CRON_ENABLED_OPTION ) );
		$this->assertFalse( wp_next_scheduled( 'msm_cron_update_sitemap' ) );
		foreach ( $events as $hook => $args ) {
			$this->assertFalse( wp_next_scheduled( $hook, array( $args ) ), "$hook should be unscheduled." );
		}
		$this->assertFalse( get_option( 'msm_sitemap_create_in_progress' ) );
		$this->assertFalse( get_option( 'msm_years_to_process' ) );
	}

	/**
	 * Test that disable_cron() returns false when already disabled.
	 */
	public function test_disable_cron_when_already_disabled(): void {
		$result = Cron_Service::disable_cron();

		$this->assertFalse( $result );
		$this->assertFalse( (bool) get_option( Cron_Service::CRON_ENABLED_OPTION ) );
	}

	/**
	 * Test that is_cron_enabled() returns true when enabled.
	 */
	public function test_is_cron_enabled_when_enabled(): void {
		Cron_Service::enable_cron();

		$this->assertTrue( Cron_Service::is_cron_enabled() );
	}

	/**
	 * Test that is_cron_enabled() returns false when disabled.
	 */
	public function test_is_cron_enabled_when_disabled(): void {
		$this->assertFalse( Cron_Service::is_cron_enabled() );
	}

	/**
	 * Test that is_cron_enabled() clears scheduled events when option doesn't exist.
	 */
	public function test_is_cron_enabled_clears_events_when_option_missing(): void {
		// Manually schedule an event without setting the option
		wp_schedule_event( time(), 'hourly', 'msm_cron_update_sitemap' );

		$result = Cron_Service::is_cron_enabled();

		$this->assertFalse( $result );
		$this->assertFalse( wp_next_scheduled( 'msm_cron_update_sitemap' ) );
	}

	/**
	 * Test that the msm_sitemap_cron_enabled filter overrides the stored option both ways.
	 */
	public function test_is_cron_enabled_with_filter(): void {
		add_filter( 'msm_sitemap_cron_enabled', '__return_true' );
		$this->assertTrue( Cron_Service::is_cron_enabled(), 'The filter should enable cron when the option is unset.' );
		remove_filter( 'msm_sitemap_cron_enabled', '__return_true' );

		Cron_Service::enable_cron();
		add_filter( 'msm_sitemap_cron_enabled', '__return_false' );
		$this->assertFalse( Cron_Service::is_cron_enabled(), 'The filter should disable cron when the option is set.' );
	}

	/**
	 * Test that get_cron_status() returns correct status when enabled.
	 */
	public function test_get_cron_status_when_enabled(): void {
		Cron_Service::enable_cron();

		$status = Cron_Service::get_cron_status();

		$this->assertTrue( $status['enabled'] );
		$this->assertSame( wp_next_scheduled( 'msm_cron_update_sitemap' ), $status['next_scheduled'] );
		$this->assertIsInt( $status['next_scheduled'] );
		$this->assertFalse( $status['can_enable'] );
		$this->assertTrue( $status['can_disable'] );
	}

	/**
	 * Test that get_cron_status() returns correct status when disabled.
	 */
	public function test_get_cron_status_when_disabled(): void {
		$status = Cron_Service::get_cron_status();

		$this->assertFalse( $status['enabled'] );
		$this->assertFalse( $status['next_scheduled'] );
		$this->assertTrue( $status['can_enable'] );
		$this->assertFalse( $status['can_disable'] );
	}

	/**
	 * Test that get_cron_status() clears events when inconsistent.
	 */
	public function test_get_cron_status_clears_events_when_inconsistent(): void {
		// Set option to false but schedule an event
		update_option( Cron_Service::CRON_ENABLED_OPTION, false );
		wp_schedule_event( time(), 'hourly', 'msm_cron_update_sitemap' );

		$status = Cron_Service::get_cron_status();

		$this->assertFalse( $status['enabled'] );
		$this->assertFalse( $status['next_scheduled'] );
		$this->assertFalse( wp_next_scheduled( 'msm_cron_update_sitemap' ) );
	}

	/**
	 * Test that reset_cron() clears all cron data.
	 */
	public function test_reset_cron(): void {
		// Enable cron and add some processing options
		Cron_Service::enable_cron();
		update_option( 'msm_years_to_process', array( '2024' ) );
		update_option( 'msm_months_to_process', array( 1 ) );
		update_option( 'msm_days_to_process', array( 1 ) );
		update_option( 'msm_sitemap_create_in_progress', true );
		update_option( 'msm_stop_processing', true );
		$this->assertNotFalse( wp_next_scheduled( 'msm_cron_update_sitemap' ), 'Precondition: enabling cron schedules the update event.' );

		Cron_Service::reset_cron();

		$this->assertFalse( (bool) get_option( Cron_Service::CRON_ENABLED_OPTION ) );
		$this->assertFalse( wp_next_scheduled( 'msm_cron_update_sitemap' ) );
		$this->assertEmpty( get_option( 'msm_years_to_process' ) );
		$this->assertEmpty( get_option( 'msm_months_to_process' ) );
		$this->assertEmpty( get_option( 'msm_days_to_process' ) );
		$this->assertFalse( (bool) get_option( 'msm_sitemap_create_in_progress' ) );
		$this->assertFalse( (bool) get_option( 'msm_stop_processing' ) );
	}

	/**
	 * Test that should_auto_enable_cron() returns false by default.
	 */
	public function test_should_auto_enable_cron(): void {
		$this->assertFalse( Cron_Service::should_auto_enable_cron() );
	}
}

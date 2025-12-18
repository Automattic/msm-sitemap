<?php
/**
 * CronTest
 *
 * @package Automattic\MSM_Sitemap
 */
declare(strict_types=1);

namespace Automattic\MSM_Sitemap\Tests\Integration\Cli;

use Metro_Sitemap_CLI;
use Automattic\MSM_Sitemap\Cron_Service;

require_once __DIR__ . '/../Includes/mock-wp-cli.php';
require_once __DIR__ . '/../../../includes/wp-cli.php';

/**
 * Class CronTest
 *
 * @package Automattic\MSM_Sitemap\Tests\Cli
 */
final class CronTest extends \Automattic\MSM_Sitemap\Tests\Integration\TestCase {

	/**
	 * Set up test environment.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		// Clear any existing cron options
		delete_option( Cron_Service::CRON_ENABLED_OPTION );
		wp_unschedule_hook( 'msm_cron_update_sitemap' );
	}

	/**
	 * Clean up after tests.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		// Clean up cron options and events
		delete_option( Cron_Service::CRON_ENABLED_OPTION );
		wp_unschedule_hook( 'msm_cron_update_sitemap' );
		parent::tearDown();
	}

	/**
	 * Test that cron enable command enables cron.
	 *
	 * @return void
	 */
	public function test_cron_enable(): void {
		// Remove the filter that forces cron enabled in tests
		remove_filter( 'msm_sitemap_cron_enabled', '__return_true' );
		
		$cli = new Metro_Sitemap_CLI();

		ob_start();
		$cli->cron( array( 'enable' ), array() );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Sitemap cron enabled successfully', $output );
		$this->assertTrue( (bool) get_option( Cron_Service::CRON_ENABLED_OPTION ) );
		$this->assertNotFalse( wp_next_scheduled( 'msm_cron_update_sitemap' ) );
		
		// Restore the filter for other tests
		add_filter( 'msm_sitemap_cron_enabled', '__return_true' );
	}

	/**
	 * Test that cron enable command shows warning when already enabled.
	 *
	 * @return void
	 */
	public function test_cron_enable_when_already_enabled(): void {
		// Remove the filter that forces cron enabled in tests
		remove_filter( 'msm_sitemap_cron_enabled', '__return_true' );
		
		// Enable cron first
		Cron_Service::enable_cron();

		$cli = new Metro_Sitemap_CLI();

		ob_start();
		$cli->cron( array( 'enable' ), array() );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Cron is already enabled', $output );
		
		// Restore the filter for other tests
		add_filter( 'msm_sitemap_cron_enabled', '__return_true' );
	}

	/**
	 * Test that cron disable command disables cron.
	 *
	 * @return void
	 */
	public function test_cron_disable(): void {
		// Remove the filter that forces cron enabled in tests
		remove_filter( 'msm_sitemap_cron_enabled', '__return_true' );
		
		// Enable cron first
		Cron_Service::enable_cron();

		$cli = new Metro_Sitemap_CLI();

		ob_start();
		$cli->cron( array( 'disable' ), array() );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Sitemap cron disabled successfully', $output );
		$this->assertFalse( (bool) get_option( Cron_Service::CRON_ENABLED_OPTION ) );
		$this->assertFalse( wp_next_scheduled( 'msm_cron_update_sitemap' ) );
		
		// Restore the filter for other tests
		add_filter( 'msm_sitemap_cron_enabled', '__return_true' );
	}

	/**
	 * Test that cron disable command shows warning when already disabled.
	 *
	 * @return void
	 */
	public function test_cron_disable_when_already_disabled(): void {
		// Remove the filter that forces cron enabled in tests
		remove_filter( 'msm_sitemap_cron_enabled', '__return_true' );
		
		$cli = new Metro_Sitemap_CLI();

		ob_start();
		$cli->cron( array( 'disable' ), array() );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Cron is already disabled', $output );
		
		// Restore the filter for other tests
		add_filter( 'msm_sitemap_cron_enabled', '__return_true' );
	}

	/**
	 * Test that cron status command shows status when enabled.
	 *
	 * @return void
	 */
	public function test_cron_status_when_enabled(): void {
		// Enable cron first
		Cron_Service::enable_cron();

		$cli = new Metro_Sitemap_CLI();

		ob_start();
		$cli->cron( array( 'status' ), array() );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Yes', $output );
		$this->assertStringContainsString( 'enabled', $output );
	}

	/**
	 * Test that cron status command shows status when disabled.
	 *
	 * @return void
	 */
	public function test_cron_status_when_disabled(): void {
		// Remove the filter that forces cron enabled in tests
		remove_filter( 'msm_sitemap_cron_enabled', '__return_true' );
		
		$cli = new Metro_Sitemap_CLI();

		ob_start();
		$cli->cron( array( 'status' ), array() );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'No', $output );
		$this->assertStringContainsString( 'enabled', $output );
		
		// Restore the filter for other tests
		add_filter( 'msm_sitemap_cron_enabled', '__return_true' );
	}

	/**
	 * Test that cron status command shows JSON format.
	 *
	 * @return void
	 */
	public function test_cron_status_json_format(): void {
		// Enable cron first
		Cron_Service::enable_cron();

		$cli = new Metro_Sitemap_CLI();

		ob_start();
		$cli->cron( array( 'status' ), array( 'format' => 'json' ) );
		$output = ob_get_clean();
		$data = json_decode( $output, true );

		$this->assertIsArray( $data );
		$this->assertCount( 1, $data );
		$this->assertArrayHasKey( 'enabled', $data[0] );
		$this->assertArrayHasKey( 'next_scheduled', $data[0] );
		$this->assertArrayHasKey( 'blog_public', $data[0] );
		$this->assertArrayHasKey( 'generating', $data[0] );
		$this->assertArrayHasKey( 'halted', $data[0] );
		$this->assertEquals( 'Yes', $data[0]['enabled'] );
	}

	/**
	 * Test that cron reset command resets cron data.
	 *
	 * @return void
	 */
	public function test_cron_reset(): void {
		// Remove the filter that forces cron enabled in tests
		remove_filter( 'msm_sitemap_cron_enabled', '__return_true' );
		
		// Enable cron and add some processing options
		Cron_Service::enable_cron();
		update_option( 'msm_years_to_process', array( '2024' ) );
		update_option( 'msm_months_to_process', array( 1 ) );
		update_option( 'msm_days_to_process', array( 1 ) );
		update_option( 'msm_sitemap_create_in_progress', true );
		update_option( 'msm_stop_processing', true );

		$cli = new Metro_Sitemap_CLI();

		ob_start();
		$cli->cron( array( 'reset' ), array() );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Sitemap cron reset to clean state', $output );
		$this->assertFalse( (bool) get_option( Cron_Service::CRON_ENABLED_OPTION ) );
		$this->assertFalse( wp_next_scheduled( 'msm_cron_update_sitemap' ) );
		$this->assertEmpty( get_option( 'msm_years_to_process' ) );
		$this->assertEmpty( get_option( 'msm_months_to_process' ) );
		$this->assertEmpty( get_option( 'msm_days_to_process' ) );
		$this->assertFalse( (bool) get_option( 'msm_sitemap_create_in_progress' ) );
		$this->assertFalse( (bool) get_option( 'msm_stop_processing' ) );
		
		// Restore the filter for other tests
		add_filter( 'msm_sitemap_cron_enabled', '__return_true' );
	}

	/**
	 * Test that cron command without subcommand shows status.
	 *
	 * @return void
	 */
	public function test_cron_without_subcommand(): void {
		$cli = new Metro_Sitemap_CLI();

		ob_start();
		$cli->cron( array(), array() );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'enabled', $output );
		$this->assertStringContainsString( 'Yes', $output );
	}

	/**
	 * Test that cron command with invalid subcommand shows error.
	 *
	 * @return void
	 */
	public function test_cron_with_invalid_subcommand(): void {
		$cli = new Metro_Sitemap_CLI();

		// This test expects an exception to be thrown
		$this->expectException( 'WP_CLI\ExitException' );
		
		$cli->cron( array( 'invalid' ), array() );
	}
} 

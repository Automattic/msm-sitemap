<?php
/**
 * CronTest
 *
 * @package Automattic\MSM_Sitemap
 */
declare(strict_types=1);

namespace Automattic\MSM_Sitemap\Tests\Cli;

use Automattic\MSM_Sitemap\Infrastructure\CLI\CLICommand;
use Automattic\MSM_Sitemap\Application\Services\CronManagementService;

require_once __DIR__ . '/../Includes/mock-wp-cli.php';
require_once __DIR__ . '/../../includes/Infrastructure/CLI/CLICommand.php';

/**
 * Class CronTest
 *
 * @package Automattic\MSM_Sitemap\Tests\Cli
 */
final class CronTest extends \Automattic\MSM_Sitemap\Tests\TestCase {

	/**
	 * Set up test environment.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		// Clear any existing cron options
		delete_option( CronManagementService::CRON_ENABLED_OPTION );
		wp_unschedule_hook( 'msm_cron_update_sitemap' );
		// Clear serialized settings
		delete_option( 'msm_sitemap' );
	}

	/**
	 * Clean up after tests.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		// Clean up cron options and events
		delete_option( CronManagementService::CRON_ENABLED_OPTION );
		wp_unschedule_hook( 'msm_cron_update_sitemap' );
		// Clear serialized settings
		delete_option( 'msm_sitemap' );
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
		
		$cli = CLICommand::create();

		ob_start();
		$cli->cron( array( 'enable' ), array() );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Automatic sitemap updates enabled successfully', $output );
		$this->assertTrue( (bool) get_option( CronManagementService::CRON_ENABLED_OPTION ) );
		$this->assertNotFalse( wp_next_scheduled( 'msm_cron_update_sitemap' ) );
		
		// Restore the filter for other tests
		add_filter( 'msm_sitemap_cron_enabled', '__return_true' );
	}

	/**
	 * Get a CronManagementService instance for testing.
	 *
	 * @return CronManagementService
	 */
	private function get_cron_management(): CronManagementService {
		return $this->get_service( CronManagementService::class );
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
		$this->get_cron_management()->enable_cron();

		$cli = CLICommand::create();

		ob_start();
		$cli->cron( array( 'enable' ), array() );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Automatic updates are already enabled', $output );
		
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
		$this->get_cron_management()->enable_cron();

		$cli = CLICommand::create();

		ob_start();
		$cli->cron( array( 'disable' ), array() );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Automatic sitemap updates disabled successfully', $output );
		$this->assertFalse( (bool) get_option( CronManagementService::CRON_ENABLED_OPTION ) );
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
		
		$cli = CLICommand::create();

		ob_start();
		$cli->cron( array( 'disable' ), array() );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Automatic updates are already disabled', $output );
		
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
		$this->get_cron_management()->enable_cron();

		$cli = CLICommand::create();

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
		
		$cli = CLICommand::create();

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
		$this->get_cron_management()->enable_cron();

		$cli = CLICommand::create();

		ob_start();
		$cli->cron( array( 'status' ), array( 'format' => 'json' ) );
		$output = ob_get_clean();
		$data   = json_decode( $output, true );

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
		$this->get_cron_management()->enable_cron();
		update_option( 'msm_generation_in_progress', true );
		update_option( 'msm_sitemap_stop_generation', true );
		update_option( 'msm_background_generation_in_progress', true );
		update_option( 'msm_background_generation_total', 10 );
		update_option( 'msm_background_generation_remaining', 5 );

		$cli = CLICommand::create();

		ob_start();
		$cli->cron( array( 'reset' ), array() );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Sitemap cron reset to clean state', $output );
		$this->assertFalse( (bool) get_option( CronManagementService::CRON_ENABLED_OPTION ) );
		$this->assertFalse( wp_next_scheduled( 'msm_cron_update_sitemap' ) );
		$this->assertFalse( (bool) get_option( 'msm_generation_in_progress' ) );
		$this->assertFalse( (bool) get_option( 'msm_sitemap_stop_generation' ) );
		$this->assertFalse( (bool) get_option( 'msm_background_generation_in_progress' ) );
		$this->assertEmpty( get_option( 'msm_background_generation_total' ) );
		$this->assertEmpty( get_option( 'msm_background_generation_remaining' ) );

		// Restore the filter for other tests
		add_filter( 'msm_sitemap_cron_enabled', '__return_true' );
	}

	/**
	 * Test that cron command without subcommand shows status.
	 *
	 * @return void
	 */
	public function test_cron_without_subcommand(): void {
		$cli = CLICommand::create();

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
		$cli = CLICommand::create();

		// This test expects an exception to be thrown
		$this->expectException( 'WP_CLI\ExitException' );
		
		$cli->cron( array( 'invalid' ), array() );
	}

	/**
	 * Test that cron frequency command shows current frequency when no argument provided.
	 *
	 * @return void
	 */
	public function test_cron_frequency_show_current(): void {
		$cli = CLICommand::create();

		ob_start();
		$cli->cron( array( 'frequency' ), array() );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Current cron frequency:', $output );
		$this->assertStringContainsString( 'Valid frequencies:', $output );
		$this->assertStringContainsString( '5min', $output );
		$this->assertStringContainsString( 'hourly', $output );
	}

	/**
	 * Test that cron frequency command updates frequency successfully.
	 *
	 * @return void
	 */
	public function test_cron_frequency_update(): void {
		// Store original frequency
		$settings_service   = $this->get_service( \Automattic\MSM_Sitemap\Application\Services\SettingsService::class );
		$original_frequency = $settings_service->get_setting( 'cron_frequency', '15min' );

		// Enable cron first
		$this->get_cron_management()->enable_cron();

		// Test the CronManagementService directly
		$cron_management_service = $this->get_service( \Automattic\MSM_Sitemap\Application\Services\CronManagementService::class );
		$result                  = $cron_management_service->update_frequency( 'hourly' );
		
		$this->assertTrue( $result['success'], 'CronManagementService::update_frequency failed: ' . ( $result['message'] ?? 'Unknown error' ) );
		
		// Debug: Check what the setting actually is
		$actual_frequency = $settings_service->get_setting( 'cron_frequency' );
		$this->assertEquals( 'hourly', $actual_frequency, "Expected 'hourly' but got '$actual_frequency'. Result: " . json_encode( $result ) );

		// Restore original frequency
		$settings_service->update_setting( 'cron_frequency', $original_frequency );
	}

	/**
	 * Test that cron frequency command shows error for invalid frequency.
	 *
	 * @return void
	 */
	public function test_cron_frequency_invalid(): void {
		$cli = CLICommand::create();

		// This test expects an exception to be thrown
		$this->expectException( 'WP_CLI\ExitException' );
		
		$cli->cron( array( 'frequency', 'invalid' ), array() );
	}
} 

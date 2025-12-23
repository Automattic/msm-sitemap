<?php
/**
 * UI Test
 *
 * @package Automattic\MSM_Sitemap\Tests\Infrastructure\WordPress\Admin
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Infrastructure\WordPress\Admin;

use Automattic\MSM_Sitemap\Infrastructure\WordPress\Admin\UI;
use Automattic\MSM_Sitemap\Infrastructure\Cron\CronScheduler;
use Automattic\MSM_Sitemap\Application\Services\MissingSitemapDetectionService;
use Automattic\MSM_Sitemap\Application\Services\SitemapStatsService;
use Automattic\MSM_Sitemap\Application\Services\SettingsService;
use Automattic\MSM_Sitemap\Domain\Contracts\SitemapRepositoryInterface;
use Automattic\MSM_Sitemap\Infrastructure\WordPress\Admin\ActionHandlers;
use Automattic\MSM_Sitemap\Tests\TestCase;

/**
 * Test UI class
 */
class UITest extends TestCase {

	/**
	 * Test that the UI class correctly receives and stores the plugin file path and version.
	 */
	public function test_ui_constructor_accepts_plugin_file_path_and_version(): void {
		$cron_scheduler            = $this->createMock( CronScheduler::class );
		$plugin_file_path          = '/path/to/plugin/msm-sitemap.php';
		$plugin_version            = '1.5.2';
		$missing_detection_service = $this->createMock( MissingSitemapDetectionService::class );
		$stats_service             = $this->createMock( SitemapStatsService::class );
		$settings_service          = $this->createMock( SettingsService::class );
		$sitemap_repository        = $this->createMock( SitemapRepositoryInterface::class );
		$action_handlers           = $this->createMock( ActionHandlers::class );

		$ui = new UI( $cron_scheduler, $plugin_file_path, $plugin_version, $missing_detection_service, $stats_service, $settings_service, $sitemap_repository, $action_handlers );

		// Use reflection to access the private properties for testing
		$reflection         = new \ReflectionClass( $ui );
		$file_path_property = $reflection->getProperty( 'plugin_file_path' );
		$file_path_property->setAccessible( true );
		$version_property = $reflection->getProperty( 'plugin_version' );
		$version_property->setAccessible( true );

		$this->assertEquals( $plugin_file_path, $file_path_property->getValue( $ui ) );
		$this->assertEquals( $plugin_version, $version_property->getValue( $ui ) );
	}

	/**
	 * Test that the UI class can be instantiated with plugin file path and version.
	 */
	public function test_ui_can_be_instantiated_with_plugin_file_path_and_version(): void {
		$cron_scheduler            = $this->createMock( CronScheduler::class );
		$plugin_file_path          = '/path/to/plugin/msm-sitemap.php';
		$plugin_version            = '1.5.2';
		$missing_detection_service = $this->createMock( MissingSitemapDetectionService::class );
		$stats_service             = $this->createMock( SitemapStatsService::class );
		$settings_service          = $this->createMock( SettingsService::class );
		$sitemap_repository        = $this->createMock( SitemapRepositoryInterface::class );
		$action_handlers           = $this->createMock( ActionHandlers::class );

		// This should not throw any errors
		$ui = new UI( $cron_scheduler, $plugin_file_path, $plugin_version, $missing_detection_service, $stats_service, $settings_service, $sitemap_repository, $action_handlers );

		$this->assertInstanceOf( UI::class, $ui );
	}

	/**
	 * Test that the UI class setup method can be called.
	 */
	public function test_ui_setup_can_be_called(): void {
		$cron_scheduler            = $this->createMock( CronScheduler::class );
		$plugin_file_path          = '/path/to/plugin/msm-sitemap.php';
		$plugin_version            = '1.5.2';
		$missing_detection_service = $this->createMock( MissingSitemapDetectionService::class );
		$stats_service             = $this->createMock( SitemapStatsService::class );
		$settings_service          = $this->createMock( SettingsService::class );
		$sitemap_repository        = $this->createMock( SitemapRepositoryInterface::class );
		$action_handlers           = $this->createMock( ActionHandlers::class );

		$ui = new UI( $cron_scheduler, $plugin_file_path, $plugin_version, $missing_detection_service, $stats_service, $settings_service, $sitemap_repository, $action_handlers );

		// This should not throw any errors
		$ui->register_hooks();

		$this->assertInstanceOf( UI::class, $ui );
	}
}

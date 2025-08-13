<?php
/**
 * Settings Service Test
 *
 * @package Automattic\MSM_Sitemap\Tests
 */

declare(strict_types=1);

namespace Automattic\MSM_Sitemap\Tests;

use Automattic\MSM_Sitemap\Application\Services\SettingsService;

/**
 * Unit Tests for Settings Service.
 */
class SettingsServiceTest extends TestCase {

	/**
	 * Test that default settings are returned correctly.
	 */
	public function test_get_default_settings(): void {
		$container = \Automattic\MSM_Sitemap\Infrastructure\DI\msm_sitemap_container();
		$settings_service = $container->get( SettingsService::class );
		$defaults = $settings_service->get_default_settings();
		
		$this->assertEquals( '1', $defaults['include_images'] );
		$this->assertEquals( '1', $defaults['featured_images'] );
		$this->assertEquals( '1', $defaults['content_images'] );
		$this->assertEquals( 1000, $defaults['max_images_per_sitemap'] );
	}

	/**
	 * Test that current settings are retrieved correctly.
	 */
	public function test_get_all_settings(): void {
		// Clean up any existing options first
		delete_option( 'msm_sitemap' );

		$container = \Automattic\MSM_Sitemap\Infrastructure\DI\msm_sitemap_container();
		$settings_service = $container->get( SettingsService::class );

		// Test default values
		$settings = $settings_service->get_all_settings();
		
		$this->assertEquals( '1', $settings['include_images'] );
		$this->assertEquals( '1', $settings['featured_images'] );
		$this->assertEquals( '1', $settings['content_images'] );
		$this->assertEquals( 1000, $settings['max_images_per_sitemap'] );

		// Test with custom values
		$custom_settings = array(
			'include_images' => '0',
			'featured_images' => '0',
			'content_images' => '1',
			'max_images_per_sitemap' => 500,
		);
		update_option( 'msm_sitemap', $custom_settings );

		$settings = $settings_service->get_all_settings();
		
		$this->assertEquals( '0', $settings['include_images'] );
		$this->assertEquals( '0', $settings['featured_images'] );
		$this->assertEquals( '1', $settings['content_images'] );
		$this->assertEquals( 500, $settings['max_images_per_sitemap'] );

		// Clean up
		delete_option( 'msm_sitemap' );
	}

	/**
	 * Test that settings are updated successfully.
	 */
	public function test_update_settings_success(): void {
		// Clean up any existing options first
		delete_option( 'msm_sitemap' );

		$container = \Automattic\MSM_Sitemap\Infrastructure\DI\msm_sitemap_container();
		$settings_service = $container->get( SettingsService::class );

		$settings = array(
			'include_images' => false,
			'featured_images' => true,
			'content_images' => false,
			'max_images_per_sitemap' => 500,
		);

		$result = $settings_service->update_settings( $settings );

		$this->assertTrue( $result['success'] );
		$this->assertEquals( 'Settings saved successfully.', $result['message'] );
		$this->assertEquals( '0', $result['settings']['include_images'] );
		$this->assertEquals( '1', $result['settings']['featured_images'] );
		$this->assertEquals( '0', $result['settings']['content_images'] );
		$this->assertEquals( 500, $result['settings']['max_images_per_sitemap'] );

		// Verify options were actually saved
		$saved_settings = get_option( 'msm_sitemap' );
		$this->assertEquals( '0', $saved_settings['include_images'] );
		$this->assertEquals( '1', $saved_settings['featured_images'] );
		$this->assertEquals( '0', $saved_settings['content_images'] );
		$this->assertEquals( 500, $saved_settings['max_images_per_sitemap'] );

		// Clean up
		delete_option( 'msm_sitemap' );
	}

	/**
	 * Test that partial settings updates work correctly.
	 */
	public function test_update_settings_partial(): void {
		// Clean up any existing options first
		delete_option( 'msm_sitemap' );

		$container = \Automattic\MSM_Sitemap\Infrastructure\DI\msm_sitemap_container();
		$settings_service = $container->get( SettingsService::class );

		// Set initial settings
		$initial_settings = array(
			'include_images' => true,
			'featured_images' => true,
			'content_images' => true,
			'max_images_per_sitemap' => 1000,
		);
		$settings_service->update_settings( $initial_settings );

		// Update only one setting
		$partial_settings = array(
			'include_images' => false,
		);

		$result = $settings_service->update_settings( $partial_settings );

		$this->assertTrue( $result['success'] );
		
		// Verify only the specified setting was updated
		$final_settings = $settings_service->get_all_settings();
		$this->assertEquals( '0', $final_settings['include_images'] );
		$this->assertEquals( '1', $final_settings['featured_images'] );
		$this->assertEquals( '1', $final_settings['content_images'] );
		$this->assertEquals( 1000, $final_settings['max_images_per_sitemap'] );

		// Clean up
		delete_option( 'msm_sitemap' );
	}

	/**
	 * Test that settings validation works correctly.
	 */
	public function test_update_settings_validation_failure(): void {
		$container = \Automattic\MSM_Sitemap\Infrastructure\DI\msm_sitemap_container();
		$settings_service = $container->get( SettingsService::class );

		$invalid_settings = array(
			'max_images_per_sitemap' => 15000, // Exceeds maximum
		);

		$result = $settings_service->update_settings( $invalid_settings );

		$this->assertFalse( $result['success'] );
		$this->assertEquals( 'validation_failed', $result['error_code'] );
		$this->assertStringContainsString( 'Maximum images per sitemap must be between', $result['message'] );
	}

	/**
	 * Test that single setting updates work correctly.
	 */
	public function test_update_setting(): void {
		// Clean up any existing options first
		delete_option( 'msm_sitemap' );

		$container = \Automattic\MSM_Sitemap\Infrastructure\DI\msm_sitemap_container();
		$settings_service = $container->get( SettingsService::class );

		$result = $settings_service->update_setting( 'include_images', false );

		$this->assertTrue( $result['success'] );
		
		// Verify the setting was updated
		$settings = $settings_service->get_all_settings();
		$this->assertEquals( '0', $settings['include_images'] );

		// Clean up
		delete_option( 'msm_sitemap' );
	}

	/**
	 * Test that settings can be reset to defaults.
	 */
	public function test_reset_to_defaults(): void {
		// Clean up any existing options first
		delete_option( 'msm_sitemap' );

		$container = \Automattic\MSM_Sitemap\Infrastructure\DI\msm_sitemap_container();
		$settings_service = $container->get( SettingsService::class );

		// Set some custom settings first
		$custom_settings = array(
			'include_images' => '0',
			'featured_images' => '0',
			'content_images' => '0',
			'max_images_per_sitemap' => 500,
		);
		update_option( 'msm_sitemap', $custom_settings );

		// Reset to defaults
		$result = $settings_service->reset_to_defaults();

		$this->assertTrue( $result['success'] );
		$this->assertEquals( 'Settings reset to defaults successfully.', $result['message'] );

		// Verify settings are back to defaults
		$settings = $settings_service->get_all_settings();
		$this->assertEquals( '1', $settings['include_images'] );
		$this->assertEquals( '1', $settings['featured_images'] );
		$this->assertEquals( '1', $settings['content_images'] );
		$this->assertEquals( 1000, $settings['max_images_per_sitemap'] );

		// Clean up
		delete_option( 'msm_sitemap' );
	}

	/**
	 * Test that individual settings can be retrieved.
	 */
	public function test_get_setting(): void {
		// Clean up any existing options first
		delete_option( 'msm_sitemap' );

		$container = \Automattic\MSM_Sitemap\Infrastructure\DI\msm_sitemap_container();
		$settings_service = $container->get( SettingsService::class );

		// Test default value
		$value = $settings_service->get_setting( 'include_images' );
		$this->assertEquals( '1', $value );

		// Test custom value
		$settings_service->update_setting( 'include_images', false );
		$value = $settings_service->get_setting( 'include_images' );
		$this->assertEquals( '0', $value );

		// Test non-existent setting
		$value = $settings_service->get_setting( 'non_existent_setting', 'default' );
		$this->assertEquals( 'default', $value );

		// Clean up
		delete_option( 'msm_sitemap' );
	}

	/**
	 * Test that settings can be deleted.
	 */
	public function test_delete_setting(): void {
		// Clean up any existing options first
		delete_option( 'msm_sitemap' );

		$container = \Automattic\MSM_Sitemap\Infrastructure\DI\msm_sitemap_container();
		$settings_service = $container->get( SettingsService::class );

		// Set a custom setting
		$settings_service->update_setting( 'include_images', false );

		// Delete the setting
		$result = $settings_service->delete_setting( 'include_images' );

		$this->assertTrue( $result['success'] );
		$this->assertStringContainsString( 'Setting include_images deleted successfully', $result['message'] );

		// Verify the setting is back to default
		$value = $settings_service->get_setting( 'include_images' );
		$this->assertEquals( '1', $value );

		// Test deleting non-existent setting
		$result = $settings_service->delete_setting( 'non_existent_setting' );
		$this->assertFalse( $result['success'] );
		$this->assertEquals( 'setting_not_found', $result['error_code'] );

		// Clean up
		delete_option( 'msm_sitemap' );
	}

	/**
	 * Test that image-specific settings method works correctly.
	 */
	public function test_get_image_settings(): void {
		// Clean up any existing options first
		delete_option( 'msm_sitemap' );

		$container = \Automattic\MSM_Sitemap\Infrastructure\DI\msm_sitemap_container();
		$settings_service = $container->get( SettingsService::class );

		$image_settings = $settings_service->get_image_settings();

		$this->assertArrayHasKey( 'include_images', $image_settings );
		$this->assertArrayHasKey( 'featured_images', $image_settings );
		$this->assertArrayHasKey( 'content_images', $image_settings );
		$this->assertArrayHasKey( 'max_images_per_sitemap', $image_settings );

		$this->assertEquals( '1', $image_settings['include_images'] );
		$this->assertEquals( '1', $image_settings['featured_images'] );
		$this->assertEquals( '1', $image_settings['content_images'] );
		$this->assertEquals( 1000, $image_settings['max_images_per_sitemap'] );

		// Clean up
		delete_option( 'msm_sitemap' );
	}
}

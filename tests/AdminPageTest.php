<?php
/**
 * Admin page UI and permissions tests for MSM Sitemap
 *
 * @package Automattic\MSM_Sitemap\Tests
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests;

use Automattic\MSM_Sitemap\Infrastructure\WordPress\Admin\UI;
/**
 * Admin page UI and permissions tests for MSM Sitemap
 */
class AdminPageTest extends TestCase {
	/**
	 * Admin user ID.
	 *
	 * @var int
	 */
	protected $admin_id;

	/**
	 * Editor user ID.
	 *
	 * @var int
	 */
	protected $editor_id;

	/**
	 * Set up the test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		// Create admin and non-admin users
		$this->admin_id  = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$this->editor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
	}

	/**
	 * Test that the admin page loads for an admin user.
	 */
	public function test_admin_page_loads_for_admin() {
		global $plugin_page;
		wp_set_current_user( $this->admin_id );
		$plugin_page = 'msm-sitemap';
		ob_start();
		$ui = $this->get_service( UI::class );
		$ui->render_page();
		$output = ob_get_clean();
		$this->assertStringContainsString( 'Sitemap', $output );
		$this->assertStringContainsString( 'Indexed URLs', $output );
	}

	/**
	 * Test that the admin page is denied for a non-admin user.
	 */
	public function test_admin_page_denied_for_non_admin() {
		wp_set_current_user( $this->editor_id );
		$_GET['page'] = 'msm-sitemap';
		// WP will call wp_die, which in the test suite throws WPDieException.
		if ( ! class_exists( 'WPDieException' ) ) {
			$this->markTestSkipped( 'WPDieException not available in this environment.' );
		}
		$this->expectException( 'WPDieException' );
		$ui = $this->get_service( UI::class );
		$ui->render_page();
	}

	/**
	 * Test that the private blog message is displayed for a private site.
	 */
	public function test_private_blog_message() {
		global $plugin_page;
		update_option( 'blog_public', 0 );
		wp_set_current_user( $this->admin_id );
		$plugin_page = 'msm-sitemap';
		ob_start();
		$ui = $this->get_service( UI::class );
		$ui->render_page();
		$output = ob_get_clean();
		$this->assertStringContainsString( 'Sitemaps are not supported on private sites', $output );
		// Reset for other tests
		update_option( 'blog_public', 1 );
	}

	/**
	 * Test that the cron management section is rendered.
	 */
	public function test_cron_section_rendered() {
		global $plugin_page;
		wp_set_current_user( $this->admin_id );
		$plugin_page = 'msm-sitemap';
		ob_start();
		$ui = $this->get_service( UI::class );
		$ui->render_page();
		$output = ob_get_clean();
		// Automatic Updates section is now under the Posts tab with checkbox
		$this->assertStringContainsString( 'Automatic Updates', $output );
		$this->assertStringContainsString( 'automatic_updates_enabled', $output );
	}

	/**
	 * Test that the generate section is rendered.
	 */
	public function test_generate_section_rendered() {
		global $plugin_page;
		wp_set_current_user( $this->admin_id );
		// Enable the Danger Zone section visibility (hidden by default via Screen Options)
		update_user_meta( $this->admin_id, 'msm_sitemap_show_danger_zone', '1' );
		$plugin_page = 'msm-sitemap';
		ob_start();
		$ui = $this->get_service( UI::class );
		$ui->render_page();
		$output = ob_get_clean();
		// Check for danger zone content (expanded via toggle)
		$this->assertStringContainsString( 'Danger Zone', $output );
		$this->assertStringContainsString( 'Generate All Sitemaps (Force)', $output );
		$this->assertStringContainsString( 'Reset Sitemap Data', $output );
	}

	/**
	 * Test that generate buttons are disabled when cron is disabled.
	 */
	public function test_generate_buttons_disabled_when_cron_disabled() {
		global $plugin_page;
		wp_set_current_user( $this->admin_id );
		// Enable the Danger Zone section visibility (hidden by default via Screen Options)
		update_user_meta( $this->admin_id, 'msm_sitemap_show_danger_zone', '1' );
		$plugin_page = 'msm-sitemap';

		// Remove the filter that forces cron enabled in tests
		remove_filter( 'msm_sitemap_cron_enabled', '__return_true' );

		// Ensure cron is disabled
		delete_option( 'msm_sitemap_cron_enabled' );
		wp_unschedule_hook( 'msm_cron_update_sitemap' );

		ob_start();
		$ui = $this->get_service( UI::class );
		$ui->render_page();
		$output = ob_get_clean();

		// When cron is disabled, the generate buttons show a message instead
		$this->assertStringContainsString( 'Enable automatic updates to use this feature', $output );
		$this->assertStringContainsString( 'Reset Sitemap Data', $output );

		// Restore the filter for other tests
		add_filter( 'msm_sitemap_cron_enabled', '__return_true' );
	}
} 

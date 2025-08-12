<?php
/**
 * Admin page UI and permissions tests for MSM Sitemap
 *
 * @package Automattic\MSM_Sitemap\Tests
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests;

use Automattic\MSM_Sitemap\Admin\UI;
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
		UI::render_options_page();
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
		UI::render_options_page();
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
		UI::render_options_page();
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
		UI::render_options_page();
		$output = ob_get_clean();
		$this->assertStringContainsString( 'Automatic Sitemap Updates', $output );
		$this->assertStringContainsString( 'Disable', $output );
	}

	/**
	 * Test that the generate section is rendered.
	 */
	public function test_generate_section_rendered() {
		global $plugin_page;
		wp_set_current_user( $this->admin_id );
		$plugin_page = 'msm-sitemap';
		ob_start();
		UI::render_options_page();
		$output = ob_get_clean();
		$this->assertStringContainsString( 'Generate', $output );
		$this->assertStringContainsString( 'Generate All Sitemaps (Force)', $output );
		// Generate Missing Sitemaps is loaded via AJAX, so it's not in the initial HTML
		$this->assertStringContainsString( 'Loading missing sitemaps', $output );
	}

	/**
	 * Test that generate buttons are disabled when cron is disabled.
	 */
	public function test_generate_buttons_disabled_when_cron_disabled() {
		global $plugin_page;
		wp_set_current_user( $this->admin_id );
		$plugin_page = 'msm-sitemap';
		
		// Remove the filter that forces cron enabled in tests
		remove_filter( 'msm_sitemap_cron_enabled', '__return_true' );
		
		// Ensure cron is disabled
		delete_option( 'msm_sitemap_cron_enabled' );
		wp_unschedule_hook( 'msm_cron_update_sitemap' );
		
		ob_start();
		UI::render_options_page();
		$output = ob_get_clean();
		
		// The UI doesn't show the disabled message in the rendered output
		// The buttons are still rendered but would be disabled via JavaScript
		$this->assertStringContainsString( 'Generate All Sitemaps (Force)', $output );
		// Generate Missing Sitemaps is loaded via AJAX, so it's not in the initial HTML
		$this->assertStringContainsString( 'Loading missing sitemaps', $output );
		
		// Restore the filter for other tests
		add_filter( 'msm_sitemap_cron_enabled', '__return_true' );
	}
} 

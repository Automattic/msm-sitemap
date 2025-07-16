<?php
/**
 * Admin page UI and permissions tests for Metro Sitemap
 *
 * @package Metro_Sitemap/unit_tests
 */

namespace Automattic\MSM_Sitemap\Tests;

use Metro_Sitemap;

class AdminPageTest extends TestCase {
	protected $admin_id;
	protected $editor_id;

	public function setUp(): void {
		parent::setUp();
		// Create admin and non-admin users
		$this->admin_id = $this->factory->user->create(['role' => 'administrator']);
		$this->editor_id = $this->factory->user->create(['role' => 'editor']);
	}

	public function test_admin_page_loads_for_admin() {
		global $plugin_page;
		wp_set_current_user( $this->admin_id );
		$plugin_page = 'metro-sitemap';
		ob_start();
		Metro_Sitemap::render_sitemap_options_page();
		$output = ob_get_clean();
		$this->assertStringContainsString( 'Sitemap', $output );
		$this->assertStringContainsString( 'Indexed URLs', $output );
	}

	public function test_admin_page_denied_for_non_admin() {
		wp_set_current_user( $this->editor_id );
		$_GET['page'] = 'metro-sitemap';
		// WP will call wp_die, which in the test suite throws WPDieException.
		if ( ! class_exists( 'WPDieException' ) ) {
			$this->markTestSkipped( 'WPDieException not available in this environment.' );
		}
		$this->expectException( 'WPDieException' );
		Metro_Sitemap::render_sitemap_options_page();
	}

	public function test_private_blog_message() {
		global $plugin_page;
		update_option( 'blog_public', 0 );
		wp_set_current_user( $this->admin_id );
  		$plugin_page = 'metro-sitemap';
		ob_start();
		Metro_Sitemap::render_sitemap_options_page();
		$output = ob_get_clean();
		$this->assertStringContainsString( 'Sitemaps are not supported on private sites', $output );
		// Reset for other tests
		update_option( 'blog_public', 1 );
	}
} 

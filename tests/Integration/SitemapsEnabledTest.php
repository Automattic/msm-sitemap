<?php
/**
 * SitemapsEnabledTest
 *
 * @package Metro_Sitemap/unit_tests
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Integration;

use Automattic\MSM_Sitemap\Admin\UI;
use Automattic\MSM_Sitemap\Cron_Service;
use Automattic\MSM_Sitemap\Site;
use Metro_Sitemap_CLI;
use MSM_Sitemap_Builder_Cron;

require_once __DIR__ . '/Includes/mock-wp-cli.php';
require_once __DIR__ . '/../../includes/wp-cli.php';

/**
 * Tests for the msm_sitemap_is_enabled filter and every gate that uses it.
 */
class SitemapsEnabledTest extends TestCase {

	/**
	 * Clean up after tests.
	 */
	public function tearDown(): void {
		update_option( 'blog_public', '1' );
		delete_option( Cron_Service::CRON_ENABLED_OPTION );
		delete_option( 'msm_sitemap_create_in_progress' );
		delete_option( 'msm_days_to_process' );
		wp_unschedule_hook( 'msm_cron_update_sitemap' );
		parent::tearDown();
	}

	/**
	 * Without the filter, sitemaps follow blog_public.
	 */
	public function test_defaults_to_blog_public(): void {
		update_option( 'blog_public', '1' );
		$this->assertTrue( Site::are_sitemaps_enabled() );

		update_option( 'blog_public', '0' );
		$this->assertFalse( Site::are_sitemaps_enabled() );

		update_option( 'blog_public', '-1' );
		$this->assertFalse( Site::are_sitemaps_enabled() );
	}

	/**
	 * The filter receives the blog_public default and can override it both ways.
	 */
	public function test_filter_overrides_blog_public(): void {
		$received = null;
		add_filter(
			'msm_sitemap_is_enabled',
			static function ( $is_enabled ) use ( &$received ) {
				$received = $is_enabled;
				return ! $is_enabled;
			}
		);

		update_option( 'blog_public', '-1' );
		$this->assertTrue( Site::are_sitemaps_enabled() );
		$this->assertFalse( $received );

		update_option( 'blog_public', '1' );
		$this->assertFalse( Site::are_sitemaps_enabled() );
		$this->assertTrue( $received );
	}

	/**
	 * The admin page renders on a non-public site when the filter enables sitemaps.
	 */
	public function test_admin_page_renders_when_enabled_on_non_public_site(): void {
		global $plugin_page;
		update_option( 'blog_public', '-1' );
		add_filter( 'msm_sitemap_is_enabled', '__return_true' );
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		$plugin_page = 'metro-sitemap';

		ob_start();
		UI::render_options_page();
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'Sitemaps are not supported on private sites', $output );
		$this->assertStringContainsString( 'View XML Sitemap Index', $output );
	}

	/**
	 * The admin page shows the private site message when the filter disables sitemaps.
	 */
	public function test_admin_page_blocked_when_disabled_on_public_site(): void {
		global $plugin_page;
		add_filter( 'msm_sitemap_is_enabled', '__return_false' );
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		$plugin_page = 'metro-sitemap';

		ob_start();
		UI::render_options_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Sitemaps are not supported on private sites', $output );
	}

	/**
	 * Cron status reports whether sitemaps are enabled separately from blog_public.
	 */
	public function test_cron_status_can_enable_follows_filter(): void {
		remove_filter( 'msm_sitemap_cron_enabled', '__return_true' );
		update_option( 'blog_public', '-1' );

		$status = Cron_Service::get_cron_status();
		$this->assertFalse( $status['sitemaps_enabled'] );
		$this->assertFalse( $status['can_enable'] );

		add_filter( 'msm_sitemap_is_enabled', '__return_true' );
		$status = Cron_Service::get_cron_status();
		$this->assertFalse( $status['blog_public'] );
		$this->assertTrue( $status['sitemaps_enabled'] );
		$this->assertTrue( $status['can_enable'] );
	}

	/**
	 * WP-CLI can enable cron on a non-public site when the filter enables sitemaps.
	 */
	public function test_cli_cron_enable_on_non_public_site(): void {
		remove_filter( 'msm_sitemap_cron_enabled', '__return_true' );
		update_option( 'blog_public', '-1' );
		$cli = new Metro_Sitemap_CLI();

		try {
			ob_start();
			$cli->cron( array( 'enable' ), array() );
			$this->fail( 'Expected cron enable to fail when sitemaps are disabled.' );
		} catch ( \WP_CLI\ExitException $e ) {
			$this->assertFalse( (bool) get_option( Cron_Service::CRON_ENABLED_OPTION ) );
		} finally {
			ob_end_clean();
		}

		add_filter( 'msm_sitemap_is_enabled', '__return_true' );
		ob_start();
		$cli->cron( array( 'enable' ), array() );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Sitemap cron enabled successfully', $output );
		$this->assertTrue( (bool) get_option( Cron_Service::CRON_ENABLED_OPTION ) );
	}

	/**
	 * Full generation continues on a non-public site when the filter enables sitemaps.
	 */
	public function test_full_generation_follows_filter(): void {
		update_option( 'blog_public', '-1' );
		update_option( 'msm_days_to_process', array( 1 ) );

		// Halting leaves the queue where it got up to.
		MSM_Sitemap_Builder_Cron::find_next_day_to_process( 2024, 1, 1 );
		$this->assertSame( array( 1 ), get_option( 'msm_days_to_process' ) );

		// Processing carries on, and with nothing left to do, clears the queue.
		add_filter( 'msm_sitemap_is_enabled', '__return_true' );
		MSM_Sitemap_Builder_Cron::find_next_day_to_process( 2024, 1, 1 );
		$this->assertFalse( get_option( 'msm_days_to_process' ) );
	}
}

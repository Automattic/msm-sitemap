<?php
/**
 * Tests for Admin HelpTabs class.
 *
 * @package Automattic\MSM_Sitemap\Tests\Admin
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Admin;

use Automattic\MSM_Sitemap\Admin\HelpTabs;
use Automattic\MSM_Sitemap\Tests\TestCase;

/**
 * Tests for HelpTabs class.
 */
class HelpTabsTest extends TestCase {

	/**
	 * Admin user ID.
	 *
	 * @var int
	 */
	protected int $admin_id;

	/**
	 * Set up the test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
	}

	/**
	 * Test that setup registers the load action.
	 */
	public function test_setup_registers_load_action(): void {
		$page_hook = 'settings_page_msm-sitemap';

		// Remove any existing action first.
		remove_action( 'load-' . $page_hook, array( HelpTabs::class, 'add_help_tabs' ) );

		// Call setup.
		HelpTabs::setup( $page_hook );

		// Verify the action is registered.
		$this->assertGreaterThan(
			0,
			has_action( 'load-' . $page_hook, array( HelpTabs::class, 'add_help_tabs' ) )
		);

		// Clean up.
		remove_action( 'load-' . $page_hook, array( HelpTabs::class, 'add_help_tabs' ) );
	}

	/**
	 * Test that help tabs are added to the screen.
	 */
	public function test_add_help_tabs_adds_tabs_to_screen(): void {
		wp_set_current_user( $this->admin_id );
		set_current_screen( 'settings_page_msm-sitemap' );

		$screen = get_current_screen();
		$this->assertNotNull( $screen );

		// Call the method.
		HelpTabs::add_help_tabs();

		// Get the help tabs.
		$tabs = $screen->get_help_tabs();

		// Verify tabs are added.
		$expected_tabs = array(
			'msm-sitemap-overview',
			'msm-sitemap-auto-updates',
			'msm-sitemap-generation',
			'msm-sitemap-statistics',
			'msm-sitemap-cli',
			'msm-sitemap-troubleshooting',
		);

		foreach ( $expected_tabs as $tab_id ) {
			$this->assertArrayHasKey( $tab_id, $tabs, "Help tab '$tab_id' should be registered." );
		}
	}

	/**
	 * Test that overview tab contains expected content.
	 */
	public function test_overview_tab_content(): void {
		wp_set_current_user( $this->admin_id );
		set_current_screen( 'settings_page_msm-sitemap' );

		$screen = get_current_screen();
		HelpTabs::add_help_tabs();

		$tabs         = $screen->get_help_tabs();
		$overview_tab = $tabs['msm-sitemap-overview'] ?? null;

		$this->assertNotNull( $overview_tab );
		$this->assertStringContainsString( 'MSM Sitemap', $overview_tab['content'] );
		$this->assertStringContainsString( 'XML sitemaps', $overview_tab['content'] );
	}

	/**
	 * Test that auto updates tab contains expected content.
	 */
	public function test_auto_updates_tab_content(): void {
		wp_set_current_user( $this->admin_id );
		set_current_screen( 'settings_page_msm-sitemap' );

		$screen = get_current_screen();
		HelpTabs::add_help_tabs();

		$tabs = $screen->get_help_tabs();
		$tab  = $tabs['msm-sitemap-auto-updates'] ?? null;

		$this->assertNotNull( $tab );
		$this->assertStringContainsString( 'Automatic', $tab['content'] );
		$this->assertStringContainsString( 'Frequency', $tab['content'] );
	}

	/**
	 * Test that generation tab contains expected content.
	 */
	public function test_generation_tab_content(): void {
		wp_set_current_user( $this->admin_id );
		set_current_screen( 'settings_page_msm-sitemap' );

		$screen = get_current_screen();
		HelpTabs::add_help_tabs();

		$tabs = $screen->get_help_tabs();
		$tab  = $tabs['msm-sitemap-generation'] ?? null;

		$this->assertNotNull( $tab );
		$this->assertStringContainsString( 'Missing Sitemaps', $tab['content'] );
		$this->assertStringContainsString( 'Generate', $tab['content'] );
	}

	/**
	 * Test that CLI tab contains expected content.
	 */
	public function test_cli_tab_content(): void {
		wp_set_current_user( $this->admin_id );
		set_current_screen( 'settings_page_msm-sitemap' );

		$screen = get_current_screen();
		HelpTabs::add_help_tabs();

		$tabs = $screen->get_help_tabs();
		$tab  = $tabs['msm-sitemap-cli'] ?? null;

		$this->assertNotNull( $tab );
		$this->assertStringContainsString( 'WP-CLI', $tab['content'] );
		$this->assertStringContainsString( 'wp msm-sitemap', $tab['content'] );
	}

	/**
	 * Test that troubleshooting tab contains expected content.
	 */
	public function test_troubleshooting_tab_content(): void {
		wp_set_current_user( $this->admin_id );
		set_current_screen( 'settings_page_msm-sitemap' );

		$screen = get_current_screen();
		HelpTabs::add_help_tabs();

		$tabs = $screen->get_help_tabs();
		$tab  = $tabs['msm-sitemap-troubleshooting'] ?? null;

		$this->assertNotNull( $tab );
		$this->assertStringContainsString( 'Troubleshooting', $tab['content'] );
		$this->assertStringContainsString( 'cron', $tab['content'] );
	}

	/**
	 * Test that help sidebar is set.
	 */
	public function test_help_sidebar_is_set(): void {
		wp_set_current_user( $this->admin_id );
		set_current_screen( 'settings_page_msm-sitemap' );

		$screen = get_current_screen();
		HelpTabs::add_help_tabs();

		$sidebar = $screen->get_help_sidebar();

		$this->assertNotEmpty( $sidebar );
		$this->assertStringContainsString( 'GitHub', $sidebar );
		$this->assertStringContainsString( 'Documentation', $sidebar );
	}

	/**
	 * Test that add_help_tabs handles null screen gracefully.
	 */
	public function test_add_help_tabs_handles_null_screen(): void {
		// Reset current screen to null to test the early return guard.
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Testing edge case behaviour.
		$GLOBALS['current_screen'] = null;

		// This should not throw an error.
		HelpTabs::add_help_tabs();

		// If we get here, the test passed.
		$this->assertTrue( true );
	}
}

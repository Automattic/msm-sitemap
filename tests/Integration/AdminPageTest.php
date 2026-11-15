<?php
/**
 * Admin page UI and permissions tests for MSM Sitemap
 *
 * @package Metro_Sitemap/unit_tests
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Integration;

use Automattic\MSM_Sitemap\Admin\UI;
use Automattic\MSM_Sitemap\Cron_Service;
use Metro_Sitemap;
use WPDieException;

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
		$plugin_page = 'metro-sitemap';
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
		$_GET['page'] = 'metro-sitemap';
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
		$plugin_page = 'metro-sitemap';
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
		$plugin_page = 'metro-sitemap';
		ob_start();
		UI::render_options_page();
		$output = ob_get_clean();
		$this->assertStringContainsString( 'Automatic Sitemap Updates', $output );
		$this->assertStringContainsString( 'Disable Automatic Updates', $output );
	}

	/**
	 * Test that decorative icons are hidden from assistive technology and the new-tab link is announced.
	 */
	public function test_admin_page_accessibility_markup() {
		global $plugin_page;
		wp_set_current_user( $this->admin_id );
		$plugin_page = 'metro-sitemap';
		ob_start();
		UI::render_options_page();
		$output = ob_get_clean();
		$this->assertStringContainsString( '<span class="screen-reader-text">(opens in a new tab)</span>', $output );
		$this->assertStringContainsString( 'dashicons-external" style="vertical-align: middle;" aria-hidden="true"', $output );
		$this->assertStringContainsString( 'noticon-time" aria-hidden="true"', $output );
		$this->assertMatchesRegularExpression( '/<span aria-hidden="true">(✅|❌)<\/span> (Enabled|Disabled)/', $output );
	}

	/**
	 * Test that the generate section is rendered.
	 */
	public function test_generate_section_rendered() {
		global $plugin_page;
		wp_set_current_user( $this->admin_id );
		$plugin_page = 'metro-sitemap';
		ob_start();
		UI::render_options_page();
		$output = ob_get_clean();
		$this->assertStringContainsString( 'Generate', $output );
		$this->assertStringContainsString( 'Generate from all articles', $output );
		$this->assertStringContainsString( 'Generate from recently modified posts', $output );
	}

	/**
	 * Test that generate buttons are disabled when cron is disabled.
	 */
	public function test_generate_buttons_disabled_when_cron_disabled() {
		global $plugin_page;
		wp_set_current_user( $this->admin_id );
		$plugin_page = 'metro-sitemap';

		// Ensure cron is disabled
		delete_option( 'msm_sitemap_cron_enabled' );
		wp_unschedule_hook( 'msm_cron_update_sitemap' );
		
		ob_start();
		UI::render_options_page();
		$output = ob_get_clean();
		
		$this->assertMatchesRegularExpression( '/<button[^>]*value="generate-full"[^>]*\sdisabled[\s>]/', $output );
		$this->assertMatchesRegularExpression( '/<button[^>]*value="generate-latest"[^>]*\sdisabled[\s>]/', $output );
	}

	/**
	 * Data provider: each admin action value and the notices its handler can show.
	 *
	 * @return array<string, array{0: string, 1: string[]}>
	 */
	public function data_admin_actions(): array {
		return array(
			'enable cron'     => array( 'enable-cron', array( 'Automatic updates are already enabled.' ) ),
			'disable cron'    => array( 'disable-cron', array( 'Automatic sitemap updates disabled successfully.' ) ),
			'generate full'   => array( 'generate-full', array( 'Starting sitemap generation...' ) ),
			'generate latest' => array( 'generate-latest', array( 'Updating sitemap from recently modified posts...', 'Cannot generate from recently modified posts: no posts updated lately.' ) ),
			'halt'            => array( 'halt-generation', array( 'Cannot stop sitemap generation: sitemap generation not in progress.' ) ),
			'reset'           => array( 'reset-data', array( 'Sitemap data reset.' ) ),
		);
	}

	/**
	 * Test that admin actions run when the button labels are translated.
	 *
	 * @dataProvider data_admin_actions
	 *
	 * @param string   $action         The fixed value the button submits.
	 * @param string[] $notices        The notices the handler can show; one must appear.
	 */
	public function test_admin_actions_run_with_translated_labels( string $action, array $notices ) {
		global $plugin_page;
		wp_set_current_user( $this->admin_id );
		$plugin_page = 'metro-sitemap';

		// Simulate a translation: prefix every plugin string.
		add_filter(
			'gettext',
			static function ( $translation, $text, $domain ) {
				return 'msm-sitemap' === $domain ? '[xx] ' . $translation : $translation;
			},
			10,
			3
		);

		$nonce                = wp_create_nonce( 'msm-sitemap-action' );
		$_POST['action']      = $action;
		$_POST['_wpnonce']    = $nonce;
		$_REQUEST['_wpnonce'] = $nonce;

		ob_start();
		UI::render_options_page();
		$output = ob_get_clean();

		unset( $_POST['action'], $_POST['_wpnonce'], $_REQUEST['_wpnonce'] );

		$pattern = '/<p>\\[xx\\] (' . implode( '|', array_map( 'preg_quote', $notices ) ) . ')/';
		$this->assertMatchesRegularExpression( $pattern, $output );
	}

	/**
	 * Test that buttons submit fixed values and show translated labels.
	 */
	public function test_buttons_submit_fixed_values() {
		global $plugin_page;
		wp_set_current_user( $this->admin_id );
		$plugin_page = 'metro-sitemap';

		add_filter(
			'gettext',
			static function ( $translation, $text, $domain ) {
				return 'msm-sitemap' === $domain ? '[xx] ' . $translation : $translation;
			},
			10,
			3
		);

		ob_start();
		UI::render_options_page();
		$output = ob_get_clean();

		$this->assertMatchesRegularExpression( '/<button type="submit" name="action" value="disable-cron"[^>]*>\s*\[xx\] Disable Automatic Updates\s*<\/button>/', $output );
		$this->assertStringContainsString( 'value="generate-full"', $output );
		$this->assertStringContainsString( 'value="generate-latest"', $output );
		$this->assertStringContainsString( 'value="reset-data"', $output );
	}

	/**
	 * Post an admin action as the current user and render the page.
	 *
	 * @param string      $action The action value the button submits.
	 * @param string|null $nonce  The nonce to send, or null to send none.
	 */
	private function post_admin_action( string $action, ?string $nonce ): void {
		global $plugin_page;
		$plugin_page     = 'metro-sitemap'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$_POST['action'] = $action;
		if ( null !== $nonce ) {
			$_POST['_wpnonce']    = $nonce;
			$_REQUEST['_wpnonce'] = $nonce;
		}

		ob_start();
		try {
			UI::render_options_page();
		} finally {
			ob_end_clean();
		}
	}

	/**
	 * Test that an admin action without a valid nonce is refused before it runs.
	 *
	 * @dataProvider data_bad_nonces
	 *
	 * @param string|null $nonce The nonce to send, or null to send none.
	 */
	public function test_admin_action_without_valid_nonce_is_refused( ?string $nonce ): void {
		wp_set_current_user( $this->admin_id );
		delete_option( Cron_Service::CRON_ENABLED_OPTION );

		try {
			$this->post_admin_action( 'enable-cron', $nonce );
			$this->fail( 'Expected the action to be refused.' );
		} catch ( WPDieException $e ) {
			$this->assertSame( 403, $e->getCode() );
		}

		$this->assertFalse( Cron_Service::is_cron_enabled(), 'The action should not have run.' );
	}

	/**
	 * Data provider for test_admin_action_without_valid_nonce_is_refused().
	 *
	 * @return array<string, array{string|null}>
	 */
	public function data_bad_nonces(): array {
		return array(
			'no nonce'      => array( null ),
			'invalid nonce' => array( 'not-a-nonce' ),
		);
	}

	/**
	 * Test that users who cannot manage options cannot run admin actions, even with their own valid nonce.
	 *
	 * @dataProvider data_unprivileged_roles
	 *
	 * @param string $role The user's role, or an empty string for a logged-out visitor.
	 */
	public function test_admin_action_refused_for_unprivileged_user( string $role ): void {
		wp_set_current_user( '' === $role ? 0 : $this->factory->user->create( array( 'role' => $role ) ) );
		delete_option( Cron_Service::CRON_ENABLED_OPTION );

		try {
			$this->post_admin_action( 'enable-cron', wp_create_nonce( 'msm-sitemap-action' ) );
			$this->fail( 'Expected the action to be refused.' );
		} catch ( WPDieException $e ) {
			$this->assertStringContainsString( 'You do not have sufficient permissions', $e->getMessage() );
		}

		$this->assertFalse( Cron_Service::is_cron_enabled(), 'The action should not have run.' );
	}

	/**
	 * Data provider for test_admin_action_refused_for_unprivileged_user().
	 *
	 * @return array<string, array{string}>
	 */
	public function data_unprivileged_roles(): array {
		return array(
			'editor'     => array( 'editor' ),
			'subscriber' => array( 'subscriber' ),
			'logged out' => array( '' ),
		);
	}
}

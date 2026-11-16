<?php
/**
 * Tests for the MSM Sitemap AJAX endpoint and data retrieval logic.
 *
 * @package Metro_Sitemap/unit_tests
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Integration;

use Metro_Sitemap;
use WPDieException;

/**
 * Tests for the MSM Sitemap AJAX endpoint and data retrieval logic.
 */
class AjaxEndpointTest extends TestCase {
	/**
	 * Admin user ID.
	 *
	 * @var int
	 */
	protected $admin_id;

	/**
	 * Set up the test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
	}

	/**
	 * Test that the get_sitemap_counts_data method returns the expected keys.
	 */
	public function test_get_sitemap_counts_data_returns_expected_keys() {
		$data = Metro_Sitemap::get_sitemap_counts_data( 5 );
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'total_indexed_urls', $data );
		$this->assertArrayHasKey( 'total_sitemaps', $data );
		$this->assertArrayHasKey( 'sitemap_indexed_urls', $data );
		$this->assertIsArray( $data['sitemap_indexed_urls'] );
		$this->assertCount( 5, $data['sitemap_indexed_urls'] );
	}

	/**
	 * Call the AJAX action as WordPress would, and return the JSON it sends.
	 *
	 * @param array<string, string> $request The request arguments.
	 * @return array<string, mixed>|null The decoded response, or null if nothing was sent.
	 *
	 * @throws WPDieException When the request is refused.
	 */
	private function request_sitemap_counts( array $request ): ?array {
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'wp_die_ajax_handler', array( $this, 'get_wp_die_handler' ) );
		$_REQUEST = $request;
		$_POST    = $request;

		ob_start();
		try {
			do_action( 'wp_ajax_msm-sitemap-get-sitemap-counts' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WordPress.NamingConventions.ValidHookName.UseUnderscores -- Core's AJAX hook for our action.
		} catch ( WPDieException $e ) {
			// wp_send_json() ends with wp_die() once the response is sent.
			if ( '' !== $e->getMessage() ) {
				throw $e;
			}
		} finally {
			$output = (string) ob_get_clean();
		}

		return '' === $output ? null : json_decode( $output, true );
	}

	/**
	 * Test that an admin with a valid nonce gets the counts for the requested number of days.
	 */
	public function test_ajax_returns_counts_for_admin(): void {
		wp_set_current_user( $this->admin_id );

		$data = $this->request_sitemap_counts(
			array(
				'_wpnonce' => wp_create_nonce( 'msm-sitemap-action' ),
				'num_days' => '3',
			)
		);

		$this->assertSame( array( 'total_indexed_urls', 'total_sitemaps', 'sitemap_indexed_urls' ), array_keys( $data ) );
		$this->assertCount( 3, $data['sitemap_indexed_urls'] );
	}

	/**
	 * Test that a non-numeric number of days returns no daily counts rather than an error.
	 */
	public function test_ajax_with_non_numeric_days_returns_no_daily_counts(): void {
		wp_set_current_user( $this->admin_id );

		$data = $this->request_sitemap_counts(
			array(
				'_wpnonce' => wp_create_nonce( 'msm-sitemap-action' ),
				'num_days' => 'abc',
			)
		);

		$this->assertSame( array(), $data['sitemap_indexed_urls'] );
	}

	/**
	 * Test that a request without a valid nonce is refused and sends no data.
	 *
	 * @dataProvider data_bad_nonces
	 *
	 * @param array<string, string> $request The request arguments.
	 */
	public function test_ajax_without_valid_nonce_is_refused( array $request ): void {
		wp_set_current_user( $this->admin_id );

		$this->expectException( WPDieException::class );
		$this->expectExceptionCode( 403 );
		$this->expectExceptionMessage( 'The link you followed has expired.' );

		$this->request_sitemap_counts( $request );
	}

	/**
	 * Data provider for test_ajax_without_valid_nonce_is_refused().
	 *
	 * @return array<string, array{array<string, string>}>
	 */
	public function data_bad_nonces(): array {
		return array(
			'no nonce'      => array( array() ),
			'invalid nonce' => array( array( '_wpnonce' => 'not-a-nonce' ) ),
		);
	}

	/**
	 * Test that a user who cannot manage options is refused, even with their own valid nonce.
	 */
	public function test_ajax_refused_for_subscriber(): void {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'subscriber' ) ) );

		$this->expectException( WPDieException::class );
		$this->expectExceptionMessage( 'You do not have sufficient permissions' );

		$this->request_sitemap_counts( array( '_wpnonce' => wp_create_nonce( 'msm-sitemap-action' ) ) );
	}
}

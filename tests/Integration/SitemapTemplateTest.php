<?php
/**
 * Tests for serving sitemaps through the sitemap template.
 *
 * @package Automattic\MSM_Sitemap\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Integration;

use Metro_Sitemap;
use WPDieException;

/**
 * The template is where sitemap requests are answered, so it is where the
 * msm_sitemap_is_enabled filter has to stop a sitemap being served.
 */
final class SitemapTemplateTest extends TestCase {

	/**
	 * Build one day's sitemap and return the permalink of its post.
	 */
	private function build_day(): string {
		$post_id = $this->create_dummy_post( '2024-03-05 10:00:00' );
		Metro_Sitemap::generate_sitemap_for_date( '2024-03-05' );

		return (string) get_permalink( $post_id );
	}

	/**
	 * Request the template as WordPress would for a sitemap URL, and return what it prints.
	 *
	 * @param array<string, int> $query The yyyy, mm and dd query arguments.
	 */
	private function render_template( array $query = array() ): string {
		$_GET = $query;

		// The template sends a Content-Type header, which PHP refuses once PHPUnit has printed anything.
		// Ignore that warning only; pass anything else on to PHPUnit's handler.
		$previous = null;
		$previous = set_error_handler( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
			static function ( ...$error ) use ( &$previous ): bool {
				if ( false !== strpos( $error[1], 'Cannot modify header information' ) ) {
					return true;
				}
				return $previous ? (bool) $previous( ...$error ) : false;
			}
		);

		ob_start();
		try {
			require dirname( __DIR__, 2 ) . '/templates/full-sitemaps.php';
			return (string) ob_get_contents();
		} finally {
			ob_end_clean();
			restore_error_handler();
		}
	}

	/**
	 * A public site serves the index and the daily sitemap.
	 */
	public function test_public_site_serves_index_and_day(): void {
		$permalink = $this->build_day();

		$index = $this->render_template();
		$this->assertStringContainsString( '<sitemapindex', $index );
		$this->assertStringContainsString( 'yyyy=2024&amp;mm=03&amp;dd=05', $index );

		$day = $this->render_template(
			array(
				'yyyy' => 2024,
				'mm'   => 3,
				'dd'   => 5,
			)
		);
		$this->assertStringContainsString( '<loc>' . $permalink . '</loc>', $day );
	}

	/**
	 * The filter can serve sitemaps on a site that is not public.
	 */
	public function test_filter_serves_sitemaps_on_non_public_site(): void {
		$permalink = $this->build_day();
		update_option( 'blog_public', '0' );
		add_filter( 'msm_sitemap_is_enabled', '__return_true' );

		$day = $this->render_template(
			array(
				'yyyy' => 2024,
				'mm'   => 3,
				'dd'   => 5,
			)
		);

		$this->assertStringContainsString( '<loc>' . $permalink . '</loc>', $day );
	}

	/**
	 * Sites that are not public, or where the filter disables sitemaps, return a 404.
	 *
	 * @dataProvider data_disabled_sites
	 *
	 * @param string $blog_public The blog_public option.
	 * @param bool   $filtered    Whether to disable sitemaps with the filter.
	 */
	public function test_disabled_sites_return_404( string $blog_public, bool $filtered ): void {
		$this->build_day();
		update_option( 'blog_public', $blog_public );
		if ( $filtered ) {
			add_filter( 'msm_sitemap_is_enabled', '__return_false' );
		}

		try {
			$this->render_template();
			$this->fail( 'Expected the sitemap request to be refused.' );
		} catch ( WPDieException $e ) {
			$this->assertSame( 404, $e->getCode() );
			$this->assertStringContainsString( 'sitemaps are not available', $e->getMessage() );
		}
	}

	/**
	 * Data provider for test_disabled_sites_return_404().
	 *
	 * @return array<string, array{string, bool}>
	 */
	public function data_disabled_sites(): array {
		return array(
			'non-public site'              => array( '0', false ),
			'public site, filter disables' => array( '1', true ),
		);
	}

	/**
	 * Requests for a day with no sitemap, or with only part of a date, return a 404.
	 *
	 * @dataProvider data_unavailable_requests
	 *
	 * @param array<string, int> $query The yyyy, mm and dd query arguments.
	 */
	public function test_unavailable_sitemaps_return_404( array $query ): void {
		$this->build_day();

		try {
			$this->render_template( $query );
			$this->fail( 'Expected the sitemap request to be refused.' );
		} catch ( WPDieException $e ) {
			$this->assertSame( 404, $e->getCode() );
			$this->assertStringContainsString( 'no sitemap available', $e->getMessage() );
		}
	}

	/**
	 * Data provider for test_unavailable_sitemaps_return_404().
	 *
	 * @return array<string, array{array<string, int>}>
	 */
	public function data_unavailable_requests(): array {
		return array(
			'day with no posts'   => array(
				array(
					'yyyy' => 2024,
					'mm'   => 3,
					'dd'   => 6,
				),
			),
			'year and month only' => array(
				array(
					'yyyy' => 2024,
					'mm'   => 3,
				),
			),
			'day without month'   => array(
				array(
					'yyyy' => 2024,
					'dd'   => 5,
				),
			),
		);
	}
}

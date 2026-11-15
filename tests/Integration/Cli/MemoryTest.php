<?php
/**
 * Class MemoryTest
 *
 * @package Automattic\MSM_Sitemap\Tests\Cli
 */
declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Integration\Cli;

use Metro_Sitemap_CLI;

require_once __DIR__ . '/../Includes/mock-wp-cli.php';
require_once __DIR__ . '/../../../includes/wp-cli.php';

/**
 * Tests that long WP-CLI runs free the in-memory object cache as they go.
 */
final class MemoryTest extends \Automattic\MSM_Sitemap\Tests\Integration\TestCase {

	/**
	 * Generating a month of sitemaps flushes the in-memory object cache.
	 */
	public function test_generate_flushes_runtime_cache(): void {
		$this->create_dummy_post( '2023-04-15 10:00:00' );
		wp_cache_set( 'canary', 1, 'msm_sitemap_test' );

		( new Metro_Sitemap_CLI() )->generate(
			array(),
			array(
				'date'  => '2023-04',
				'quiet' => true,
			)
		);

		$this->assertFalse( wp_cache_get( 'canary', 'msm_sitemap_test' ) );
		$this->assertNotFalse( \Metro_Sitemap::get_sitemap_post_id( 2023, 4, 15 ) );
	}

	/**
	 * A run shorter than the flush interval leaves the cache alone.
	 */
	public function test_short_run_keeps_runtime_cache(): void {
		$this->create_dummy_post( '2023-04-15 10:00:00' );
		wp_cache_set( 'canary', 1, 'msm_sitemap_test' );

		( new Metro_Sitemap_CLI() )->generate(
			array(),
			array(
				'date'  => '2023-04-15',
				'quiet' => true,
			)
		);

		$this->assertSame( 1, wp_cache_get( 'canary', 'msm_sitemap_test' ) );
	}
}

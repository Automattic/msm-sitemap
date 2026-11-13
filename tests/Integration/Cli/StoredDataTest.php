<?php
/**
 * Class StoredDataTest
 *
 * @package Automattic\MSM_Sitemap\Tests\Cli
 */
declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Integration\Cli;

use Metro_Sitemap;
use Metro_Sitemap_CLI;

require_once __DIR__ . '/../Includes/mock-wp-cli.php';
require_once __DIR__ . '/../../../includes/wp-cli.php';

/**
 * CLI commands work with sitemaps stored as entry data.
 *
 * @package Automattic\MSM_Sitemap\Tests\Cli
 */
final class StoredDataTest extends \Automattic\MSM_Sitemap\Tests\Integration\TestCase {

	/**
	 * Sitemap post ID.
	 *
	 * @var int
	 */
	private int $sitemap_id;

	/**
	 * Generate a sitemap for one post.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->create_dummy_post( '2024-07-10 00:00:00' );
		Metro_Sitemap::generate_sitemap_for_date( '2024-07-10' );
		$this->sitemap_id = (int) Metro_Sitemap::get_sitemap_post_id( 2024, 7, 10 );
		$this->assertSame( '', get_post_meta( $this->sitemap_id, 'msm_sitemap_xml', true ) );
	}

	/**
	 * Validate reads the rendered XML.
	 *
	 * @return void
	 */
	public function test_validate_stored_data(): void {
		$cli = new Metro_Sitemap_CLI();
		$this->expectOutputRegex( '/1 valid sitemap/' );
		$cli->validate( array(), array( 'all' => true ) );
	}

	/**
	 * Recount counts the URLs in the rendered XML.
	 *
	 * @return void
	 */
	public function test_recount_stored_data(): void {
		update_post_meta( $this->sitemap_id, 'msm_indexed_url_count', 0 );
		$cli = new Metro_Sitemap_CLI();
		$this->expectOutputRegex( '/Total URLs found: 1/' );
		$cli->recount( array(), array() );
		$this->assertSame( '1', get_post_meta( $this->sitemap_id, 'msm_indexed_url_count', true ) );
	}
}

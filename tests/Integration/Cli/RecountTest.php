<?php
/**
 * Class RecountTest
 *
 * @package Automattic\MSM_Sitemap\Tests\Cli
 */
declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Integration\Cli;

use Metro_Sitemap_CLI;

require_once __DIR__ . '/../Includes/mock-wp-cli.php';
require_once __DIR__ . '/../../../includes/wp-cli.php';

/**
 * Class RecountTest
 *
 * @package Automattic\MSM_Sitemap\Tests\Cli
 */
final class RecountTest extends \Automattic\MSM_Sitemap\Tests\Integration\TestCase {

	/**
	 * Test sitemap post ID.
	 *
	 * @var int
	 */
	private int $post_id;

	/**
	 * Set up test environment.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$date          = '2024-07-10';
		$this->post_id = wp_insert_post(
			array(
				'post_type'   => 'msm_sitemap',
				'post_name'   => $date,
				'post_title'  => $date,
				'post_status' => 'publish',
				'post_date'   => $date . ' 00:00:00',
			) 
		);
		$this->assertIsInt( $this->post_id );
		update_post_meta( $this->post_id, 'msm_sitemap_xml', '<urlset><url><loc>https://example.com/</loc></url></urlset>' );
	}

	/**
	 * Clean up after tests.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		wp_delete_post( $this->post_id, true );
		parent::tearDown();
	}

	/**
	 * Test recounting indexed URLs for sitemaps.
	 *
	 * @return void
	 */
	public function test_recount_indexed_urls(): void {
		$cli = new Metro_Sitemap_CLI();
		$this->expectOutputRegex( '/Total URLs found: [0-9]+/' );
		$cli->recount( array(), array() );
	}

	/**
	 * Test recounting when there are no sitemaps.
	 *
	 * @return void
	 */
	public function test_recount_no_sitemaps(): void {
		$cli = new Metro_Sitemap_CLI();
		wp_delete_post( $this->post_id, true );
		$this->expectOutputRegex( '/Total URLs found: 0/' );
		$cli->recount( array(), array() );
	}

	/**
	 * Test recounting with multiple sitemaps and different URL counts.
	 *
	 * @return void
	 */
	public function test_recount_multiple_sitemaps(): void {
		$cli      = new Metro_Sitemap_CLI();
		$date2    = '2024-07-11';
		$post_id2 = wp_insert_post(
			array(
				'post_type'   => 'msm_sitemap',
				'post_name'   => $date2,
				'post_title'  => $date2,
				'post_status' => 'publish',
				'post_date'   => $date2 . ' 00:00:00',
			)
		);
		$this->assertIsInt( $post_id2 );
		update_post_meta( $post_id2, 'msm_sitemap_xml', '<urlset><url><loc>https://example.com/1</loc></url><url><loc>https://example.com/2</loc></url></urlset>' );
		$this->expectOutputRegex( '/Total URLs found: [0-9]+/' );
		$cli->recount( array(), array() );
		wp_delete_post( $post_id2, true );
	}

	/**
	 * Test recounting after deleting a sitemap.
	 *
	 * @return void
	 */
	public function test_recount_after_delete(): void {
		$cli      = new Metro_Sitemap_CLI();
		$date2    = '2024-07-11';
		$post_id2 = wp_insert_post(
			array(
				'post_type'   => 'msm_sitemap',
				'post_name'   => $date2,
				'post_title'  => $date2,
				'post_status' => 'publish',
				'post_date'   => $date2 . ' 00:00:00',
			)
		);
		$this->assertIsInt( $post_id2 );
		update_post_meta( $post_id2, 'msm_sitemap_xml', '<urlset><url><loc>https://example.com/1</loc></url></urlset>' );
		wp_delete_post( $post_id2, true );
		$this->expectOutputRegex( '/Total URLs found: [0-9]+/' );
		$cli->recount( array(), array() );
	}
} 

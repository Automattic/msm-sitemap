<?php
/**
 * Class ValidateTest
 *
 * @package Automattic\MSM_Sitemap\Tests\Cli
 */
declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Cli;

use Automattic\MSM_Sitemap\Infrastructure\CLI\CLI_Command;
require_once __DIR__ . '/../Includes/mock-wp-cli.php';
require_once __DIR__ . '/../../includes/Infrastructure/CLI/CLI_Command.php';

/**
 * Class ValidateTest
 *
 * @package Automattic\MSM_Sitemap\Tests\Cli
 */
final class ValidateTest extends \Automattic\MSM_Sitemap\Tests\TestCase {

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
		update_post_meta( $this->post_id, 'msm_sitemap_xml', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>https://example.com/</loc></url></urlset>' );
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
	 * Test validating a valid sitemap.
	 *
	 * @return void
	 */
	public function test_validate_valid_sitemap(): void {
		$cli = CLI_Command::create();
		$this->expectOutputRegex( '/1 valid, 0 invalid/' );
		$cli->validate( array(), array( 'all' => true ) );
	}

	/**
	 * Test validating when no sitemaps exist.
	 *
	 * @return void
	 */
	public function test_validate_no_sitemaps(): void {
		$cli = CLI_Command::create();
		wp_delete_post( $this->post_id, true );
		$this->expectOutputRegex( '/No sitemaps found to validate/' );
		$cli->validate( array(), array( 'all' => true ) );
	}

	/**
	 * Test validating a sitemap with invalid XML.
	 *
	 * @return void
	 */
	public function test_validate_invalid_xml(): void {
		$cli = CLI_Command::create();
		update_post_meta( $this->post_id, 'msm_sitemap_xml', '<urlset><url><loc>broken' );
		$this->expectOutputRegex( '/Invalid XML format|0 valid, 1 invalid/' );
		$cli->validate( array(), array( 'all' => true ) );
	}

	/**
	 * Test validating a sitemap with empty XML.
	 *
	 * @return void
	 */
	public function test_validate_empty_xml(): void {
		$cli = CLI_Command::create();
		update_post_meta( $this->post_id, 'msm_sitemap_xml', '' );
		$this->expectOutputRegex( '/Invalid XML format|0 valid, 1 invalid/' );
		$cli->validate( array(), array( 'all' => true ) );
	}

	/**
	 * Test validating a sitemap with no <url> entries.
	 *
	 * @return void
	 */
	public function test_validate_no_url_entries(): void {
		$cli = CLI_Command::create();
		update_post_meta( $this->post_id, 'msm_sitemap_xml', '<urlset></urlset>' );
		$this->expectOutputRegex( '/Sitemap must contain at least one <url> entry|0 valid, 1 invalid/' );
		$cli->validate( array(), array( 'all' => true ) );
	}

	/**
	 * Test validating a mix of valid and invalid sitemaps.
	 *
	 * @return void
	 */
	public function test_validate_mixed_sitemaps(): void {
		$cli = CLI_Command::create();
		// Add a valid and an invalid sitemap
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
		update_post_meta( $post_id2, 'msm_sitemap_xml', '<urlset></urlset>' );
		$this->expectOutputRegex( '/1 valid, 1 invalid/' );
		$cli->validate( array(), array( 'all' => true ) );
		wp_delete_post( $post_id2, true );
	}
} 

<?php
/**
 * ExportTest
 *
 * @package Automattic\MSM_Sitemap
 */
declare(strict_types=1);

namespace Automattic\MSM_Sitemap\Tests\Cli;

use Metro_Sitemap_CLI;

require_once __DIR__ . '/../Includes/mock-wp-cli.php';
require_once __DIR__ . '/../../includes/wp-cli.php';

/**
 * Class ExportTest
 *
 * @package Automattic\MSM_Sitemap\Tests\Cli
 */
final class ExportTest extends \Automattic\MSM_Sitemap\Tests\TestCase {

	/**
	 * Temporary export directory for test files.
	 *
	 * @var string
	 */
	private string $export_dir;

	/**
	 * Test date string used for sitemap posts.
	 *
	 * @var string
	 */
	private string $date;

	/**
	 * Set up test environment.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->date       = '2024-07-10';
		$this->export_dir = sys_get_temp_dir() . '/msm-sitemap-export-test';
		if ( is_dir( $this->export_dir ) ) {
			array_map( 'unlink', glob( $this->export_dir . '/*' ) );
			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.directory_rmdir
			rmdir( $this->export_dir );
		}
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'msm_sitemap',
				'post_name'   => $this->date,
				'post_title'  => $this->date,
				'post_status' => 'publish',
				'post_date'   => $this->date . ' 00:00:00',
			) 
		);
		$this->assertIsInt( $post_id );
		update_post_meta( $post_id, 'msm_sitemap_xml', '<urlset><url><loc>https://example.com/</loc></url></urlset>' );
	}

	/**
	 * Clean up after tests.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		$query = new \WP_Query(
			array(
				'post_type'      => 'msm_sitemap',
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => -1,
			) 
		);
		foreach ( $query->posts as $post_id ) {
			wp_delete_post( $post_id, true );
		}
		if ( is_dir( $this->export_dir ) ) {
			array_map( 'unlink', glob( $this->export_dir . '/*' ) );
			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.directory_rmdir
			rmdir( $this->export_dir );
		}
		parent::tearDown();
	}

	/**
	 * Test that export requires the output argument.
	 *
	 * @return void
	 */
	public function test_export_requires_output(): void {
		$cli = new Metro_Sitemap_CLI();
		$this->expectException( \Exception::class );
		$cli->export( array(), array() );
	}

	/**
	 * Test that export creates the output directory if it does not exist.
	 *
	 * @return void
	 */
	public function test_export_creates_directory(): void {
		$cli = new Metro_Sitemap_CLI();
		$this->expectOutputRegex( '/Exported [0-9]+ sitemap/' );
		if ( is_dir( $this->export_dir ) ) {
			array_map( 'unlink', glob( $this->export_dir . '/*' ) );
			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.directory_rmdir
			rmdir( $this->export_dir );
		}
		$cli->export( array(), array( 'output' => $this->export_dir ) );
		$this->assertDirectoryExists( $this->export_dir );
	}

	/**
	 * Test that export writes a file to the output directory.
	 *
	 * @return void
	 */
	public function test_export_file_written(): void {
		$cli = new Metro_Sitemap_CLI();
		$this->expectOutputRegex( '/Exported [0-9]+ sitemap/' );
		$cli->export( array(), array( 'output' => $this->export_dir ) );
		$files = glob( $this->export_dir . '/*.xml' );
		$this->assertNotEmpty( $files );
		// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
		$xml = file_get_contents( $files[0] );
		$this->assertStringContainsString( '<urlset>', $xml );
	}

	/**
	 * Test that export pretty prints XML when the pretty flag is set.
	 *
	 * @return void
	 */
	public function test_export_pretty_print(): void {
		$cli = new Metro_Sitemap_CLI();
		$this->expectOutputRegex( '/Exported [0-9]+ sitemap/' );
		$cli->export(
			array(),
			array(
				'output' => $this->export_dir,
				'pretty' => true,
			) 
		);
		$files = glob( $this->export_dir . '/*.xml' );
		$this->assertNotEmpty( $files );
		// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
		$xml = file_get_contents( $files[0] );
		// Pretty XML should have indents/newlines.
		$this->assertStringContainsString( "\n", $xml );
		$this->assertStringContainsString( '  <url>', $xml );
	}

	/**
	 * Test that export by date only exports the specified date.
	 *
	 * @return void
	 */
	public function test_export_by_date(): void {
		$cli = new Metro_Sitemap_CLI();
		$this->expectOutputRegex( '/Exported [0-9]+ sitemap/' );
		$cli->export(
			array(),
			array(
				'output' => $this->export_dir,
				'date'   => $this->date,
			) 
		);
		$files = glob( $this->export_dir . '/*.xml' );
		$this->assertNotEmpty( $files );
	}

	/**
	 * Test that export with --all exports all sitemaps.
	 *
	 * @return void
	 */
	public function test_export_all(): void {
		$cli = new Metro_Sitemap_CLI();
		$this->expectOutputRegex( '/Exported [0-9]+ sitemap/' );
		$cli->export(
			array(),
			array(
				'output' => $this->export_dir,
				'all'    => true,
			) 
		);
		$files = glob( $this->export_dir . '/*.xml' );
		$this->assertNotEmpty( $files );
	}

	/**
	 * Test that export outputs the correct message including the directory path.
	 *
	 * @return void
	 */
	public function test_export_output_message(): void {
		$cli = new Metro_Sitemap_CLI();
		$this->expectOutputRegex( '/Exported [0-9]+ sitemap.*' . preg_quote( $this->export_dir, '/' ) . '/s' );
		$cli->export( array(), array( 'output' => $this->export_dir ) );
	}
} 

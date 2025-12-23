<?php
/**
 * ExportTest
 *
 * @package Automattic\MSM_Sitemap
 */
declare(strict_types=1);

namespace Automattic\MSM_Sitemap\Tests\Cli;

use Automattic\MSM_Sitemap\Infrastructure\CLI\Commands\ExportCommand;
use function Automattic\MSM_Sitemap\Infrastructure\DI\msm_sitemap_container;

require_once __DIR__ . '/../Includes/mock-wp-cli.php';

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
	 * Get the export command instance.
	 *
	 * @return ExportCommand
	 */
	private function get_command(): ExportCommand {
		return msm_sitemap_container()->get( ExportCommand::class );
	}

	/**
	 * Test that export requires the output argument.
	 *
	 * @return void
	 */
	public function test_export_requires_output(): void {
		$command = $this->get_command();
		$this->expectException( \Exception::class );
		$command( array(), array() );
	}

	/**
	 * Test that export creates the output directory if it does not exist.
	 *
	 * @return void
	 */
	public function test_export_creates_directory(): void {
		$command = $this->get_command();
		$this->expectOutputRegex( '/Exported [0-9]+ sitemap/' );
		if ( is_dir( $this->export_dir ) ) {
			array_map( 'unlink', glob( $this->export_dir . '/*' ) );
			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.directory_rmdir
			rmdir( $this->export_dir );
		}
		$command( array(), array( 'output' => $this->export_dir ) );
		$this->assertDirectoryExists( $this->export_dir );
	}

	/**
	 * Test that export writes a file to the output directory.
	 *
	 * @return void
	 */
	public function test_export_file_written(): void {
		$command = $this->get_command();
		$this->expectOutputRegex( '/Exported [0-9]+ sitemap/' );
		$command( array(), array( 'output' => $this->export_dir ) );
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
		$command = $this->get_command();
		$this->expectOutputRegex( '/Exported [0-9]+ sitemap/' );
		$command(
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
		$command = $this->get_command();
		$this->expectOutputRegex( '/Exported [0-9]+ sitemap/' );
		$command(
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
		$command = $this->get_command();
		$this->expectOutputRegex( '/Exported [0-9]+ sitemap/' );
		$command(
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
		$command = $this->get_command();
		$this->expectOutputRegex( '/Exported [0-9]+ sitemap.*' . preg_quote( $this->export_dir, '/' ) . '/s' );
		$command( array(), array( 'output' => $this->export_dir ) );
	}
}

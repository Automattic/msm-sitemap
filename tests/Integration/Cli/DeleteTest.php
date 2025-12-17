<?php
/**
 * DeleteTest
 *
 * @package Automattic\MSM_Sitemap
 */
declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Integration\Cli;

use Metro_Sitemap_CLI;
use Automattic\MSM_Sitemap\Tests\Integration\TestCase;

require_once __DIR__ . '/../Includes/mock-wp-cli.php';
require_once __DIR__ . '/../../../includes/wp-cli.php';

/**
 * DeleteTest
 * 
 * Because the delete command is inherently destructive, it has an Are You Sure confirmation prompt.
 * We test the prompt appears via one test, and then pass `--yes` to the commands in other tests to bypass the check.
 *
 * @package Automattic\MSM_Sitemap
 */
final class DeleteTest extends TestCase {
	/**
	 * Set up the test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		$dates = array(
			'2024-07-10',
			'2024-07-11',
			'2024-08-01',
			'2023-12-25',
		);

		foreach ( $dates as $date ) {
			$post_id = wp_insert_post(
				array(
					'post_type'   => 'msm_sitemap',
					'post_name'   => $date,
					'post_title'  => $date,
					'post_status' => 'publish',
					'post_date'   => $date . ' 00:00:00',
				)
			);
			$this->assertIsInt( $post_id );
		}
	}
	
	/**
	 * Tear down the test environment.
	 */
	public function tearDown(): void {
		$posts = get_posts(
			array(
				'post_type'   => 'msm_sitemap',
				'numberposts' => -1,
				'post_status' => 'any',
			)
		);
		foreach ( $posts as $post ) {
			wp_delete_post( $post->ID, true );
		}

		parent::tearDown();
	}

	/**
	 * Test that delete with no --date or --all errors and does not delete.
	 *
	 * @return void
	 */
	public function test_delete_requires_date_or_all(): void {
		$cli = new Metro_Sitemap_CLI();
		$this->expectException( \WP_CLI\ExitException::class );
		$cli->delete( array(), array() );
	}

	/**
	 * Test that deleting all sitemaps prompts for confirmation.
	 */
	public function test_delete_all_prompts_for_confirmation() {
		$cli = new Metro_Sitemap_CLI();
		$this->expectException( \WP_CLI_ConfirmationException::class );
		$this->expectOutputRegex( '/Are you sure you want to delete ALL sitemaps?/' );
		$cli->delete( array(), array( 'all' => true ) );
	}
	

	/**
	 * Test deleting all sitemaps using the --all flag.
	 */
	public function test_delete_all(): void {
		$cli = new Metro_Sitemap_CLI();
		$cli->delete(
			array(),
			array(
				'all' => true,
				'yes' => true,
			) 
		);
		$this->expectOutputRegex( '/Deleted [0-9]+ sitemap/' );
		$this->assertSitemapCount( 0 );
	}

	/**
	 * Test deleting all sitemaps for a given year.
	 */
	public function test_delete_by_year(): void {
		$cli = new Metro_Sitemap_CLI();
		$cli->delete(
			array(),
			array(
				'date' => '2024',
				'yes'  => true,
			) 
		);
		$this->expectOutputRegex( '/Deleted [0-9]+ sitemap/' );
		$this->assertSitemapCount( 1 );
	}
	
	/**
	 * Test deleting all sitemaps for a given year and month.
	 */
	public function test_delete_by_year_month(): void {
		$cli = new Metro_Sitemap_CLI();
		$cli->delete(
			array(),
			array(
				'date' => '2024-07',
				'yes'  => true,
			) 
		);
		$this->expectOutputRegex( '/Deleted [0-9]+ sitemap/' );
		$this->assertSitemapCount( 2 );
	}
	
	/**
	 * Test deleting a sitemap by year, month, and day.
	 */
	public function test_delete_by_year_month_day(): void {
		$cli = new Metro_Sitemap_CLI();
		$cli->delete(
			array(),
			array(
				'date' => '2024-07-10',
				'yes'  => true,
			) 
		);
		$this->expectOutputRegex( '/Deleted [0-9]+ sitemap/' );
		$this->assertSitemapCount( 3 );
		$this->assertNull( get_page_by_path( '2024-07-10', OBJECT, 'msm_sitemap' ) );
	}
	
	/**
	 * Test that --quiet suppresses output for the delete command.
	 */
	public function test_delete_quiet(): void {
		$cli = new Metro_Sitemap_CLI();

		ob_start();
		$cli->delete(
			array(),
			array(
				'date'  => '2023-12-25',
				'quiet' => true,
				'yes'   => true,
			) 
		);
		$output = ob_get_clean();
		$this->assertSame( '', $output, 'Output should be empty with --quiet' );
	}
	
	/**
	 * Test that an invalid date (e.g., month=13) throws an exception.
	 */
	public function test_delete_invalid_date(): void {
		$cli = new Metro_Sitemap_CLI();

		$this->expectException( \Exception::class );
		$cli->delete(
			array(),
			array(
				'date' => '2024-13-01',
				'yes'  => true,
			) 
		); // Invalid month
	}

	/**
	 * Test that deleting a date with no sitemaps does not throw and leaves no sitemaps for that date.
	 */
	public function test_delete_no_sitemaps_found(): void {
		$cli = new Metro_Sitemap_CLI();
		$cli->delete(
			array(),
			array(
				'date' => '2022-01-01',
				'yes'  => true,
			) 
		);
		$this->expectOutputRegex( '/No sitemaps found to delete/' );
		$this->assertSitemapCount( 4 );
	}

	/**
	 * Test deleting a date with multiple sitemaps having that date.
	 */
	public function test_delete_date_multiple() {
		// Create extra sitemap for 2024-07-10
		wp_insert_post(
			array(
				'post_type'   => 'msm_sitemap',
				'post_name'   => '2024-07-10', // WP will change this to 2024-07-10-2.
				'post_title'  => '2024-07-10',
				'post_status' => 'publish',
				'post_date'   => '2024-07-10 00:00:00',
			)
		);

		$cli = new Metro_Sitemap_CLI();
		$cli->delete(
			array(),
			array(
				'date' => '2024-07-10',
				'yes'  => true,
			) 
		);
		$this->expectOutputRegex( '/Deleted [0-9]+ sitemap/' );
		$this->assertSitemapCount( 3 ); // Original 4 plus 1, minus the two with the same post_date we deleted
	}
} 

<?php
/**
 * Tests for the WP-CLI generate command and its older command names.
 *
 * @package Automattic\MSM_Sitemap\Tests\Integration\Cli
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Integration\Cli;

use Automattic\MSM_Sitemap\Tests\Integration\TestCase;
use Metro_Sitemap;
use Metro_Sitemap_CLI;
use WP_CLI\ExitException;

require_once __DIR__ . '/../Includes/mock-wp-cli.php';
require_once __DIR__ . '/../../../includes/wp-cli.php';

/**
 * Tests for `wp msm-sitemap generate`.
 */
final class GenerateTest extends TestCase {

	/**
	 * Every day with a post, in order.
	 */
	private const ALL_DAYS = array( '2023-12-25', '2024-07-10', '2024-07-11', '2024-08-01' );

	/**
	 * Create one post on each day.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->create_dummy_posts( array_map( static fn( $day ) => $day . ' 10:00:00', self::ALL_DAYS ) );
	}

	/**
	 * Run a command and return what it prints.
	 *
	 * @param string               $method     The command's method on Metro_Sitemap_CLI.
	 * @param array<string, mixed> $assoc_args The command's options.
	 */
	private function run_command( string $method, array $assoc_args = array() ): string {
		$cli = new Metro_Sitemap_CLI();

		ob_start();
		try {
			$cli->$method( array(), $assoc_args );
			return (string) ob_get_contents();
		} finally {
			ob_end_clean();
		}
	}

	/**
	 * The days that have a sitemap, in order.
	 *
	 * @return string[]
	 */
	private function get_sitemap_days(): array {
		global $wpdb;

		return $wpdb->get_col( $wpdb->prepare( "SELECT post_name FROM $wpdb->posts WHERE post_type = %s ORDER BY post_name", Metro_Sitemap::SITEMAP_CPT ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Test that generate builds sitemaps for exactly the days in the requested range.
	 *
	 * @dataProvider data_scopes
	 *
	 * @param array<string, mixed> $assoc_args The command's options.
	 * @param string[]             $days       The days that should get a sitemap.
	 */
	public function test_generate_builds_sitemaps_for_scope( array $assoc_args, array $days ): void {
		$output = $this->run_command( 'generate', $assoc_args );

		$this->assertSame( $days, $this->get_sitemap_days() );
		$this->assertSame( sprintf( 1 === count( $days ) ? "Generated %d sitemap.\n" : "Generated %d sitemaps.\n", count( $days ) ), $output );
	}

	/**
	 * Data provider for scopes of generate.
	 *
	 * @return array<string, array{array<string, mixed>, string[]}>
	 */
	public function data_scopes(): array {
		return array(
			'all'   => array( array( 'all' => true ), self::ALL_DAYS ),
			'year'  => array( array( 'date' => '2024' ), array( '2024-07-10', '2024-07-11', '2024-08-01' ) ),
			'month' => array( array( 'date' => '2024-07' ), array( '2024-07-10', '2024-07-11' ) ),
			'day'   => array( array( 'date' => '2024-07-11' ), array( '2024-07-11' ) ),
		);
	}

	/**
	 * Test that --quiet prints nothing but still generates.
	 */
	public function test_quiet_prints_nothing(): void {
		$output = $this->run_command(
			'generate',
			array(
				'all'   => true,
				'quiet' => true,
			)
		);

		$this->assertSame( '', $output );
		$this->assertSame( self::ALL_DAYS, $this->get_sitemap_days() );
	}

	/**
	 * Test that generating a day that already has a sitemap rebuilds it, and --force counts it.
	 */
	public function test_generate_rebuilds_existing_sitemap(): void {
		$this->run_command( 'generate', array( 'date' => '2024-07-11' ) );
		$new_post = $this->create_dummy_post( '2024-07-11 12:00:00' );

		$output = $this->run_command(
			'generate',
			array(
				'date'  => '2024-07-11',
				'force' => true,
			)
		);

		$xml = (string) get_post_meta( Metro_Sitemap::get_sitemap_post_id( 2024, 7, 11 ), 'msm_sitemap_xml', true );
		$this->assertStringContainsString( '<loc>' . get_permalink( $new_post ) . '</loc>', $xml );
		$this->assertSame( "Generated 1 sitemap.\n", $output );
	}

	/**
	 * Test that generate removes the sitemap for a day that no longer has posts.
	 */
	public function test_generate_removes_sitemap_for_day_without_posts(): void {
		$this->run_command( 'generate', array( 'date' => '2024-07-11' ) );
		$this->assertSame( array( '2024-07-11' ), $this->get_sitemap_days() );
		$post_id = $this->posts[2]['ID'];
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'draft',
			)
		);

		$this->run_command( 'generate', array( 'date' => '2024-07-11' ) );

		$this->assertSame( array(), $this->get_sitemap_days() );
	}

	/**
	 * Test that invalid dates stop the command with an error.
	 *
	 * @dataProvider data_invalid_dates
	 *
	 * @param string $date    The --date value.
	 * @param string $message The start of the expected error.
	 */
	public function test_invalid_date_is_an_error( string $date, string $message ): void {
		$this->expectException( ExitException::class );
		$this->expectExceptionMessage( $message );

		$this->run_command( 'generate', array( 'date' => $date ) );
	}

	/**
	 * Data provider for test_invalid_date_is_an_error().
	 *
	 * @return array<string, array{string, string}>
	 */
	public function data_invalid_dates(): array {
		return array(
			'day that does not exist' => array( '2024-02-30', 'Invalid date.' ),
			'month 13'                => array( '2024-13', 'Invalid month.' ),
			'year before 1970'        => array( '1969', 'Invalid year.' ),
			'next year'               => array( (string) ( (int) gmdate( 'Y' ) + 2 ), 'Invalid year.' ),
			'not a date'              => array( '2024/07', 'Invalid date format.' ),
		);
	}

	/**
	 * Test that a halt request stops generate, and is cleared so the next run isn't stopped too.
	 *
	 * @dataProvider data_scopes
	 *
	 * @param array<string, mixed> $assoc_args The command's options.
	 * @param string[]             $days       The days that should get a sitemap once the halt is cleared.
	 */
	public function test_halt_stops_generate_and_is_cleared( array $assoc_args, array $days ): void {
		update_option( 'msm_stop_processing', true );
		update_option( 'msm_sitemap_create_in_progress', true );

		$output = $this->run_command( 'generate', $assoc_args );

		$this->assertSame( "Generated 0 sitemaps.\n", $output );
		$this->assertSame( array(), $this->get_sitemap_days() );
		$this->assertFalse( get_option( 'msm_stop_processing' ), 'The halt request should be cleared.' );
		$this->assertFalse( get_option( 'msm_sitemap_create_in_progress' ) );

		$this->run_command( 'generate', $assoc_args );
		$this->assertSame( $days, $this->get_sitemap_days(), 'The next run should not be halted.' );
	}

	/**
	 * Test that each older generate command runs generate for the matching dates.
	 *
	 * @dataProvider data_legacy_commands
	 *
	 * @param string               $method     The command's method on Metro_Sitemap_CLI.
	 * @param array<string, mixed> $assoc_args The command's options.
	 * @param string[]             $days       The days that should get a sitemap.
	 */
	public function test_legacy_generate_commands( string $method, array $assoc_args, array $days ): void {
		$this->run_command( $method, $assoc_args );

		$this->assertSame( $days, $this->get_sitemap_days() );
	}

	/**
	 * Data provider for test_legacy_generate_commands().
	 *
	 * @return array<string, array{string, array<string, mixed>, string[]}>
	 */
	public function data_legacy_commands(): array {
		return array(
			'generate-sitemaps'                   => array( 'generate_sitemaps', array(), self::ALL_DAYS ),
			'generate-sitemap'                    => array( 'generate_sitemap', array(), self::ALL_DAYS ),
			'generate-sitemap-for-year'           => array( 'generate_sitemap_for_year', array( 'year' => '2024' ), array( '2024-07-10', '2024-07-11', '2024-08-01' ) ),
			'generate-sitemap-for-year-month'     => array(
				'generate_sitemap_for_year_month',
				array(
					'year'  => '2024',
					'month' => '7',
				),
				array( '2024-07-10', '2024-07-11' ),
			),
			'generate-sitemap-for-year-month-day' => array(
				'generate_sitemap_for_year_month_day',
				array(
					'year'  => '2024',
					'month' => '7',
					'day'   => '11',
				),
				array( '2024-07-11' ),
			),
		);
	}

	/**
	 * Test that recount-indexed-posts runs recount.
	 */
	public function test_recount_indexed_posts_runs_recount(): void {
		$this->run_command( 'generate', array( 'all' => true ) );
		update_option( 'msm_sitemap_indexed_url_count', 99, false );

		$output = $this->run_command( 'recount_indexed_posts' );

		$this->assertSame( 4, (int) get_option( 'msm_sitemap_indexed_url_count' ) );
		$this->assertStringContainsString( 'Total URLs found: 4', $output );
	}
}

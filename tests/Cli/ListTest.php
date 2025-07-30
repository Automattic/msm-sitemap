<?php
/**
 * ListTest
 *
 * @package Automattic\MSM_Sitemap
 */
declare(strict_types=1);

namespace Automattic\MSM_Sitemap\Tests\Cli;

use Metro_Sitemap_CLI;

require_once __DIR__ . '/../Includes/mock-wp-cli.php';
require_once __DIR__ . '/../../includes/wp-cli.php';

/**
 * Class ListTest
 *
 * @package Automattic\MSM_Sitemap\Tests\Cli
 */
final class ListTest extends \Automattic\MSM_Sitemap\Tests\TestCase {

	/**
	 * Dates used for test sitemaps.
	 *
	 * @var array
	 */
	private array $dates;

	/**
	 * Set up test environment.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->dates = array(
			'2024-07-10',
			'2024-07-11',
			'2024-08-01',
			'2023-12-25',
		);
		foreach ( $this->dates as $date ) {
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
			update_post_meta( $post_id, 'msm_indexed_url_count', 1 );
		}
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
		parent::tearDown();
	}

	/**
	 * Test listing all sitemaps with --all.
	 *
	 * @return void
	 */
	public function test_list_all(): void {
		$cli = new Metro_Sitemap_CLI();

		ob_start();
		$cli->list( array(), array( 'all' => true, 'format' => 'json' ) );
		$output = ob_get_clean();
		$data = json_decode( $output, true );
		foreach ( $data as $row ) {
			$this->assertStringContainsString( 'id', $output );
			$this->assertStringContainsString( 'date', $output );
			$this->assertStringContainsString( '2024-07-10', $output );
			$this->assertStringContainsString( 'sitemap_url', $output );
		}
	}

	/**
	 * Test listing sitemaps by year with --date.
	 *
	 * @return void
	 */
	public function test_list_by_year(): void {
		$cli = new Metro_Sitemap_CLI();

		ob_start();
		$cli->list( array(), array( 'date' => '2024' ) );
		$output = ob_get_clean();

		$this->assertStringContainsString( '2024-07-10', $output );
		$this->assertStringContainsString( '2024-08-01', $output );
		$this->assertStringNotContainsString( '2023-12-25', $output );
	}

	/**
	 * Test listing sitemaps by year-month with --date.
	 *
	 * @return void
	 */
	public function test_list_by_year_month(): void {
		$cli = new Metro_Sitemap_CLI();

		ob_start();
		$cli->list( array(), array( 'date' => '2024-07' ) );
		$output = ob_get_clean();

		$this->assertStringContainsString( '2024-07-10', $output );
		$this->assertStringContainsString( '2024-07-11', $output );
		$this->assertStringNotContainsString( '2024-08-01', $output );
	}

	/**
	 * Test listing sitemaps by year-month-day with --date.
	 *
	 * @return void
	 */
	public function test_list_by_year_month_day(): void {
		$cli = new Metro_Sitemap_CLI();

		ob_start();
		$cli->list( array(), array( 'date' => '2024-07-10' ) );
		$output = ob_get_clean();

		$this->assertStringContainsString( '2024-07-10', $output );
		$this->assertStringNotContainsString( '2024-07-11', $output );
	}

	/**
	 * Test listing with --fields argument.
	 *
	 * @return void
	 */
	public function test_list_with_fields(): void {
		$cli = new Metro_Sitemap_CLI();

		ob_start();
		$cli->list(
			array(),
			array(
				'all'    => true,
				'fields' => 'id,date',
			) 
		);
		$output = ob_get_clean();

		$this->assertStringContainsString( 'id', $output );
		$this->assertStringContainsString( 'date', $output );
		$this->assertStringNotContainsString( 'url_count', $output );
	}

	/**
	 * Test listing with --format=json.
	 *
	 * @return void
	 */
	public function test_list_format_json(): void {
		$cli = new Metro_Sitemap_CLI();

		ob_start();
		$cli->list(
			array(),
			array(
				'all'    => true,
				'format' => 'json',
			)
		);
		$output = ob_get_clean();
		$data = json_decode( $output, true );
		foreach ( $data as $row ) {
			$this->assertStringContainsString( '2024-07-10', $output );
			$this->assertStringContainsString( '"id"', $output );
			$this->assertStringContainsString( '"date"', $output );
			$this->assertStringContainsString( '"sitemap_url"', $output );
		}
	}

	/**
	 * Test listing with --format=csv.
	 *
	 * @return void
	 */
	public function test_list_format_csv(): void {
		$cli = new Metro_Sitemap_CLI();

		ob_start();
		$cli->list(
			array(),
			array(
				'all'    => true,
				'format' => 'csv',
			)
		);
		$output = ob_get_clean();

		$this->assertStringContainsString( 'id,date,url_count,status,sitemap_url', $output );
		$this->assertStringContainsString( '2024-07-10', $output );
	}

	/**
	 * Test listing when no sitemaps exist.
	 *
	 * @return void
	 */
	public function test_list_no_sitemaps(): void {
		$cli = new Metro_Sitemap_CLI();
		// Delete all sitemaps
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

		ob_start();
		$cli->list( array(), array( 'all' => true ) );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'No sitemaps found', $output );
	}
} 

<?php
/**
 * Class GetTest
 *
 * @package Automattic\MSM_Sitemap\Tests\Cli
 */
declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Cli;

use Metro_Sitemap_CLI;
use Automattic\MSM_Sitemap\Site;

require_once __DIR__ . '/../Includes/mock-wp-cli.php';
require_once __DIR__ . '/../../includes/wp-cli.php';

/**
 * Class GetTest
 *
 * @package Automattic\MSM_Sitemap\Tests\Cli
 */
final class GetTest extends \Automattic\MSM_Sitemap\Tests\TestCase {

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
		update_post_meta( $this->post_id, 'msm_indexed_url_count', 1 );
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
	 * Test getting a sitemap by ID.
	 *
	 * @return void
	 */
	public function test_get_by_id(): void {
		$cli = new Metro_Sitemap_CLI();
		$this->expectOutputRegex( '/"id".*' . $this->post_id . '/s' );
		$cli->get( array( (string) $this->post_id ), array( 'format' => 'json' ) );
	}

	/**
	 * Test getting a sitemap with an invalid ID.
	 *
	 * @return void
	 */
	public function test_get_invalid_id(): void {
		$cli = new Metro_Sitemap_CLI();
		$this->expectException( \Exception::class );
		$cli->get( array( '999999' ), array( 'format' => 'json' ) );
	}

	/**
	 * Test getting a sitemap by date (YYYY-MM-DD).
	 *
	 * @return void
	 */
	public function test_get_by_date_day(): void {
		$cli = new Metro_Sitemap_CLI();
		$this->expectOutputRegex( '/"date".*2024-07-10/s' );
		$cli->get( array( '2024-07-10' ), array( 'format' => 'json' ) );
	}

	/**
	 * Test getting a sitemap by date (YYYY-MM).
	 *
	 * @return void
	 */
	public function test_get_by_date_month(): void {
		$cli = new Metro_Sitemap_CLI();
		$this->expectOutputRegex( '/"date".*2024-07-10/s' );
		$cli->get( array( '2024-07' ), array( 'format' => 'json' ) );
	}

	/**
	 * Test getting a sitemap by date (YYYY).
	 *
	 * @return void
	 */
	public function test_get_by_date_year(): void {
		$cli = new Metro_Sitemap_CLI();
		// Delete the only sitemap so none exist for the year
		wp_delete_post( $this->post_id, true );
		$this->expectException( \WP_CLI\ExitException::class );
		$cli->get( array( '2024' ), array( 'format' => 'json' ) );
	}

	/**
	 * Test getting with multiple sitemaps for a date (should show all).
	 *
	 * @return void
	 */
	public function test_get_multiple_results(): void {
		$cli = new Metro_Sitemap_CLI();
		// Add another sitemap for the same year
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
		update_post_meta( $post_id2, 'msm_indexed_url_count', 1 );

		$sitemap_url_partial = Site::is_indexed_by_year() ? 'sitemap-2024.xml?' : 'sitemap.xml?yyyy=2024&';

		$expected =
				'[{"id":' . $post_id2 . ',"date":"2024-07-11","url_count":1,"status":"publish","last_modified":"2024-07-11 00:00:00","sitemap_url":"http:\/\/example.org\/' . $sitemap_url_partial . 'mm=07&dd=11"},' .
				'{"id":' . $this->post_id . ',"date":"2024-07-10","url_count":1,"status":"publish","last_modified":"2024-07-10 00:00:00","sitemap_url":"http:\/\/example.org\/' . $sitemap_url_partial . 'mm=07&dd=10"}]';
		$this->expectOutputString( $expected );
		$cli->get( array( '2024-07' ), array( 'format' => 'json' ) );
		wp_delete_post( $post_id2, true );
	}

	/**
	 * Test getting with invalid date format.
	 *
	 * @return void
	 */
	public function test_get_invalid_date_format(): void {
		$cli = new Metro_Sitemap_CLI();
		$this->expectException( \Exception::class );
		$cli->get( array( '2024-99-99' ), array( 'format' => 'json' ) );
	}

	/**
	 * Test getting with no argument.
	 *
	 * @return void
	 */
	public function test_get_no_argument(): void {
		$cli = new Metro_Sitemap_CLI();
		$this->expectException( \WP_CLI\ExitException::class );
		$cli->get( array(), array( 'format' => 'json' ) );
	}
} 

<?php
/**
 * SitemapStatsServiceTest
 *
 * @package Automattic\MSM_Sitemap\Tests\Application\Services
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Application\Services;

use Automattic\MSM_Sitemap\Application\Services\SettingsService;
use Automattic\MSM_Sitemap\Application\Services\SitemapStatsService;
use Automattic\MSM_Sitemap\Infrastructure\Repositories\SitemapPostRepository;
use Automattic\MSM_Sitemap\Infrastructure\Repositories\PostRepository;
use Mockery;

/**
 * Test class for SitemapStatsService.
 */
final class SitemapStatsServiceTest extends \Automattic\MSM_Sitemap\Tests\TestCase {

	/**
	 * The stats service.
	 *
	 * @var SitemapStatsService
	 */
	private SitemapStatsService $stats_service;

	/**
	 * The repository.
	 *
	 * @var SitemapPostRepository
	 */
	private SitemapPostRepository $repository;

	/**
	 * Set up test environment.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->repository = new SitemapPostRepository();

		$settings_service = Mockery::mock( SettingsService::class );
		$settings_service->shouldReceive( 'get_setting' )
			->with( 'enabled_post_types', Mockery::any() )
			->andReturn( array( 'post' ) );
		$post_repository = new PostRepository( $settings_service );

		$this->stats_service = new SitemapStatsService( $this->repository, $post_repository );
	}

	/**
	 * Test comprehensive stats with no sitemaps.
	 *
	 * @return void
	 */
	public function test_comprehensive_stats_no_sitemaps(): void {
		$stats = $this->stats_service->get_comprehensive_stats();

		$this->assertIsArray( $stats );
		$this->assertArrayHasKey( 'overview', $stats );
		$this->assertArrayHasKey( 'timeline', $stats );
		$this->assertArrayHasKey( 'url_counts', $stats );
		$this->assertArrayHasKey( 'performance', $stats );
		$this->assertArrayHasKey( 'coverage', $stats );
		$this->assertArrayHasKey( 'storage', $stats );

		$this->assertEquals( 0, $stats['overview']['total_sitemaps'] );
		$this->assertEquals( 0, $stats['overview']['total_urls'] );
	}

	/**
	 * Test comprehensive stats with sitemaps.
	 *
	 * @return void
	 */
	public function test_comprehensive_stats_with_sitemaps(): void {
		// Create test sitemaps
		$dates    = array( '2024-01-01', '2024-01-02', '2024-01-03' );
		$post_ids = array();

		$total_urls = 0;
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
			$post_ids[] = $post_id;

			// Add URL count meta
			$url_count = rand( 10, 100 );
			update_post_meta( $post_id, 'msm_indexed_url_count', $url_count );
			$total_urls += $url_count;
		}

		// Update global URL count
		update_option( 'msm_sitemap_indexed_url_count', $total_urls );

		$stats = $this->stats_service->get_comprehensive_stats();

		$this->assertIsArray( $stats );
		$this->assertEquals( 3, $stats['overview']['total_sitemaps'] );
		$this->assertGreaterThan( 0, $stats['overview']['total_urls'] );
		$this->assertNotEmpty( $stats['overview']['most_recent']['date'] );
		$this->assertNotEmpty( $stats['overview']['oldest']['date'] );

		// Clean up
		foreach ( $post_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}
	}

	/**
	 * Test recent URL counts.
	 *
	 * @return void
	 */
	public function test_recent_url_counts(): void {
		// Create a sitemap for today
		$today   = date( 'Y-m-d' );
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'msm_sitemap',
				'post_name'   => $today,
				'post_title'  => $today,
				'post_status' => 'publish',
				'post_date'   => $today . ' 00:00:00',
			)
		);
		$this->assertIsInt( $post_id );
		update_post_meta( $post_id, 'msm_indexed_url_count', 50 );

		$url_counts = $this->stats_service->get_recent_url_counts( 7 );

		$this->assertIsArray( $url_counts );
		$this->assertCount( 7, $url_counts );
		$this->assertArrayHasKey( $today, $url_counts );
		$this->assertEquals( 50, $url_counts[ $today ] );

		// Clean up
		wp_delete_post( $post_id, true );
	}

	/**
	 * Test timeline statistics.
	 *
	 * @return void
	 */
	public function test_timeline_stats(): void {
		// Create sitemaps across different years and months
		$dates = array(
			'2023-12-31',
			'2024-01-01',
			'2024-01-02',
			'2024-02-01',
		);

		$post_ids = array();
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
			$post_ids[] = $post_id;
			update_post_meta( $post_id, 'msm_indexed_url_count', 25 );
		}

		$stats = $this->stats_service->get_comprehensive_stats();

		$this->assertArrayHasKey( 'timeline', $stats );
		$this->assertArrayHasKey( 'yearly', $stats['timeline'] );
		$this->assertArrayHasKey( 'monthly', $stats['timeline'] );
		$this->assertArrayHasKey( 'daily', $stats['timeline'] );

		// Should have 2 years
		$this->assertCount( 2, $stats['timeline']['yearly'] );
		$this->assertArrayHasKey( '2023', $stats['timeline']['yearly'] );
		$this->assertArrayHasKey( '2024', $stats['timeline']['yearly'] );

		// Should have 3 months (Dec 2023, Jan 2024, Feb 2024)
		$this->assertCount( 3, $stats['timeline']['monthly'] );

		// Clean up
		foreach ( $post_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}
	}

	/**
	 * Test coverage statistics.
	 *
	 * @return void
	 */
	public function test_coverage_stats(): void {
		// Create sitemaps with gaps
		$dates = array(
			'2024-01-01',
			'2024-01-02',
			'2024-01-04', // Gap on 2024-01-03
			'2024-01-05',
		);

		$post_ids = array();
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
			$post_ids[] = $post_id;
			update_post_meta( $post_id, 'msm_indexed_url_count', 25 );
		}

		$stats = $this->stats_service->get_comprehensive_stats();

		$this->assertArrayHasKey( 'coverage', $stats );
		$this->assertArrayHasKey( 'date_coverage', $stats['coverage'] );
		$this->assertArrayHasKey( 'gaps', $stats['coverage'] );
		$this->assertArrayHasKey( 'continuous_streaks', $stats['coverage'] );

		// Should have 1 gap (2024-01-03)
		$this->assertContains( '2024-01-03', $stats['coverage']['gaps'] );

		// Should have 2 continuous streaks
		$this->assertCount( 2, $stats['coverage']['continuous_streaks'] );

		// Clean up
		foreach ( $post_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}
	}

	/**
	 * Test storage statistics.
	 *
	 * @return void
	 */
	public function test_storage_stats(): void {
		// Create a sitemap with content
		$date    = '2024-01-01';
		$content = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>http://example.com/</loc></url></urlset>';
		
		$post_id = wp_insert_post(
			array(
				'post_type'    => 'msm_sitemap',
				'post_name'    => $date,
				'post_title'   => $date,
				'post_status'  => 'publish',
				'post_date'    => $date . ' 00:00:00',
				'post_content' => $content,
			)
		);
		$this->assertIsInt( $post_id );
		update_post_meta( $post_id, 'msm_indexed_url_count', 1 );

		$stats = $this->stats_service->get_comprehensive_stats();

		$this->assertArrayHasKey( 'storage', $stats );
		$this->assertArrayHasKey( 'total_size', $stats['storage'] );
		$this->assertArrayHasKey( 'total_size_human', $stats['storage'] );
		$this->assertArrayHasKey( 'average_size', $stats['storage'] );
		$this->assertArrayHasKey( 'size_distribution', $stats['storage'] );

		$this->assertGreaterThan( 0, $stats['storage']['total_size'] );
		$this->assertNotEmpty( $stats['storage']['total_size_human'] );

		// Clean up
		wp_delete_post( $post_id, true );
	}
}

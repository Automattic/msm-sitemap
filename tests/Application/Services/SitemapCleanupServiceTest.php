<?php
/**
 * SitemapCleanupService Test
 *
 * @package Automattic\MSM_Sitemap\Tests\Application\Services
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Application\Services;

use Automattic\MSM_Sitemap\Application\Services\SitemapCleanupService;
use Automattic\MSM_Sitemap\Infrastructure\Repositories\SitemapPostRepository;
use Automattic\MSM_Sitemap\Infrastructure\Repositories\PostRepository;
use Automattic\MSM_Sitemap\Tests\TestCase;

/**
 * Test SitemapCleanupService
 */
class SitemapCleanupServiceTest extends TestCase {

	/**
	 * Test cleanup_orphaned_sitemaps removes sitemaps with no posts.
	 */
	public function test_cleanup_orphaned_sitemaps_removes_sitemaps_with_no_posts(): void {
		// Create a sitemap for a date with no posts
		$date = '2024-01-01';
		$post_id = $this->factory->post->create(
			array(
				'post_type' => \Automattic\MSM_Sitemap\Plugin::SITEMAP_CPT,
				'post_name' => $date,
				'post_status' => 'publish',
			)
		);
		update_post_meta( $post_id, 'msm_indexed_url_count', 5 );

		$repository = new SitemapPostRepository();
		$post_repository = $this->createMock( PostRepository::class );
		$post_repository->method( 'date_range_has_posts' )
			->with( $date, $date )
			->willReturn( null );

		$cleanup_service = new SitemapCleanupService( $repository, $post_repository );

		$deleted_count = $cleanup_service->cleanup_orphaned_sitemaps(
			array( array( 'year' => 2024, 'month' => 1, 'day' => 1 ) )
		);

		$this->assertEquals( 1, $deleted_count );
		$this->assertNull( get_post( $post_id ) );
	}

	/**
	 * Test cleanup_orphaned_sitemaps keeps sitemaps with posts.
	 */
	public function test_cleanup_orphaned_sitemaps_keeps_sitemaps_with_posts(): void {
		// Create a sitemap for a date with posts
		$date = '2024-01-01';
		$post_id = $this->factory->post->create(
			array(
				'post_type' => \Automattic\MSM_Sitemap\Plugin::SITEMAP_CPT,
				'post_name' => $date,
				'post_status' => 'publish',
			)
		);
		update_post_meta( $post_id, 'msm_indexed_url_count', 5 );

		$repository = new SitemapPostRepository();
		$post_repository = $this->createMock( PostRepository::class );
		$post_repository->method( 'date_range_has_posts' )
			->with( $date, $date )
			->willReturn( 123 );

		$cleanup_service = new SitemapCleanupService( $repository, $post_repository );

		$deleted_count = $cleanup_service->cleanup_orphaned_sitemaps(
			array( array( 'year' => 2024, 'month' => 1, 'day' => 1 ) )
		);

		$this->assertEquals( 0, $deleted_count );
		$this->assertNotNull( get_post( $post_id ) );
	}

	/**
	 * Test cleanup_all_orphaned_sitemaps removes all orphaned sitemaps.
	 */
	public function test_cleanup_all_orphaned_sitemaps_removes_all_orphaned_sitemaps(): void {
		// Create sitemaps for dates with and without posts
		$date_with_posts = '2024-01-01';
		$date_without_posts = '2024-01-02';

		$post_with_posts = $this->factory->post->create(
			array(
				'post_type' => \Automattic\MSM_Sitemap\Plugin::SITEMAP_CPT,
				'post_name' => $date_with_posts,
				'post_status' => 'publish',
			)
		);
		update_post_meta( $post_with_posts, 'msm_indexed_url_count', 5 );

		$post_without_posts = $this->factory->post->create(
			array(
				'post_type' => \Automattic\MSM_Sitemap\Plugin::SITEMAP_CPT,
				'post_name' => $date_without_posts,
				'post_status' => 'publish',
			)
		);
		update_post_meta( $post_without_posts, 'msm_indexed_url_count', 3 );

		$repository = new SitemapPostRepository();
		$post_repository = $this->createMock( PostRepository::class );
		$post_repository->method( 'get_post_ids_for_date' )
			->willReturnMap(
				array(
					array( $date_with_posts, 1, array( 1 ) ),
					array( $date_without_posts, 1, array() ),
				)
			);

		$cleanup_service = new SitemapCleanupService( $repository, $post_repository );

		$deleted_count = $cleanup_service->cleanup_all_orphaned_sitemaps();

		$this->assertEquals( 1, $deleted_count );
		$this->assertNotNull( get_post( $post_with_posts ) );
		$this->assertNull( get_post( $post_without_posts ) );
	}
}

<?php
/**
 * Repository Interface Test
 *
 * @package Automattic\MSM_Sitemap\Tests
 */

declare(strict_types=1);

namespace Automattic\MSM_Sitemap\Tests;

use Automattic\MSM_Sitemap\Domain\Contracts\RepositoryInterface;
use Automattic\MSM_Sitemap\Domain\Contracts\PostRepositoryInterface;
use Automattic\MSM_Sitemap\Domain\Contracts\ImageRepositoryInterface;
use Automattic\MSM_Sitemap\Domain\Contracts\SitemapRepositoryInterface;
use Automattic\MSM_Sitemap\Infrastructure\Repositories\PostRepository;
use Automattic\MSM_Sitemap\Infrastructure\Repositories\ImageRepository;
use Automattic\MSM_Sitemap\Infrastructure\Repositories\SitemapPostRepository;

/**
 * Test repository interface implementations.
 */
class RepositoryInterfaceTest extends TestCase {

	/**
	 * Test that repositories implement the base interface.
	 */
	public function test_repositories_implement_base_interface(): void {
		$post_repository  = $this->get_service( PostRepository::class );
		$image_repository = $this->get_service( ImageRepository::class );

		// Test that repositories implement the base interface
		$this->assertInstanceOf( RepositoryInterface::class, $post_repository );
		$this->assertInstanceOf( RepositoryInterface::class, $image_repository );

		// Test that repositories implement their specific interfaces
		$this->assertInstanceOf( PostRepositoryInterface::class, $post_repository );
		$this->assertInstanceOf( ImageRepositoryInterface::class, $image_repository );
	}

	/**
	 * Test that repositories can be resolved by their interfaces.
	 */
	public function test_repositories_can_be_resolved_by_interfaces(): void {
		// Test that we can get repositories by their interfaces
		$post_repository    = $this->get_service( PostRepositoryInterface::class );
		$image_repository   = $this->get_service( ImageRepositoryInterface::class );
		$sitemap_repository = $this->get_service( SitemapRepositoryInterface::class );

		$this->assertInstanceOf( PostRepository::class, $post_repository );
		$this->assertInstanceOf( ImageRepository::class, $image_repository );
		$this->assertInstanceOf( SitemapPostRepository::class, $sitemap_repository );
	}

	/**
	 * Test that base repository methods work correctly.
	 */
	public function test_base_repository_methods_work(): void {
		$post_repository  = $this->get_service( PostRepositoryInterface::class );
		$image_repository = $this->get_service( ImageRepositoryInterface::class );

		// Test find method
		$post = $post_repository->find( 1 );
		// Should return null for non-existent post or null for existing post
		$this->assertTrue( is_null( $post ) || is_object( $post ) );

		// Test find_by method
		$posts = $post_repository->find_by( array(), 5 );
		$this->assertIsArray( $posts );

		// Test count method
		$count = $post_repository->count();
		$this->assertIsInt( $count );

		// Test exists method
		$exists = $post_repository->exists( 1 );
		$this->assertIsBool( $exists );
	}

	/**
	 * Test that domain-specific methods work correctly.
	 */
	public function test_domain_specific_methods_work(): void {
		$post_repository  = $this->get_service( PostRepositoryInterface::class );
		$image_repository = $this->get_service( ImageRepositoryInterface::class );

		// Test post repository domain methods
		$dates = $post_repository->get_all_post_publication_dates();
		$this->assertIsArray( $dates );

		// Test image repository domain methods
		$should_include = $image_repository->should_include_images();
		$this->assertIsBool( $should_include );

		$max_images = $image_repository->get_max_images_per_sitemap();
		$this->assertIsInt( $max_images );
	}

	/**
	 * Test that container can get services by interface.
	 */
	public function test_container_get_services_by_interface(): void {
		$container = $this->container;

		// Test getting services by base interface
		$repositories = $container->get_services_by_interface( RepositoryInterface::class );
		$this->assertIsArray( $repositories );
		$this->assertGreaterThan( 0, count( $repositories ) );

		// Test getting services by specific interfaces
		$post_repositories = $container->get_services_by_interface( PostRepositoryInterface::class );
		$this->assertIsArray( $post_repositories );
		$this->assertGreaterThan( 0, count( $post_repositories ) );

		$image_repositories = $container->get_services_by_interface( ImageRepositoryInterface::class );
		$this->assertIsArray( $image_repositories );
		$this->assertGreaterThan( 0, count( $image_repositories ) );
	}
}

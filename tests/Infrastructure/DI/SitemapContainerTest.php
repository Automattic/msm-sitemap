<?php
/**
 * SitemapContainer Test
 *
 * @package Automattic\MSM_Sitemap\Tests\Infrastructure\DI
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Infrastructure\DI;

use Automattic\MSM_Sitemap\Infrastructure\DI\SitemapContainer;
use Automattic\MSM_Sitemap\Application\Services\SitemapService;
use Automattic\MSM_Sitemap\Application\Services\SitemapStatsService;
use Automattic\MSM_Sitemap\Application\Services\SitemapCleanupService;
use Automattic\MSM_Sitemap\Domain\Contracts\SitemapRepositoryInterface;
use Automattic\MSM_Sitemap\Infrastructure\Repositories\SitemapPostRepository;
use Automattic\MSM_Sitemap\Infrastructure\Repositories\PostRepository;
use Automattic\MSM_Sitemap\Tests\TestCase;

/**
 * Test SitemapContainer
 */
class SitemapContainerTest extends TestCase {

	/**
	 * Test that the container can resolve registered services.
	 */
	public function test_container_resolves_registered_services(): void {
		$container = new SitemapContainer();

		// Test that core services can be resolved
		$this->assertInstanceOf( SitemapService::class, $container->get( SitemapService::class ) );
		$this->assertInstanceOf( SitemapStatsService::class, $container->get( SitemapStatsService::class ) );
		$this->assertInstanceOf( SitemapCleanupService::class, $container->get( SitemapCleanupService::class ) );
		$this->assertInstanceOf( SitemapRepositoryInterface::class, $container->get( SitemapRepositoryInterface::class ) );
		$this->assertInstanceOf( SitemapPostRepository::class, $container->get( SitemapRepositoryInterface::class ) );
		$this->assertInstanceOf( PostRepository::class, $container->get( PostRepository::class ) );
	}

	/**
	 * Test that the container returns the same instance for singleton services.
	 */
	public function test_container_returns_same_instance_for_singleton_services(): void {
		$container = new SitemapContainer();

		$service1 = $container->get( SitemapService::class );
		$service2 = $container->get( SitemapService::class );

		$this->assertSame( $service1, $service2 );
	}

	/**
	 * Test that the container throws an exception for unregistered services.
	 */
	public function test_container_throws_exception_for_unregistered_service(): void {
		$container = new SitemapContainer();

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( "Service 'NonExistentService' is not registered." );

		$container->get( 'NonExistentService' );
	}

	/**
	 * Test that the container can check if a service is registered.
	 */
	public function test_container_has_method_works_correctly(): void {
		$container = new SitemapContainer();

		$this->assertTrue( $container->has( SitemapService::class ) );
		$this->assertTrue( $container->has( SitemapStatsService::class ) );
		$this->assertFalse( $container->has( 'NonExistentService' ) );
	}

	/**
	 * Test that services have their dependencies properly injected.
	 */
	public function test_services_have_dependencies_properly_injected(): void {
		$container = new SitemapContainer();

		$sitemap_service = $container->get( SitemapService::class );
		$stats_service = $container->get( SitemapStatsService::class );

		// Test that services are properly instantiated (no errors)
		$this->assertInstanceOf( SitemapService::class, $sitemap_service );
		$this->assertInstanceOf( SitemapStatsService::class, $stats_service );

		// Test that we can call methods on the services (indicating proper dependency injection)
		$stats = $stats_service->get_comprehensive_stats();
		$this->assertIsArray( $stats );
		$this->assertArrayHasKey( 'overview', $stats );
	}
}

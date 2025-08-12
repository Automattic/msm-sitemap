<?php
/**
 * Sitemap Dependency Injection Container
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\DI
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Infrastructure\DI;

use Automattic\MSM_Sitemap\Application\Services\SitemapService;
use Automattic\MSM_Sitemap\Application\Services\SitemapStatsService;
use Automattic\MSM_Sitemap\Application\Services\SitemapValidationService;
use Automattic\MSM_Sitemap\Application\Services\SitemapExportService;
use Automattic\MSM_Sitemap\Application\Services\SitemapCleanupService;
use Automattic\MSM_Sitemap\Application\Services\SitemapQueryService;
use Automattic\MSM_Sitemap\Domain\Contracts\SitemapRepositoryInterface;
use Automattic\MSM_Sitemap\Infrastructure\Repositories\SitemapPostRepository;
use Automattic\MSM_Sitemap\Infrastructure\Repositories\PostRepository;
use Automattic\MSM_Sitemap\Infrastructure\Formatters\SitemapXmlFormatter;
use Automattic\MSM_Sitemap\Infrastructure\CLI\CLI_Command;
use Automattic\MSM_Sitemap\Infrastructure\Cron\MissingSitemapGenerationHandler;
use Automattic\MSM_Sitemap\Infrastructure\Cron\FullGenerationHandler;
use Automattic\MSM_Sitemap\Infrastructure\WordPress\SitemapEndpointHandler;
use Automattic\MSM_Sitemap\Admin\UI;
use Automattic\MSM_Sitemap\Admin\ActionHandlers;

/**
 * Simple dependency injection container for MSM Sitemap services.
 * 
 * This container handles service registration and resolution without
 * falling into the service locator anti-pattern. Services are only
 * resolved in factory methods, not injected into business logic classes.
 */
class SitemapContainer {

	/**
	 * Registered services and their factories.
	 *
	 * @var array<string, callable>
	 */
	private array $services = array();

	/**
	 * Resolved service instances (singletons).
	 *
	 * @var array<string, object>
	 */
	private array $instances = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->register_default_services();
	}

	/**
	 * Register a service with a factory function.
	 *
	 * @param string   $id      Service identifier (usually class name).
	 * @param callable $factory Factory function that creates the service.
	 */
	public function register( string $id, callable $factory ): void {
		$this->services[ $id ] = $factory;
	}

	/**
	 * Get a service by its identifier.
	 *
	 * @param string $id Service identifier.
	 * @return object The service instance.
	 * @throws \InvalidArgumentException If service is not registered.
	 */
	public function get( string $id ): object {
		// Return cached instance if available
		if ( isset( $this->instances[ $id ] ) ) {
			return $this->instances[ $id ];
		}

		// Check if service is registered
		if ( ! isset( $this->services[ $id ] ) ) {
			throw new \InvalidArgumentException( "Service '{$id}' is not registered." );
		}

		// Create and cache the instance
		$instance = $this->services[ $id ]( $this );
		$this->instances[ $id ] = $instance;

		return $instance;
	}

	/**
	 * Check if a service is registered.
	 *
	 * @param string $id Service identifier.
	 * @return bool True if registered, false otherwise.
	 */
	public function has( string $id ): bool {
		return isset( $this->services[ $id ] );
	}

	/**
	 * Register all default services.
	 */
	private function register_default_services(): void {
		// Register repositories
		$this->register( SitemapRepositoryInterface::class, function( $container ) {
			return new SitemapPostRepository();
		} );

		$this->register( PostRepository::class, function( $container ) {
			return new PostRepository();
		} );

		// Register formatters
		$this->register( SitemapXmlFormatter::class, function( $container ) {
			return new SitemapXmlFormatter();
		} );

		// Register query service
		$this->register( SitemapQueryService::class, function( $container ) {
			return new SitemapQueryService();
		} );

		// Register core services
		$this->register( SitemapService::class, function( $container ) {
			$generator = msm_sitemap_plugin()->get_sitemap_generator();
			$repository = $container->get( SitemapRepositoryInterface::class );
			$query_service = $container->get( SitemapQueryService::class );
			$generation_service = $container->get( \Automattic\MSM_Sitemap\Application\Services\SitemapGenerationService::class );
			
			return new SitemapService( $generator, $repository, $query_service, $generation_service );
		} );

		$this->register( SitemapStatsService::class, function( $container ) {
			$repository = $container->get( SitemapRepositoryInterface::class );
			$post_repository = $container->get( PostRepository::class );
			
			return new SitemapStatsService( $repository, $post_repository );
		} );

		$this->register( SitemapValidationService::class, function( $container ) {
			$repository = $container->get( SitemapRepositoryInterface::class );
			
			return new SitemapValidationService( $repository );
		} );

		$this->register( SitemapExportService::class, function( $container ) {
			$repository = $container->get( SitemapRepositoryInterface::class );
			$query_service = $container->get( SitemapQueryService::class );
			
			return new SitemapExportService( $repository, $query_service );
		} );

		$this->register( SitemapCleanupService::class, function( $container ) {
			$repository = $container->get( SitemapRepositoryInterface::class );
			$post_repository = $container->get( PostRepository::class );
			
			return new SitemapCleanupService( $repository, $post_repository );
		} );

		$this->register( \Automattic\MSM_Sitemap\Application\Services\MissingSitemapDetectionService::class, function( $container ) {
			$repository = $container->get( SitemapRepositoryInterface::class );
			$post_repository = $container->get( PostRepository::class );
			
			return new \Automattic\MSM_Sitemap\Application\Services\MissingSitemapDetectionService( $repository, $post_repository );
		} );

		$this->register( \Automattic\MSM_Sitemap\Application\Services\SitemapGenerationService::class, function( $container ) {
			$generator = msm_sitemap_plugin()->get_sitemap_generator();
			$repository = $container->get( SitemapRepositoryInterface::class );
			$query_service = $container->get( SitemapQueryService::class );
			
			return new \Automattic\MSM_Sitemap\Application\Services\SitemapGenerationService( $generator, $repository, $query_service );
		} );

		// Register infrastructure services
		$this->register( CLI_Command::class, function( $container ) {
			$sitemap_service = $container->get( SitemapService::class );
			$repository = $container->get( SitemapRepositoryInterface::class );
			$stats_service = $container->get( SitemapStatsService::class );
			$validation_service = $container->get( SitemapValidationService::class );
			$export_service = $container->get( SitemapExportService::class );
			
			return new CLI_Command(
				$sitemap_service,
				$repository,
				$stats_service,
				$validation_service,
				$export_service
			);
		} );

		$this->register( MissingSitemapGenerationHandler::class, function( $container ) {
			// This is a static class, so we don't need to instantiate it
			// But we can register it for consistency
			return new \stdClass(); // Placeholder
		} );

		$this->register( FullGenerationHandler::class, function( $container ) {
			// This is a static class, so we don't need to instantiate it
			// But we can register it for consistency
			return new \stdClass(); // Placeholder
		} );

		$this->register( SitemapEndpointHandler::class, function( $container ) {
			$sitemap_service = $container->get( SitemapService::class );
			
			return new SitemapEndpointHandler( $sitemap_service );
		} );

		// Register admin services
		$this->register( UI::class, function( $container ) {
			// This is a static class, so we don't need to instantiate it
			// But we can register it for consistency
			return new \stdClass(); // Placeholder
		} );

		$this->register( ActionHandlers::class, function( $container ) {
			// This is a static class, so we don't need to instantiate it
			// But we can register it for consistency
			return new \stdClass(); // Placeholder
		} );
	}
}

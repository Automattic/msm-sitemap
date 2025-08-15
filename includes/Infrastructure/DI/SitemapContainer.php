<?php
/**
 * Sitemap Dependency Injection Container
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\DI
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Infrastructure\DI;

use Automattic\MSM_Sitemap\Application\Services\CronManagementService;
use Automattic\MSM_Sitemap\Application\Services\FullSitemapGenerationService;
use Automattic\MSM_Sitemap\Application\Services\MissingSitemapDetectionService;
use Automattic\MSM_Sitemap\Application\Services\MissingSitemapGenerationService;
use Automattic\MSM_Sitemap\Application\Services\SettingsService;
use Automattic\MSM_Sitemap\Application\Services\SitemapExportService;
use Automattic\MSM_Sitemap\Application\Services\SitemapCleanupService;
use Automattic\MSM_Sitemap\Application\Services\SitemapGenerationService;
use Automattic\MSM_Sitemap\Application\Services\SitemapQueryService;
use Automattic\MSM_Sitemap\Application\Services\SitemapService;
use Automattic\MSM_Sitemap\Application\Services\SitemapStatsService;
use Automattic\MSM_Sitemap\Application\Services\SitemapValidationService;
use Automattic\MSM_Sitemap\Application\Services\ContentTypesService;
use Automattic\MSM_Sitemap\Domain\Contracts\SitemapRepositoryInterface;
use Automattic\MSM_Sitemap\Domain\Contracts\PostRepositoryInterface;
use Automattic\MSM_Sitemap\Domain\Contracts\ImageRepositoryInterface;
use Automattic\MSM_Sitemap\Infrastructure\CLI\CLICommand;
use Automattic\MSM_Sitemap\Infrastructure\Cron\CronSchedulingService;
use Automattic\MSM_Sitemap\Infrastructure\Formatters\SitemapXmlFormatter;
use Automattic\MSM_Sitemap\Infrastructure\Repositories\SitemapPostRepository;
use Automattic\MSM_Sitemap\Infrastructure\Repositories\PostRepository;
use Automattic\MSM_Sitemap\Infrastructure\Repositories\ImageRepository;
use Automattic\MSM_Sitemap\Infrastructure\Cron\MissingSitemapGenerationHandler;
use Automattic\MSM_Sitemap\Infrastructure\Cron\FullGenerationHandler;
use Automattic\MSM_Sitemap\Infrastructure\WordPress\Admin\UI;
use Automattic\MSM_Sitemap\Infrastructure\WordPress\Admin\ActionHandlers;
use Automattic\MSM_Sitemap\Infrastructure\WordPress\CoreIntegration;
use Automattic\MSM_Sitemap\Infrastructure\REST\SitemapEndpointHandler;
use Automattic\MSM_Sitemap\Infrastructure\REST\REST_API_Controller;
use Automattic\MSM_Sitemap\Infrastructure\WordPress\PostTypeRegistration;
use Automattic\MSM_Sitemap\Infrastructure\WordPress\StylesheetManager;
use Automattic\MSM_Sitemap\Infrastructure\WordPress\Permalinks;
use Automattic\MSM_Sitemap\Infrastructure\WordPress\PluginLinks;
use Automattic\MSM_Sitemap\Infrastructure\CLI\CLISetup;
use Automattic\MSM_Sitemap\Infrastructure\Factories\SitemapGeneratorFactory;
use Automattic\MSM_Sitemap\Infrastructure\Providers\PostContentProvider;
use Automattic\MSM_Sitemap\Infrastructure\Providers\ImageContentProvider;

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
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message not output to browser
			throw new \InvalidArgumentException( "Service '{$id}' is not registered." );
		}

		// Create and cache the instance
		$instance               = $this->services[ $id ]( $this );
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
	 * Get all services that implement a specific interface.
	 *
	 * @param string $interface_name The interface class name to filter by.
	 * @return array<string, object> Array of service ID => instance pairs.
	 */
	public function get_services_by_interface( string $interface_name ): array {
		$matching_services = array();

		foreach ( array_keys( $this->services ) as $service_id ) {
			try {
				$instance = $this->get( $service_id );
				if ( $instance instanceof $interface_name ) {
					$matching_services[ $service_id ] = $instance;
				}
			} catch ( \Exception $e ) {
				// Skip services that can't be instantiated in the current context
				// (e.g., CLICommand when WP_CLI is not available)
				continue;
			} catch ( \Error $e ) {
				// Skip services that can't be instantiated due to missing dependencies
				// (e.g., WP_CLI_Command not found)
				continue;
			}
		}

		return $matching_services;
	}

	/**
	 * Register all default services.
	 */
	private function register_default_services(): void {
		// Register repositories
		$this->register(
			SitemapRepositoryInterface::class,
			function ( $container ) {
				$post_type_registration = $container->get( PostTypeRegistration::class );
				return new SitemapPostRepository( $post_type_registration );
			} 
		);

		$this->register(
			PostRepository::class,
			function () {
				return new PostRepository();
			} 
		);

		$this->register(
			PostRepositoryInterface::class,
			function ( $container ) {
				return $container->get( PostRepository::class );
			} 
		);

		$this->register(
			ImageRepository::class,
			function ( $container ) {
				$settings_service = $container->get( SettingsService::class );
				return new ImageRepository( $settings_service );
			} 
		);

		$this->register(
			ImageRepositoryInterface::class,
			function ( $container ) {
				return $container->get( ImageRepository::class );
			} 
		);

		// Register content types service
		$this->register(
			ContentTypesService::class,
			function ( $container ) {
				$content_types_service = new ContentTypesService();
				
				// Register content providers based on WordPress filters
				$providers = array();
				
				if ( apply_filters( 'msm_sitemap_posts_provider_enabled', true ) ) {
					$providers[] = $container->get( PostContentProvider::class );
				}

				if ( apply_filters( 'msm_sitemap_images_provider_enabled', true ) ) {
					$providers[] = $container->get( ImageContentProvider::class );
				}

				// Future providers can be added here:

				$content_types_service->register_providers( $providers );
				
				return $content_types_service;
			} 
		);

		// Register content providers
		$this->register(
			PostContentProvider::class,
			function ( $container ) {
				$post_repository = $container->get( PostRepository::class );
				return new PostContentProvider( $post_repository );
			} 
		);

		$this->register(
			ImageContentProvider::class,
			function ( $container ) {
				$image_repository = $container->get( ImageRepository::class );
				return new ImageContentProvider( $image_repository );
			} 
		);

		// Register formatters
		$this->register(
			SitemapXmlFormatter::class,
			function () {
				return new SitemapXmlFormatter();
			} 
		);

		// Register query service
		$this->register(
			SitemapQueryService::class,
			function () {
				return new SitemapQueryService();
			} 
		);

		// Register core services
		$this->register(
			SitemapService::class,
			function ( $container ) {
				$content_types_service  = $container->get( ContentTypesService::class );
				$generator              = SitemapGeneratorFactory::create( $content_types_service->get_content_types() );
				$repository             = $container->get( SitemapRepositoryInterface::class );
				$query_service          = $container->get( SitemapQueryService::class );
				$generation_service     = $container->get( SitemapGenerationService::class );
				$post_type_registration = $container->get( PostTypeRegistration::class );
			
				return new SitemapService( $generator, $repository, $query_service, $generation_service, $post_type_registration );
			} 
		);

		$this->register(
			SitemapStatsService::class,
			function ( $container ) {
				$repository             = $container->get( SitemapRepositoryInterface::class );
				$post_repository        = $container->get( PostRepository::class );
				$post_type_registration = $container->get( PostTypeRegistration::class );
			
				return new SitemapStatsService( $repository, $post_repository, $post_type_registration );
			} 
		);

		$this->register(
			SitemapValidationService::class,
			function ( $container ) {
				$repository = $container->get( SitemapRepositoryInterface::class );
			
				return new SitemapValidationService( $repository );
			} 
		);

		$this->register(
			SitemapExportService::class,
			function ( $container ) {
				$repository    = $container->get( SitemapRepositoryInterface::class );
				$query_service = $container->get( SitemapQueryService::class );
			
				return new SitemapExportService( $repository, $query_service );
			} 
		);

		$this->register(
			SitemapCleanupService::class,
			function ( $container ) {
				$repository      = $container->get( SitemapRepositoryInterface::class );
				$post_repository = $container->get( PostRepository::class );
			
				return new SitemapCleanupService( $repository, $post_repository );
			} 
		);

		$this->register(
			MissingSitemapDetectionService::class,
			function ( $container ) {
				$repository      = $container->get( SitemapRepositoryInterface::class );
				$post_repository = $container->get( PostRepository::class );
			
				return new MissingSitemapDetectionService( $repository, $post_repository );
			} 
		);

		$this->register(
			MissingSitemapGenerationService::class,
			function ( $container ) {
				$cron_scheduler             = $container->get( CronSchedulingService::class );
				$missing_detection_service  = $container->get( MissingSitemapDetectionService::class );
				$missing_generation_handler = $container->get( MissingSitemapGenerationHandler::class );
				$sitemap_service            = $container->get( SitemapService::class );
				return new MissingSitemapGenerationService( $cron_scheduler, $missing_detection_service, $missing_generation_handler, $sitemap_service );
			} 
		);

		$this->register(
			CronManagementService::class,
			function ( $container ) {
				$settings_service = $container->get( SettingsService::class );
				$cron_scheduler   = $container->get( CronSchedulingService::class );
				return new CronManagementService( $settings_service, $cron_scheduler );
			} 
		);

		$this->register(
			FullSitemapGenerationService::class,
			function ( $container ) {
				$cron_scheduler = $container->get( CronSchedulingService::class );
				return new FullSitemapGenerationService( $cron_scheduler );
			} 
		);

		$this->register(
			SitemapGenerationService::class,
			function ( $container ) {
				$content_types_service = $container->get( ContentTypesService::class );
				$generator             = SitemapGeneratorFactory::create( $content_types_service->get_content_types() );
				$repository            = $container->get( SitemapRepositoryInterface::class );
				$query_service         = $container->get( SitemapQueryService::class );
			
				return new SitemapGenerationService( $generator, $repository, $query_service );
			} 
		);

		$this->register(
			SettingsService::class,
			function () {
				return new SettingsService();
			} 
		);

		$this->register(
			CronSchedulingService::class,
			function ( $container ) {
				$settings_service = $container->get( SettingsService::class );
				$post_repository  = $container->get( PostRepository::class );
				return new CronSchedulingService( $settings_service, $post_repository );
			} 
		);

		$this->register(
			CLICommand::class,
			function ( $container ) {
				$sitemap_service         = $container->get( SitemapService::class );
				$repository              = $container->get( SitemapRepositoryInterface::class );
				$stats_service           = $container->get( SitemapStatsService::class );
				$validation_service      = $container->get( SitemapValidationService::class );
				$export_service          = $container->get( SitemapExportService::class );
				$cron_management_service = $container->get( CronManagementService::class );
				$settings_service        = $container->get( SettingsService::class );
			
				return new CLICommand(
					$sitemap_service,
					$repository,
					$stats_service,
					$validation_service,
					$export_service,
					$cron_management_service,
					$settings_service
				);
			} 
		);

		$this->register(
			MissingSitemapGenerationHandler::class,
			function ( $container ) {
				$cron_scheduler  = $container->get( CronSchedulingService::class );
				$missing_service = $container->get( MissingSitemapDetectionService::class );
				$sitemap_service = $container->get( SitemapService::class );
				$cleanup_service = $container->get( SitemapCleanupService::class );
				return new MissingSitemapGenerationHandler( $cron_scheduler, $missing_service, $sitemap_service, $cleanup_service );
			} 
		);

		$this->register(
			FullGenerationHandler::class,
			function ( $container ) {
				$cron_scheduler        = $container->get( CronSchedulingService::class );
				$post_repository       = $container->get( PostRepository::class );
				$content_types_service = $container->get( ContentTypesService::class );
				$sitemap_generator     = SitemapGeneratorFactory::create( $content_types_service->get_content_types() );
				return new FullGenerationHandler( $cron_scheduler, $post_repository, $sitemap_generator );
			} 
		);

		$this->register(
			SitemapEndpointHandler::class,
			function ( $container ) {
				$sitemap_service        = $container->get( SitemapService::class );
				$post_type_registration = $container->get( PostTypeRegistration::class );
			
				return new SitemapEndpointHandler( $sitemap_service, $post_type_registration );
			} 
		);

		$this->register(
			REST_API_Controller::class,
			function ( $container ) {
				$sitemap_service                    = $container->get( SitemapService::class );
				$stats_service                      = $container->get( SitemapStatsService::class );
				$validation_service                 = $container->get( SitemapValidationService::class );
				$export_service                     = $container->get( SitemapExportService::class );
				$missing_sitemap_generation_service = $container->get( MissingSitemapGenerationService::class );
				$full_sitemap_generation_service    = $container->get( FullSitemapGenerationService::class );
				$cron_management_service            = $container->get( CronManagementService::class );
				$content_types_service              = $container->get( ContentTypesService::class );
				$sitemap_generator                  = SitemapGeneratorFactory::create( $content_types_service->get_content_types() );
			
				return new REST_API_Controller(
					$sitemap_service,
					$stats_service,
					$validation_service,
					$export_service,
					$missing_sitemap_generation_service,
					$full_sitemap_generation_service,
					$cron_management_service,
					$sitemap_generator
				);
			} 
		);

		$this->register(
			UI::class,
			function ( $container ) {
				$cron_scheduler            = $container->get( CronSchedulingService::class );
				$plugin_file_path          = msm_sitemap_plugin()->get_plugin_file_path();
				$plugin_version            = msm_sitemap_plugin()->get_plugin_version();
				$missing_detection_service = $container->get( MissingSitemapDetectionService::class );
				$stats_service             = $container->get( SitemapStatsService::class );
				$settings_service          = $container->get( SettingsService::class );
				$sitemap_repository        = $container->get( SitemapRepositoryInterface::class );
				$action_handlers           = $container->get( ActionHandlers::class );
				return new UI( $cron_scheduler, $plugin_file_path, $plugin_version, $missing_detection_service, $stats_service, $settings_service, $sitemap_repository, $action_handlers );
			} 
		);

		$this->register(
			ActionHandlers::class,
			function ( $container ) {
				$cron_management_service            = $container->get( CronManagementService::class );
				$settings_service                   = $container->get( SettingsService::class );
				$sitemap_service                    = $container->get( SitemapService::class );
				$full_sitemap_generation_service    = $container->get( FullSitemapGenerationService::class );
				$missing_sitemap_generation_service = $container->get( MissingSitemapGenerationService::class );
				return new ActionHandlers( $cron_management_service, $settings_service, $sitemap_service, $full_sitemap_generation_service, $missing_sitemap_generation_service );
			} 
		);

		$this->register(
			CoreIntegration::class,
			function ( $container ) {
				$post_repository = $container->get( PostRepository::class );
				return new CoreIntegration( $post_repository );
			} 
		);

		$this->register(
			PostTypeRegistration::class,
			function () {
				return new PostTypeRegistration();
			} 
		);

		// Register WordPress integration classes
		$this->register(
			StylesheetManager::class,
			function () {
				return new StylesheetManager();
			} 
		);

		$this->register(
			Permalinks::class,
			function () {
				return new Permalinks();
			} 
		);

		$this->register(
			PluginLinks::class,
			function () {
				return new PluginLinks();
			} 
		);

		$this->register(
			CLISetup::class,
			function () {
				return new CLISetup();
			} 
		);
	}
}

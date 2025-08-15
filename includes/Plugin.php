<?php
/**
 * Main Plugin bootstrap class
 *
 * @package MSM_Sitemap
 */

namespace Automattic\MSM_Sitemap;

use Automattic\MSM_Sitemap\Infrastructure\WordPress\CoreIntegration;


use Automattic\MSM_Sitemap\Infrastructure\REST\SitemapEndpointHandler;
use Automattic\MSM_Sitemap\Infrastructure\Cron\FullGenerationHandler;
use Automattic\MSM_Sitemap\Domain\Contracts\WordPressIntegrationInterface;
use function Automattic\MSM_Sitemap\Infrastructure\DI\msm_sitemap_container;

/**
 * Plugin bootstrap class responsible for initializing the entire plugin
 */
class Plugin {

	/**
	 * The plugin file path.
	 *
	 * @var string
	 */
	private string $plugin_file_path;

	/**
	 * The plugin version.
	 *
	 * @var string
	 */
	private string $plugin_version;

	/**
	 * Constructor.
	 *
	 * @param string $plugin_file_path The path to the main plugin file.
	 * @param string $plugin_version The plugin version.
	 */
	public function __construct( string $plugin_file_path, string $plugin_version ) {
		$this->plugin_file_path = $plugin_file_path;
		$this->plugin_version   = $plugin_version;
	}

	/**
	 * Initialize and setup the plugin
	 */
	public function run(): void {
		add_action( 'init', array( $this, 'setup_components' ) );
		add_filter( 'template_include', array( SitemapEndpointHandler::class, 'handle_template_include' ) );
	}

	/**
	 * Setup all plugin components with proper dependency injection
	 */
	public function setup_components(): void {
		if ( ! defined( 'WPCOM_SKIP_DEFAULT_SITEMAP' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
			define( 'WPCOM_SKIP_DEFAULT_SITEMAP', true );
		}

		$container = msm_sitemap_container();
		
		// Get all services that implement WordPressIntegrationInterface
		$wordpress_integrations = $container->get_services_by_interface( WordPressIntegrationInterface::class );

		// By default, we use wp-cron to help generate the full sitemap.
		// However, this will let us override it, if necessary, like on WP.com
		if ( false === apply_filters( 'msm_sitemap_use_cron_builder', true ) ) {
			// Remove FullGenerationHandler from the integrations if cron builder is disabled
			unset( $wordpress_integrations[ FullGenerationHandler::class ] );
		}

		// Setup all WordPress integration classes
		$this->setup_wordpress_integrations( $container, $wordpress_integrations );
	}

	/**
	 * Setup WordPress integration classes.
	 *
	 * @param \Automattic\MSM_Sitemap\Infrastructure\DI\SitemapContainer $container The dependency injection container.
	 * @param array<string, object> $integration_instances Array of service ID => instance pairs implementing WordPressIntegrationInterface.
	 */
	private function setup_wordpress_integrations( $container, array $integration_instances ): void {
		foreach ( $integration_instances as $service_id => $integration ) {
			// Ensure the class implements the interface
			if ( ! $integration instanceof \Automattic\MSM_Sitemap\Domain\Contracts\WordPressIntegrationInterface ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message not output to browser
				throw new \InvalidArgumentException( "Service {$service_id} must implement WordPressIntegrationInterface" );
			}

			$integration->register_hooks();
		}
	}

	/**
	 * Get the plugin file path.
	 *
	 * @return string
	 */
	public function get_plugin_file_path(): string {
		return $this->plugin_file_path;
	}

	/**
	 * Get the plugin version.
	 *
	 * @return string
	 */
	public function get_plugin_version(): string {
		return $this->plugin_version;
	}
}

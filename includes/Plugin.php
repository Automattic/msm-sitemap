<?php
/**
 * Main Plugin bootstrap class
 *
 * @package MSM_Sitemap
 */

namespace Automattic\MSM_Sitemap;

/**
 * Plugin bootstrap class responsible for initializing the entire plugin
 */
class Plugin {

	const SITEMAP_CPT = 'msm_sitemap';

	/**
	 * Whether sitemaps should be indexed by year
	 *
	 * @var bool
	 */
	private bool $index_by_year = false;

	/**
	 * Sitemap content types collection.
	 *
	 * @var \Automattic\MSM_Sitemap\Domain\ValueObjects\SitemapContentTypes|null
	 */
	private ?\Automattic\MSM_Sitemap\Domain\ValueObjects\SitemapContentTypes $sitemap_content_types = null;

	/**
	 * Initialize and setup the plugin
	 */
	public function initialize(): void {
		// Define constants
		define( 'MSM_INTERVAL_PER_GENERATION_EVENT', 60 ); // how far apart should full cron generation events be spaced

		// Initialize sitemap content types
		$this->initialize_sitemap_content_types();

		// Filter to allow the sitemap to be indexed by year
		$this->index_by_year = apply_filters( 'msm_sitemap_index_by_year', false );

		// Register WordPress hooks
		$this->register_wordpress_hooks();

		// Setup DDD layer integrations
		$this->setup_integrations();
	}

	/**
	 * Register all WordPress hooks and actions
	 */
	private function register_wordpress_hooks(): void {
		// Core initialization
		add_action( 'init', array( $this, 'register_sitemap_hooks' ) );
		add_action( 'init', array( $this, 'register_post_type' ) );

		// Template handling
		add_filter( 'template_include', array( '\Automattic\MSM_Sitemap\Infrastructure\WordPress\SitemapEndpointHandler', 'handle_template_include' ) );
	}

	/**
	 * Setup DDD layer integrations
	 */
	private function setup_integrations(): void {
		// By default, we use wp-cron to help generate the full sitemap.
		// However, this will let us override it, if necessary, like on WP.com
		if ( true === apply_filters( 'msm_sitemap_use_cron_builder', true ) ) {
			\Automattic\MSM_Sitemap\Infrastructure\Cron\FullGenerationHandler::setup();
		}

		\Automattic\MSM_Sitemap\Infrastructure\WordPress\Permalinks::setup();
		\Automattic\MSM_Sitemap\Infrastructure\WordPress\CoreIntegration::setup();
		\Automattic\MSM_Sitemap\Infrastructure\WordPress\StylesheetManager::setup();
		\Automattic\MSM_Sitemap\Infrastructure\WordPress\PluginLinks::setup();
		\Automattic\MSM_Sitemap\Infrastructure\CLI\CLISetup::init();
	}

	/**
	 * Register sitemap-specific hooks and endpoints
	 */
	public function register_sitemap_hooks(): void {
		if ( ! defined( 'WPCOM_SKIP_DEFAULT_SITEMAP' ) ) {
			define( 'WPCOM_SKIP_DEFAULT_SITEMAP', true );
		}

		\Automattic\MSM_Sitemap\Infrastructure\WordPress\CoreIntegration::sitemap_rewrite_init();
		\Automattic\MSM_Sitemap\Admin\UI::setup_admin();

		\Automattic\MSM_Sitemap\Infrastructure\Cron\MissingSitemapGenerationHandler::setup();

		// Register REST API routes
		add_action( 'rest_api_init', array( $this, 'register_rest_api_routes' ) );
	}

	/**
	 * Initialize sitemap content types collection.
	 */
	private function initialize_sitemap_content_types(): void {
		$this->sitemap_content_types = new \Automattic\MSM_Sitemap\Domain\ValueObjects\SitemapContentTypes();

		// Register content providers based on WordPress filters
		if ( apply_filters( 'msm_sitemap_posts_provider_enabled', true ) ) {
			$this->sitemap_content_types->register( new \Automattic\MSM_Sitemap\Infrastructure\Providers\PostContentProvider() );
		}

		// Future providers can be added here:
		// if ( apply_filters( 'msm_sitemap_images_provider_enabled', true ) ) {
		//     $this->sitemap_content_types->register( new \Automattic\MSM_Sitemap\Infrastructure\Providers\ImageContentProvider() );
		// }
		// if ( apply_filters( 'msm_sitemap_taxonomies_provider_enabled', true ) ) {
		//     $this->sitemap_content_types->register( new \Automattic\MSM_Sitemap\Infrastructure\Providers\TaxonomyContentProvider() );
		// }
		// if ( apply_filters( 'msm_sitemap_users_provider_enabled', true ) ) {
		//     $this->sitemap_content_types->register( new \Automattic\MSM_Sitemap\Infrastructure\Providers\UserContentProvider() );
		// }
	}

	/**
	 * Register the sitemap custom post type
	 */
	public function register_post_type(): void {
		register_post_type(
			self::SITEMAP_CPT,
			array(
				'labels'       => array(
					'name'          => __( 'Sitemaps', 'msm-sitemap' ),
					'singular_name' => __( 'Sitemap', 'msm-sitemap' ),
				),
				'public'       => false,
				'has_archive'  => false,
				'rewrite'      => false,
				'show_ui'      => true,  // TODO: should probably have some sort of custom UI
				'show_in_menu' => false, // Since we're manually adding a Sitemaps menu, no need to auto-add one through the CPT.
				'supports'     => array(
					'title',
				),
			)
		);
	}

	/**
	 * Get the sitemap content types collection
	 *
	 * @return \Automattic\MSM_Sitemap\Domain\ValueObjects\SitemapContentTypes
	 */
	public function get_sitemap_content_types(): \Automattic\MSM_Sitemap\Domain\ValueObjects\SitemapContentTypes {
		if ( null === $this->sitemap_content_types ) {
			$this->initialize_sitemap_content_types();
		}
		
		return $this->sitemap_content_types;
	}

	/**
	 * Check if sitemaps should be indexed by year
	 *
	 * @return bool
	 */
	public function is_indexed_by_year(): bool {
		return $this->index_by_year;
	}

	/**
	 * Get a SitemapGenerator instance via factory
	 *
	 * @return \Automattic\MSM_Sitemap\Application\Services\SitemapGenerator
	 */
	public function get_sitemap_generator(): \Automattic\MSM_Sitemap\Application\Services\SitemapGenerator {
		return \Automattic\MSM_Sitemap\Infrastructure\Factories\SitemapGeneratorFactory::create(
			$this->get_sitemap_content_types()
		);
	}

	/**
	 * Get years that have posts (delegates to PostRepository)
	 *
	 * @return array<int> Years with posts
	 */
	public function get_years_with_posts(): array {
		static $post_repository = null;
		if ( null === $post_repository ) {
			$post_repository = new \Automattic\MSM_Sitemap\Infrastructure\Repositories\PostRepository();
		}
		return $post_repository->get_years_with_posts();
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_api_routes(): void {
		$container = \Automattic\MSM_Sitemap\Infrastructure\DI\msm_sitemap_container();
		$rest_controller = $container->get( \Automattic\MSM_Sitemap\Infrastructure\WordPress\REST_API_Controller::class );
		$rest_controller->register_routes();
	}
}

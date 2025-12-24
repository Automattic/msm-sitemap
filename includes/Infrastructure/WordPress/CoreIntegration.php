<?php
/**
 * CoreIntegration.php
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\WordPress
 */

declare(strict_types=1);

namespace Automattic\MSM_Sitemap\Infrastructure\WordPress;

use Automattic\MSM_Sitemap\Domain\Contracts\WordPressIntegrationInterface;
use Automattic\MSM_Sitemap\Domain\ValueObjects\Site;
use Automattic\MSM_Sitemap\Infrastructure\Repositories\PostRepository;

/**
 * Handles WordPress core sitemap system integration.
 *
 * This class manages the integration with WordPress core sitemaps,
 * primarily for stylesheet support while preventing interference with MSM functionality.
 */
class CoreIntegration implements WordPressIntegrationInterface {

	/**
	 * The post repository.
	 *
	 * @var PostRepository
	 */
	private PostRepository $post_repository;

	/**
	 * Constructor.
	 *
	 * @param PostRepository $post_repository The post repository.
	 */
	public function __construct( PostRepository $post_repository ) {
		$this->post_repository = $post_repository;
	}

	/**
	 * Register WordPress hooks and filters for core sitemap integration.
	 *
	 * Note: This method is called during 'init' by Plugin::setup_components(),
	 * so we register rewrite rules directly rather than adding another init action.
	 */
	public function register_hooks(): void {
		// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Using WordPress core hooks.
		// Enable WordPress core sitemaps for stylesheet support, but prevent them from interfering with MSM
		add_action( 'wp_sitemaps_init', array( $this, 'disable_core_providers' ), 999 );
		add_action( 'wp_sitemaps_init', array( $this, 'remove_core_robots_hook' ), 1000 );
		add_filter( 'wp_sitemaps_robots', '__return_empty_string' ); // Prevent core sitemaps from being added to robots.txt

		// Disable main query for sitemap rendering to improve performance
		add_filter( 'posts_pre_query', array( $this, 'disable_main_query_for_sitemap_xml' ), 10, 2 );

		// Disable canonical redirects for sitemap files to prevent interference
		add_filter( 'redirect_canonical', array( $this, 'disable_canonical_redirects_for_sitemap_xml' ), 10, 2 );

		// Add entries to the bottom of robots.txt
		add_filter( 'robots_txt', array( $this, 'robots_txt' ), 10, 2 );
		// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		// Hook into post deletion/trashing to trigger sitemap updates
		add_action( 'deleted_post', array( $this, 'handle_post_deletion' ), 10, 2 );
		add_action( 'trashed_post', array( $this, 'handle_post_deletion' ), 10, 1 );

		// Register custom cron schedule for sitemap updates
		add_filter( 'cron_schedules', array( $this, 'sitemap_cron_intervals' ) );

		// Register sitemap rewrite rules (we're already in init context)
		$this->sitemap_rewrite_init();
	}

	/**
	 * Remove the core sitemaps' robots.txt hook to prevent them from adding entries.
	 *
	 * @param \WP_Sitemaps $wp_sitemaps WordPress core sitemaps instance.
	 */
	public function remove_core_robots_hook( \WP_Sitemaps $wp_sitemaps ): void {
		// Remove the core sitemaps' robots.txt hook to prevent them from adding entries
		remove_filter( 'robots_txt', array( $wp_sitemaps, 'add_robots' ), 0 );
	}

	/**
	 * Disable core sitemap providers to prevent interference with MSM.
	 *
	 * Replaces all core providers with empty implementations to prevent
	 * core sitemaps from working while keeping the stylesheet endpoints available.
	 *
	 * @param \WP_Sitemaps $wp_sitemaps WordPress core sitemaps instance.
	 */
	public function disable_core_providers( \WP_Sitemaps $wp_sitemaps ): void {
		// Remove all core providers to prevent them from interfering with MSM
		$core_providers = array( 'posts', 'taxonomies', 'users' );
		
		foreach ( $core_providers as $provider_name ) {
			$wp_sitemaps->registry->add_provider(
				$provider_name,
				new class() extends \WP_Sitemaps_Provider {
					/**
					 * Get URL list for the provider.
					 *
					 * @param int    $page_num Page of results.
					 * @param string $subtype  Subtype to query.
					 * @return array Array of URL data.
					 */
					public function get_url_list( $page_num, $subtype = '' ): array {
						return array(); // Return empty array to prevent core sitemaps from working
					}
				
					/**
					 * Get the maximum number of pages available for the provider.
					 *
					 * @param string $subtype Subtype to query.
					 * @return int Number of pages.
					 */
					public function get_max_num_pages( $subtype = '' ): int {
						return 0; // No pages
					}
				} 
			);
		}
	}

	/**
	 * Disable Main Query when rendering sitemaps.
	 *
	 * @param array|null $posts Array of posts or null.
	 * @param \WP_Query  $query The WP_Query instance.
	 * @return array|null Modified posts array or null.
	 */
	public function disable_main_query_for_sitemap_xml( $posts, $query ) {
		if ( $query->is_main_query() && $query->get( 'sitemap' ) ) {
			return array(); // Return empty array to prevent main query from running
		}
		return $posts;
	}

	/**
	 * Disable canonical redirects for sitemap XML requests.
	 *
	 * @param string $redirect_url The redirect URL.
	 * @param string $requested_url The requested URL.
	 * @return string|false The redirect URL or false to prevent redirect.
	 */
	public function disable_canonical_redirects_for_sitemap_xml( $redirect_url, $requested_url ) {
		if ( strpos( $requested_url, 'sitemap' ) !== false ) {
			return false; // Prevent canonical redirects for sitemap URLs
		}
		return $redirect_url;
	}

	/**
	 * Handle post deletion by updating sitemaps
	 *
	 * @param int      $post_id
	 * @param \WP_Post $post
	 */
	public function handle_post_deletion( $post_id, $post = null ) {
		if ( ! $post ) {
			$post = get_post( $post_id );
		}

		if ( ! $post || ! in_array( $post->post_type, $this->post_repository->get_supported_post_types() ) ) {
			return;
		}

		$year  = get_the_date( 'Y', $post );
		$month = get_the_date( 'm', $post );
		$day   = get_the_date( 'd', $post );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook name for backwards compatibility.
		do_action( 'msm_update_sitemap_for_year_month_date', array( $year, $month, $day ), time() );
	}

	/**
	 * Register custom cron intervals for sitemap updates
	 *
	 * @param array[] $schedules WordPress cron schedules array.
	 * @return array[] Modified schedules with custom intervals added.
	 */
	public function sitemap_cron_intervals( $schedules ) {
		$schedules['ms-sitemap-5-min-cron-interval']  = array(
			'interval' => 300,
			'display'  => __( 'Every 5 minutes', 'msm-sitemap' ),
		);
		$schedules['ms-sitemap-10-min-cron-interval'] = array(
			'interval' => 600,
			'display'  => __( 'Every 10 minutes', 'msm-sitemap' ),
		);
		$schedules['ms-sitemap-15-min-cron-interval'] = array(
			'interval' => 900,
			'display'  => __( 'Every 15 minutes', 'msm-sitemap' ),
		);
		$schedules['ms-sitemap-30-min-cron-interval'] = array(
			'interval' => 1800,
			'display'  => __( 'Every 30 minutes', 'msm-sitemap' ),
		);
		$schedules['ms-sitemap-2-hour-cron-interval'] = array(
			'interval' => 7200,
			'display'  => __( 'Every 2 hours', 'msm-sitemap' ),
		);
		$schedules['ms-sitemap-3-hour-cron-interval'] = array(
			'interval' => 10800,
			'display'  => __( 'Every 3 hours', 'msm-sitemap' ),
		);
		return $schedules;
	}

	/**
	 * Setup rewrite rules for the sitemap
	 */
	public function sitemap_rewrite_init() {
		// Allow 'sitemap=true' parameter
		add_rewrite_tag( '%sitemap%', 'true' );

		// Define rewrite rules for the index based on the setup
		if ( Site::is_indexed_by_year() ) {
			add_rewrite_tag( '%sitemap-year%', '[0-9]{4}' );
			add_rewrite_rule( '^sitemap-([0-9]{4}).xml$', 'index.php?sitemap=true&sitemap-year=$matches[1]', 'top' );
		} else {
			add_rewrite_rule( '^sitemap.xml$', 'index.php?sitemap=true', 'top' );
		}

		// Taxonomy sitemap rewrite rules
		add_rewrite_tag( '%taxonomy-sitemap%', '[a-z0-9_-]+' );
		add_rewrite_tag( '%taxonomy-sitemap-page%', '[0-9]+' );

		// Match /sitemap-taxonomy-{slug}.xml (page 1)
		add_rewrite_rule(
			'^sitemap-taxonomy-([a-z0-9_-]+)\.xml$',
			'index.php?taxonomy-sitemap=$matches[1]&taxonomy-sitemap-page=1',
			'top'
		);

		// Match /sitemap-taxonomy-{slug}-{page}.xml (page 2+)
		add_rewrite_rule(
			'^sitemap-taxonomy-([a-z0-9_-]+)-([0-9]+)\.xml$',
			'index.php?taxonomy-sitemap=$matches[1]&taxonomy-sitemap-page=$matches[2]',
			'top'
		);

		// Author sitemap rewrite rules
		add_rewrite_tag( '%author-sitemap%', 'true' );
		add_rewrite_tag( '%author-sitemap-page%', '[0-9]+' );

		// Match /sitemap-author.xml (page 1)
		add_rewrite_rule(
			'^sitemap-author\.xml$',
			'index.php?author-sitemap=true&author-sitemap-page=1',
			'top'
		);

		// Match /sitemap-author-{page}.xml (page 2+)
		add_rewrite_rule(
			'^sitemap-author-([0-9]+)\.xml$',
			'index.php?author-sitemap=true&author-sitemap-page=$matches[1]',
			'top'
		);
	}

	/**
	 * Add entries to the bottom of robots.txt
	 * 
	 * @param string $output The output of the robots.txt file.
	 * @param string $public Whether the site is public.
	 * @return string The output of the robots.txt file.
	 */
	public function robots_txt( $output, $public ) {

		// Make sure the site isn't private
		if ( '1' == $public ) {
			$output .= '# Sitemap archive' . PHP_EOL;

			if ( Site::is_indexed_by_year() ) {
				$years = $this->post_repository->get_years_with_posts();
				foreach ( $years as $year ) {
					$output .= 'Sitemap: ' . Site::get_sitemap_index_url( (int) $year ) . PHP_EOL;
				}

				$output .= PHP_EOL;
			} else {
				$output .= 'Sitemap: ' . Site::get_sitemap_index_url() . PHP_EOL . PHP_EOL;
			}
		}
		return $output;
	}
}

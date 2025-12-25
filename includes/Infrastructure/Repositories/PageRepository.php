<?php
/**
 * Page Repository
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\Repositories
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Infrastructure\Repositories;

use Automattic\MSM_Sitemap\Application\Services\SettingsService;
use Automattic\MSM_Sitemap\Domain\Contracts\PageRepositoryInterface;
use WP_Post;
use WP_Query;

/**
 * Page Repository
 *
 * Handles fetching page data from WordPress for sitemap generation.
 */
class PageRepository implements PageRepositoryInterface {

	/**
	 * The settings service.
	 *
	 * @var SettingsService
	 */
	private SettingsService $settings_service;

	/**
	 * Constructor.
	 *
	 * @param SettingsService $settings_service The settings service.
	 */
	public function __construct( SettingsService $settings_service ) {
		$this->settings_service = $settings_service;
	}

	/**
	 * Get enabled page types from settings.
	 *
	 * @return array<string> Array of page type slugs.
	 */
	private function get_enabled_page_types(): array {
		$enabled = $this->settings_service->get_setting( 'enabled_page_types', array( 'page' ) );

		if ( ! is_array( $enabled ) || empty( $enabled ) ) {
			return array();
		}

		return $enabled;
	}

	/**
	 * Get published pages.
	 *
	 * @param int $offset Number of pages to skip.
	 * @param int $limit  Maximum pages to return.
	 * @return array<WP_Post> Array of post objects.
	 */
	public function get_pages( int $offset = 0, int $limit = 2000 ): array {
		$page_types = $this->get_enabled_page_types();

		if ( empty( $page_types ) ) {
			return array();
		}

		$args = array(
			'post_type'      => $page_types,
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'offset'         => $offset,
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		);

		/**
		 * Filter page query arguments for sitemap generation.
		 *
		 * @since 2.0.0
		 *
		 * @param array $args Page query arguments.
		 */
		$args = apply_filters( 'msm_sitemap_page_query_args', $args );

		$query = new WP_Query( $args );
		$pages = $query->posts;

		if ( empty( $pages ) ) {
			return array();
		}

		// Filter out noindex pages
		return array_values(
			array_filter(
				$pages,
				function ( WP_Post $page ): bool {
					return ! $this->is_page_noindex( $page->ID );
				}
			)
		);
	}

	/**
	 * Get total count of published pages.
	 *
	 * @return int Total number of pages.
	 */
	public function get_page_count(): int {
		$page_types = $this->get_enabled_page_types();

		if ( empty( $page_types ) ) {
			return 0;
		}

		$args = array(
			'post_type'      => $page_types,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		);

		$query = new WP_Query( $args );

		return (int) $query->post_count;
	}

	/**
	 * Get the URL for a page.
	 *
	 * @param WP_Post $page The post object.
	 * @return string|null The page URL or null if not available.
	 */
	public function get_page_url( WP_Post $page ): ?string {
		$url = get_permalink( $page );

		return $url ? $url : null;
	}

	/**
	 * Check if a page should be excluded from sitemaps (noindex).
	 *
	 * @param int $post_id The post ID.
	 * @return bool True if the page should be excluded, false otherwise.
	 */
	public function is_page_noindex( int $post_id ): bool {
		/**
		 * Filter whether a page should be excluded from sitemaps.
		 *
		 * Allows integration with SEO plugins that set noindex on pages.
		 *
		 * @since 2.0.0
		 *
		 * @param bool $noindex Whether the page is noindex. Default false.
		 * @param int  $post_id The post ID.
		 */
		return (bool) apply_filters( 'msm_sitemap_page_noindex', false, $post_id );
	}

	/**
	 * Get the last modified date for a page.
	 *
	 * @param WP_Post $page The post object.
	 * @return string|null The last modified date in W3C format or null.
	 */
	public function get_page_lastmod( WP_Post $page ): ?string {
		$modified = get_post_modified_time( 'c', true, $page );

		return $modified ? $modified : null;
	}
}

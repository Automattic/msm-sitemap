<?php
/**
 * Page Sitemap Cache Invalidator
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\WordPress
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Infrastructure\WordPress;

use Automattic\MSM_Sitemap\Application\Services\PageSitemapService;
use Automattic\MSM_Sitemap\Domain\Contracts\WordPressIntegrationInterface;

/**
 * Handles cache invalidation for page sitemaps.
 *
 * Invalidates on structural changes that affect the sitemap:
 * - Page published
 * - Page deleted
 * - Page trashed
 * - Page status changed
 */
class PageSitemapCacheInvalidator implements WordPressIntegrationInterface {

	/**
	 * The page sitemap service.
	 *
	 * @var PageSitemapService
	 */
	private PageSitemapService $page_sitemap_service;

	/**
	 * Constructor.
	 *
	 * @param PageSitemapService $page_sitemap_service The page sitemap service.
	 */
	public function __construct( PageSitemapService $page_sitemap_service ) {
		$this->page_sitemap_service = $page_sitemap_service;
	}

	/**
	 * Register WordPress hooks.
	 */
	public function register_hooks(): void {
		// Invalidate when a page is saved
		add_action( 'save_post_page', array( $this, 'on_page_saved' ), 10, 3 );

		// Invalidate when a page is deleted
		add_action( 'deleted_post', array( $this, 'on_page_deleted' ), 10, 2 );

		// Invalidate when a page is trashed
		add_action( 'trashed_post', array( $this, 'on_page_trashed' ), 10, 1 );
	}

	/**
	 * Handle page saved.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @param bool     $update  Whether this is an update.
	 */
	public function on_page_saved( int $post_id, \WP_Post $post, bool $update ): void {
		// Only invalidate for published pages
		if ( 'publish' !== $post->post_status ) {
			return;
		}

		$this->maybe_invalidate_cache();
	}

	/**
	 * Handle page deleted.
	 *
	 * @param int           $post_id Post ID.
	 * @param \WP_Post|null $post    Post object (may be null).
	 */
	public function on_page_deleted( int $post_id, $post = null ): void {
		if ( null === $post ) {
			$post = get_post( $post_id );
		}

		if ( ! $post || 'page' !== $post->post_type ) {
			return;
		}

		$this->maybe_invalidate_cache();
	}

	/**
	 * Handle page trashed.
	 *
	 * @param int $post_id Post ID.
	 */
	public function on_page_trashed( int $post_id ): void {
		$post = get_post( $post_id );

		if ( ! $post || 'page' !== $post->post_type ) {
			return;
		}

		$this->maybe_invalidate_cache();
	}

	/**
	 * Invalidate cache if page sitemaps are enabled.
	 */
	private function maybe_invalidate_cache(): void {
		if ( ! $this->page_sitemap_service->is_enabled() ) {
			return;
		}

		$this->page_sitemap_service->invalidate_cache();
	}
}

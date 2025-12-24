<?php
/**
 * Author Sitemap Cache Invalidator
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\WordPress
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Infrastructure\WordPress;

use Automattic\MSM_Sitemap\Application\Services\AuthorSitemapService;
use Automattic\MSM_Sitemap\Domain\Contracts\WordPressIntegrationInterface;

/**
 * Handles cache invalidation for author sitemaps.
 *
 * Only invalidates on structural changes that affect the sitemap:
 * - User deleted (author removed from sitemap)
 * - User role changed (might gain/lose capability to have published posts)
 *
 * Does NOT invalidate on post saves - author sitemaps list authors with
 * published posts, and a high-volume site would thrash the cache if we
 * invalidated on every post change. Uses TTL-based expiry for eventual
 * consistency when authors publish their first post.
 */
class AuthorSitemapCacheInvalidator implements WordPressIntegrationInterface {

	/**
	 * The author sitemap service.
	 *
	 * @var AuthorSitemapService
	 */
	private AuthorSitemapService $author_sitemap_service;

	/**
	 * Constructor.
	 *
	 * @param AuthorSitemapService $author_sitemap_service The author sitemap service.
	 */
	public function __construct( AuthorSitemapService $author_sitemap_service ) {
		$this->author_sitemap_service = $author_sitemap_service;
	}

	/**
	 * Register WordPress hooks.
	 */
	public function register_hooks(): void {
		// Invalidate when a user is deleted
		add_action( 'deleted_user', array( $this, 'on_user_deleted' ), 10, 1 );

		// Invalidate when a user's role changes
		add_action( 'set_user_role', array( $this, 'on_user_role_changed' ), 10, 3 );
	}

	/**
	 * Handle user deleted.
	 *
	 * @param int $user_id User ID.
	 */
	public function on_user_deleted( int $user_id ): void {
		$this->maybe_invalidate_cache();
	}

	/**
	 * Handle user role changed.
	 *
	 * @param int    $user_id   User ID.
	 * @param string $role      New role.
	 * @param array  $old_roles Previous roles.
	 */
	public function on_user_role_changed( int $user_id, string $role, array $old_roles ): void {
		$this->maybe_invalidate_cache();
	}

	/**
	 * Invalidate cache if author sitemaps are enabled.
	 */
	private function maybe_invalidate_cache(): void {
		if ( ! $this->author_sitemap_service->is_enabled() ) {
			return;
		}

		$this->author_sitemap_service->invalidate_cache();
	}
}

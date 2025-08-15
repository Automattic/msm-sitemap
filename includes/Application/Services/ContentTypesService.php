<?php
/**
 * Content Types Service
 *
 * @package Automattic\MSM_Sitemap\Application\Services
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Application\Services;

use Automattic\MSM_Sitemap\Domain\ValueObjects\SitemapContentTypes;
use Automattic\MSM_Sitemap\Domain\Contracts\ContentProviderInterface;

/**
 * Service responsible for managing sitemap content types.
 *
 * Handles the initialization, registration, and retrieval of content providers
 * for sitemap generation.
 */
class ContentTypesService {

	/**
	 * Sitemap content types collection.
	 *
	 * @var SitemapContentTypes|null
	 */
	private ?SitemapContentTypes $sitemap_content_types = null;

	/**
	 * Initialize sitemap content types collection.
	 */
	public function initialize(): void {
		$this->sitemap_content_types = new SitemapContentTypes();
	}

	/**
	 * Register content providers based on WordPress filters.
	 *
	 * @param ContentProviderInterface[] $providers Array of content providers to register.
	 */
	public function register_providers( array $providers ): void {
		if ( null === $this->sitemap_content_types ) {
			$this->initialize();
		}

		foreach ( $providers as $provider ) {
			$this->sitemap_content_types->register( $provider );
		}
	}

	/**
	 * Get the sitemap content types collection.
	 *
	 * @return SitemapContentTypes The content types collection.
	 */
	public function get_content_types(): SitemapContentTypes {
		if ( null === $this->sitemap_content_types ) {
			$this->initialize();
		}
		
		return $this->sitemap_content_types;
	}
}

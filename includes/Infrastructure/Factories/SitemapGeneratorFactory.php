<?php
/**
 * Factory for creating SitemapGenerator instances
 *
 * @package MSM_Sitemap
 */

namespace Automattic\MSM_Sitemap\Infrastructure\Factories;

use Automattic\MSM_Sitemap\Application\Services\SitemapGenerator;
use Automattic\MSM_Sitemap\Domain\ValueObjects\SitemapContentTypes;

/**
 * Factory responsible for creating configured SitemapGenerator instances
 */
class SitemapGeneratorFactory {

	/**
	 * Cached generator instance
	 *
	 * @var SitemapGenerator|null
	 */
	private static ?SitemapGenerator $generator = null;

	/**
	 * Create a SitemapGenerator instance with registered content providers
	 *
	 * @param SitemapContentTypes $content_types The content types collection
	 * @return SitemapGenerator
	 */
	public static function create( SitemapContentTypes $content_types ): SitemapGenerator {
		if ( null === self::$generator ) {
			self::$generator = new SitemapGenerator( $content_types );
		}
		
		return self::$generator;
	}

	/**
	 * Reset the cached generator instance (mainly for testing)
	 */
	public static function reset(): void {
		self::$generator = null;
	}
}

<?php
/**
 * StylesheetManager.php
 *
 * Provides XSL stylesheet reference strings for sitemap XML output.
 * The actual XSL content is served by XslRequestHandler in Infrastructure/HTTP.
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\WordPress
 */

declare(strict_types=1);

namespace Automattic\MSM_Sitemap\Infrastructure\WordPress;

use Automattic\MSM_Sitemap\Domain\ValueObjects\Site;

/**
 * Provides XSL stylesheet reference strings for sitemap XML output.
 */
class StylesheetManager {

	/**
	 * Get XSL stylesheet reference for individual sitemaps.
	 *
	 * @return string XSL stylesheet reference for sitemap.
	 */
	public static function get_sitemap_stylesheet_reference(): string {
		/**
		 * Filters whether to include XSL stylesheet reference in sitemap XML.
		 *
		 * @since 2.0.0
		 *
		 * @param bool $include_xsl Whether to include XSL stylesheet reference. Default true.
		 */
		if ( ! apply_filters( 'msm_sitemap_include_xsl_reference', true ) ) {
			return '';
		}

		return "\n" . '<?xml-stylesheet type="text/xsl" href="' . Site::get_home_url( '/msm-sitemap.xsl' ) . '"?>' . "\n";
	}

	/**
	 * Get XSL stylesheet reference for sitemap index.
	 *
	 * @return string XSL stylesheet reference for sitemap index.
	 */
	public static function get_index_stylesheet_reference(): string {
		/**
		 * Filters whether to include XSL stylesheet reference in sitemap index XML.
		 *
		 * @since 2.0.0
		 *
		 * @param bool $include_xsl Whether to include XSL stylesheet reference. Default true.
		 */
		if ( ! apply_filters( 'msm_sitemap_include_xsl_reference', true ) ) {
			return '';
		}

		return "\n" . '<?xml-stylesheet type="text/xsl" href="' . Site::get_home_url( '/msm-sitemap-index.xsl' ) . '"?>' . "\n";
	}
}

<?php
/**
 * SitemapXmlFormatter
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\Formatters
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Infrastructure\Formatters;

use Automattic\MSM_Sitemap\Domain\ValueObjects\SitemapContent;
use Automattic\MSM_Sitemap\Domain\ValueObjects\UrlEntry;
use Automattic\MSM_Sitemap\Domain\ValueObjects\ImageEntry;
use Automattic\MSM_Sitemap\Infrastructure\WordPress\StylesheetManager;

/**
 * Formats SitemapContent to XML.
 */
class SitemapXmlFormatter {

	/**
	 * Format SitemapContent to XML.
	 *
	 * @param SitemapContent $content The sitemap content to format.
	 * @return string The XML representation.
	 */
	public function format( SitemapContent $content ): string {
		$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		
		// Add XSL stylesheet reference
		$xml .= StylesheetManager::get_sitemap_stylesheet_reference();
		
		// Add urlset with all required namespaces
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';
		$xml .= ' xmlns:news="http://www.google.com/schemas/sitemap-news/0.9"';
		$xml .= ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"';
		$xml .= ' xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">' . "\n";

		foreach ( $content->get_entries() as $entry ) {
			$xml .= $this->format_url_entry( $entry );
		}

		$xml .= '</urlset>';

		return $xml;
	}

	/**
	 * Format a single URL entry to XML.
	 *
	 * @param UrlEntry $entry The URL entry to format.
	 * @return string The XML representation of the URL entry.
	 */
	private function format_url_entry( UrlEntry $entry ): string {
		$xml  = "\t<url>\n";
		$xml .= "\t\t<loc>" . esc_xml( $entry->loc() ) . "</loc>\n";

		if ( $entry->lastmod() ) {
			$xml .= "\t\t<lastmod>" . esc_xml( $entry->lastmod() ) . "</lastmod>\n";
		}

		if ( $entry->changefreq() ) {
			$xml .= "\t\t<changefreq>" . esc_xml( $entry->changefreq() ) . "</changefreq>\n";
		}

		if ( $entry->priority() ) {
			$xml .= "\t\t<priority>" . esc_xml( $entry->priority() ) . "</priority>\n";
		}

		// Add images if present
		if ( $entry->has_images() ) {
			foreach ( $entry->images() as $image ) {
				$xml .= $this->format_image_entry( $image );
			}
		}

		$xml .= "\t</url>\n";

		return $xml;
	}

	/**
	 * Format a single image entry to XML.
	 *
	 * Per Google's Image Sitemap specification, only the loc element is required.
	 * Other elements (caption, geo_location, title, license) have been deprecated by Google.
	 *
	 * @see https://developers.google.com/search/docs/crawling-indexing/sitemaps/image-sitemaps
	 *
	 * @param ImageEntry $image The image entry to format.
	 * @return string The XML representation of the image entry.
	 */
	private function format_image_entry( ImageEntry $image ): string {
		$xml  = "\t\t<image:image>\n";
		$xml .= "\t\t\t<image:loc>" . esc_xml( $image->loc() ) . "</image:loc>\n";
		$xml .= "\t\t</image:image>\n";

		return $xml;
	}
}

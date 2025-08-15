<?php
/**
 * Sitemap Index XML Formatter
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\Formatters
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Infrastructure\Formatters;

use Automattic\MSM_Sitemap\Domain\ValueObjects\SitemapIndexCollection;
use Automattic\MSM_Sitemap\Domain\ValueObjects\SitemapIndexEntry;
use Automattic\MSM_Sitemap\Infrastructure\WordPress\StylesheetManager;

/**
 * Formatter for converting SitemapIndexCollection objects to XML.
 *
 * Handles XML-specific concerns while keeping the domain layer pure.
 */
class SitemapIndexXmlFormatter {

	/**
	 * Convert a sitemap index collection to XML representation.
	 *
	 * @param SitemapIndexCollection $collection The sitemap index collection to format.
	 * @return string XML representation of the sitemap index.
	 */
	public function format( SitemapIndexCollection $collection ): string {
		$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		
		// Add XSL stylesheet reference for sitemap index
		$xml .= StylesheetManager::get_index_stylesheet_reference();
		
		$xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		foreach ( $collection->get_entries() as $entry ) {
			$xml .= $this->format_sitemap_entry( $entry );
		}

		$xml .= '</sitemapindex>';

		return $xml;
	}

	/**
	 * Format a sitemap index entry as XML.
	 *
	 * @param SitemapIndexEntry $entry The sitemap index entry to format.
	 * @return string XML representation of the sitemap index entry.
	 */
	private function format_sitemap_entry( SitemapIndexEntry $entry ): string {
		$xml  = '    <sitemap>' . "\n";
		$xml .= '        <loc>' . esc_xml( $entry->loc() ) . '</loc>' . "\n";

		if ( $entry->lastmod() ) {
			$xml .= '        <lastmod>' . esc_xml( $entry->lastmod() ) . '</lastmod>' . "\n";
		}

		$xml .= '    </sitemap>' . "\n";

		return $xml;
	}
}

<?php
/**
 * Sitemap XML Request Handler
 *
 * Handles direct HTTP requests for sitemap XML files. This class intercepts requests
 * to URLs like /sitemap.xml and /sitemap-2024.xml, serving the appropriate XML content
 * directly to search engines and users without going through the normal WordPress
 * template system.
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\HTTP
 */

declare(strict_types=1);

namespace Automattic\MSM_Sitemap\Infrastructure\HTTP;

use Automattic\MSM_Sitemap\Domain\ValueObjects\Site;
use Automattic\MSM_Sitemap\Domain\ValueObjects\SitemapDate;
use Automattic\MSM_Sitemap\Application\Services\AuthorSitemapService;
use Automattic\MSM_Sitemap\Application\Services\SitemapService;
use Automattic\MSM_Sitemap\Application\Services\TaxonomySitemapService;
use Automattic\MSM_Sitemap\Infrastructure\Formatters\SitemapIndexXmlFormatter;
use Automattic\MSM_Sitemap\Infrastructure\Factories\SitemapIndexEntryFactory;
use Automattic\MSM_Sitemap\Infrastructure\Factories\SitemapIndexCollectionFactory;
use Automattic\MSM_Sitemap\Infrastructure\WordPress\StylesheetManager;
use Automattic\MSM_Sitemap\Infrastructure\WordPress\PostTypeRegistration;

use Automattic\MSM_Sitemap\Domain\Contracts\WordPressIntegrationInterface;

/**
 * Handles sitemap XML request handling and responses.
 */
class SitemapXmlRequestHandler implements WordPressIntegrationInterface {

	/**
	 * The sitemap service.
	 */
	private SitemapService $sitemap_service;

	/**
	 * The post type registration service.
	 */
	private PostTypeRegistration $post_type_registration;

	/**
	 * The taxonomy sitemap service.
	 */
	private TaxonomySitemapService $taxonomy_sitemap_service;

	/**
	 * The author sitemap service.
	 */
	private AuthorSitemapService $author_sitemap_service;

	/**
	 * Constructor.
	 *
	 * @param SitemapService          $sitemap_service          The sitemap service.
	 * @param PostTypeRegistration    $post_type_registration   The post type registration service.
	 * @param TaxonomySitemapService  $taxonomy_sitemap_service The taxonomy sitemap service.
	 * @param AuthorSitemapService    $author_sitemap_service   The author sitemap service.
	 */
	public function __construct(
		SitemapService $sitemap_service,
		PostTypeRegistration $post_type_registration,
		TaxonomySitemapService $taxonomy_sitemap_service,
		AuthorSitemapService $author_sitemap_service
	) {
		$this->sitemap_service          = $sitemap_service;
		$this->post_type_registration   = $post_type_registration;
		$this->taxonomy_sitemap_service = $taxonomy_sitemap_service;
		$this->author_sitemap_service   = $author_sitemap_service;
	}

	/**
	 * Register WordPress hooks and filters for sitemap XML request handling.
	 */
	public function register_hooks(): void {
		add_filter( 'template_include', array( $this, 'handle_template_include' ) );
	}

	/**
	 * Handle template_include filter for sitemap requests.
	 *
	 * @param string $template The path of the template to include.
	 * @return string The template path (unchanged if not a sitemap request).
	 */
	public function handle_template_include( string $template ): string {
		// Handle taxonomy sitemap requests
		$taxonomy_sitemap = get_query_var( 'taxonomy-sitemap' );
		if ( ! empty( $taxonomy_sitemap ) ) {
			$page = (int) get_query_var( 'taxonomy-sitemap-page', 1 );
			$this->handle_taxonomy_sitemap_request( $taxonomy_sitemap, max( 1, $page ) );
		}

		// Handle author sitemap requests
		$author_sitemap = get_query_var( 'author-sitemap' );
		if ( 'true' === $author_sitemap ) {
			$page = (int) get_query_var( 'author-sitemap-page', 1 );
			$this->handle_author_sitemap_request( max( 1, $page ) );
		}

		// Handle regular sitemap requests
		if ( get_query_var( 'sitemap' ) === 'true' ) {
			$this->handle_sitemap_request();
		}

		return $template;
	}

	/**
	 * Handle sitemap requests and output appropriate responses.
	 */
	public function handle_sitemap_request(): void {
		// Check if sitemaps are enabled
		if ( ! Site::are_sitemaps_enabled() ) {
			$this->send_sitemap_error_response(
				__( 'Sorry, sitemaps are not available on this site.', 'msm-sitemap' )
			);
			return;
		}

		// Parse request parameters
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Public sitemap endpoint, no state modification.
		$req_year = get_query_var( 'sitemap-year' );
		if ( empty( $req_year ) ) {
			$req_year = ( isset( $_GET['yyyy'] ) ) ? intval( $_GET['yyyy'] ) : false;
		}

		$req_month = ( isset( $_GET['mm'] ) ) ? intval( $_GET['mm'] ) : false;
		$req_day   = ( isset( $_GET['dd'] ) ) ? intval( $_GET['dd'] ) : false;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		// Determine what type of sitemap to serve
		if ( ( false === $req_year || is_numeric( $req_year ) ) && false === $req_month && false === $req_day ) {
			// Root sitemap index
			$xml = $this->get_sitemap_index_xml( $req_year );
		} elseif ( $req_year > 0 && $req_month > 0 && $req_day > 0 ) {
			// Individual day sitemap
			$xml = $this->get_individual_sitemap_xml( $req_year, $req_month, $req_day );
		} else {
			// Invalid parameters
			$xml = false;
		}

		// Output response
		if ( false === $xml ) {
			$this->send_sitemap_not_found_response();
		} else {
			$this->send_sitemap_xml_response( $xml );
		}
	}

	/**
	 * Handle taxonomy sitemap requests.
	 *
	 * @param string $taxonomy The taxonomy slug.
	 * @param int    $page     The page number (1-indexed).
	 */
	public function handle_taxonomy_sitemap_request( string $taxonomy, int $page = 1 ): void {
		// Check if sitemaps are enabled
		if ( ! Site::are_sitemaps_enabled() ) {
			$this->send_sitemap_error_response(
				__( 'Sorry, sitemaps are not available on this site.', 'msm-sitemap' )
			);
			return;
		}

		// Generate sitemap XML
		$xml = $this->taxonomy_sitemap_service->generate_sitemap_xml( $taxonomy, $page );

		if ( null === $xml ) {
			$this->send_sitemap_not_found_response();
		} else {
			$this->send_sitemap_xml_response( $xml );
		}
	}

	/**
	 * Handle author sitemap requests.
	 *
	 * @param int $page The page number (1-indexed).
	 */
	public function handle_author_sitemap_request( int $page = 1 ): void {
		// Check if sitemaps are enabled
		if ( ! Site::are_sitemaps_enabled() ) {
			$this->send_sitemap_error_response(
				__( 'Sorry, sitemaps are not available on this site.', 'msm-sitemap' )
			);
			return;
		}

		// Generate sitemap XML
		$xml = $this->author_sitemap_service->generate_sitemap_xml( $page );

		if ( null === $xml ) {
			$this->send_sitemap_not_found_response();
		} else {
			$this->send_sitemap_xml_response( $xml );
		}
	}

	/**
	 * Get sitemap index XML using proper DDD services.
	 *
	 * @param int|false $year Optional year for year-specific index.
	 * @return string|false Sitemap index XML or false if not found.
	 */
	public function get_sitemap_index_xml( $year = false ) {
		global $wpdb;

		// Use the same query logic as the old build_root_sitemap_xml method for consistency
		if ( is_numeric( $year ) ) {
			$query = $wpdb->prepare(
				"SELECT post_date FROM $wpdb->posts WHERE post_type = %s AND YEAR(post_date) = %s AND post_status = 'publish' ORDER BY post_date DESC LIMIT 10000",
				$this->post_type_registration->get_post_type(),
				$year
			);
		} else {
			$query = $wpdb->prepare(
				"SELECT post_date FROM $wpdb->posts WHERE post_type = %s AND post_status = 'publish' ORDER BY post_date DESC LIMIT 10000",
				$this->post_type_registration->get_post_type()
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$sitemaps = $wpdb->get_col( $query );

		// Remove duplicates like the old method
		$sitemaps = array_unique( $sitemaps );

		/**
		 * Filter daily sitemaps from the index by date.
		 *
		 * Expects an array of dates in MySQL DATETIME format [ Y-m-d H:i:s ].
		 *
		 * Since adding dates that do not have posts is pointless, this filter is primarily intended for removing
		 * dates before or after a specific date or possibly targeting specific dates to exclude.
		 *
		 * @param array $sitemaps Array of sitemap dates in MySQL DATETIME format.
		 */
		$sitemaps = apply_filters( 'msm_sitemap_index_sitemaps', $sitemaps );

		// Convert dates to sitemap entries
		$entries = array();
		foreach ( $sitemaps as $sitemap_date ) {
			$date = SitemapDate::fromString( $sitemap_date );

			$loc     = $this->get_sitemap_url( $date );
			$lastmod = $sitemap_date;

			$entries[] = SitemapIndexEntryFactory::from_data( $loc, $lastmod );
		}

		// Add taxonomy sitemap entries (only for root index, not year-specific)
		if ( false === $year ) {
			$taxonomy_entries = $this->taxonomy_sitemap_service->get_sitemap_index_entries();
			foreach ( $taxonomy_entries as $entry ) {
				$entries[] = SitemapIndexEntryFactory::from_data( $entry['url'], $entry['lastmod'] );
			}
		}

		// Add author sitemap entries (only for root index, not year-specific)
		if ( false === $year ) {
			$author_entries = $this->author_sitemap_service->get_sitemap_index_entries();
			foreach ( $author_entries as $entry ) {
				$entries[] = SitemapIndexEntryFactory::from_data( $entry['url'], $entry['lastmod'] );
			}
		}

		// Return false if no entries (neither date-based, taxonomy, nor author sitemaps)
		if ( empty( $entries ) ) {
			return false;
		}

		// Create collection and format XML
		$collection = SitemapIndexCollectionFactory::from_entries( $entries );
		$formatter  = new SitemapIndexXmlFormatter();

		$xml_string = $formatter->format( $collection );

		/**
		 * Filter the XML to append to the sitemap index before the closing tag.
		 *
		 * Useful for adding in extra sitemaps to the index.
		 *
		 * @param string   $appended_xml The XML to append. Default empty string.
		 * @param int|bool $year         The year for which the sitemap index is being generated, or false for all years.
		 * @param array    $sitemaps     The sitemaps to be included in the index.
		 */
		$appended   = apply_filters( 'msm_sitemap_index_appended_xml', '', $year, $sitemaps );
		$xml_string = str_replace( '</sitemapindex>', $appended . '</sitemapindex>', $xml_string );

		/**
		 * Filter the whole generated sitemap index XML before output.
		 *
		 * @param string   $xml_string The sitemap index XML.
		 * @param int|bool $year       The year for which the sitemap index is being generated, or false for all years.
		 * @param array    $sitemaps   The sitemaps to be included in the index.
		 */
		$xml_string = apply_filters( 'msm_sitemap_index_xml', $xml_string, $year, $sitemaps );

		return $xml_string;
	}

	/**
	 * Get individual sitemap XML for a specific date.
	 *
	 * @param int $year  Year.
	 * @param int $month Month.
	 * @param int $day   Day.
	 * @return string|false Sitemap XML or false if not found.
	 */
	public function get_individual_sitemap_xml( int $year, int $month, int $day ) {
		$date = new SitemapDate( $year, $month, $day );

		// Get sitemap data for this date
		$sitemap_data = $this->sitemap_service->get_sitemap_data( $date->toString() );

		if ( ! $sitemap_data || ! isset( $sitemap_data['xml_content'] ) ) {
			return false;
		}

		return $sitemap_data['xml_content'];
	}

	/**
	 * Get sitemap URL for a specific date.
	 *
	 * @param SitemapDate $date The sitemap date.
	 * @return string Sitemap URL.
	 */
	private function get_sitemap_url( SitemapDate $date ): string {
		$year  = $date->yearString();
		$month = $date->monthString();
		$day   = $date->dayString();

		if ( Site::is_indexed_by_year() ) {
			return Site::get_home_url( "/sitemap-{$year}.xml?yyyy={$year}&mm={$month}&dd={$day}" );
		}
		return Site::get_home_url( "/sitemap.xml?yyyy={$year}&mm={$month}&dd={$day}" );
	}

	/**
	 * Send sitemap XML response.
	 *
	 * Note: The stylesheet reference is already included in the XML content
	 * by the formatters (SitemapXmlFormatter, SitemapIndexXmlFormatter).
	 *
	 * @param string $xml XML content.
	 */
	private function send_sitemap_xml_response( string $xml ): void {
		status_header( 200 );
		header( 'Content-Type: application/xml; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $xml;
		exit;
	}

	/**
	 * Send sitemap error response.
	 *
	 * @param string $message Error message.
	 */
	private function send_sitemap_error_response( string $message ): void {
		header( 'Content-Type: application/xml; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo StylesheetManager::get_sitemap_stylesheet_reference();
		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
		echo '<url>' . "\n";
		echo '<loc>' . esc_url( home_url( '/' ) ) . '</loc>' . "\n";
		echo '<lastmod>' . gmdate( 'c' ) . '</lastmod>' . "\n";
		echo '</url>' . "\n";
		echo '</urlset>';
		exit;
	}

	/**
	 * Send sitemap not found response.
	 */
	private function send_sitemap_not_found_response(): void {
		header( 'HTTP/1.0 404 Not Found' );
		header( 'Content-Type: text/plain; charset=UTF-8' );
		echo esc_html( __( 'Sitemap not found.', 'msm-sitemap' ) );
		exit;
	}
}

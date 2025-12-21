<?php
/**
 * Sitemap Endpoint Handler
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\WordPress
 */

declare(strict_types=1);

namespace Automattic\MSM_Sitemap\Infrastructure\WordPress;

use Automattic\MSM_Sitemap\Domain\ValueObjects\Site;
use Automattic\MSM_Sitemap\Application\Services\SitemapService;
use Automattic\MSM_Sitemap\Infrastructure\Repositories\SitemapPostRepository;
use Automattic\MSM_Sitemap\Infrastructure\Formatters\SitemapIndexXmlFormatter;
use Automattic\MSM_Sitemap\Infrastructure\Factories\SitemapIndexEntryFactory;
use Automattic\MSM_Sitemap\Domain\ValueObjects\SitemapIndexCollection;

/**
 * Handles sitemap endpoint requests and responses.
 */
class SitemapEndpointHandler {

	/**
	 * Handle template_include filter for sitemap requests.
	 *
	 * @param string $template The path of the template to include.
	 * @return string The template path (unchanged if not a sitemap request).
	 */
	public static function handle_template_include( string $template ): string {
		if ( get_query_var( 'sitemap' ) === 'true' ) {
			// Handle sitemap request directly without template file
			self::handle_sitemap_request();
		}
		return $template;
	}

	/**
	 * Handle sitemap requests and output appropriate responses.
	 */
	public static function handle_sitemap_request(): void {
		// Check if site is public
		if ( ! Site::is_public() ) {
			self::send_sitemap_error_response( 
				__( 'Sorry, this site is not public so sitemaps are not available.', 'msm-sitemap' )
			);
			return;
		}

		// Parse request parameters
		$req_year = get_query_var( 'sitemap-year' );
		if ( empty( $req_year ) ) {
			$req_year = ( isset( $_GET['yyyy'] ) ) ? intval( $_GET['yyyy'] ) : false;
		}

		$req_month = ( isset( $_GET['mm'] ) ) ? intval( $_GET['mm'] ) : false;
		$req_day   = ( isset( $_GET['dd'] ) ) ? intval( $_GET['dd'] ) : false;

		// Determine what type of sitemap to serve
		if ( ( false === $req_year || is_numeric( $req_year ) ) && false === $req_month && false === $req_day ) {
			// Root sitemap index
			$xml = self::get_sitemap_index_xml( $req_year );
		} elseif ( $req_year > 0 && $req_month > 0 && $req_day > 0 ) {
			// Individual day sitemap
			$xml = self::get_individual_sitemap_xml( $req_year, $req_month, $req_day );
		} else {
			// Invalid parameters
			$xml = false;
		}

		// Output response
		if ( false === $xml ) {
			self::send_sitemap_not_found_response();
		} else {
			self::send_sitemap_xml_response( $xml );
		}
	}

	/**
	 * Get sitemap index XML using proper DDD services.
	 *
	 * @param int|false $year Optional year for year-specific index.
	 * @return string|false Sitemap index XML or false if not found.
	 */
	public static function get_sitemap_index_xml( $year = false ) {
		global $wpdb;
		
		// Use the same query logic as the old build_root_sitemap_xml method for consistency
		if ( is_numeric( $year ) ) {
			$query = $wpdb->prepare( 
				"SELECT post_date FROM $wpdb->posts WHERE post_type = %s AND YEAR(post_date) = %s AND post_status = 'publish' ORDER BY post_date DESC LIMIT 10000", 
				\Automattic\MSM_Sitemap\Plugin::SITEMAP_CPT, 
				$year 
			);
		} else {
			$query = $wpdb->prepare( 
				"SELECT post_date FROM $wpdb->posts WHERE post_type = %s AND post_status = 'publish' ORDER BY post_date DESC LIMIT 10000", 
				\Automattic\MSM_Sitemap\Plugin::SITEMAP_CPT 
			);
		}

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
		 * @since 1.4.0
		 *
		 * @param array  $sitemaps Array of dates in MySQL DATETIME format [ Y-m-d H:i:s ].
		 * @param string $year     Year that sitemap is being generated for.
		 */
		$sitemaps = apply_filters( 'msm_sitemap_index', $sitemaps, $year );

		if ( empty( $sitemaps ) ) {
			return false;
		}

		// Convert dates to sitemap index entries using the factory
		$entries = SitemapIndexEntryFactory::from_sitemap_dates( $sitemaps );
		
		if ( empty( $entries ) ) {
			return false;
		}
		
		// Create collection and format to XML
		$collection = new SitemapIndexCollection( $entries );
		$formatter = new SitemapIndexXmlFormatter();
		
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
		$appended = apply_filters( 'msm_sitemap_index_appended_xml', '', $year, $sitemaps );
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
	 * Get individual sitemap XML using SitemapService.
	 *
	 * @param int $year Year.
	 * @param int $month Month.
	 * @param int $day Day.
	 * @return string|false Sitemap XML or false if not found.
	 */
	private static function get_individual_sitemap_xml( int $year, int $month, int $day ) {
		$repository = new SitemapPostRepository();
		$generator = msm_sitemap_plugin()->get_sitemap_generator();
		$service = new SitemapService( $generator, $repository );

		// Format date for service
		$date = sprintf( '%04d-%02d-%02d', $year, $month, $day );
		
		// Get sitemap data using the service
		$sitemap_data = $service->get_sitemap_data( $date );
		
		if ( $sitemap_data && isset( $sitemap_data['xml_content'] ) ) {
			return $sitemap_data['xml_content'];
		}

		return false;
	}

	/**
	 * Send a valid XML sitemap response.
	 *
	 * @param string $xml_content The XML content to send.
	 */
	private static function send_sitemap_xml_response( string $xml_content ): void {
		// Explicitly set 200 OK status to override any previously set status.
		//
		// WordPress core sitemaps (introduced in WP 5.5) use the same 'sitemap' query var.
		// When core's sitemap handler runs and finds no matching core sitemap, it sets a
		// 404 status. Even though MSM Sitemap outputs valid XML, the 404 status persists
		// because we only set headers for Content-Type, not the HTTP status.
		//
		// This ensures search engines receive the correct 200 status with our valid sitemap.
		//
		// @see https://core.trac.wordpress.org/ticket/51136
		// @see https://github.com/Automattic/msm-sitemap/pull/168
		status_header( 200 );

		header( 'Content-Type: application/xml; charset=UTF-8' );
		echo $xml_content;
		exit;
	}

	/**
	 * Send a 404 XML response for missing sitemaps.
	 */
	private static function send_sitemap_not_found_response(): void {
		status_header( 404 );
		header( 'Content-Type: application/xml; charset=UTF-8' );
		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo StylesheetManager::get_sitemap_stylesheet_reference();
		echo '<!-- Sitemap not found for the requested date -->' . "\n";
		echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"/>';
		exit;
	}

	/**
	 * Send an error response for sitemap requests.
	 *
	 * @param string $message Error message.
	 */
	private static function send_sitemap_error_response( string $message ): void {
		status_header( 404 );
		header( 'Content-Type: application/xml; charset=UTF-8' );
		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo StylesheetManager::get_sitemap_stylesheet_reference();
		echo '<!-- ' . esc_xml( $message ) . ' -->' . "\n";
		echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"/>';
		exit;
	}
}

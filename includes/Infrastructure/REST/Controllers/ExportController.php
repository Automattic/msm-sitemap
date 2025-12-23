<?php
/**
 * Export REST Controller
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\REST\Controllers
 */

declare(strict_types=1);

namespace Automattic\MSM_Sitemap\Infrastructure\REST\Controllers;

use Automattic\MSM_Sitemap\Application\Services\SitemapExportService;
use Automattic\MSM_Sitemap\Infrastructure\REST\Traits\RestControllerTrait;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * REST controller for sitemap export.
 */
class ExportController {

	use RestControllerTrait;

	/**
	 * The export service.
	 *
	 * @var SitemapExportService
	 */
	private SitemapExportService $export_service;

	/**
	 * Constructor.
	 *
	 * @param SitemapExportService $export_service The export service.
	 */
	public function __construct( SitemapExportService $export_service ) {
		$this->export_service = $export_service;
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/export',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'export_sitemaps' ),
					'permission_callback' => array( $this, 'check_manage_options_permission' ),
					'args'                => $this->get_export_params(),
				),
			)
		);
	}

	/**
	 * Get export parameters.
	 *
	 * @return array Parameters for export endpoint.
	 */
	private function get_export_params(): array {
		return array(
			'format'    => array(
				'default'           => 'json',
				'sanitize_callback' => 'sanitize_text_field',
				'enum'              => array( 'json', 'csv', 'xml' ),
			),
			'date_from' => array(
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => array( $this, 'validate_date_format' ),
			),
			'date_to'   => array(
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => array( $this, 'validate_date_format' ),
			),
		);
	}

	/**
	 * Export sitemap data.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error The response object.
	 */
	public function export_sitemaps( WP_REST_Request $request ) {
		$format    = $request->get_param( 'format' );
		$date_from = $request->get_param( 'date_from' );
		$date_to   = $request->get_param( 'date_to' );

		$date_queries = null;
		if ( ! empty( $date_from ) && ! empty( $date_to ) ) {
			$date_queries = array(
				array(
					'year'  => (int) substr( $date_from, 0, 4 ),
					'month' => (int) substr( $date_from, 5, 2 ),
					'day'   => (int) substr( $date_from, 8, 2 ),
				),
				array(
					'year'  => (int) substr( $date_to, 0, 4 ),
					'month' => (int) substr( $date_to, 5, 2 ),
					'day'   => (int) substr( $date_to, 8, 2 ),
				),
			);
		}

		$sitemaps = $this->export_service->get_sitemaps_for_export( $date_queries );

		if ( empty( $sitemaps ) ) {
			return new WP_Error(
				'rest_not_found',
				__( 'No sitemaps found to export.', 'msm-sitemap' ),
				array( 'status' => 404 )
			);
		}

		switch ( $format ) {
			case 'json':
				$response_data = $sitemaps;
				$content_type  = 'application/json';
				break;

			case 'xml':
				$combined_xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
				$combined_xml .= '<sitemaps>' . "\n";
				foreach ( $sitemaps as $sitemap ) {
					$combined_xml .= '<sitemap>' . "\n";
					$combined_xml .= '<date>' . esc_html( $sitemap['date'] ) . '</date>' . "\n";
					$combined_xml .= '<filename>' . esc_html( $sitemap['filename'] ) . '</filename>' . "\n";
					$combined_xml .= '<content><![CDATA[' . $sitemap['xml_content'] . ']]></content>' . "\n";
					$combined_xml .= '</sitemap>' . "\n";
				}
				$combined_xml .= '</sitemaps>';
				$response_data = $combined_xml;
				$content_type  = 'application/xml';
				break;

			case 'csv':
				$csv_data   = array();
				$csv_data[] = array( 'Date', 'Filename', 'URL Count' );
				foreach ( $sitemaps as $sitemap ) {
					$url_count = 0;
					if ( ! empty( $sitemap['xml_content'] ) ) {
						$dom = new \DOMDocument();
						@$dom->loadXML( $sitemap['xml_content'] );
						$urls      = $dom->getElementsByTagName( 'url' );
						$url_count = $urls->length;
					}
					$csv_data[] = array( $sitemap['date'], $sitemap['filename'], $url_count );
				}
				$response_data = $this->array_to_csv( $csv_data );
				$content_type  = 'text/csv';
				break;

			default:
				return new WP_Error(
					'rest_bad_request',
					__( 'Invalid export format specified.', 'msm-sitemap' ),
					array( 'status' => 400 )
				);
		}

		$response = rest_ensure_response( $response_data );
		$response->header( 'Content-Type', $content_type );

		return $response;
	}

	/**
	 * Convert array to CSV string.
	 *
	 * @param array $data Array of arrays to convert to CSV.
	 * @return string CSV formatted string.
	 */
	private function array_to_csv( array $data ): string {
		$output = fopen( 'php://temp', 'r+' );
		foreach ( $data as $row ) {
			fputcsv( $output, $row, ',', '"', '\\' );
		}
		rewind( $output );
		$csv = stream_get_contents( $output );
		fclose( $output );
		return $csv;
	}
}

<?php
/**
 * SitemapExportService
 *
 * @package Automattic\MSM_Sitemap\Application\Services
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Application\Services;

use Automattic\MSM_Sitemap\Domain\Contracts\SitemapRepositoryInterface;

/**
 * Service for handling sitemap export functionality.
 */
class SitemapExportService {

	/**
	 * The sitemap repository.
	 *
	 * @var SitemapRepositoryInterface
	 */
	private SitemapRepositoryInterface $repository;

	/**
	 * The query service.
	 *
	 * @var SitemapQueryService
	 */
	private SitemapQueryService $query_service;

	/**
	 * Constructor.
	 *
	 * @param SitemapRepositoryInterface $repository The sitemap repository.
	 * @param SitemapQueryService $query_service The query service.
	 */
	public function __construct( SitemapRepositoryInterface $repository, SitemapQueryService $query_service ) {
		$this->repository    = $repository;
		$this->query_service = $query_service;
	}

	/**
	 * Export sitemaps to a directory.
	 *
	 * @param string $output_dir The output directory path.
	 * @param array|null $date_queries Optional date queries to filter by.
	 * @param bool $pretty Whether to pretty-print the XML.
	 * @return array Export result with count and errors.
	 */
	public function export_sitemaps( string $output_dir, ?array $date_queries = null, bool $pretty = false ): array {
		// Check for halt signal
		if ( $this->is_stopped() ) {
			return array(
				'success' => false,
				'count'   => 0,
				'message' => __( 'Sitemap export was stopped by user request.', 'msm-sitemap' ),
				'errors'  => array(),
			);
		}

		// Validate and create output directory
		$abs_output = $this->resolve_output_path( $output_dir );
		
		$dir_result = $this->ensure_output_directory( $abs_output );
		if ( ! $dir_result['success'] ) {
			return $dir_result;
		}

		// Get sitemaps for export
		$sitemaps = $this->get_sitemaps_for_export( $date_queries );
		
		if ( empty( $sitemaps ) ) {
			return array(
				'success' => true,
				'count'   => 0,
				'message' => __( 'No sitemaps found to export.', 'msm-sitemap' ),
				'errors'  => array(),
			);
		}

		return $this->write_sitemap_files( $sitemaps, $abs_output, $pretty );
	}

	/**
	 * Get sitemap data for export, filtered by date queries.
	 *
	 * @param array|null $date_queries Optional date queries to filter by.
	 * @return array Array of sitemap data for export.
	 */
	public function get_sitemaps_for_export( ?array $date_queries = null ): array {
		$sitemap_dates = $this->repository->get_all_sitemap_dates();
		
		// If no date queries, return all sitemaps
		if ( empty( $date_queries ) ) {
			return $this->build_export_data_from_dates( $sitemap_dates );
		}
		
		// Filter dates based on queries
		$filtered_dates = array();
		foreach ( $date_queries as $query ) {
			$matching_dates = $this->query_service->get_matching_dates_for_query( $query, $sitemap_dates );
			$filtered_dates = array_merge( $filtered_dates, $matching_dates );
		}
		
		return $this->build_export_data_from_dates( array_unique( $filtered_dates ) );
	}

	/**
	 * Format XML content for export with optional pretty printing.
	 *
	 * @param string $xml_content The XML content to format.
	 * @param bool $pretty Whether to pretty-print the XML.
	 * @return string The formatted XML content.
	 */
	public function format_xml_for_export( string $xml_content, bool $pretty = false ): string {
		if ( ! $pretty ) {
			return $xml_content;
		}

		$dom                     = new \DOMDocument( '1.0', 'UTF-8' );
		$dom->preserveWhiteSpace = false;
		$dom->formatOutput       = true;
		
		if ( @$dom->loadXML( $xml_content ) ) {
			return $dom->saveXML();
		}
		
		// Return original content if formatting fails
		return $xml_content;
	}

	/**
	 * Resolve the absolute output path.
	 *
	 * @param string $output_dir The output directory path.
	 * @return string The absolute output path.
	 */
	private function resolve_output_path( string $output_dir ): string {
		if ( ! preg_match( '/^\//', $output_dir ) ) {
			return realpath( getcwd() ) . DIRECTORY_SEPARATOR . $output_dir;
		}
		return $output_dir;
	}

	/**
	 * Ensure the output directory exists.
	 *
	 * @param string $abs_output The absolute output path.
	 * @return array Result with success status and optional error message.
	 */
	private function ensure_output_directory( string $abs_output ): array {
		if ( ! is_dir( $abs_output ) ) {
			if ( ! mkdir( $abs_output, 0777, true ) ) {
				return array(
					'success' => false,
					'count'   => 0,
					'message' => sprintf(
						/* translators: %s is the path to the export directory. */
						__( 'Failed to create export directory: %s', 'msm-sitemap' ),
						$abs_output
					),
					'errors'  => array(),
				);
			}
		}

		return array( 'success' => true );
	}

	/**
	 * Write sitemap files to the output directory.
	 *
	 * @param array $sitemaps Array of sitemap data.
	 * @param string $abs_output The absolute output path.
	 * @param bool $pretty Whether to pretty-print the XML.
	 * @return array Export result with count and errors.
	 */
	private function write_sitemap_files( array $sitemaps, string $abs_output, bool $pretty ): array {
		$count  = 0;
		$errors = array();

		foreach ( $sitemaps as $sitemap ) {
			// Check for halt signal
			if ( $this->is_stopped() ) {
				return array(
					'success' => false,
					'count'   => $count,
					'message' => __( 'Export was stopped by user request.', 'msm-sitemap' ),
					'errors'  => $errors,
				);
			}

			$xml_content = $sitemap['xml_content'];
			if ( empty( $xml_content ) ) {
				continue;
			}

			// Format XML if pretty printing is requested
			$formatted_xml = $this->format_xml_for_export( $xml_content, $pretty );

			// Write file
			$filename = rtrim( $abs_output, '/' ) . '/' . $sitemap['filename'] . '.xml';
			if ( file_put_contents( $filename, $formatted_xml ) === false ) {
				$errors[] = sprintf(
					/* translators: %s is the path to the exported sitemap. */
					__( 'Failed to write file: %s', 'msm-sitemap' ),
					$filename
				);
				continue;
			}

			++$count;
		}

		$dir = realpath( $abs_output );
		if ( ! $dir ) {
			$dir = $abs_output;
		}

		$message = sprintf(
			/* translators: %1$d is the number of sitemaps exported, %2$s is the path to the exported sitemaps. */
			_n( 'Exported %1$d sitemap to %2$s.', 'Exported %1$d sitemaps to %2$s.', $count, 'msm-sitemap' ),
			$count,
			$dir
		);

		return array(
			'success'    => true,
			'count'      => $count,
			'message'    => $message,
			'errors'     => $errors,
			'output_dir' => $dir,
		);
	}

	/**
	 * Build export data from sitemap dates.
	 *
	 * @param array $dates Array of sitemap dates.
	 * @return array Array of sitemap data for export.
	 */
	private function build_export_data_from_dates( array $dates ): array {
		$export_data = array();

		foreach ( $dates as $date ) {
			$post_id = $this->repository->find_by_date( $date );
			if ( ! $post_id ) {
				continue;
			}

			$post = get_post( $post_id );
			if ( ! $post ) {
				continue;
			}

			$xml_content = get_post_meta( $post_id, 'msm_sitemap_xml', true );
			if ( empty( $xml_content ) ) {
				continue;
			}

			$export_data[] = array(
				'post_id'     => $post_id,
				'filename'    => $post->post_name,
				'xml_content' => $xml_content,
				'date'        => $date,
			);
		}

		return $export_data;
	}

	/**
	 * Check if sitemap processing has been stopped by user request.
	 *
	 * @return bool True if stopped, false otherwise.
	 */
	private function is_stopped(): bool {
		return (bool) get_option( 'msm_stop_processing' );
	}
}

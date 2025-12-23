<?php
/**
 * List Command
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\CLI\Commands
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Infrastructure\CLI\Commands;

use Automattic\MSM_Sitemap\Application\Services\SitemapService;
use Automattic\MSM_Sitemap\Infrastructure\CLI\Traits\DateQueryTrait;
use WP_CLI;
use function WP_CLI\Utils\format_items;

/**
 * List sitemaps.
 */
class ListCommand {

	use DateQueryTrait;

	/**
	 * The sitemap service.
	 *
	 * @var SitemapService
	 */
	private SitemapService $service;

	/**
	 * Constructor.
	 *
	 * @param SitemapService $service The sitemap service.
	 */
	public function __construct( SitemapService $service ) {
		$this->service = $service;
	}

	/**
	 * List sitemaps.
	 *
	 * ## OPTIONS
	 *
	 * [--date=<date>]
	 * : List sitemaps for a specific year (YYYY), month (YYYY-MM), or day (YYYY-MM-DD).
	 *
	 * [--all]
	 * : List all sitemaps.
	 *
	 * [--fields=<fields>]
	 * : Comma-separated list of fields to display.
	 *
	 * [--format=<format>]
	 * : Output format: table, json, or csv. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     # List all sitemaps.
	 *     $ wp msm-sitemap list
	 *
	 *     # List sitemaps for a specific month.
	 *     $ wp msm-sitemap list --date=2024-07
	 *
	 *     # List all sitemaps in JSON format.
	 *     $ wp msm-sitemap list --all --format=json
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$fields = isset( $assoc_args['fields'] ) ? explode( ',', $assoc_args['fields'] ) : array( 'id', 'date', 'url_count', 'status' );
		$format = $assoc_args['format'] ?? 'table';
		$all    = ! empty( $assoc_args['all'] );
		$date   = $assoc_args['date'] ?? null;

		// Parse date queries
		$date_queries = $this->parse_date_query( $date, $all );

		// Get sitemap data from service
		$sitemap_data = $this->service->get_sitemap_list_data( $date_queries );

		if ( empty( $sitemap_data ) ) {
			WP_CLI::log( __( 'No sitemaps found.', 'msm-sitemap' ) );
			return;
		}

		// Format the data for CLI output
		$items = array();
		foreach ( $sitemap_data as $sitemap ) {
			$row = array();
			foreach ( $fields as $field ) {
				switch ( trim( $field ) ) {
					case 'id':
						$row['id'] = $sitemap['id'];
						break;
					case 'date':
						$row['date'] = $sitemap['date'];
						break;
					case 'url_count':
						$row['url_count'] = $sitemap['url_count'];
						break;
					case 'status':
						$row['status'] = $sitemap['status'];
						break;
					case 'sitemap_url':
						$row['sitemap_url'] = $sitemap['sitemap_url'];
						break;
				}
			}
			// Always add sitemap_url as last column if not already present
			if ( ! isset( $row['sitemap_url'] ) ) {
				$row['sitemap_url'] = $sitemap['sitemap_url'];
			}
			$items[] = $row;
		}

		// Always add sitemap_url to fields if not present
		if ( ! in_array( 'sitemap_url', $fields, true ) ) {
			$fields[] = 'sitemap_url';
		}

		format_items( $format, $items, $fields );
	}
}

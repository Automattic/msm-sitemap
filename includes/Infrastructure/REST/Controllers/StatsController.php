<?php
/**
 * Stats REST Controller
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\REST\Controllers
 */

declare(strict_types=1);

namespace Automattic\MSM_Sitemap\Infrastructure\REST\Controllers;

use Automattic\MSM_Sitemap\Application\Services\AuthorSitemapService;
use Automattic\MSM_Sitemap\Application\Services\PageSitemapService;
use Automattic\MSM_Sitemap\Application\Services\SitemapStatsService;
use Automattic\MSM_Sitemap\Application\Services\TaxonomySitemapService;
use Automattic\MSM_Sitemap\Infrastructure\REST\Traits\RestControllerTrait;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST controller for sitemap statistics.
 */
class StatsController {

	use RestControllerTrait;

	/**
	 * The stats service.
	 *
	 * @var SitemapStatsService
	 */
	private SitemapStatsService $stats_service;

	/**
	 * The taxonomy sitemap service.
	 *
	 * @var TaxonomySitemapService
	 */
	private TaxonomySitemapService $taxonomy_sitemap_service;

	/**
	 * The author sitemap service.
	 *
	 * @var AuthorSitemapService
	 */
	private AuthorSitemapService $author_sitemap_service;

	/**
	 * The page sitemap service.
	 *
	 * @var PageSitemapService
	 */
	private PageSitemapService $page_sitemap_service;

	/**
	 * Constructor.
	 *
	 * @param SitemapStatsService    $stats_service            The stats service.
	 * @param TaxonomySitemapService $taxonomy_sitemap_service The taxonomy sitemap service.
	 * @param AuthorSitemapService   $author_sitemap_service   The author sitemap service.
	 * @param PageSitemapService     $page_sitemap_service     The page sitemap service.
	 */
	public function __construct(
		SitemapStatsService $stats_service,
		TaxonomySitemapService $taxonomy_sitemap_service,
		AuthorSitemapService $author_sitemap_service,
		PageSitemapService $page_sitemap_service
	) {
		$this->stats_service            = $stats_service;
		$this->taxonomy_sitemap_service = $taxonomy_sitemap_service;
		$this->author_sitemap_service   = $author_sitemap_service;
		$this->page_sitemap_service     = $page_sitemap_service;
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/stats',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_stats' ),
					'permission_callback' => array( $this, 'check_manage_options_permission' ),
					'args'                => $this->get_stats_params(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/recent-urls',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_recent_urls' ),
					'permission_callback' => array( $this, 'check_manage_options_permission' ),
					'args'                => $this->get_recent_urls_params(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/sitemap-summary',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_sitemap_summary' ),
					'permission_callback' => array( $this, 'check_manage_options_permission' ),
				),
			)
		);
	}

	/**
	 * Get stats parameters.
	 *
	 * @return array Parameters for stats endpoint.
	 */
	private function get_stats_params(): array {
		return array(
			'date_range' => array(
				'default'           => 'all',
				'sanitize_callback' => 'sanitize_text_field',
				'enum'              => array( 'all', '7', '30', '90', '180', '365', 'custom' ),
			),
			'start_date' => array(
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => array( $this, 'validate_date_format' ),
			),
			'end_date'   => array(
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => array( $this, 'validate_date_format' ),
			),
		);
	}

	/**
	 * Get recent URLs parameters.
	 *
	 * @return array Parameters for recent URLs endpoint.
	 */
	private function get_recent_urls_params(): array {
		return array(
			'days' => array(
				'type'        => 'integer',
				'default'     => 7,
				'minimum'     => 1,
				'maximum'     => 365,
				'description' => 'Number of days to show',
			),
		);
	}

	/**
	 * Get statistics.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response The response object.
	 */
	public function get_stats( WP_REST_Request $request ): WP_REST_Response {
		$cache_key = 'msm_sitemap_rest_stats_' . md5( serialize( $request->get_params() ) );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return rest_ensure_response( $cached );
		}

		$date_range = $request->get_param( 'date_range' );
		$start_date = $request->get_param( 'start_date' );
		$end_date   = $request->get_param( 'end_date' );

		$stats = $this->stats_service->get_comprehensive_stats( $date_range, $start_date, $end_date );

		set_transient( $cache_key, $stats, 15 * MINUTE_IN_SECONDS );

		return rest_ensure_response( $stats );
	}

	/**
	 * Get recent URL counts.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response The response object.
	 */
	public function get_recent_urls( WP_REST_Request $request ): WP_REST_Response {
		$days = $request->get_param( 'days' ) ?? 7;

		$recent_counts = $this->stats_service->get_recent_url_counts( $days );

		return rest_ensure_response( $recent_counts );
	}

	/**
	 * Get sitemap summary counts for the status display.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response The response object.
	 */
	public function get_sitemap_summary( WP_REST_Request $request ): WP_REST_Response {
		$comprehensive_stats = $this->stats_service->get_comprehensive_stats();

		$post_sitemap_count = $comprehensive_stats['overview']['total_sitemaps'] ?? 0;
		$taxonomy_entries   = $this->taxonomy_sitemap_service->is_enabled()
			? count( $this->taxonomy_sitemap_service->get_sitemap_index_entries() )
			: 0;
		$author_entries     = $this->author_sitemap_service->is_enabled()
			? count( $this->author_sitemap_service->get_sitemap_index_entries() )
			: 0;
		$page_entries       = $this->page_sitemap_service->is_enabled()
			? count( $this->page_sitemap_service->get_sitemap_index_entries() )
			: 0;

		// Build the summary text parts
		$parts = array();
		if ( $post_sitemap_count > 0 ) {
			$parts[] = sprintf(
				/* translators: %d: number of daily sitemaps */
				_n( '%d daily sitemap', '%d daily sitemaps', $post_sitemap_count, 'msm-sitemap' ),
				$post_sitemap_count
			);
		}
		if ( $taxonomy_entries > 0 ) {
			$parts[] = sprintf(
				/* translators: %d: number of taxonomy sitemaps */
				_n( '%d taxonomy', '%d taxonomies', $taxonomy_entries, 'msm-sitemap' ),
				$taxonomy_entries
			);
		}
		if ( $author_entries > 0 ) {
			$parts[] = sprintf(
				/* translators: %d: number of author sitemaps */
				_n( '%d author', '%d authors', $author_entries, 'msm-sitemap' ),
				$author_entries
			);
		}
		if ( $page_entries > 0 ) {
			$parts[] = sprintf(
				/* translators: %d: number of page sitemaps */
				_n( '%d page', '%d pages', $page_entries, 'msm-sitemap' ),
				$page_entries
			);
		}

		$summary_text = implode( ', ', $parts );
		$has_any      = $post_sitemap_count > 0 || $taxonomy_entries > 0 || $author_entries > 0 || $page_entries > 0;

		return rest_ensure_response(
			array(
				'post_sitemaps'    => $post_sitemap_count,
				'taxonomy_entries' => $taxonomy_entries,
				'author_entries'   => $author_entries,
				'page_entries'     => $page_entries,
				'summary_text'     => $summary_text,
				'has_any'          => $has_any,
			)
		);
	}
}

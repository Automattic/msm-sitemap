<?php
/**
 * MSM Sitemap CLI
 *
 * @package Automattic\MSM_Sitemap
 */

declare(strict_types=1);

namespace Automattic\MSM_Sitemap\Infrastructure\CLI;

use Automattic\MSM_Sitemap\Application\Services\CronManagementService;
use Automattic\MSM_Sitemap\Application\Services\SitemapService;
use Automattic\MSM_Sitemap\Application\Services\SitemapStatsService;
use Automattic\MSM_Sitemap\Application\Services\SitemapValidationService;
use Automattic\MSM_Sitemap\Application\Services\SitemapExportService;
use Automattic\MSM_Sitemap\Application\Services\SettingsService;
use Automattic\MSM_Sitemap\Domain\Contracts\SitemapRepositoryInterface;
use Automattic\MSM_Sitemap\Application\UseCases\GenerateSitemapUseCase;
use Automattic\MSM_Sitemap\Application\Commands\GenerateSitemapCommand;
use WP_CLI;
use WP_CLI_Command;
use function WP_CLI\Utils\format_items;
use function Automattic\MSM_Sitemap\Infrastructure\DI\msm_sitemap_container;

/**
 * Class CLICommand
 *
 * @package Automattic\MSM_Sitemap
 */
class CLICommand extends WP_CLI_Command {

	/**
	 * The sitemap service.
	 *
	 * @var SitemapService
	 */
	private SitemapService $sitemap_service;

	/**
	 * The stats service.
	 *
	 * @var SitemapStatsService
	 */
	private SitemapStatsService $stats_service;

	/**
	 * The validation service.
	 *
	 * @var SitemapValidationService
	 */
	private SitemapValidationService $validation_service;

	/**
	 * The export service.
	 *
	 * @var SitemapExportService
	 */
	private SitemapExportService $export_service;

	/**
	 * The cron management service.
	 *
	 * @var CronManagementService
	 */
	private CronManagementService $cron_management;

	/**
	 * The settings service.
	 *
	 * @var SettingsService
	 */
	private SettingsService $settings_service;

	/**
	 * The generate sitemap use case.
	 *
	 * @var GenerateSitemapUseCase
	 */
	private GenerateSitemapUseCase $generate_use_case;

	/**
	 * Constructor.
	 *
	 * @param SitemapService $sitemap_service The sitemap service.
	 * @param SitemapRepositoryInterface $repository The sitemap repository.
	 * @param SitemapStatsService $stats_service The stats service.
	 * @param SitemapValidationService $validation_service The validation service.
	 * @param SitemapExportService $export_service The export service.
	 * @param CronManagementService $cron_management The cron management service.
	 * @param SettingsService $settings_service The settings service.
	 * @param GenerateSitemapUseCase $generate_use_case The generate sitemap use case.
	 */
	public function __construct( 
		SitemapService $sitemap_service,
		SitemapRepositoryInterface $repository,
		SitemapStatsService $stats_service,
		SitemapValidationService $validation_service,
		SitemapExportService $export_service,
		CronManagementService $cron_management,
		SettingsService $settings_service,
		GenerateSitemapUseCase $generate_use_case
	) {
		$this->sitemap_service    = $sitemap_service;
		$this->stats_service      = $stats_service;
		$this->validation_service = $validation_service;
		$this->export_service     = $export_service;
		$this->cron_management    = $cron_management;
		$this->settings_service   = $settings_service;
		$this->generate_use_case  = $generate_use_case;
	}

	/**
	 * Create a CLI command instance with proper dependency injection.
	 *
	 * @return self
	 */
	public static function create(): self {
		$container = msm_sitemap_container();
		
		return new self(
			$container->get( SitemapService::class ),
			$container->get( SitemapRepositoryInterface::class ),
			$container->get( SitemapStatsService::class ),
			$container->get( SitemapValidationService::class ),
			$container->get( SitemapExportService::class ),
			$container->get( CronManagementService::class ),
			$container->get( SettingsService::class ),
			$container->get( GenerateSitemapUseCase::class )
		);
	}

	/**
	 * Generate sitemaps for the specified date or all dates.
	 *
	 * ## OPTIONS
	 *
	 * [--date=<date>]
	 * : Generate sitemaps for a specific year (YYYY), month (YYYY-MM), or day (YYYY-MM-DD).
	 * [--all]
	 * : Generate sitemaps for all years with posts.
	 * [--force]
	 * : Force regeneration even if sitemap already exists.
	 * [--quiet]
	 * : Suppress all output except errors.
	 *
	 * ## EXAMPLES
	 *
	 *     wp msm-sitemap generate --date=2024-07
	 *     wp msm-sitemap generate --all
	 *
	 * @subcommand generate
	 */
	public function generate( $args, $assoc_args ) {
		$quiet = ! empty( $assoc_args['quiet'] );
		$force = ! empty( $assoc_args['force'] );
		$all   = ! empty( $assoc_args['all'] );
		$date  = $assoc_args['date'] ?? null;

		// Create command with parameters
		$command = new GenerateSitemapCommand(
			$date,
			array(), // Will be handled by the use case
			$force,
			$all
		);

		// Execute the use case
		$result = $this->generate_use_case->execute( $command );

		// CLI-specific output handling
		if ( ! $quiet ) {
			if ( $result->is_success() ) {
				WP_CLI::success( $result->get_message() );
			} else {
				WP_CLI::error( $result->get_message() );
			}
		}
	}

	/**
	 * Delete sitemaps for the specified date or all dates.
	 *
	 * ## OPTIONS
	 *
	 * [--date=<date>]
	 * : Delete sitemaps for a specific year (YYYY), month (YYYY-MM), or day (YYYY-MM-DD).
	 * [--all]
	 * : Delete all sitemaps. Requires confirmation.
	 * [--quiet]
	 * : Suppress all output except errors.
	 * [--yes]
	 * : Answer yes to any confirmation prompts.
	 *
	 * ## SAFETY
	 *
	 * You must specify either --date or --all. If --all is used, or --date matches multiple sitemaps, you must confirm deletion (or use --yes).
	 *
	 * ## EXAMPLES
	 *
	 *     wp msm-sitemap delete --date=2024-07
	 *     wp msm-sitemap delete --all --yes
	 *
	 * @subcommand delete
	 */
	public function delete( $args, $assoc_args ) {
		$quiet = ! empty( $assoc_args['quiet'] );
		$all   = ! empty( $assoc_args['all'] );
		$date  = $assoc_args['date'] ?? null;
		$yes   = ! empty( $assoc_args['yes'] );

		if ( ! $all && empty( $date ) ) {
			WP_CLI::error( __( 'You must specify either --date or --all to delete sitemaps.', 'msm-sitemap' ) );
		}

		if ( $all ) {
			// Confirm bulk delete
			if ( ! $yes ) {
				WP_CLI::confirm( 'Are you sure you want to delete ALL sitemaps?', $assoc_args );
			}
			$result = $this->sitemap_service->delete_all();
		} elseif ( $date ) {
			$date_queries = $this->parse_date_query( $date, false );
			
			// Count how many sitemaps would be deleted for confirmation
			$to_delete_count = $this->sitemap_service->count_deletable_sitemaps( $date_queries );
			
			if ( $to_delete_count > 1 && ! $yes ) {
				WP_CLI::confirm( sprintf( 'Are you sure you want to delete %d sitemaps for the specified date?', $to_delete_count ), $assoc_args );
			}
			
			$result = $this->sitemap_service->delete_for_date_queries( $date_queries );
		}

		if ( ! $quiet ) {
			if ( $result->is_success() ) {
				WP_CLI::success( $result->get_message() );
			} else {
				WP_CLI::log( $result->get_message() );
			}
		}
	}

	/**
	 * List sitemaps.
	 *
	 * ## OPTIONS
	 *
	 * [--date=<date>]
	 * : List sitemaps for a specific year (YYYY), month (YYYY-MM), or day (YYYY-MM-DD).
	 * [--all]
	 * : List all sitemaps.
	 * [--fields=<fields>]
	 * : Comma-separated list of fields to display.
	 * [--format=<format>]
	 * : Output format: table, json, or csv. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp msm-sitemap list
	 *     wp msm-sitemap list --date=2024-07
	 *     wp msm-sitemap list --all --format=json
	 *
	 * @subcommand list
	 */
	public function list( $args, $assoc_args ) {
		$fields = isset( $assoc_args['fields'] ) ? explode( ',', $assoc_args['fields'] ) : array( 'id', 'date', 'url_count', 'status' );
		$format = $assoc_args['format'] ?? 'table';
		$all    = ! empty( $assoc_args['all'] );
		$date   = $assoc_args['date'] ?? null;
		
		// Parse date queries
		$date_queries = $this->parse_date_query( $date, $all );
		
		// Get sitemap data from service
		$sitemap_data = $this->sitemap_service->get_sitemap_list_data( $date_queries );
		
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

	/**
	 * Get details for a sitemap by ID or date.
	 *
	 * ## OPTIONS
	 *
	 * <id|date>
	 * : The sitemap post ID or date (YYYY-MM-DD, YYYY-MM, YYYY).
	 * [--format=<format>]
	 * : Output format: table, json, or csv. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp msm-sitemap get 123
	 *     wp msm-sitemap get 2024-07-10 --format=json
	 *
	 * @subcommand get
	 */
	public function get( $args, $assoc_args ) {
		if ( empty( $args ) ) {
			WP_CLI::error( __( 'No ID or date provided.', 'msm-sitemap' ) );
		}
		
		$input  = $args[0] ?? null;
		$format = $assoc_args['format'] ?? 'table';
		
		$items = array();
		
		if ( is_numeric( $input ) ) {
			// Get by ID
			$sitemap_data = $this->sitemap_service->get_sitemap_by_id( (int) $input );
			if ( ! $sitemap_data ) {
				WP_CLI::error( __( 'Sitemap not found for that ID.', 'msm-sitemap' ) );
			}
			$items[] = $sitemap_data;
		} else {
			// Get by date
			$sitemap_data = $this->sitemap_service->get_sitemaps_by_date( $input );
			if ( empty( $sitemap_data ) ) {
				WP_CLI::error( __( 'No sitemaps found for that date.', 'msm-sitemap' ) );
			}
			if ( count( $sitemap_data ) > 1 && 'json' !== $format ) {
				WP_CLI::warning( __( 'Multiple sitemaps found for that date. Showing all.', 'msm-sitemap' ) );
			}
			$items = $sitemap_data;
		}
		
		format_items( $format, $items, array( 'id', 'date', 'url_count', 'status', 'last_modified', 'sitemap_url' ) );
	}

	/**
	 * Validate sitemap XML content and structure.
	 *
	 * ## OPTIONS
	 *
	 * [--date=<date>]
	 * : Validate sitemaps for a specific date (YYYY-MM-DD format).
	 *
	 * [--year=<year>]
	 * : Validate sitemaps for a specific year.
	 *
	 * [--month=<month>]
	 * : Validate sitemaps for a specific month (YYYY-MM format).
	 *
	 * ## EXAMPLES
	 *
	 *     wp msm-sitemap validate
	 *     wp msm-sitemap validate --date=2024-01-15
	 *     wp msm-sitemap validate --year=2024
	 *
	 * @subcommand validate
	 */
	public function validate( $args, $assoc_args ) {
		// Parse date queries
		$date_queries = $this->parse_date_query( $assoc_args['date'] ?? null, ! empty( $assoc_args['year'] ) || ! empty( $assoc_args['month'] ) );
		
		if ( ! empty( $assoc_args['year'] ) ) {
			$date_queries[] = array( 'year' => (int) $assoc_args['year'] );
		}
		
		if ( ! empty( $assoc_args['month'] ) ) {
			$parsed_month = $this->parse_date_query( $assoc_args['month'], false );
			if ( $parsed_month ) {
				$date_queries[] = $parsed_month;
			}
		}
		
		// Validate sitemaps using validation service directly
		$result = $this->validation_service->validate_sitemaps( $date_queries );
		
		if ( $result->is_success() ) {
			WP_CLI::log( $result->get_message() );
			
			// Output validation errors as warnings
			foreach ( $result->get_validation_errors() as $error ) {
				WP_CLI::warning( $error );
			}
		} elseif ( 'no_sitemaps_found' === $result->get_error_code() ) {
			// For validation, treat "no sitemaps found" as a log message, not an error
			WP_CLI::log( $result->get_message() );
		} else {
			WP_CLI::error( $result->get_message() );
		}
	}

	/**
	 * Export sitemaps for the specified date or all dates.
	 *
	 * ## OPTIONS
	 *
	 * [--date=<date>]
	 * : Export sitemaps for a specific year (YYYY), month (YYYY-MM), or day (YYYY-MM-DD).
	 * [--all]
	 * : Export all sitemaps.
	 * --output=<path>
	 * : Output directory or file path. (Required)
	 * [--pretty]
	 * : Pretty-print (indent) the exported XML for human readability.
	 *
	 * ## EXAMPLES
	 *
	 *     wp msm-sitemap export --all --output=export
	 *     wp msm-sitemap export --date=2024-07 --output=export --pretty
	 *
	 * @subcommand export
	 */
	public function export( $args, $assoc_args ) {
		if ( empty( $assoc_args['output'] ) ) {
			WP_CLI::error( __( 'You must specify an output directory with --output. Example: --output=/path/to/dir', 'msm-sitemap' ) );
		}
		
		$output       = $assoc_args['output'];
		$all          = ! empty( $assoc_args['all'] );
		$date         = $assoc_args['date'] ?? null;
		$pretty       = ! empty( $assoc_args['pretty'] );
		$date_queries = $this->parse_date_query( $date, $all );
		
		// Export sitemaps using export service directly
		$result = $this->export_service->export_sitemaps( $output, $date_queries, $pretty );
		
		if ( $result['success'] ) {
			WP_CLI::success( $result['message'] );
			
			// Output export errors as warnings
			foreach ( $result['errors'] as $error ) {
				WP_CLI::warning( $error );
			}
			
			// Show platform-specific open command if files were exported
			if ( $result['count'] > 0 && isset( $result['output_dir'] ) ) {
				$quoted_dir = '"' . $result['output_dir'] . '"';
				if ( strtoupper( substr( PHP_OS, 0, 3 ) ) === 'DAR' ) {
					/* translators: %s is the path to the exported sitemaps. */
					WP_CLI::log( sprintf( __( 'To view the files, run: open %s', 'msm-sitemap' ), $quoted_dir ) );
				} elseif ( strtoupper( substr( PHP_OS, 0, 3 ) ) === 'WIN' ) {
					/* translators: %s is the path to the exported sitemaps. */
					WP_CLI::log( sprintf( __( 'To view the files, run: start %s', 'msm-sitemap' ), $quoted_dir ) );
				} else {
					/* translators: %s is the path to the exported sitemaps. */
					WP_CLI::log( sprintf( __( 'To view the files, run: xdg-open %s', 'msm-sitemap' ), $quoted_dir ) );
				}
			}
		} else {
			WP_CLI::error( $result['message'] );
		}
	}

	/**
	 * Recount URLs in all sitemaps and update counts.
	 *
	 * ## OPTIONS
	 *
	 * [--full]
	 * : Perform a full recount by parsing XML (slower but more accurate).
	 *
	 * ## EXAMPLES
	 *
	 *     wp msm-sitemap recount
	 *     wp msm-sitemap recount --full
	 *
	 * @subcommand recount
	 */
	public function recount( $args, $assoc_args ) {
		$full_recount = ! empty( $assoc_args['full'] );
		
		// Recount URLs using service
		$result = $this->sitemap_service->recount_urls( $full_recount );
		
		if ( $result->is_success() ) {
			WP_CLI::log( $result->get_message() );
			
			// Output recount errors as warnings
			foreach ( $result->get_recount_errors() as $error ) {
				WP_CLI::warning( $error );
			}
		} else {
			WP_CLI::log( $result->get_message() );
		}
	}

	/**
	 * Show sitemap statistics (total, most recent, etc).
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format: table, json, or csv. Default: table.
	 * [--detailed]
	 * : Show detailed statistics including timeline, coverage, and storage info.
	 * [--section=<section>]
	 * : Show only a specific section: overview, timeline, url_counts, performance, coverage, storage.
	 *
	 * ## EXAMPLES
	 *
	 *     wp msm-sitemap stats --format=json
	 *     wp msm-sitemap stats --detailed --format=table
	 *     wp msm-sitemap stats --section=coverage --format=json
	 *
	 * @subcommand stats
	 */
	public function stats( $args, $assoc_args ) {
		$detailed = ! empty( $assoc_args['detailed'] );
		$section  = $assoc_args['section'] ?? null;
		$format   = $assoc_args['format'] ?? 'table';

		if ( $detailed || $section ) {
			// Use comprehensive stats service
			$stats = $this->stats_service->get_comprehensive_stats();
			
			if ( $section ) {
				if ( ! isset( $stats[ $section ] ) ) {
					WP_CLI::error(
						sprintf(
						/* translators: 1: Unknown section name, 2: Comma-separated list of available sections */
							__( 'Unknown section: %1$s. Available sections: %2$s', 'msm-sitemap' ),
							$section,
							implode( ', ', array_keys( $stats ) )
						) 
					);
				}
				$stats = $stats[ $section ];
			}
			
			// For detailed output, use JSON format for better readability
			if ( $detailed && 'table' === $format ) {
				$format = 'json';
			}
			
			if ( 'json' === $format ) {
				WP_CLI::log( json_encode( $stats, JSON_PRETTY_PRINT ) );
			} else {
				// For table format, show overview by default
				$overview_stats = $stats['overview'] ?? $stats;
				$items          = array( $overview_stats );
				$fields         = array_keys( $overview_stats );
				format_items( $format, $items, $fields );
			}
		} else {
			// Use basic stats from stats service for backward compatibility
			$comprehensive_stats = $this->stats_service->get_comprehensive_stats();
			$stats               = array(
				'total'       => $comprehensive_stats['overview']['total_sitemaps'],
				'most_recent' => $comprehensive_stats['overview']['most_recent']['date'] ? $comprehensive_stats['overview']['most_recent']['date'] . ' (ID ' . $comprehensive_stats['overview']['most_recent']['id'] . ')' : '',
				'created'     => $comprehensive_stats['overview']['most_recent']['created'],
			);
			
			$fields = array( 'total', 'most_recent', 'created' );
			$items  = array( $stats );
			format_items( $format, $items, $fields );
		}
	}

	/**
	 * Show recent URL counts for the last N days.
	 *
	 * ## OPTIONS
	 *
	 * [--days=<days>]
	 * : Number of days to show (default: 7).
	 * [--format=<format>]
	 * : Output format: table, json, or csv. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp msm-sitemap recent-urls --days=14 --format=json
	 *     wp msm-sitemap recent-urls --format=table
	 *
	 * @subcommand recent-urls
	 */
	public function recent_urls( $args, $assoc_args ) {
		$days   = (int) ( $assoc_args['days'] ?? 7 );
		$format = $assoc_args['format'] ?? 'table';

		$url_counts = $this->stats_service->get_recent_url_counts( $days );
		
		if ( empty( $url_counts ) ) {
			WP_CLI::log( __( 'No recent URL counts found.', 'msm-sitemap' ) );
			return;
		}

		// Convert to items for formatting
		$items = array();
		foreach ( $url_counts as $date => $count ) {
			$items[] = array(
				'date'      => $date,
				'url_count' => $count,
			);
		}

		$fields = array( 'date', 'url_count' );
		format_items( $format, $items, $fields );
	}

	/**
	 * Manage the sitemap cron functionality.
	 *
	 * ## SUBCOMMANDS
	 *
	 * [<command>]
	 * : Subcommand to run.
	 * ---
	 * default: status
	 * options:
	 *   - enable
	 *   - disable
	 *   - status
	 *   - reset
	 *   - frequency
	 * ---
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format for status command: table, json, or csv. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp msm-sitemap cron
	 *     wp msm-sitemap cron enable
	 *     wp msm-sitemap cron disable
	 *     wp msm-sitemap cron status --format=json
	 *     wp msm-sitemap cron reset
	 *     wp msm-sitemap cron frequency
	 *     wp msm-sitemap cron frequency hourly
	 *
	 * @when before_wp_load
	 */
	public function cron( $args, $assoc_args ) {
		// If no command provided, default to status
		if ( empty( $args ) ) {
			$this->cron_status( array(), $assoc_args );
			return;
		}
		
		$command = $args[0];
		
		switch ( $command ) {
			case 'enable':
				$this->cron_enable( array(), $assoc_args );
				break;
			case 'disable':
				$this->cron_disable( array(), $assoc_args );
				break;
			case 'status':
				$this->cron_status( array(), $assoc_args );
				break;
			case 'reset':
				$this->cron_reset( array(), $assoc_args );
				break;
			case 'frequency':
				// Remove 'frequency' from args and pass the remaining arguments
				array_shift( $args );
				$this->cron_frequency( $args, $assoc_args );
				break;
			default:
				WP_CLI::error(
					sprintf(
					/* translators: %s: Unknown subcommand name */
						__( 'Unknown subcommand: %s', 'msm-sitemap' ),
						$command
					) 
				);
		}
	}

	/**
	 * Enable the sitemap cron functionality.
	 */
	private function cron_enable( $args, $assoc_args ) {
		$result = $this->cron_management->enable_cron();
		
		if ( $result['success'] ) {
			WP_CLI::success( '✅ ' . $result['message'] );
		} elseif ( isset( $result['error_code'] ) && 'blog_not_public' === $result['error_code'] ) {
				WP_CLI::error( '❌ ' . $result['message'] );
		} else {
			WP_CLI::warning( '⚠️ ' . $result['message'] );
		}
	}

	/**
	 * Disable the sitemap cron functionality.
	 */
	private function cron_disable( $args, $assoc_args ) {
		$result = $this->cron_management->disable_cron();
		
		if ( $result['success'] ) {
			WP_CLI::success( '✅ ' . $result['message'] );
			
			// Check if cron was actually cleared
			$next_scheduled = wp_next_scheduled( 'msm_cron_update_sitemap' );
			if ( $next_scheduled ) {
				WP_CLI::warning( __( '⚠️ Warning: Cron event still scheduled. This may be a WordPress cron system delay.', 'msm-sitemap' ) );
			} else {
				WP_CLI::log( __( '✅ Cron events cleared successfully.', 'msm-sitemap' ) );
			}
		} else {
			WP_CLI::warning( '⚠️ ' . $result['message'] );
		}
	}

	/**
	 * Show the current status of the sitemap cron functionality.
	 */
	private function cron_status( $args, $assoc_args ) {
		$status = $this->cron_management->get_cron_status();
		
		$format = $assoc_args['format'] ?? 'table';
		$fields = array( 'enabled', 'next_scheduled', 'blog_public', 'generating', 'halted', 'current_frequency' );
		$items  = array(
			array(
				'enabled'           => $status['enabled'] ? 'Yes' : 'No',
				'next_scheduled'    => $status['next_scheduled'] ? date( 'Y-m-d H:i:s T', $status['next_scheduled'] ) : 'Not scheduled',
				'blog_public'       => $status['blog_public'] ? 'Yes' : 'No',
				'generating'        => $status['generating'] ? 'Yes' : 'No',
				'halted'            => $status['halted'] ? 'Yes' : 'No',
				'current_frequency' => $status['current_frequency'],
			),
		);
		format_items( $format, $items, $fields );
		return;
	}

	/**
	 * Reset the sitemap cron to a clean state (for testing).
	 */
	private function cron_reset( $args, $assoc_args ) {
		$result = $this->cron_management->reset_cron();
		
		if ( $result['success'] ) {
			WP_CLI::success( '✅ ' . $result['message'] );
			WP_CLI::log( __( '📝 This simulates a fresh install state.', 'msm-sitemap' ) );
		} else {
			WP_CLI::error( '❌ ' . $result['message'] );
		}
	}

	/**
	 * Manage the sitemap cron frequency.
	 *
	 * ## OPTIONS
	 *
	 * [<frequency>]
	 * : The frequency to set. If not provided, shows current frequency.
	 * ---
	 * options:
	 *   - 5min
	 *   - 10min
	 *   - 15min
	 *   - 30min
	 *   - hourly
	 *   - 2hourly
	 *   - 3hourly
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp msm-sitemap cron frequency
	 *     wp msm-sitemap cron frequency hourly
	 *     wp msm-sitemap cron frequency 15min
	 *
	 * @when before_wp_load
	 */
	private function cron_frequency( $args, $assoc_args ) {
		// If no frequency provided, show current frequency
		if ( empty( $args ) ) {
			$current_frequency = $this->cron_management->get_current_frequency();
			$valid_frequencies = $this->cron_management->get_valid_frequencies();
			
			WP_CLI::log(
				sprintf(
				/* translators: %s: Current frequency */
					__( 'Current cron frequency: %s', 'msm-sitemap' ),
					$current_frequency
				) 
			);
			WP_CLI::log( __( 'Valid frequencies:', 'msm-sitemap' ) );
			foreach ( $valid_frequencies as $frequency ) {
				WP_CLI::log( sprintf( '  - %s', $frequency ) );
			}
			return;
		}
		
		$frequency = $args[0];
		$result    = $this->cron_management->update_frequency( $frequency );
		
		if ( $result['success'] ) {
			WP_CLI::success( '✅ ' . $result['message'] );
		} else {
			WP_CLI::error( '❌ ' . $result['message'] );
		}
	}

	/**
	 * Manage sitemap options.
	 *
	 * ## SUBCOMMANDS
	 *
	 * [<command>]
	 * : Subcommand to run.
	 * ---
	 * default: list
	 * options:
	 *   - list
	 *   - get
	 *   - update
	 *   - delete
	 *   - reset
	 * ---
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format: table, json, csv, count, yaml. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp msm-sitemap options
	 *     wp msm-sitemap options list --format=json
	 *     wp msm-sitemap options get include_images
	 *     wp msm-sitemap options update featured_images 1
	 *     wp msm-sitemap options update content_images false
	 *     wp msm-sitemap options update max_images_per_sitemap 500
	 *     wp msm-sitemap options reset
	 *
	 * @when before_wp_load
	 */
	public function options( $args, $assoc_args ) {
		// If no command provided, default to list
		if ( empty( $args ) ) {
			$this->options_list( array(), $assoc_args );
			return;
		}
		
		$command = $args[0];
		
		switch ( $command ) {
			case 'list':
				$this->options_list( array(), $assoc_args );
				break;
			case 'get':
				$this->options_get( $args, $assoc_args );
				break;
			case 'update':
				$this->options_update( $args, $assoc_args );
				break;
			case 'delete':
				$this->options_delete( $args, $assoc_args );
				break;
			case 'reset':
				$this->options_reset( array(), $assoc_args );
				break;
			default:
				WP_CLI::error(
					sprintf(
					/* translators: %s: Unknown subcommand name */
						__( 'Unknown subcommand: %s', 'msm-sitemap' ),
						$command
					) 
				);
		}
	}

	/**
	 * List all sitemap options.
	 *
	 * @param array $args     Command arguments.
	 * @param array $assoc_args Associated arguments.
	 */
	private function options_list( $args, $assoc_args ) {
		$settings = $this->settings_service->get_all_settings();
		$format   = $assoc_args['format'] ?? 'table';
		
		if ( 'json' === $format ) {
			WP_CLI::log( json_encode( $settings, JSON_PRETTY_PRINT ) );
		} else {
			$items = array();
			foreach ( $settings as $key => $value ) {
				$items[] = array(
					'option' => $key,
					'value'  => $value,
				);
			}
			$fields = array( 'option', 'value' );
			format_items( $format, $items, $fields );
		}
	}

	/**
	 * Get a specific option value.
	 *
	 * @param array $args     Command arguments.
	 * @param array $assoc_args Associated arguments.
	 */
	private function options_get( $args, $assoc_args ) {
		if ( empty( $args[1] ) ) {
			WP_CLI::error( __( 'Please specify an option name.', 'msm-sitemap' ) );
		}
		
		$option_name = $args[1];
		$value       = $this->settings_service->get_setting( $option_name );
		
		if ( null === $value ) {
			WP_CLI::error(
				sprintf(
				/* translators: %s: Option name */
					__( 'Option %s not found.', 'msm-sitemap' ),
					$option_name
				) 
			);
		}
		
		WP_CLI::log( $value );
	}

	/**
	 * Update an option value.
	 *
	 * @param array $args     Command arguments.
	 * @param array $assoc_args Associated arguments.
	 */
	private function options_update( $args, $assoc_args ) {
		if ( empty( $args[1] ) ) {
			WP_CLI::error( __( 'Please specify an option name.', 'msm-sitemap' ) );
		}
		
		if ( empty( $args[2] ) ) {
			WP_CLI::error( __( 'Please specify an option value.', 'msm-sitemap' ) );
		}
		
		$option_name  = $args[1];
		$option_value = $args[2];
		
		// Validate option name
		$valid_options = array( 'include_images', 'featured_images', 'content_images', 'max_images_per_sitemap' );
		
		if ( ! in_array( $option_name, $valid_options, true ) ) {
			WP_CLI::error(
				sprintf(
				/* translators: %s: Option name */
					__( 'Unknown option: %1$s. Valid options: %2$s', 'msm-sitemap' ),
					$option_name,
					implode( ', ', $valid_options )
				) 
			);
		}
		
		$result = $this->settings_service->update_setting( $option_name, $this->parse_option_value( $option_value ) );
		
		if ( $result['success'] ) {
			WP_CLI::success(
				sprintf(
				/* translators: %s: Option name */
					__( 'Updated %s option.', 'msm-sitemap' ),
					$option_name
				) 
			);
		} else {
			WP_CLI::error( '❌ ' . $result['message'] );
		}
	}

	/**
	 * Delete an option.
	 *
	 * @param array $args     Command arguments.
	 * @param array $assoc_args Associated arguments.
	 */
	private function options_delete( $args, $assoc_args ) {
		if ( empty( $args[1] ) ) {
			WP_CLI::error( __( 'Please specify an option name.', 'msm-sitemap' ) );
		}
		
		$option_name = $args[1];
		
		// Validate option name
		$valid_options = array( 'include_images', 'featured_images', 'content_images', 'max_images_per_sitemap' );
		
		if ( ! in_array( $option_name, $valid_options, true ) ) {
			WP_CLI::error(
				sprintf(
				/* translators: %s: Option name */
					__( 'Unknown option: %1$s. Valid options: %2$s', 'msm-sitemap' ),
					$option_name,
					implode( ', ', $valid_options )
				) 
			);
		}
		
		$result = $this->settings_service->delete_setting( $option_name );
		
		if ( $result['success'] ) {
			WP_CLI::success( $result['message'] );
		} else {
			WP_CLI::error( $result['message'] );
		}
	}

	/**
	 * Reset all options to defaults.
	 *
	 * @param array $args     Command arguments.
	 * @param array $assoc_args Associated arguments.
	 */
	private function options_reset( $args, $assoc_args ) {
		$result = $this->settings_service->reset_to_defaults();
		
		if ( $result['success'] ) {
			WP_CLI::success( '✅ ' . $result['message'] );
		} else {
			WP_CLI::error( '❌ ' . $result['message'] );
		}
	}

	/**
	 * Parse option value from string.
	 *
	 * @param mixed $value Value to parse.
	 * @return mixed Parsed value.
	 */
	private function parse_option_value( $value ) {
		// Handle boolean values
		if ( in_array( $value, array( 'true', 'false', '1', '0', 'yes', 'no', 'on', 'off' ), true ) ) {
			return $this->parse_boolean_value( $value );
		}
		
		// Handle numeric values
		if ( is_numeric( $value ) ) {
			return (int) $value;
		}
		
		// Return as string
		return $value;
	}

	/**
	 * Parse boolean value from string.
	 *
	 * @param mixed $value Value to parse.
	 * @return bool Parsed boolean value.
	 */
	private function parse_boolean_value( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		
		$string_value = (string) $value;
		return in_array( $string_value, array( '1', 'true', 'yes', 'on' ), true );
	}

	// Utility functions:

	/**
	 * Parse a flexible date string (YYYY, YYYY-MM, YYYY-MM-DD) or --all into a date_query array.
	 *
	 * @param string|null $date
	 * @param bool        $all
	 * @return array|null
	 */
	private function parse_date_query( $date = null, $all = false ) {
		if ( $all ) {
			return array(); // Empty array for --all, will be handled by caller
		}
		if ( empty( $date ) ) {
			return array();
		}
		$parts = explode( '-', $date );
		if ( count( $parts ) === 3 ) {
			$year  = (int) $parts[0];
			$month = (int) $parts[1];
			$day   = (int) $parts[2];
			if ( ! checkdate( $month, $day, $year ) ) {
				WP_CLI::error( __( 'Invalid date. Please provide a real calendar date (e.g., 2024-02-29).', 'msm-sitemap' ) );
			}
			return array(
				array(
					'year'  => $year,
					'month' => $month,
					'day'   => $day,
				),
			);
		} elseif ( count( $parts ) === 2 ) {
			$year  = (int) $parts[0];
			$month = (int) $parts[1];
			if ( $month < 1 || $month > 12 ) {
				WP_CLI::error( __( 'Invalid month. Please specify a month between 1 and 12.', 'msm-sitemap' ) );
			}
			if ( $year < 1970 || $year > (int) date( 'Y' ) ) {
				WP_CLI::error( __( 'Invalid year. Please specify a year between 1970 and the current year.', 'msm-sitemap' ) );
			}
			return array(
				array(
					'year'  => $year,
					'month' => $month,
				),
			);
		} elseif ( count( $parts ) === 1 && strlen( $parts[0] ) === 4 ) {
			$year = (int) $parts[0];
			if ( $year < 1970 || $year > (int) date( 'Y' ) ) {
				WP_CLI::error( __( 'Invalid year. Please specify a year between 1970 and the current year.', 'msm-sitemap' ) );
			}
			return array( array( 'year' => $year ) );
		} else {
			WP_CLI::error( __( 'Invalid date format. Use YYYY, YYYY-MM, or YYYY-MM-DD.', 'msm-sitemap' ) );
		}
	}
}

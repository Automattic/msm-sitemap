<?php
/**
 * Metro Sitemap CLI
 */

use function WP_CLI\Utils\format_items;

/**
 * Class Metro_Sitemap_CLI
 *
 * @package Automattic\MSM_Sitemap
 */
class Metro_Sitemap_CLI extends WP_CLI_Command {
	/**
	 * @var string Type of command triggered so we can keep track of killswitch cleanup.
	 */
	private $command = '';

	/**
	 * @var bool Flag whether or not execution should be stopped.
	 */
	private $halt = false;

	/**
	 * Generate sitemaps for the specified date or all dates.
	 *
	 * ## OPTIONS
	 *
	 * [--date=<date>]
	 * : Generate sitemaps for a specific year (YYYY), month (YYYY-MM), or day (YYYY-MM-DD).
	 * [--all]
	 * : Generate sitemaps for all dates.
	 * [--quiet]
	 * : Suppress all output except errors.
	 * [--force]
	 * : Force regeneration even if sitemap exists.
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
		$all = ! empty( $assoc_args['all'] );
		$date = $assoc_args['date'] ?? null;
		$date_queries = $this->parse_date_query( $date, $all );
		$total_generated = 0;

		if ( $date_queries === null ) {
			// All years
			$all_years_with_posts = \Metro_Sitemap::check_year_has_posts();
			foreach ( $all_years_with_posts as $year ) {
				if ( $this->halt_execution() ) {
					delete_option( 'msm_stop_processing' );
					break;
				}
				$max_month = ( $year == date('Y') ) ? date('n') : 12;
				for ( $month = 1; $month <= $max_month; $month++ ) {
					$max_day = ( $year == date('Y') && $month == date('n') ) ? date('j') : $this->cal_days_in_month( $month, $year );
					for ( $day = 1; $day <= $max_day; $day++ ) {
						if ( $this->halt_execution() ) {
							delete_option( 'msm_stop_processing' );
							break 3;
						}
						$sitemap_id_before = \Metro_Sitemap::get_sitemap_post_id($year, $month, $day);
						$date_stamp = \Metro_Sitemap::get_date_stamp( $year, $month, $day );
						\Metro_Sitemap::generate_sitemap_for_date( $date_stamp, $force );
						$sitemap_id_after = \Metro_Sitemap::get_sitemap_post_id($year, $month, $day);
						if (( $force && $sitemap_id_after ) || ( !$sitemap_id_before && $sitemap_id_after )) {
							$total_generated++;
						}
						else if ( !\Metro_Sitemap::date_range_has_posts( $date_stamp, $date_stamp ) ) {
							\Metro_Sitemap::delete_sitemap_for_date( $date_stamp );
						}
					}
				}
			}
		} else {
			foreach ( $date_queries as $query ) {
				if ( isset($query['year'], $query['month'], $query['day']) ) {
					if ( $this->halt_execution() ) break;
					$year = $query['year'];
					$month = $query['month'];
					$day = $query['day'];
					$sitemap_id_before = \Metro_Sitemap::get_sitemap_post_id($year, $month, $day);
					$date_stamp = \Metro_Sitemap::get_date_stamp( $year, $month, $day );
					\Metro_Sitemap::generate_sitemap_for_date( $date_stamp, $force );
					$sitemap_id_after = \Metro_Sitemap::get_sitemap_post_id($year, $month, $day);
					if (( $force && $sitemap_id_after ) || ( !$sitemap_id_before && $sitemap_id_after )) {
						$total_generated++;
					}
					else if ( !\Metro_Sitemap::date_range_has_posts( $date_stamp, $date_stamp ) ) {
						\Metro_Sitemap::delete_sitemap_for_date( $date_stamp );
					}
				} elseif ( isset($query['year'], $query['month']) ) {
					if ( $this->halt_execution() ) break;
					$year = $query['year'];
					$month = $query['month'];
					$max_day = ( $year == date('Y') && $month == date('n') ) ? date('j') : $this->cal_days_in_month( $month, $year );
					for ( $day = 1; $day <= $max_day; $day++ ) {
						if ( $this->halt_execution() ) {
							delete_option( 'msm_stop_processing' );
							break 2;
						}
						$sitemap_id_before = \Metro_Sitemap::get_sitemap_post_id($year, $month, $day);
						$date_stamp = \Metro_Sitemap::get_date_stamp( $year, $month, $day );
						\Metro_Sitemap::generate_sitemap_for_date( $date_stamp, $force );
						$sitemap_id_after = \Metro_Sitemap::get_sitemap_post_id($year, $month, $day);
						if (( $force && $sitemap_id_after ) || ( !$sitemap_id_before && $sitemap_id_after )) {
							$total_generated++;
						}
						else if ( !\Metro_Sitemap::date_range_has_posts( $date_stamp, $date_stamp ) ) {
							\Metro_Sitemap::delete_sitemap_for_date( $date_stamp );
						}
					}
				} elseif ( isset($query['year']) ) {
					if ( $this->halt_execution() ) break;
					$year = $query['year'];
					$max_month = ( $year == date('Y') ) ? date('n') : 12;
					for ( $month = 1; $month <= $max_month; $month++ ) {
						$max_day = ( $year == date('Y') && $month == date('n') ) ? date('j') : $this->cal_days_in_month( $month, $year );
						for ( $day = 1; $day <= $max_day; $day++ ) {
							if ( $this->halt_execution() ) {
								delete_option( 'msm_stop_processing' );
								break 3;
							}
							$sitemap_id_before = \Metro_Sitemap::get_sitemap_post_id($year, $month, $day);
							$date_stamp = \Metro_Sitemap::get_date_stamp( $year, $month, $day );
							\Metro_Sitemap::generate_sitemap_for_date( $date_stamp, $force );
							$sitemap_id_after = \Metro_Sitemap::get_sitemap_post_id($year, $month, $day);
							if (( $force && $sitemap_id_after ) || ( !$sitemap_id_before && $sitemap_id_after )) {
								$total_generated++;
							}
							else if ( !\Metro_Sitemap::date_range_has_posts( $date_stamp, $date_stamp ) ) {
								\Metro_Sitemap::delete_sitemap_for_date( $date_stamp );
							}
						}
					}
				}
			}
		}
		if ( ! $quiet ) {
			\WP_CLI::success( sprintf( _n( 'Generated %d sitemap.', 'Generated %d sitemaps.', $total_generated, 'msm-sitemap' ), $total_generated ) );
		}
	}

	/**
	 * Helper: Generate all sitemaps for a year.
	 */
	private function generate_for_year( $year, $force, $quiet ) {
		$max_month = ( $year == date('Y') ) ? date('n') : 12;
		$count = 0;
		for ( $month = 1; $month <= $max_month; $month++ ) {
			$count += $this->generate_for_month( $year, $month, $force, $quiet );
		}
		return $count;
	}

	/**
	 * Helper: Generate all sitemaps for a month.
	 */
	private function generate_for_month( $year, $month, $force, $quiet ) {
		$max_day = ( $year == date('Y') && $month == date('n') ) ? date('j') : $this->cal_days_in_month( $month, $year );
		$count = 0;
		for ( $day = 1; $day <= $max_day; $day++ ) {
			$count += $this->generate_for_day( $year, $month, $day, $force, $quiet );
		}
		return $count;
	}

	/**
	 * Helper: Generate sitemap for a specific day.
	 */
	private function generate_for_day( $year, $month, $day, $force, $quiet ) {
		$date_stamp = \Metro_Sitemap::get_date_stamp( $year, $month, $day );
		if ( \Metro_Sitemap::date_range_has_posts( $date_stamp, $date_stamp ) ) {
			\Metro_Sitemap::generate_sitemap_for_date( $date_stamp, $force );
			return 1;
		} else {
			\Metro_Sitemap::delete_sitemap_for_date( $date_stamp );
			return 0;
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
		$all = ! empty( $assoc_args['all'] );
		$date = $assoc_args['date'] ?? null;


		if ( ! $all && empty( $date ) ) {
			WP_CLI::error( __( 'You must specify either --date or --all to delete sitemaps.', 'msm-sitemap' ) );
		}

		$deleted = 0;
		$to_delete = [];

		if ( $all ) {
			// Confirm bulk delete
			WP_CLI::confirm( 'Are you sure you want to delete ALL sitemaps?', $assoc_args );
			$sitemap_query = new \WP_Query([
				'post_type' => 'msm_sitemap',
				'post_status' => 'any',
				'fields' => 'ids',
				'posts_per_page' => -1,
			]);
			$to_delete = $sitemap_query->posts;
		} elseif ( $date ) {
			$date_queries = $this->parse_date_query( $date, false );
			foreach ( $date_queries as $query ) {
				$sitemap_query = new \WP_Query([
					'post_type' => 'msm_sitemap',
					'post_status' => 'any',
					'fields' => 'ids',
					'posts_per_page' => -1,
					'date_query' => [ $query ],
				]);
				$to_delete = array_merge( $to_delete, $sitemap_query->posts );
			}
			if ( count( $to_delete ) > 1 ) {
				WP_CLI::confirm( sprintf( 'Are you sure you want to delete %d sitemaps for the specified date?', count( $to_delete ) ), $assoc_args );
			}
		}

		foreach ( $to_delete as $post_id ) {
			wp_delete_post( $post_id, true );
			$deleted++;
		}

		if ( ! $quiet ) {
			if ( $deleted ) {
				WP_CLI::success( sprintf( _n( 'Deleted %d sitemap.', 'Deleted %d sitemaps.', $deleted, 'msm-sitemap' ), $deleted ) );
			} else {
				WP_CLI::log( __( 'No sitemaps found to delete.', 'msm-sitemap' ) );
			}
		}
	}

	/**
	 * List sitemaps for the specified date or all dates.
	 *
	 * ## OPTIONS
	 *
	 * [--date=<date>]
	 * : List sitemaps for a specific year (YYYY), month (YYYY-MM), or day (YYYY-MM-DD).
	 * [--all]
	 * : List all sitemaps.
	 * [--fields=<fields>]
	 * : Comma-separated list of fields to display (id,date,url_count,status).
	 * [--format=<format>]
	 * : Output format: table, json, or csv. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp msm-sitemap list --date=2024-07
	 *     wp msm-sitemap list --all --format=json
	 *
	 * @subcommand list
	 */
	public function list( $args, $assoc_args ) {
		$fields = isset($assoc_args['fields']) ? explode(',', $assoc_args['fields']) : ['id','date','url_count','status'];
		$format = $assoc_args['format'] ?? 'table';
		$all = ! empty( $assoc_args['all'] );
		$date = $assoc_args['date'] ?? null;
		$date_queries = $this->parse_date_query( $date, $all );
		$query_args = [
			'post_type' => 'msm_sitemap',
			'post_status' => 'any',
			'fields' => 'ids',
			'posts_per_page' => -1,
		];
		if ( $date_queries ) {
			$query_args['date_query'] = $date_queries;
		}
		$posts = get_posts($query_args);
		if ( empty($posts) ) {
			WP_CLI::log( __( 'No sitemaps found.', 'msm-sitemap' ) );
			return;
		}
		$items = [];
		foreach ( $posts as $post_id ) {
			$post = get_post($post_id);
			$row = [];
			foreach ( $fields as $field ) {
				switch ( trim($field) ) {
					case 'id':
						$row['id'] = $post->ID;
						break;
					case 'date':
						$row['date'] = $post->post_name;
						break;
					case 'url_count':
						$row['url_count'] = (int)get_post_meta($post->ID, 'msm_indexed_url_count', true);
						break;
					case 'status':
						$row['status'] = $post->post_status;
						break;
				}
			}
			$items[] = $row;
		}
		format_items($format, $items, $fields);
		return;
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
		$input = $args[0] ?? null;
		$format = $assoc_args['format'] ?? 'table';
		$items = [];
		if ( is_numeric($input) ) {
			$post = get_post((int)$input);
			if ( ! $post || $post->post_type !== 'msm_sitemap' ) {
				WP_CLI::error( __( 'Sitemap not found for that ID.', 'msm-sitemap' ) );
			}
			$items[] = [
				'id' => $post->ID,
				'date' => $post->post_name,
				'url_count' => (int)get_post_meta($post->ID, 'msm_indexed_url_count', true),
				'status' => $post->post_status,
				'last_modified' => $post->post_modified_gmt,
			];
		} else {
			$date_queries = $this->parse_date_query($input, false);
			$query_args = [
				'post_type' => 'msm_sitemap',
				'post_status' => 'any',
				'fields' => 'ids',
				'posts_per_page' => -1,
			];
			if ( $date_queries ) {
				$query_args['date_query'] = $date_queries;
			}
			$posts = get_posts($query_args);
			if ( empty($posts) ) {
				WP_CLI::error( __( 'No sitemaps found for that date.', 'msm-sitemap' ) );
			}
			if ( count($posts) > 1 && $format !== 'json' ) {
				WP_CLI::warning( __( 'Multiple sitemaps found for that date. Showing all.', 'msm-sitemap' ) );
			}
			foreach ( $posts as $post_id ) {
				$post = get_post($post_id);
				$items[] = [
					'id' => $post->ID,
					'date' => $post->post_name,
					'url_count' => (int)get_post_meta($post->ID, 'msm_indexed_url_count', true),
					'status' => $post->post_status,
					'last_modified' => $post->post_modified_gmt,
				];
			}
		}
		format_items($format, $items, ['id','date','url_count','status','last_modified']);
		return;
	}

	/**
	 * Validate sitemaps for the specified date or all dates.
	 *
	 * Checks that each sitemap post contains valid XML and at least one <url> entry. Reports sitemaps with missing or invalid XML, or with no URLs.
	 *
	 * ## OPTIONS
	 *
	 * [--date=<date>]
	 * : Validate sitemaps for a specific year (YYYY), month (YYYY-MM), or day (YYYY-MM-DD).
	 * [--all]
	 * : Validate all sitemaps.
	 *
	 * ## EXAMPLES
	 *
	 *     wp msm-sitemap validate --date=2024-07
	 *     wp msm-sitemap validate --all
	 *
	 * @subcommand validate
	 */
	public function validate( $args, $assoc_args ) {
		$all = ! empty( $assoc_args['all'] );
		$date = $assoc_args['date'] ?? null;
		$date_queries = $this->parse_date_query( $date, $all );
		$query_args = [
			'post_type' => 'msm_sitemap',
			'post_status' => 'any',
			'fields' => 'ids',
			'posts_per_page' => -1,
		];
		if ( $date_queries ) {
			$query_args['date_query'] = $date_queries;
		}
		$posts = get_posts($query_args);
		if ( empty($posts) ) {
			WP_CLI::log( __( 'No sitemaps found to validate.', 'msm-sitemap' ) );
			return;
		}
		$valid_count = 0;
		$invalid_count = 0;
		foreach ( $posts as $post_id ) {
			$xml = get_post_meta($post_id, 'msm_sitemap_xml', true);
			if ( ! $xml ) {
				WP_CLI::warning( sprintf( __( 'Sitemap %d has no XML.', 'msm-sitemap' ), $post_id ) );
				$invalid_count++;
				continue;
			}
			libxml_use_internal_errors(true);
			$doc = simplexml_load_string($xml);
			if ( $doc === false ) {
				WP_CLI::warning( sprintf( __( 'Sitemap %d has invalid XML.', 'msm-sitemap' ), $post_id ) );
				$invalid_count++;
				continue;
			}
			if ( !isset($doc->url) || count($doc->url) < 1 ) {
				WP_CLI::warning( sprintf( __( 'Sitemap %d has no <url> entries.', 'msm-sitemap' ), $post_id ) );
				$invalid_count++;
				continue;
			}
			$valid_count++;
		}
		WP_CLI::success( sprintf( _n( '%d sitemap valid.', '%d sitemaps valid.', $valid_count, 'msm-sitemap' ), $valid_count ) );
		if ( $invalid_count ) {
			WP_CLI::warning( sprintf( _n( '%d sitemap invalid.', '%d sitemaps invalid.', $invalid_count, 'msm-sitemap' ), $invalid_count ) );
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
		$output = $assoc_args['output'];
		$abs_output = $output;
		if ( !preg_match('/^\//', $output) ) {
			$abs_output = realpath(getcwd()) . DIRECTORY_SEPARATOR . $output;
		}
		if ( ! is_dir( $abs_output ) ) {
			if ( ! mkdir( $abs_output, 0777, true ) ) {
				WP_CLI::error( sprintf( __( 'Failed to create export directory: %s', 'msm-sitemap' ), $abs_output ) );
			}
		}
		$all = ! empty( $assoc_args['all'] );
		$date = $assoc_args['date'] ?? null;
		$pretty = ! empty( $assoc_args['pretty'] );
		$date_queries = $this->parse_date_query( $date, $all );
		$query_args = [
			'post_type' => 'msm_sitemap',
			'post_status' => 'any',
			'fields' => 'ids',
			'posts_per_page' => -1,
		];
		if ( $date_queries ) {
			$query_args['date_query'] = $date_queries;
		}
		$posts = get_posts($query_args);
		if ( empty($posts) ) {
			WP_CLI::log( __( 'No sitemaps found to export.', 'msm-sitemap' ) );
			return;
		}
		$count = 0;
		foreach ( $posts as $post_id ) {
			$xml = get_post_meta($post_id, 'msm_sitemap_xml', true);
			if ( ! $xml ) continue;
			if ( $pretty ) {
				$dom = new \DOMDocument('1.0', 'UTF-8');
				$dom->preserveWhiteSpace = false;
				$dom->formatOutput = true;
				if ( @$dom->loadXML($xml) ) {
					$xml = $dom->saveXML();
				}
			}
			$post = get_post($post_id);
			$filename = rtrim($abs_output, '/').'/'.$post->post_name.'.xml';
			if ( file_put_contents($filename, $xml) === false ) {
				WP_CLI::error( sprintf( __( 'Failed to write file: %s', 'msm-sitemap' ), $filename ) );
			}
			$count++;
		}
		if ( $count ) {
			$dir = realpath($abs_output);
			if ( ! $dir ) { $dir = $abs_output; }
			$message = sprintf( _n( 'Exported %d sitemap to %s.', 'Exported %d sitemaps to %s.', $count, 'msm-sitemap' ), $count, $dir );
			WP_CLI::success( $message );
			$quoted_dir = '"' . $dir . '"';
			if ( strtoupper(substr(PHP_OS, 0, 3)) === 'DAR' ) {
				WP_CLI::log( sprintf( __( 'To view the files, run: open %s', 'msm-sitemap' ), $quoted_dir ) );
			} elseif ( strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ) {
				WP_CLI::log( sprintf( __( 'To view the files, run: start %s', 'msm-sitemap' ), $quoted_dir ) );
			} else {
				WP_CLI::log( sprintf( __( 'To view the files, run: xdg-open %s', 'msm-sitemap' ), $quoted_dir ) );
			}
		} else {
			WP_CLI::success( __( 'No sitemaps were exported.', 'msm-sitemap' ) );
		}
	}

	/**
	 * Recalculate and update the indexed URL count for all sitemap posts.
	 *
	 * ## DESCRIPTION
	 *
	 * Recalculates and updates the indexed URL count for all sitemap posts. Useful for debugging or after manual changes.
	 *
	 * ## EXAMPLES
	 *
	 *     wp msm-sitemap recount
	 *
	 * @subcommand recount
	 */
	public function recount( $args, $assoc_args ) {
		$all_sitemaps = get_posts(
			array(
				'post_type' => Metro_Sitemap::SITEMAP_CPT,
				'post_status' => 'publish',
				'fields' => 'ids',
				'suppress_filters' => false,
				'posts_per_page' => -1,
			)
		);

		$total_count = 0;
		$sitemap_count = 0;

		foreach ( $all_sitemaps as $sitemap_id ) {
			$xml_data = get_post_meta( $sitemap_id, 'msm_sitemap_xml', true );
			$xml = simplexml_load_string( $xml_data );
			$count = is_object($xml) && isset($xml->url) ? count( $xml->url ) : 0;
			update_post_meta( $sitemap_id, 'msm_indexed_url_count', $count );
			$total_count += $count;
			$sitemap_count += 1;
		}

		update_option( 'msm_sitemap_indexed_url_count', $total_count, false );
		WP_CLI::log( sprintf( __( 'Total URLs found: %s', 'msm-sitemap' ), $total_count ) );
		WP_CLI::log( sprintf( __( 'Number of sitemaps found: %s', 'msm-sitemap' ), $sitemap_count ) );
	}

	/**
	 * Show sitemap statistics (total, most recent, etc).
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format: table, json, or csv. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp msm-sitemap stats --format=json
	 *
	 * @subcommand stats
	 */
	public function stats( $args, $assoc_args ) {
		$posts = get_posts([
			'post_type' => 'msm_sitemap',
			'post_status' => 'any',
			'fields' => 'ids',
			'posts_per_page' => -1,
		]);
		$total = count($posts);
		$most_recent = null;
		$max_date = '';
		foreach ( $posts as $post_id ) {
			$post = get_post($post_id);
			if ( $post && ( $max_date === '' || $post->post_date_gmt > $max_date ) ) {
				$max_date = $post->post_date_gmt;
				$most_recent = $post;
			}
		}
		$format = $assoc_args['format'] ?? 'table';
		$fields = ['total','most_recent','created'];
		$items = [[
			'total' => $total,
			'most_recent' => $most_recent ? $most_recent->post_name . ' (ID ' . $most_recent->ID . ')' : '',
			'created' => $most_recent ? $most_recent->post_date_gmt : '',
		]];
		format_items($format, $items, $fields);
		return;
	}

	// LEGACY Commands:

	/**
	 * [LEGACY] Generate all sitemaps for site.
	 *
	 * ## DESCRIPTION
	 *
	 * This command is a backward-compatible wrapper. The canonical command is now `generate --all`.
	 *
	 * @subcommand generate-sitemaps
	 */
	public function generate_sitemaps( $args, $assoc_args ) {
		return $this->generate([], array_merge($assoc_args, ['all' => true]));
	}

	/**
	 * [LEGACY] Generate all sitemaps for site.
	 * 
	 * ## DESCRIPTION
	 *
	 * This command is a backward-compatible wrapper. The canonical command is now `generate`.
	 *
	 * @subcommand generate-sitemap
	 */
   public function generate_sitemap( $args, $assoc_args ) {
	   return $this->generate([], $assoc_args);
   }

	/**
	 * [LEGACY] Generate sitemap for a given year.
	 *
	 * ## DESCRIPTION
	 *
	 * This command is a backward-compatible wrapper. The canonical command is now `generate` with `--date=YYYY`.
	 *
	 * @subcommand generate-sitemap-for-year
	 */
	public function generate_sitemap_for_year( $args, $assoc_args ) {
		$date = sprintf('%04d', $assoc_args['year']);
		return $this->generate([], array_merge($assoc_args, ['date' => $date]));
	}

	/**
	 * [LEGACY] Generate sitemap for a given year and month.
	 *
	 * ## DESCRIPTION
	 *
	 * This command is a backward-compatible wrapper. The canonical command is now `generate` with `--date=YYYY-MM`.
	 *
	 * @subcommand generate-sitemap-for-year-month
	 */
	public function generate_sitemap_for_year_month( $args, $assoc_args ) {
		$date = sprintf('%04d-%02d', $assoc_args['year'], $assoc_args['month']);
		return $this->generate([], array_merge($assoc_args, ['date' => $date]));
	}

	/**
	 * [LEGACY] Generate sitemap for a given year and month.
	 *
	 * ## DESCRIPTION
	 *
	 * This command is a backward-compatible wrapper. The canonical command is now `generate` with `--date=YYYY-MM-DD`.
	 *
	 * @subcommand generate-sitemap-for-year-month-day
	 */
	public function generate_sitemap_for_year_month_day( $args, $assoc_args ) {
		$date = sprintf('%04d-%02d-%02d', $assoc_args['year'], $assoc_args['month'], $assoc_args['day']);
		return $this->generate([], array_merge($assoc_args, ['date' => $date]));
	}
	
	/**
	 * [LEGACY] Recount indexed posts in all sitemaps.
	 *
	 * ## DESCRIPTION
	 *
	 * This command is a backward-compatible wrapper. The canonical command is now `recount`.
	 *
	 * ## EXAMPLES
	 *
	 *     wp msm-sitemap recount-indexed-posts
	 *
	 * @subcommand recount-indexed-posts
	 */
	public function recount_indexed_posts( $args = [], $assoc_args = [] ) {
		return $this->recount( $args, $assoc_args );
	}

	// Utility functions:

	/**
	 * Check if the user has flagged to bail on sitemap generation.
	 *
	 * Once `$this->halt` is set, we take advantage of PHP's boolean operator to stop querying the option in hopes of
	 * limiting database interaction.
	 *
	 * @return bool
	 */
	private function halt_execution() {
		if ( $this->halt || get_option( 'msm_stop_processing' ) ) {
			// Allow user to bail out of the current process, doesn't remove where the job got up to
			delete_option( 'msm_sitemap_create_in_progress' );
			$this->halt = true;
			return true;
		}

		return false;
	}

	/**
	 * Return max number of days in a month of a year.
	 *
	 * Uses cal_days_in_month if available, if not, takes advantage of `date( 't' )` and `mktime`.
	 *
	 * @param int $month Month
	 * @param int $year Year
	 *
	 * @return int Number of days in a month of a year.
	 */
	private function cal_days_in_month( $month, $year ) {
		if ( function_exists( 'cal_days_in_month' ) ) {
			return cal_days_in_month( CAL_GREGORIAN, $month, $year );
		}
		// Calculate actual number of days in the month since we don't have cal_days_in_month available
		return date( 't', mktime( 0, 0, 0, $month, 1, $year ) );
	}

	/**
	 * Parse a flexible date string (YYYY, YYYY-MM, YYYY-MM-DD) or --all into a date_query array.
	 *
	 * @param string|null $date
	 * @param bool $all
	 * @return array|null
	 */
	protected function parse_date_query( $date = null, $all = false ) {
		if ( $all ) {
			return null; // No date_query, get all
		}
		if ( empty($date) ) {
			return null;
		}
		$parts = explode('-', $date);
		if ( count($parts) === 3 ) {
			$year = (int)$parts[0];
			$month = (int)$parts[1];
			$day = (int)$parts[2];
			if (!checkdate($month, $day, $year)) {
				WP_CLI::error( __( 'Invalid date. Please provide a real calendar date (e.g., 2024-02-29).', 'msm-sitemap' ) );
			}
			return [ [ 'year' => $year, 'month' => $month, 'day' => $day ] ];
		} elseif ( count($parts) === 2 ) {
			$year = (int)$parts[0];
			$month = (int)$parts[1];
			if ( $month < 1 || $month > 12 ) {
				WP_CLI::error( __( 'Invalid month. Please specify a month between 1 and 12.', 'msm-sitemap' ) );
			}
			if ( $year < 1970 || $year > (int)date('Y') ) {
				WP_CLI::error( __( 'Invalid year. Please specify a year between 1970 and the current year.', 'msm-sitemap' ) );
			}
			return [ [ 'year' => $year, 'month' => $month ] ];
		} elseif ( count($parts) === 1 && strlen($parts[0]) === 4 ) {
			$year = (int)$parts[0];
			if ( $year < 1970 || $year > (int)date('Y') ) {
				WP_CLI::error( __( 'Invalid year. Please specify a year between 1970 and the current year.', 'msm-sitemap' ) );
			}
			return [ [ 'year' => $year ] ];
		} else {
			WP_CLI::error( __( 'Invalid date format. Use YYYY, YYYY-MM, or YYYY-MM-DD.', 'msm-sitemap' ) );
		}
	}
}

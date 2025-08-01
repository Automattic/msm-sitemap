<?php
/**
 * Metro Sitemap - joint collaboration between Metro.co.uk, MAKE, Alley Interactive, and WordPress VIP.
 *
 * @package           automattic/msm-sitemap
 * @author            Automattic
 * @copyright         2015-onwards Artur Synowiec, Paul Kevan, and contributors.
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Metro Sitemap
 * Plugin URI:        https://github.com/Automattic/msm-sitemap
 * Description:       Comprehensive sitemaps for your WordPress site.
 * Version:           1.5.2
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Metro.co.uk, MAKE, Alley Interactive, WordPress VIP.
 * Text Domain:       msm-sitemap
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.txt
 */

if ( defined( 'WP_CLI' ) && true === WP_CLI ) {
	require __DIR__ . '/includes/wp-cli.php';
	WP_CLI::add_command( 'msm-sitemap', 'Metro_Sitemap_CLI' );
}

class Metro_Sitemap {

	const DEFAULT_POSTS_PER_SITEMAP_PAGE = 500;

	const SITEMAP_CPT = 'msm_sitemap';

	public static $index_by_year = false;

	/**
	 * Register actions for our hook
	 */
	public static function setup() {
		define( 'MSM_INTERVAL_PER_GENERATION_EVENT', 60 ); // how far apart should full cron generation events be spaced

		add_filter( 'cron_schedules', array( __CLASS__, 'sitemap_15_min_cron_interval' ) );

		// Filter to allow the sitemap to be indexed by year
		self::$index_by_year = apply_filters( 'msm_sitemap_index_by_year', false );

		// A cron schedule for creating/updating sitemap posts based on updated content since the last run
		add_action( 'init', array( __CLASS__, 'sitemap_init' ) );
		add_action( 'admin_init', array( __CLASS__, 'sitemap_init_cron' ) );
		add_action( 'redirect_canonical', array( __CLASS__, 'disable_canonical_redirects_for_sitemap_xml' ), 10, 2 );
		add_action( 'init', array( __CLASS__, 'create_post_type' ) );
		add_filter( 'posts_pre_query', array( __CLASS__, 'disable_main_query_for_sitemap_xml' ), 10, 2 );
		add_filter( 'template_include', array( __CLASS__, 'load_sitemap_template' ) );

		// Hook into post deletion/trashing to trigger sitemap updates
		add_action( 'deleted_post', array( __CLASS__, 'handle_post_deletion' ), 10, 2 );
		add_action( 'trashed_post', array( __CLASS__, 'handle_post_deletion' ), 10, 1 );

		// By default, we use wp-cron to help generate the full sitemap.
		// However, this will let us override it, if necessary, like on WP.com
		if ( true === apply_filters( 'msm_sitemap_use_cron_builder', true ) ) {
			require __DIR__ . '/includes/msm-sitemap-builder-cron.php';
			MSM_Sitemap_Builder_Cron::setup();
		}

		// Setup WordPress core integration and stylesheet management
		require_once __DIR__ . '/includes/CoreIntegration.php';
		require_once __DIR__ . '/includes/StylesheetManager.php';
		\Automattic\MSM_Sitemap\CoreIntegration::setup();
		\Automattic\MSM_Sitemap\StylesheetManager::setup();
	}

	/**
	 * Register 15 minute cron interval for latest articles
	 *
	 * @param array[] $schedules
	 * @return array[] modified schedules
	 */
	public static function sitemap_15_min_cron_interval( $schedules ) {
		$schedules['ms-sitemap-15-min-cron-interval'] = array(
			'interval' => 900,
			'display'  => __( 'Every 15 minutes', 'msm-sitemap' ),
		);
		return $schedules;
	}

	/**
	 * Register endpoint for sitemap and other hooks
	 */
	public static function sitemap_init() {
		if ( ! defined( 'WPCOM_SKIP_DEFAULT_SITEMAP' ) ) {
			define( 'WPCOM_SKIP_DEFAULT_SITEMAP', true );
		}

		self::sitemap_rewrite_init();

		add_filter( 'robots_txt', array( __CLASS__, 'robots_txt' ), 10, 2 );
		add_action( 'admin_menu', array( __CLASS__, 'metro_sitemap_menu' ) );
		add_action( 'msm_cron_update_sitemap', array( __CLASS__, 'update_sitemap_from_modified_posts' ) );
		add_action( 'wp_ajax_msm-sitemap-get-sitemap-counts', array( __CLASS__, 'ajax_get_sitemap_counts' ) );
	}

	/**
	 * Setup rewrite rules for the sitemap
	 */
	public static function sitemap_rewrite_init() {
		// Allow 'sitemap=true' parameter
		add_rewrite_tag( '%sitemap%', 'true' );

		// Define rewrite rules for the index based on the setup
		if ( self::$index_by_year ) {
			add_rewrite_tag( '%sitemap-year%', '[0-9]{4}' );
			add_rewrite_rule( '^sitemap-([0-9]{4}).xml$', 'index.php?sitemap=true&sitemap-year=$matches[1]', 'top' );
		} else {
			add_rewrite_rule( '^sitemap.xml$', 'index.php?sitemap=true', 'top' );
		}
	}

	/**
	 * Register admin menu for sitemap
	 */
	public static function metro_sitemap_menu() {
		$page_hook = add_management_page( __( 'Sitemap', 'msm-sitemap' ), __( 'Sitemap', 'msm-sitemap' ), 'manage_options', 'metro-sitemap', array( __CLASS__, 'render_sitemap_options_page' ) );
		add_action( 'admin_print_scripts-' . $page_hook, array( __CLASS__, 'add_admin_scripts' ) );
	}

	public static function add_admin_scripts() {
		wp_enqueue_script( 'flot', plugins_url( '/js/flot/jquery.flot.js', __FILE__ ), array( 'jquery' ) );
		wp_enqueue_script( 'msm-sitemap-admin', plugins_url( '/js/msm-sitemap-admin.js', __FILE__ ), array( 'jquery', 'flot' ) );
		wp_enqueue_script( 'flot-time', plugins_url( '/js/flot/jquery.flot.time.js', __FILE__ ), array( 'jquery', 'flot' ) );

		wp_enqueue_style( 'msm-sitemap-css', plugins_url( 'css/style.css', __FILE__ ) );
		wp_enqueue_style( 'noticons', '//s0.wordpress.com/i/noticons/noticons.css' );
	}

	/**
	 * Retrieve sitemap counts and stats for AJAX/REST endpoints.
	 *
	 * @param int $num_days Number of days of stats to retrieve.
	 * @return array
	 */
	public static function get_sitemap_counts_data( $num_days = 10 ) {
		return array(
			'total_indexed_urls'   => number_format( self::get_total_indexed_url_count() ),
			'total_sitemaps'       => number_format( self::count_sitemaps() ),
			'sitemap_indexed_urls' => self::get_recent_sitemap_url_counts( $num_days ),
		);
	}

	public static function ajax_get_sitemap_counts() {
		check_admin_referer( 'msm-sitemap-action' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'msm-sitemap' ) );
		}

		$n = 10;
		if ( isset( $_REQUEST['num_days'] ) ) {
			$n = intval( $_REQUEST['num_days'] );
		}

		$data = self::get_sitemap_counts_data( $n );

		wp_send_json( $data );
	}

	/**
	 * Render admin options page
	 */
	public static function render_sitemap_options_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'msm-sitemap' ) );
		}

		// Array of possible user actions
		$actions = apply_filters( 'msm_sitemap_actions', array() );

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<?php
		if ( ! self::is_blog_public() ) {
			self::show_action_message( __( 'Oops! Sitemaps are not supported on private sites. Please make your site is public and try again.', 'msm-sitemap' ), 'error' );
			echo '</div>';
			return;
		}

		if ( isset( $_POST['action'] ) ) {
			check_admin_referer( 'msm-sitemap-action' );
			foreach ( $actions as $slug => $action ) {
				if ( $action['text'] !== $_POST['action'] ) {
					continue;
				}
				do_action( 'msm_sitemap_action-' . $slug );
				break;
			}
		}

		// All the settings we need to read to display the page
		$sitemap_create_in_progress = (bool) get_option( 'msm_sitemap_create_in_progress' ) === true;
		$sitemap_update_last_run    = get_option( 'msm_sitemap_update_last_run' );

		// Determine sitemap status text
		$sitemap_create_status = apply_filters(
			'msm_sitemap_create_status',
			$sitemap_create_in_progress ? __( 'Running', 'msm-sitemap' ) : __( 'Not Running', 'msm-sitemap' )
		);

		?>
		<div class="stats-container">
			<div class="stats-box"><strong id="sitemap-count"><?php echo number_format( self::count_sitemaps() ); ?></strong><?php esc_html_e( 'Sitemaps', 'msm-sitemap' ); ?></div>
			<div class="stats-box"><strong id="sitemap-indexed-url-count"><?php echo number_format( self::get_total_indexed_url_count() ); ?></strong><?php esc_html_e( 'Indexed URLs', 'msm-sitemap' ); ?></div>
			<div class="stats-footer"><span><span class="noticon noticon-time"></span><?php esc_html_e( 'Updated', 'msm-sitemap' ); ?> <strong><?php echo human_time_diff( $sitemap_update_last_run ); ?> <?php esc_html_e( 'ago', 'msm-sitemap' ); ?></strong></span></div>
		</div>

		<h2><?php esc_html_e( 'Latest Sitemaps', 'msm-sitemap' ); ?></h2>
		<div class="stats-container stats-placeholder"></div>
		<div id="stats-graph-summary">
		<?php
		printf(
			/* translators: 1: max number of indexed URLs, 2: date of max indexed URLs, 3: number of days to show */
			__( 'Max: %1$s on %2$s. Showing the last %3$s days.', 'msm-sitemap' ),
			'<span id="stats-graph-max"></span>',
			'<span id="stats-graph-max-date"></span>',
			'<span id="stats-graph-num-days"></span>',
		);
		?>
		</div>

		<h2><?php esc_html_e( 'Generate', 'msm-sitemap' ); ?></h2>
		<p><strong><?php esc_html_e( 'Sitemap Creation Status:', 'msm-sitemap' ); ?></strong> <?php echo esc_html( $sitemap_create_status ); ?></p>
		<form action="<?php echo menu_page_url( 'metro-sitemap', false ); ?>" method="post" style="float: left;">
			<?php wp_nonce_field( 'msm-sitemap-action' ); ?>
			<?php
			foreach ( $actions as $action ) :
				if ( ! $action['enabled'] ) {
					continue;
				}
				?>
				<input type="submit" name="action" class="button-secondary" value="<?php echo esc_attr( $action['text'] ); ?>">
			<?php endforeach; ?>
		</form>
		</div>
		<div id="tooltip">
			<strong class="content"></strong>
				<span class="url-label"
					data-singular="<?php esc_attr_e( 'indexed URL', 'msm-sitemap' ); ?>"
					data-plural="<?php esc_attr_e( 'indexed URLs', 'msm-sitemap' ); ?>"
				><?php esc_html_e( 'indexed URLs', 'msm-sitemap' ); ?></span>
		</div>
		<?php
	}

	/**
	 * Displays a notice, error or warning to the user
	 *
	 * @param str $message The message to show to the user
	 */
	public static function show_action_message( $message, $level = 'notice' ) {
		$class = 'updated';
		if ( $level === 'warning' ) {
			$class = 'update-nag';
		} elseif ( $level === 'error' ) {
			$class = 'error';
		}

		echo '<div class="' . esc_attr( $class ) . ' msm-sitemap-message"><p>' . wp_kses( $message, wp_kses_allowed_html( 'post' ) ) . '</p></div>';
	}

	/**
	 * Counts the number of sitemaps that have been generated.
	 *
	 * @return int The number of sitemaps that have been generated
	 */
	public static function count_sitemaps() {
		$count = wp_count_posts( self::SITEMAP_CPT );
		return (int) $count->publish;
	}

	/**
	 * Gets the current number of URLs indexed by msm-sitemap accross all sitemaps.
	 *
	 * @return int The number of total number URLs indexed
	 */
	public static function get_total_indexed_url_count() {
		return intval( get_option( 'msm_sitemap_indexed_url_count', 0 ) );
	}

	/**
	 * Returns the $n most recent sitemap indexed url counts.
	 *
	 * @param int $n The number of days of sitemap stats to grab.
	 * @return array An array of sitemap stats
	 */
	public static function get_recent_sitemap_url_counts( $n = 7 ) {
		$stats = array();

		for ( $i = 0; $i < $n; $i++ ) {
			$date = date( 'Y-m-d', strtotime( "-$i days" ) );

			list( $year, $month, $day ) = explode( '-', $date );

			$stats[ $date ] = self::get_indexed_url_count( $year, $month, $day );
		}

		return $stats;
	}

	public static function is_blog_public() {
		return ( 1 == get_option( 'blog_public' ) );
	}

	/**
	 * Gets the number of URLs indexed for the given sitemap.
	 *
	 * @param array $sitemaps The sitemaps to retrieve counts for.
	 */
	public static function get_indexed_url_count( $year, $month, $day ) {
		$sitemap_id = self::get_sitemap_post_id( $year, $month, $day );

		if ( $sitemap_id ) {
			return intval( get_post_meta( $sitemap_id, 'msm_indexed_url_count', true ) );
		}

		return false;
	}

	/**
	 * Add entries to the bottom of robots.txt
	 */
	public static function robots_txt( $output, $public ) {

		// Make sure the site isn't private
		if ( '1' == $public ) {
			$output .= '# Sitemap archive' . PHP_EOL;

			if ( self::$index_by_year ) {
				$years = self::check_year_has_posts();
				foreach ( $years as $year ) {
					$output .= 'Sitemap: ' . home_url( '/sitemap-' . absint( $year ) . '.xml' ) . PHP_EOL;
				}

				$output .= PHP_EOL;
			} else {
				$output .= 'Sitemap: ' . home_url( '/sitemap.xml' ) . PHP_EOL . PHP_EOL;
			}
		}
		return $output;
	}

	/**
	 * Add cron jobs required to generate these sitemaps
	 */
	public static function sitemap_init_cron() {
		if ( self::is_blog_public() && ! wp_next_scheduled( 'msm_cron_update_sitemap' ) ) {
			wp_schedule_event( time(), 'ms-sitemap-15-min-cron-interval', 'msm_cron_update_sitemap' );
		}
	}

	/**
	 * Disable canonical redirects for the sitemap files
	 *
	 * @see http://codex.wordpress.org/Function_Reference/redirect_canonical
	 * @param string $redirect_url
	 * @param string $requested_url
	 * @return string URL to redirect
	 */
	public static function disable_canonical_redirects_for_sitemap_xml( $redirect_url, $requested_url ) {
		if ( self::$index_by_year ) {
			$pattern = '|sitemap-([0-9]{4})\.xml|';
		} else {
			$pattern = '|sitemap\.xml|';
		}

		if ( preg_match( $pattern, $requested_url ) ) {
			return $requested_url;
		}
		return $redirect_url;
	}

	/**
	 * Hook allows developers to extend the sitemap functionality easily and integrate their custom post statuses.
	 *
	 * Rather than having to modify the plugin code, developers can use this filter to add their custom post statuses.
	 *
	 * @since 1.4.3
	 */
	public static function get_post_status(): string {
		$default_status = 'publish';
		$post_status    = apply_filters( 'msm_sitemap_post_status', $default_status );

		$allowed_statuses = get_post_stati();

		if ( ! in_array( $post_status, $allowed_statuses ) ) {
			$post_status = $default_status;
		}

		return $post_status;
	}

	/**
	 * Return range of years for posts in the database
	 *
	 * @return int[] valid years
	 */
	public static function get_post_year_range() {
		/**
		 * Allow the post year range to be short-circuited.
		 *
		 * @param array|false $pre The pre-filtered value. If false, the default value will be used.
		 */
		$pre = apply_filters( 'msm_sitemap_pre_get_post_year_range', false );

		// Return the pre-filtered value if it's an array.
		if ( is_array( $pre ) ) {
			return $pre;
		}

		$oldest_post_date_year = wp_cache_get( 'oldest_post_date_year', 'msm_sitemap' );

		if ( false === $oldest_post_date_year ) {
			global $wpdb;

			$post_types_in = self::get_supported_post_types_in();

			$oldest_post_date_year = $wpdb->get_var( $wpdb->prepare( "SELECT DISTINCT YEAR(post_date) as year FROM $wpdb->posts WHERE post_status = %s AND post_type IN ( {$post_types_in} ) AND post_date > 0 ORDER BY year ASC LIMIT 1", self::get_post_status() ) );

			wp_cache_set( 'oldest_post_date_year', $oldest_post_date_year, 'msm_sitemap', WEEK_IN_SECONDS );
		}

		if ( null !== $oldest_post_date_year ) {
			$current_year = date( 'Y' );
			return range( (int) $oldest_post_date_year, $current_year );
		}

		return array();
	}

	/**
	 * Get every year that has valid posts in a range
	 *
	 * @return int[] years with posts
	 */
	public static function check_year_has_posts() {
		$all_years = self::get_post_year_range();

		$years_with_posts = array();
		foreach ( $all_years as $year ) {
			if ( self::date_range_has_posts( self::get_date_stamp( $year, 1, 1 ), self::get_date_stamp( $year, 12, 31 ) ) ) {
				$years_with_posts[] = $year;
			}
		}
		return $years_with_posts;
	}

	/**
	 * Get properly formatted data stamp from year, month, and day
	 *
	 * @param int $year
	 * @param int $month
	 * @param int $day
	 * @return string formatted stamp
	 */
	public static function get_date_stamp( $year, $month, $day ) {
		return sprintf( '%s-%s-%s', $year, str_pad( $month, 2, '0', STR_PAD_LEFT ), str_pad( $day, 2, '0', STR_PAD_LEFT ) );
	}

	/**
	 * Does a current date range have posts?
	 *
	 * @param string $start_date
	 * @param string $end_date
	 * @return int|false
	 */
	public static function date_range_has_posts( $start_date, $end_date ) {
		global $wpdb;

		// Validate date format and existence
		if (
			! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start_date ) ||
			! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end_date )
		) {
			return null;
		}
		list( $start_year, $start_month, $start_day ) = explode( '-', $start_date );
		list( $end_year, $end_month, $end_day )       = explode( '-', $end_date );
		if (
			! checkdate( (int) $start_month, (int) $start_day, (int) $start_year ) ||
			! checkdate( (int) $end_month, (int) $end_day, (int) $end_year )
		) {
			return null;
		}

		$start_date .= ' 00:00:00';
		$end_date   .= ' 23:59:59';
		$post_status = self::get_post_status();

		$post_types_in = self::get_supported_post_types_in();
		return $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_status = %s AND post_date >= %s AND post_date <= %s AND post_type IN ( {$post_types_in} ) LIMIT 1", $post_status, $start_date, $end_date ) );
	}

	/**
	 * Get a list of support post_type IDs for a given date
	 *
	 * @param string $sitemap_date Date in Y-m-d
	 * @param int    $limit        Number of post IDs to return
	 * @return array IDs of posts
	 */
	public static function get_post_ids_for_date( $sitemap_date, int $limit = 500 ) {
		global $wpdb;

		if ( $limit < 1 ) {
			return array();
		}

		// Validate date format and existence
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $sitemap_date ) ) {
			return array();
		}
		list( $year, $month, $day ) = explode( '-', $sitemap_date );
		if ( ! checkdate( (int) $month, (int) $day, (int) $year ) ) {
			return array();
		}

		$post_status   = self::get_post_status();
		$start_date    = $sitemap_date . ' 00:00:00';
		$end_date      = $sitemap_date . ' 23:59:59';
		$post_types_in = self::get_supported_post_types_in();

		$posts = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_date FROM $wpdb->posts WHERE post_status = %s AND post_date >= %s AND post_date <= %s AND post_type IN ( {$post_types_in} ) LIMIT %d", $post_status, $start_date, $end_date, $limit ) );

		usort( $posts, array( __CLASS__, 'order_by_post_date' ) );

		$post_ids = wp_list_pluck( $posts, 'ID' );

		return array_map( 'intval', $post_ids );
	}

	/**
	 * Generate sitemap for a date; this is where XML is rendered.
	 *
	 * @param string $sitemap_date
	 */
	public static function generate_sitemap_for_date( $sitemap_date ) {
		global $wpdb;

		list( $year, $month, $day ) = explode( '-', $sitemap_date );

		$sitemap_name   = $sitemap_date;
		$sitemap_exists = false;

		$sitemap_data = array(
			'post_name'   => $sitemap_name,
			'post_title'  => $sitemap_name,
			'post_type'   => self::SITEMAP_CPT,
			'post_status' => self::get_post_status(),
			'post_date'   => $sitemap_date,
		);

		$sitemap_id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type = %s AND post_name = %s LIMIT 1", self::SITEMAP_CPT, $sitemap_name ) );

		if ( $sitemap_id ) {
			$sitemap_exists = true;
		} else {
			$sitemap_id     = wp_insert_post( $sitemap_data );
			$sitemap_exists = true;
		}

		$per_page = apply_filters( 'msm_sitemap_entry_posts_per_page', self::DEFAULT_POSTS_PER_SITEMAP_PAGE );
		$post_ids = self::get_post_ids_for_date( $sitemap_date, $per_page );

		if ( empty( $post_ids ) ) {
			// If no entries - delete the whole sitemap post
			if ( $sitemap_exists ) {
				self::delete_sitemap_by_id( $sitemap_id );
			}
			return;
		}

		$total_url_count = self::get_total_indexed_url_count();

		// For migration: in case the previous version used an array for this option
		if ( is_array( $total_url_count ) ) {
			$total_url_count = array_sum( $total_url_count );
			update_option( 'msm_sitemap_indexed_url_count', $total_url_count, false );
		}

		// Get XSL stylesheet reference
		$stylesheet = \Automattic\MSM_Sitemap\StylesheetManager::get_sitemap_stylesheet_reference();
		// $stylesheet = '';
		// SimpleXML doesn't allow us to define namespaces using addAttribute, so we need to specify them in the construction instead.
		$namespaces = apply_filters(
			'msm_sitemap_namespace',
			array(
				'xmlns:xsi'          => 'http://www.w3.org/2001/XMLSchema-instance',
				'xmlns'              => 'http://www.sitemaps.org/schemas/sitemap/0.9',
				'xmlns:n'            => 'http://www.google.com/schemas/sitemap-news/0.9',
				'xmlns:image'        => 'http://www.google.com/schemas/sitemap-image/1.1',
				'xsi:schemaLocation' => 'http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd',
			)
		);

		$namespace_str = '<?xml version="1.0" encoding="utf-8"?>' . $stylesheet . '<urlset';
		foreach ( $namespaces as $ns => $value ) {
			$namespace_str .= sprintf( ' %s="%s"', esc_attr( $ns ), esc_attr( $value ) );
		}
		$namespace_str .= '></urlset>';

		// Create XML
		$xml = new SimpleXMLElement( $namespace_str );

		$url_count = 0;
		foreach ( $post_ids as $post_id ) {
			$GLOBALS['post'] = get_post( $post_id );
			setup_postdata( $GLOBALS['post'] );

			if ( apply_filters( 'msm_sitemap_skip_post', false, $post_id ) ) {
				continue;
			}

			$url = $xml->addChild( 'url' );
			$url->addChild( 'loc', esc_url( get_permalink() ) );
			$url->addChild( 'lastmod', get_post_modified_time( 'c', true ) );
			$url->addChild( 'changefreq', 'monthly' );
			$url->addChild( 'priority', '0.7' );

			apply_filters( 'msm_sitemap_entry', $url );

			++$url_count;
			// TODO: add images to sitemap via <image:image> tag
		}

		// If all posts were skipped, remove the sitemap post.
		if ( 0 === $url_count ) {
			self::delete_sitemap_by_id( $sitemap_id );
			wp_reset_postdata();
			return;
		}

		$generated_xml_string = $xml->asXML();

		// Save the sitemap
		if ( $sitemap_exists ) {
			// Get the previous post count
			$previous_url_count = intval( get_post_meta( $sitemap_id, 'msm_indexed_url_count', true ) );

			// Update the total post count with the difference
			$total_url_count += $url_count - $previous_url_count;

			update_post_meta( $sitemap_id, 'msm_sitemap_xml', $generated_xml_string );
			update_post_meta( $sitemap_id, 'msm_indexed_url_count', $url_count );
			do_action( 'msm_update_sitemap_post', $sitemap_id, $year, $month, $day, $generated_xml_string, $url_count );
		} else {
			/* Should no longer hit this */
			$sitemap_id = wp_insert_post( $sitemap_data );
			add_post_meta( $sitemap_id, 'msm_sitemap_xml', $generated_xml_string );
			add_post_meta( $sitemap_id, 'msm_indexed_url_count', $url_count );
			do_action( 'msm_insert_sitemap_post', $sitemap_id, $year, $month, $day, $generated_xml_string, $url_count );

			// Update the total url count
			$total_url_count += $url_count;
		}

		// Update indexed url counts
		update_option( 'msm_sitemap_indexed_url_count', $total_url_count, false );

		wp_reset_postdata();
	}

	public static function delete_sitemap_for_date( $sitemap_date ) {
		list( $year, $month, $day ) = explode( '-', $sitemap_date );
		$sitemap_id                 = self::get_sitemap_post_id( $year, $month, $day );
		if ( ! $sitemap_id ) {
			return false;
		}
		return self::delete_sitemap_by_id( $sitemap_id );
	}

	public static function delete_sitemap_by_id( $sitemap_id ) {
		$sitemap = get_post( $sitemap_id );
		if ( ! $sitemap ) {
			return false;
		}

		$sitemap_date               = date( 'Y-m-d', strtotime( $sitemap->post_date ) );
		list( $year, $month, $day ) = explode( '-', $sitemap_date );

		$total_url_count  = self::get_total_indexed_url_count();
		$total_url_count -= intval( get_post_meta( $sitemap_id, 'msm_indexed_url_count', true ) );
		update_option( 'msm_sitemap_indexed_url_count', $total_url_count, false );

		wp_delete_post( $sitemap_id, true );
		do_action( 'msm_delete_sitemap_post', $sitemap_id, $year, $month, $day );
	}

	/**
	 * Register our CPT
	 */
	public static function create_post_type() {
		register_post_type(
			self::SITEMAP_CPT,
			array(
				'labels'       => array(
					'name'          => __( 'Sitemaps', 'msm-sitemap' ),
					'singular_name' => __( 'Sitemap', 'msm-sitemap' ),
				),
				'public'       => false,
				'has_archive'  => false,
				'rewrite'      => false,
				'show_ui'      => true,  // TODO: should probably have some sort of custom UI
				'show_in_menu' => false, // Since we're manually adding a Sitemaps menu, no need to auto-add one through the CPT.
				'supports'     => array(
					'title',
				),
			)
		);
	}

	/**
	 * Get posts modified within the last hour
	 *
	 * @return object[] modified posts
	 */
	public static function get_last_modified_posts() {
		global $wpdb;

		$sitemap_last_run = get_option( 'msm_sitemap_update_last_run', false );

		$date = date( 'Y-m-d H:i:s', ( time() - 3600 ) ); // posts changed within the last hour

		if ( $sitemap_last_run ) {
			$date = date( 'Y-m-d H:i:s', $sitemap_last_run );
		}

		$post_types_in = self::get_supported_post_types_in();

		$query = $wpdb->prepare( "SELECT ID, post_date FROM $wpdb->posts WHERE post_type IN ( {$post_types_in} ) AND post_status = %s AND post_modified_gmt >= %s LIMIT 1000", self::get_post_status(), $date );

		/**
		 * Filter the query used to get the last modified posts.
		 * $wpdb->prepare() should be used for security if a new replacement query is created in the callback.
		 *
		 * @param string $query         The query to use to get the last modified posts.
		 * @param string $post_types_in A comma-separated list of post types to include in the query.
		 * @param string $date          The date to use as the cutoff for the query.
		 */
		$query = apply_filters( 'msm_pre_get_last_modified_posts', $query, $post_types_in, $date );

		$modified_posts = $wpdb->get_results( $query );

		return $modified_posts;
	}

	/**
	 * Get dates for an array of posts
	 *
	 * @param object[] $posts
	 * @return string[] unique dates of each post.
	 */
	public static function get_post_dates( $posts ) {
		$dates = array();
		foreach ( $posts as $post ) {
			$dates[] = date( 'Y-m-d', strtotime( $post->post_date ) );
		}
		$dates = array_unique( $dates );

		return $dates;
	}

	/**
	 * Update the sitemap with changes from recently modified posts
	 */
	public static function update_sitemap_from_modified_posts() {
		$time                = time();
		$last_modified_posts = self::get_last_modified_posts();
		$dates               = self::get_post_dates( $last_modified_posts );

		foreach ( $dates as $date ) {
			list( $year, $month, $day ) = explode( '-', $date );

			// Do not allow non-existant or future dates to be queued
			if ( false === checkdate( $month, $day, $year ) || $time <= mktime( 0, 0, 0, $month, $day, $year ) ) {
				continue;
			}

			$time += MSM_INTERVAL_PER_GENERATION_EVENT;

			do_action( 'msm_update_sitemap_for_year_month_date', array( $year, $month, $day ), $time );
		}
		update_option( 'msm_sitemap_update_last_run', time(), false );
	}

	/**
	 * Trigger rendering of the actual sitemap
	 */
	public static function load_sitemap_template( $template ) {
		if ( get_query_var( 'sitemap' ) === 'true' ) {
			$template = __DIR__ . '/templates/full-sitemaps.php';
		}
		return $template;
	}

	/**
	 * Disable Main Query when rendering sitemaps
	 *
	 * @param array|null $posts array of post data or null
	 * @param WP_Query   $query The WP_Query instance.
	 */
	public static function disable_main_query_for_sitemap_xml( $posts, $query ) {
		if ( $query->is_main_query() && isset( $query->query_vars['sitemap'] ) && 'true' === $query->query_vars['sitemap'] ) {
			$posts = array();
		}
		return $posts;
	}

	/**
	 * Build Root sitemap XML
	 * Can be all days (default) or a specific year.
	 *
	 * @param int|boolean $year
	 */
	public static function build_root_sitemap_xml( $year = false ) {
		$xml_prefix = '<?xml version="1.0" encoding="utf-8"?>';
		$stylesheet = \Automattic\MSM_Sitemap\StylesheetManager::get_index_stylesheet_reference();
		global $wpdb;

		// Direct query because we just want dates of the sitemap entries and this is much faster than WP_Query
		if ( is_numeric( $year ) ) {
			$query = $wpdb->prepare( "SELECT post_date FROM $wpdb->posts WHERE post_type = %s AND YEAR(post_date) = %s ORDER BY post_date DESC LIMIT 10000", self::SITEMAP_CPT, $year );
		} else {
			$query = $wpdb->prepare( "SELECT post_date FROM $wpdb->posts WHERE post_type = %s ORDER BY post_date DESC LIMIT 10000", self::SITEMAP_CPT );
		}

		$sitemaps = $wpdb->get_col( $query );

		// Sometimes duplicate sitemaps exist, lets make sure so they are not output
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

		$xml = new SimpleXMLElement( $xml_prefix . $stylesheet . '<sitemapindex xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></sitemapindex>' );
		foreach ( $sitemaps as $sitemap_date ) {
			$sitemap      = $xml->addChild( 'sitemap' );
			$sitemap->loc = self::build_sitemap_url( $sitemap_date ); // manually set the child instead of addChild to prevent "unterminated entity reference" warnings due to encoded ampersands http://stackoverflow.com/a/555039/169478
		}
		$xml_string = $xml->asXML();

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
	 * Build the sitemap URL for a given date
	 *
	 * @param string $sitemap_date
	 * @return string
	 */
	public static function build_sitemap_url( $sitemap_date ) {
		$sitemap_time = strtotime( $sitemap_date );

		if ( self::$index_by_year ) {
			$sitemap_url = add_query_arg(
				array(
					'mm' => date( 'm', $sitemap_time ),
					'dd' => date( 'd', $sitemap_time ),
				),
				home_url( '/sitemap-' . date( 'Y', $sitemap_time ) . '.xml' )
			);
		} else {
			$sitemap_url = add_query_arg(
				array(
					'yyyy' => date( 'Y', $sitemap_time ),
					'mm'   => date( 'm', $sitemap_time ),
					'dd'   => date( 'd', $sitemap_time ),
				),
				home_url( '/sitemap.xml' )
			);
		}

		return $sitemap_url;
	}

	public static function get_sitemap_post_id( $year, $month, $day ) {
		$ymd = self::get_date_stamp( $year, $month, $day );

		$sitemap_args = array(
			'date_query'        => array(
				array(
					'before'    => sprintf( '%s 00:00:00', $ymd ),
					'after'     => sprintf( '%s 00:00:00', $ymd ),
					'inclusive' => true,
				),
			),
			'orderby'           => 'ID',
			'order'             => 'ASC',
			'posts_per_page'    => 1,
			'fields'            => 'ids',
			'post_type'         => self::SITEMAP_CPT,
			'no_found_rows'     => true,
			'update_term_cache' => false,
			'suppress_filters'  => false,
		);

		$sitemap_query = get_posts( $sitemap_args );

		if ( ! empty( $sitemap_query ) ) {
			return $sitemap_query[0];
		}

		return false;
	}

	/**
	 * Get XML for individual day
	 */
	public static function build_individual_sitemap_xml( $year, $month, $day ) {

		// Get XML for an individual day. Stored as full xml
		$sitemap_id = self::get_sitemap_post_id( $year, $month, $day );

		if ( $sitemap_id ) {
			$sitemap_content = get_post_meta( $sitemap_id, 'msm_sitemap_xml', true );
			// Return is now as it should be valid xml!
			return $sitemap_content;
		}
		/* There are no posts for this day */
		return false;
	}

	/**
	 * Build XML for output to clean up the template file
	 */
	public static function build_xml( $request = array() ) {
		$year  = $request['year'];
		$month = $request['month'];
		$day   = $request['day'];

		if ( ( false === $year || is_numeric( $year ) ) && false === $month && false === $day ) {
			$xml = self::build_root_sitemap_xml( $year );
		} elseif ( $year > 0 && $month > 0 && $day > 0 ) {
			$xml = self::build_individual_sitemap_xml( $year, $month, $day );
		} else {
			/* Invalid options sent */
			return false;
		}
		return $xml;
	}

	public static function get_supported_post_types() {
		return apply_filters( 'msm_sitemap_entry_post_type', array( 'post' ) );
	}

	/**
	 * Retrieve supported post types for inclusion in sitemap.
	 *
	 * @return string[]
	 */
	private static function get_supported_post_types_in() {
		global $wpdb;

		$post_types          = self::get_supported_post_types();
		$post_types_prepared = array();

		foreach ( $post_types as $post_type ) {
			$post_types_prepared[] = $wpdb->prepare( '%s', $post_type );
		}

		return implode( ', ', $post_types_prepared );
	}

	/**
	 * Helper function for PHP ordering of posts by date, desc.
	 *
	 * @param object $post_a StdClass object, or WP_Post object to order.
	 * @param object $post_b StdClass object or WP_Post object to order.
	 *
	 * @return int
	 */
	private static function order_by_post_date( $post_a, $post_b ) {
		$a_date = strtotime( $post_a->post_date );
		$b_date = strtotime( $post_b->post_date );
		if ( $a_date === $b_date ) {
			return 0;
		}
		return ( $a_date < $b_date ) ? -1 : 1;
	}

	/**
	 * Handle post deletion/trashing to trigger sitemap updates.
	 *
	 * This method is called when a post is deleted or trashed. It queues
	 * sitemap updates to be processed by the existing 15-minute cron job,
	 * avoiding performance issues when many posts are deleted at once.
	 *
	 * @param int     $post_id The ID of the post being deleted/trashed.
	 * @param WP_Post $post    The post object (optional, only passed by deleted_post hook).
	 */
	public static function handle_post_deletion( $post_id, $post = null ) {
		// If post object is not provided, try to get it from the database
		if ( ! $post || ! is_a( $post, 'WP_Post' ) ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				return;
			}
		}
		
		// Only process supported post types
		$supported_post_types = self::get_supported_post_types();
		if ( ! in_array( $post->post_type, $supported_post_types ) ) {
			return;
		}

		// Get the post date to determine the sitemap date
		$post_date                  = date( 'Y-m-d', strtotime( $post->post_date ) );
		list( $year, $month, $day ) = explode( '-', $post_date );

		// Validate the date
		if ( ! checkdate( (int) $month, (int) $day, (int) $year ) ) {
			return;
		}

		// We can either generate the sitemap immediately or queue it for processing by the existing cron job.

		// Immediately regenerate the sitemap for this date to ensure the deleted/trashed post is removed
		// self::generate_sitemap_for_date( $post_date );

		// Queue the sitemap update to be processed by the existing cron job
		do_action( 'msm_update_sitemap_for_year_month_date', array( $year, $month, $day ), current_time( 'timestamp' ) );
	}
}

// Register custom permalink handler for msm_sitemap posts.
// @see https://github.com/Automattic/msm-sitemap/issues/170
require_once __DIR__ . '/includes/Permalinks.php';
Automattic\MSM_Sitemap\Permalinks::register();

add_action( 'after_setup_theme', array( 'Metro_Sitemap', 'setup' ) );

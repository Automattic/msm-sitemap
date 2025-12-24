<?php
/**
 * Repository for post operations in WordPress.
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\Repositories
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Infrastructure\Repositories;

use Automattic\MSM_Sitemap\Domain\Contracts\PostRepositoryInterface;
use Automattic\MSM_Sitemap\Domain\ValueObjects\SitemapDate;

/**
 * Repository for post operations in WordPress.
 */
class PostRepository implements PostRepositoryInterface {

	/**
	 * Find a post by its ID.
	 *
	 * @param int|string $id The post ID.
	 * @return object|null The post object if found, null otherwise.
	 */
	public function find( $id ): ?object {
		$post = get_post( (int) $id );
		if ( ! $post || ! in_array( $post->post_type, $this->get_supported_post_types(), true ) ) {
			return null;
		}
		return $post;
	}

	/**
	 * Find posts by criteria.
	 *
	 * @param array $criteria Search criteria.
	 * @param int $limit Maximum number of results to return.
	 * @param int $offset Number of results to skip.
	 * @return array Array of post objects.
	 */
	public function find_by( array $criteria, int $limit = 100, int $offset = 0 ): array {
		$args = array(
			'post_type'      => $this->get_supported_post_types(),
			'post_status'    => $this->get_post_status(),
			'posts_per_page' => $limit,
			'offset'         => $offset,
		);

		// Add criteria filters
		if ( isset( $criteria['date'] ) ) {
			$args['date_query'] = array(
				array(
					'year'  => gmdate( 'Y', strtotime( $criteria['date'] ) ),
					'month' => gmdate( 'm', strtotime( $criteria['date'] ) ),
					'day'   => gmdate( 'd', strtotime( $criteria['date'] ) ),
				),
			);
		}

		if ( isset( $criteria['post_type'] ) ) {
			$args['post_type'] = $criteria['post_type'];
		}

		return get_posts( $args );
	}

	/**
	 * Save a post.
	 *
	 * @param mixed $entity The post data or object.
	 * @return bool True on success, false on failure.
	 */
	public function save( $entity ): bool {
		if ( is_array( $entity ) ) {
			$post_id = wp_insert_post( $entity );
			return 0 < $post_id;
		}
		return false;
	}

	/**
	 * Delete a post by its ID.
	 *
	 * @param int|string $id The post ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete( $id ): bool {
		$result = wp_delete_post( (int) $id, true );
		return false !== $result;
	}

	/**
	 * Count posts matching criteria.
	 *
	 * @param array $criteria Search criteria.
	 * @return int Number of matching posts.
	 */
	public function count( array $criteria = array() ): int {
		$args = array(
			'post_type'      => $this->get_supported_post_types(),
			'post_status'    => $this->get_post_status(),
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);

		// Add criteria filters
		if ( isset( $criteria['date'] ) ) {
			$args['date_query'] = array(
				array(
					'year'  => gmdate( 'Y', strtotime( $criteria['date'] ) ),
					'month' => gmdate( 'm', strtotime( $criteria['date'] ) ),
					'day'   => gmdate( 'd', strtotime( $criteria['date'] ) ),
				),
			);
		}

		$posts = get_posts( $args );
		return count( $posts );
	}

	/**
	 * Check if a post exists.
	 *
	 * @param int|string $id The post ID.
	 * @return bool True if post exists, false otherwise.
	 */
	public function exists( $id ): bool {
		$post = get_post( (int) $id );
		return $post && in_array( $post->post_type, $this->get_supported_post_types(), true );
	}

	/**
	 * Get posts that have been modified since the given timestamp.
	 *
	 * @param int|string|false $since_timestamp Timestamp to check since, or false for last hour.
	 * @return array Array of post objects.
	 */
	public function get_modified_posts_since( $since_timestamp = false ): array {
		global $wpdb;

		$date = gmdate( 'Y-m-d H:i:s', ( time() - 3600 ) ); // posts changed within the last hour

		if ( $since_timestamp ) {
			$date = gmdate( 'Y-m-d H:i:s', (int) $since_timestamp );
		}

		$post_types    = $this->get_supported_post_types();
		$post_types_in = "'" . implode( "','", $post_types ) . "'";

		$query = $wpdb->prepare( 
			"SELECT ID, post_date, post_modified_gmt FROM $wpdb->posts 
			WHERE post_type IN ( {$post_types_in} ) 
			AND post_status = %s 
			AND post_modified_gmt >= %s 
			LIMIT 1000", 
			$this->get_post_status(), 
			$date 
		);

		/**
		 * Filter the query used to get the last modified posts.
		 * $wpdb->prepare() should be used for security if a new replacement query is created in the callback.
		 *
		 * @param string $query         The query to use to get the last modified posts.
		 * @param string $post_types_in A comma-separated list of post types to include in the query.
		 * @param string $date          The date to use as the cutoff for the query.
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Legacy hook name for backwards compatibility.
		$query = apply_filters( 'msm_pre_get_last_modified_posts', $query, $post_types_in, $date );

		return $wpdb->get_results( $query );
	}

	/**
	 * Get the post status for sitemap generation.
	 *
	 * @return string The post status.
	 */
	public function get_post_status(): string {
		$default_status = 'publish';
		$post_status    = apply_filters( 'msm_sitemap_post_status', $default_status );

		$allowed_statuses = get_post_stati();

		if ( ! in_array( $post_status, $allowed_statuses ) ) {
			$post_status = $default_status;
		}

		return $post_status;
	}

	/**
	 * Get supported post types for inclusion in sitemap.
	 *
	 * @return array<string> Array of supported post types.
	 */
	public function get_supported_post_types(): array {
		return apply_filters( 'msm_sitemap_entry_post_type', array( 'post' ) );
	}

	/**
	 * Get supported post types as SQL IN clause.
	 *
	 * @return string SQL IN clause for post types.
	 */
	public function get_supported_post_types_in(): string {
		global $wpdb;

		$post_types          = $this->get_supported_post_types();
		$post_types_prepared = array();

		foreach ( $post_types as $post_type ) {
			$post_types_prepared[] = $wpdb->prepare( '%s', $post_type );
		}

		return implode( ', ', $post_types_prepared );
	}

	/**
	 * Get post IDs for a specific date.
	 *
	 * @param string $sitemap_date Date in Y-m-d format.
	 * @param int    $limit        Number of post IDs to return.
	 * @return array IDs of posts.
	 */
	public function get_post_ids_for_date( string $sitemap_date, int $limit = 500 ): array {
		global $wpdb;

		if ( $limit < 1 ) {
			return array();
		}

		// Validate date format and existence using SitemapDate value object
		try {
			$date = SitemapDate::fromString( $sitemap_date );
		} catch ( \InvalidArgumentException $e ) {
			return array();
		}

		$post_status   = $this->get_post_status();
		$start_date    = $date->toMysqlDatetime( '00:00:00' );
		$end_date      = $date->toMysqlDatetime( '23:59:59' );
		$post_types_in = $this->get_supported_post_types_in();

		$posts = $wpdb->get_results(
			$wpdb->prepare( 
				"SELECT ID, post_date FROM $wpdb->posts WHERE post_status = %s AND post_date >= %s AND post_date <= %s AND post_type IN ( {$post_types_in} ) LIMIT %d", 
				$post_status, 
				$start_date, 
				$end_date, 
				$limit 
			) 
		);

		usort( $posts, array( $this, 'order_by_post_date' ) );

		$post_ids = wp_list_pluck( $posts, 'ID' );

		return array_map( 'intval', $post_ids );
	}

	/**
	 * Check if a date range has posts.
	 *
	 * @param string $start_date Start date in YYYY-MM-DD format.
	 * @param string $end_date   End date in YYYY-MM-DD format.
	 * @return int|null Post ID if posts exist, null otherwise.
	 */
	public function date_range_has_posts( string $start_date, string $end_date ): ?int {
		global $wpdb;

		// Validate date format and existence using SitemapDate value object
		try {
			$start = SitemapDate::fromString( $start_date );
			$end   = SitemapDate::fromString( $end_date );
		} catch ( \InvalidArgumentException $e ) {
			return null;
		}

		$start_datetime = $start->toMysqlDatetime( '00:00:00' );
		$end_datetime   = $end->toMysqlDatetime( '23:59:59' );
		$post_status    = $this->get_post_status();

		$post_types_in = $this->get_supported_post_types_in();
		$result        = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM $wpdb->posts WHERE post_status = %s AND post_date >= %s AND post_date <= %s AND post_type IN ( {$post_types_in} ) LIMIT 1",
				$post_status,
				$start_datetime,
				$end_datetime
			)
		);

		return $result ? (int) $result : null;
	}

	/**
	 * Get the range of years for posts in the database.
	 *
	 * @return array<int> Array of valid years.
	 */
	public function get_post_year_range(): array {
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

			$post_types_in = $this->get_supported_post_types_in();

			$oldest_post_date_year = $wpdb->get_var(
				$wpdb->prepare( 
					"SELECT DISTINCT YEAR(post_date) as year FROM $wpdb->posts WHERE post_status = %s AND post_type IN ( {$post_types_in} ) AND post_date > 0 ORDER BY year ASC LIMIT 1", 
					$this->get_post_status() 
				) 
			);

			wp_cache_set( 'oldest_post_date_year', $oldest_post_date_year, 'msm_sitemap', WEEK_IN_SECONDS );
		}

		if ( null !== $oldest_post_date_year ) {
			$current_year = (int) gmdate( 'Y' );
			return range( (int) $oldest_post_date_year, $current_year );
		}

		return array();
	}

	/**
	 * Get every year that has valid posts in a range.
	 *
	 * @return array<int> Years with posts.
	 */
	public function get_years_with_posts(): array {
		$all_years = $this->get_post_year_range();

		$years_with_posts = array();
		foreach ( $all_years as $year ) {
			$start_date = sprintf( '%04d-01-01', $year );
			$end_date   = sprintf( '%04d-12-31', $year );
			if ( $this->date_range_has_posts( $start_date, $end_date ) ) {
				$years_with_posts[] = $year;
			}
		}
		return $years_with_posts;
	}

	/**
	 * Get all unique post publication dates (YYYY-MM-DD format).
	 *
	 * @return array<string> Array of unique publication dates.
	 */
	public function get_all_post_publication_dates(): array {
		global $wpdb;
		
		$post_types     = $this->get_supported_post_types();
		$post_types_sql = "'" . implode( "','", array_map( 'esc_sql', $post_types ) ) . "'";
		
		$sql = $wpdb->prepare(
			"SELECT DISTINCT DATE(post_date) as post_date
			FROM {$wpdb->posts}
			WHERE post_status = %s
			AND post_type IN ({$post_types_sql})
			ORDER BY post_date DESC",
			'publish'
		);
		
		$dates = $wpdb->get_col( $sql );
		
		return array_filter( $dates ); // Remove any null/empty dates
	}

	/**
	 * Helper function for PHP ordering of posts by date, desc.
	 *
	 * @param object $post_a StdClass object, or WP_Post object to order.
	 * @param object $post_b StdClass object or WP_Post object to order.
	 * @return int Comparison result.
	 */
	public function order_by_post_date( $post_a, $post_b ): int {
		$a_date = strtotime( $post_a->post_date );
		$b_date = strtotime( $post_b->post_date );
		
		if ( $a_date === $b_date ) {
			return 0;
		}
		
		return ( $a_date < $b_date ) ? -1 : 1;
	}
}

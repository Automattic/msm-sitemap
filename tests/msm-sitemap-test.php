<?php
/**
 * MSM_SiteMap_Test
 *
 * @package Metro_Sitemap/unit_tests
 */

/**
 * A base class for MSM SiteMap Tests that exposes a few handy functions for test cases.
 *
 * @author michaelblouin
 * @author Matthew Denton (mdbitz)
 */
class MSM_SiteMap_Test {

	/**
	 * Array of Posts Created for Test
	 *
	 * @var type array
	 */
	public $posts = array();

	/**
	 * Array of Created Post IDs
	 *
	 * @var type array
	 */
	public $posts_created = array();

	/**
	 * Creates a new post for every day in $dates.
	 *
	 * Does not trigger building of sitemaps.
	 *
	 * @param array(str) $dates The days to create posts on.
	 * @throws Exception Unable to insert posts.
	 */
	function create_dummy_posts( $dates ) {

		foreach ( $dates as $day ) {
			$post_data = array(
					'post_title' => (string) uniqid(),
					'post_type' => 'post',
					'post_content' => (string) uniqid(),
					'post_status' => 'publish',
					'post_author' => 1,
			);

			$post_data['post_date'] = $post_data['post_modified'] = date_format( new DateTime( $day ), 'Y-m-d H:i:s' );
			$post_data['ID'] = wp_insert_post( $post_data, true );

			if ( is_wp_error( $post_data['ID'] ) || 0 === $post_data['ID'] ) {
				throw new Exception( "Error: WP Error encountered inserting post. {$post_data['ID']->errors}, {$post_data['ID']->error_data}" );
			}

			$this->posts_created[] = $post_data['ID'];
			$this->posts[] = $post_data;
		}
	}

	/**
	 * Checks that the stats are correct for each individual created post
	 */
	function check_stats_for_created_posts() {
		$dates = array();

		// Count the number of posts for each date.
		foreach ( $this->posts as $post ) {
			$date = date_format( new DateTime( $post['post_date'] ), 'Y-m-d' );

			if ( isset( $dates[ $date ] ) ) {
				$dates[ $date ] ++;
			} else {
				$dates[ $date ] = 1;
			}
		}

		// Check counts for each date.
		foreach ( $dates as $date => $count ) {
			list( $year, $month, $day ) = explode( '-', $date, 3 );

			$indexed_url_count = Metro_Sitemap::get_indexed_url_count( $year, $month, $day );
			if ( $indexed_url_count !== $count ) {
				echo "\nExpected url count of $indexed_url_count but had count of $count on $year-$month-$day\n";
				return false;
			}
		}

		return true;
	}

	/**
	 * Generate sitemaps for all Posts
	 */
	function build_sitemaps() {
		global $wpdb;
		$posts = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_date FROM $wpdb->posts WHERE post_type = %s ORDER BY post_date LIMIT 1000", 'post' ) );
		$dates = array();
		foreach ( $posts as $post ) {
			$dates[] = date( 'Y-m-d', strtotime( $post->post_date ) );
		}
		$udates = array_unique( $dates );

		foreach ( $udates as $date ) {
			list( $year, $month, $day ) = explode( '-', $date );

			MSM_Sitemap_Builder_Cron::generate_sitemap_for_year_month_day( array( 'year' => $year, 'month' => $month, 'day' => $day ) );
		}
	}

	/**
	 * Generate sitemap for an individual post
	 *
	 * @param WP_Post $post Post.
	 */
	function update_sitemap_by_post( $post ) {
		$date = date( 'Y-m-d', strtotime( $post->post_date ) );
		list( $year, $month, $day ) = explode( '-', $date );
		MSM_Sitemap_Builder_Cron::generate_sitemap_for_year_month_day( array( 'year' => $year, 'month' => $month, 'day' => $day ) );
	}

}



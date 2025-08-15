<?php
/**
 * MSM_SiteMap_Test.
 *
 * @package Automattic\MSM_Sitemap\Tests
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests;

use DateTime;
use WP_Post;
use Automattic\MSM_Sitemap\Application\Services\SitemapService;
use Automattic\MSM_Sitemap\Application\Services\ContentTypesService;
use Automattic\MSM_Sitemap\Infrastructure\Repositories\PostRepository;
use Automattic\MSM_Sitemap\Domain\Contracts\SitemapRepositoryInterface;


/**
 * A base class for MSM SiteMap Tests that exposes a few handy functions for test cases.
 */
abstract class TestCase extends \Yoast\WPTestUtils\WPIntegration\TestCase {

	/**
	 * Array of Posts Created for Test
	 *
	 * @var array $posts
	 */
	public $posts = array();

	/**
	 * Array of Created Post IDs
	 *
	 * @var array $posts_created
	 */
	public $posts_created = array();

	/**
	 * Array of filters added for the test.
	 *
	 * @var array $added_filters
	 */
	protected $added_filters = array();

	/**
	 * The dependency injection container.
	 *
	 * @var \Automattic\MSM_Sitemap\Infrastructure\DI\SitemapContainer|null
	 */
	protected $container = null;

	/**
	 * Remove the sample posts, sitemap posts, and filters before each test.
	 */
	public function setUp(): void {
		// Remove all posts (from WordPress Test Library)
		_delete_all_posts();

		// Remove all posts created for the test.
		$this->posts = array();

		$this->delete_all_sitemaps();

		// Remove all posts created for the test.
		array_map( 'wp_delete_post', $this->posts_created );

		// Remove all filters added for the test.
		foreach ( $this->added_filters as $filter ) {
			remove_filter( $filter[0], $filter[1], $filter[2] );
		}

		$this->added_filters = array();

		// Force cron to be enabled during tests
		$this->add_test_filter( 'msm_sitemap_cron_enabled', '__return_true' );

		// Initialize the dependency injection container
		$this->container = \Automattic\MSM_Sitemap\Infrastructure\DI\msm_sitemap_container();

		parent::setUp();
	}

	/**
	 * Clean up after each test.
	 */
	public function tearDown(): void {
		$this->delete_all_sitemaps();

		parent::tearDown();
	}

	/**
	 * Get a service from the container.
	 *
	 * @param string $service_class The service class name.
	 * @return object The service instance.
	 */
	protected function get_service( string $service_class ) {
		return $this->container->get( $service_class );
	}

	/**
	 * Delete all sitemap posts and reset the indexed URL count.
	 */
	public function delete_all_sitemaps(): void {
		$sitemaps = get_posts(
			array(
				'post_type'      => 'msm_sitemap',
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'post_status'    => 'any', // Include all statuses
			) 
		);

		// Delete all sitemap posts
		foreach ( $sitemaps as $sitemap_id ) {
			wp_delete_post( $sitemap_id, true );
		}

		// Reset the indexed URL count.
		update_option( 'msm_sitemap_indexed_url_count', 0 );
	}

	/**
	 * Creates a new post for given day, post_type and Status
	 *
	 * Does not trigger building of sitemaps.
	 *
	 * @param string $day The day to create posts on.
	 * @param string $post_status
	 * @param string $post_type The Post Type Slug.
	 *
	 * @return int ID of created post.
	 */
	public function create_dummy_post( string $day, string $post_status = 'publish', string $post_type = 'post' ): int {
		$post_data = array(
			'post_title'   => uniqid( '', true ),
			'post_type'    => $post_type,
			'post_content' => uniqid( '', true ),
			'post_status'  => $post_status,
			'post_author'  => 1,
		);

		$post_data['post_date']     = date_format( new DateTime( $day ), 'Y-m-d H:i:s' );
		$post_data['post_modified'] = $post_data['post_date'];
		$post_data['ID']            = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_data['ID'] ) || 0 === $post_data['ID'] ) {
			$error_message = is_wp_error( $post_data['ID'] ) 
				? "Error: WP Error encountered inserting post. " . implode( ', ', $post_data['ID']->get_error_messages() )
				: "Error: Failed to insert post. ID: " . $post_data['ID'];
			$this->fail( $error_message );
		}

		$this->posts_created[] = $post_data['ID'];
		$this->posts[]         = $post_data;

		return $post_data['ID'];
	}

	/**
	 * Creates a new post for every day in $dates.
	 *
	 * Does not trigger building of sitemaps.
	 *
	 * @param array<string> $dates The days to create posts on.
	 */
	public function create_dummy_posts( array $dates ): void {
		foreach ( $dates as $day ) {
			$this->create_dummy_post( $day );
		}
	}

	/**
	 * Add a post for a day x months ago.
	 *
	 * @param int $months Number of months.
	 */
	public function add_a_post_for_a_day_x_months_ago( $months, $status = 'publish', $post_type = 'post' ): void {
		$date    = strtotime( "-$months month" );
		$cur_day = date( 'Y', $date ) . '-' . date( 'm', $date ) . '-' . date( 'd', $date ) . ' 00:00:00';
		$this->create_dummy_post( $cur_day, $status, $post_type );
	}

	/**
	 * Add a post for each day in the last x years.
	 *
	 * @param int $years Number of years.
	 */
	public function add_a_post_for_a_day_x_years_ago( $years, $status = 'publish', $post_type = 'post' ): void {
		$date    = strtotime( "-$years year" );
		$cur_day = date( 'Y', $date ) . '-' . date( 'm', $date ) . '-' . date( 'd', $date ) . ' 00:00:00';
		$this->create_dummy_post( $cur_day, $status, $post_type );
	}

	/**
	 * Add a post for each day in the last x years.
	 *
	 * @param int $years Number of years.
	 */
	public function add_a_post_for_each_of_the_last_x_years( $years, $status = 'publish', $post_type = 'post' ): void {
		for ( $i = 0; $i < $years; $i++ ) {
			$this->add_a_post_for_a_day_x_years_ago( $i, $status, $post_type );
		}
	}

	/**
	 * Add a post for each day in the last x days.
	 *
	 * @param int $days Number of days.
	 */
	public function add_a_post_for_each_of_the_last_x_days_before_today( $days, $status = 'publish', $post_type = 'post' ): void {
		for ( $i = 1; $i <= $days; $i++ ) {
			$date    = strtotime( "-$i day" );
			$cur_day = date( 'Y', $date ) . '-' . date( 'm', $date ) . '-' . date( 'd', $date ) . ' 00:00:00';
			$this->create_dummy_post( $cur_day, $status, $post_type );
		}
	}

	/**
	 * Add a post for today.
	 *
	 * @param string $status The status of the post.
	 * @param string $post_type The post type.
	 */
	public function add_a_post_for_today( $status = 'publish', $post_type = 'post' ): void {
		$this->create_dummy_post( date( 'Y-m-d' ), $status, $post_type );
	}

	/**
	 * Checks that the stats are correct for each individual created post
	 */
	public function check_stats_for_created_posts(): bool {
		$dates = array();

		// Count the number of posts for each date.
		foreach ( $this->posts as $post ) {
			$date = date_format( new DateTime( $post['post_date'] ), 'Y-m-d' );

			if ( isset( $dates[ $date ] ) ) {
				++$dates[ $date ];
			} else {
				$dates[ $date ] = 1;
			}
		}

		// Check counts for each date.
		foreach ( $dates as $date => $count ) {
			list( $year, $month, $day ) = explode( '-', $date, 3 );

			$post_id           = $this->get_sitemap_post_id( $year, $month, $day );
			$indexed_url_count = $post_id ? (int) get_post_meta( $post_id, 'msm_indexed_url_count', true ) : 0;
			if ( $indexed_url_count !== $count ) {
				$this->fail( "Expected url count of $indexed_url_count but had count of $count on $year-$month-$day" );
				return false;
			}
		}

		return true;
	}

	/**
	 * Build sitemaps for all posts using the new DDD system.
	 */
	public function build_sitemaps(): void {
		global $wpdb;
		$post_types_in = $this->get_supported_post_types_in();
		$posts         = $wpdb->get_results( "SELECT ID, post_date FROM $wpdb->posts WHERE post_type IN ( {$post_types_in} ) ORDER BY post_date LIMIT 1000" );
		$dates         = array();
		foreach ( $posts as $post ) {
			$dates[] = date( 'Y-m-d', strtotime( $post->post_date ) );
		}
		
		$service = $this->get_service( SitemapService::class );
		
		foreach ( array_unique( $dates ) as $date ) {
			$service->create_for_date( $date, true ); // Force regeneration
		}
	}

	/**
	 * Get supported post types in a format that can be used in a SQL query.
	 *
	 * @return string
	 */
	public function get_supported_post_types_in(): string {
		global $wpdb;

		$post_repository     = $this->get_service( PostRepository::class );
		$post_types          = $post_repository->get_supported_post_types();
		$post_types_prepared = array();

		foreach ( $post_types as $post_type ) {
			$post_types_prepared[] = $wpdb->prepare( '%s', $post_type );
		}

		return implode( ', ', $post_types_prepared );
	}

	/**
	 * Generate sitemap for an individual post using the new DDD system.
	 *
	 * @param WP_Post $post Post.
	 */
	public function update_sitemap_by_post( WP_Post $post ): void {
		$date = date( 'Y-m-d', strtotime( $post->post_date ) );
		
		$service = $this->get_service( SitemapService::class );
		
		$service->create_for_date( $date, true ); // Force regeneration
	}

	/**
	 * Add a filter for a test.
	 *
	 * @param string   $tag The filter tag.
	 * @param callable $callback The callback function.
	 * @param int      $priority The priority of the filter.
	 */
	protected function add_test_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
		add_filter( $tag, $callback, $priority, $accepted_args );
		$this->added_filters[] = array( $tag, $callback, $priority );
	}

	/**
	 * Fakes a cron job
	 *
	 * @param string $execute Execute the hook.
	 */
	public function fake_cron( string $execute = 'run' ): void {
		foreach ( _get_cron_array() as $timestamp => $cron ) {
			foreach ( $cron as $hook => $arg_wrapper ) {
				if ( strpos( $hook, 'msm' ) !== 0 ) {
					continue; // only run our own jobs.
				}
				$arg_struct = array_pop( $arg_wrapper );
				$args       = $arg_struct['args'];
				wp_unschedule_event( $timestamp, $hook, $arg_struct['args'] );
				if ( 'run' === $execute ) {
					// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
					call_user_func_array( 'do_action', array_merge( array( $hook ), $args ) );
				}
			}
		}
	}

	/**
	 * Assert that the number of published sitemaps matches the expected count.
	 *
	 * @param int $expected The expected number of sitemaps.
	 */
	protected function assertSitemapCount( $expected ) {
		$this->assertEquals( $expected, wp_count_posts( 'msm_sitemap' )->publish );
	}

	/**
	 * Assert that the number of posts matches the expected count.
	 *
	 * @param int $expected The expected number of posts.
	 */
	protected function assertPostCount( $expected ) {
		$this->assertCount( $expected, $this->posts );
	}

	/**
	 * Assert that the number of indexed URLs matches the expected count.
	 *
	 * @param int $expected The expected number of indexed URLs.
	 */
	protected function assertIndexedUrlCount( $expected ) {
		$this->assertEquals( $expected, (int) get_option( 'msm_sitemap_indexed_url_count', 0 ) );
	}

	/**
	 * Assert that the stats are correct for each individual created post
	 */
	protected function assertStatsForCreatedPosts(): bool {
		return $this->check_stats_for_created_posts();
	}

	/**
	 * Generate a sitemap for a specific date using the proper DDD services.
	 *
	 * @param string $sitemap_date The sitemap date (YYYY-MM-DD format).
	 * @param bool   $force Whether to force regeneration even if sitemap exists.
	 * @return bool True if sitemap was generated or already exists, false if generation failed.
	 */
	protected function generate_sitemap_for_date( string $sitemap_date, bool $force = false ): bool {
		$service = $this->get_service( SitemapService::class );

		// Convert datetime format to date format (YYYY-MM-DD HH:MM:SS -> YYYY-MM-DD) if needed
		$date = substr( $sitemap_date, 0, 10 );

		$result = $service->create_for_date( $date, $force );
		return $result->is_success();
	}

	/**
	 * Get a sitemap post ID by year, month, and day using the repository.
	 *
	 * @param int|string $year The year.
	 * @param int|string $month The month.
	 * @param int|string $day The day.
	 * @return int|false The post ID if found, false otherwise.
	 */
	protected function get_sitemap_post_id( $year, $month, $day ) {
		$date       = \Automattic\MSM_Sitemap\Domain\Utilities\DateUtility::format_date_stamp( (int) $year, (int) $month, (int) $day );
		$repository = $this->get_service( SitemapRepositoryInterface::class );
		$post_id    = $repository->find_by_date( $date );
		
		return $post_id ? $post_id : false;
	}
}

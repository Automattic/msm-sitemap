<?php
/**
 * WP_Test_Sitemap_Filter Query
 *
 * @package Metro_Sitemap/unit_tests
 */

require_once( 'msm-sitemap-test.php' );

/**
 * Unit Tests to validate Filters applied when generating Sitemaps
 *
 * @author Matthew Denton (mdbitz)
 */
class WP_Test_Sitemap_Filter extends WP_UnitTestCase {

	/**
	 * Humber of Posts to Create (1 per day)
	 *
	 * @var Integer
	 */
	private $num_days = 7;
	
	/**
	 * Base Test Class Instance
	 *
	 * @var MSM_SIteMap_Test
	 */
	private $test_base;

	/**
	 * Generate posts and build initial sitemaps
	 */
	function setup() {
		_delete_all_posts();

		$this->test_base = new MSM_SiteMap_Test();

		// Create posts for the last num_days days.
		$dates = array();
		$date = time();
		for ( $i = 0; $i < $this->num_days; $i++ ) {
			$date = strtotime( '-1 day', $date );
			$dates[] = date( 'Y', $date ) . '-' . date( 'm', $date ) . '-' . date( 'd', $date );
		}

		$this->test_base->create_dummy_posts( $dates );

		$this->assertCount( $this->num_days, $this->test_base->posts );
		$this->test_base->build_sitemaps();
	}

	/**
	 * Remove created posts, Sitemaps and options
	 */
	function teardown() {
		$this->test_base->posts = array();
		$sitemaps = get_posts( array(
			'post_type' => Metro_Sitemap::SITEMAP_CPT,
			'fields' => 'ids',
			'posts_per_page' => -1,
		) );
		update_option( 'msm_sitemap_indexed_url_count' , 0 );
		array_map( 'wp_delete_post', array_merge( $this->test_base->posts_created, $sitemaps ) );
	}

	/**
	 * Verify that request for sitemap url doesn't cause Main Query to hit db.
	 */
	function test_bypass_main_query() {
		global $wp_query;
		
		// Verify post_pre_query on sitemap queryvar returns empty array
		set_query_var( 'sitemap', 'true' );
		$posts = apply_filters_ref_array( 'posts_pre_query', array( null, $wp_query ) );
		$this->assertInternalType('array', $posts);
		$this->assertEmpty( $posts );
		
	}
	
	/**
	 * Verify that secondary query is not get modified if sitemap var is set.
	 */
	function test_secondary_query_not_bypassed() {		
		
		// Verify post_pre_query filter returns null by default
		$exp_result = array(1);
		
		$query = new WP_Query( array(
			'post_type' => 'post',
			'sitemap' => 'true'
		) );
		$sitemap_posts = apply_filters_ref_array( 'posts_pre_query', array( $exp_result, $query ) );
		$this->assertEquals($exp_result, $sitemap_posts, 'Non-Main WP_Query is being modified from sitemap query var');
		
	}

}


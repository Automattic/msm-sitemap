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

	/**
	 * Verify that msm_sitemap_index filter runs when build_root_sitemap_xml is called.
	 */
	function test_msm_sitemap_index_filter_ran() {
		$ran = false;

		add_filter( 'msm_sitemap_index', function() use ( &$ran ) { $ran = true; return []; } );
		Metro_Sitemap::build_root_sitemap_xml();

		$this->assertTrue( $ran );
	}
}


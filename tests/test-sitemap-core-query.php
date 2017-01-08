<?php
/**
 * WP_Test_Sitemap_Core Query
 *
 * @package Metro_Sitemap/unit_tests
 */

require_once( 'msm-sitemap-test.php' );

/**
 * Unit Tests to validate WordPress Main Query is bypassed when generating Sitemaps
 *
 * @author Matthew Denton (mdbitz)
 */
class WP_Test_Sitemap_Core_Query extends WP_UnitTestCase {

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
		
		// Verify Main Query not modified if not hitting Sitemap
		$this->go_to('/');
		$num_posts = count($wp_query->posts);
		$this->assertEquals( $this->num_days, $num_posts );
		
		// Verify Main Query has no posts when querrying sitemap
		$this->go_to('/?sitemap=true');
		$num_posts = count( $wp_query->posts);
		$this->assertEquals( 0, $num_posts );
		
	}

}


<?php
/**
 * WP_Test_Sitemap_Cron
 *
 * @package Metro_Sitemap/unit_tests
 */

require_once( 'msm-sitemap-test.php' );

/**
 * Unit Tests to confirm Sitemaps are generated.
 *
 * @author michaelblouin
 * @author Matthew Denton (mdbitz)
 */
class WP_Test_Sitemap_Cron extends WP_UnitTestCase {

	/**
	 * Humber of Posts to Create (1 per day)
	 *
	 * @var Integer
	 */
	private $num_days = 4;

	/**
	 * Base Test Class Instance
	 *
	 * @var MSM_SIteMap_Test
	 */
	private $test_base;

	/**
	 * Generate posts and build the sitemap
	 */
	function setup() {
		if ( ! class_exists( 'MSM_Sitemap_Builder_Cron' ) ) {
			require dirname( dirname( __FILE__ ) ) . '/includes/msm-sitemap-builder-cron.php';
			MSM_Sitemap_Builder_Cron::setup();
		}

		$this->test_base = new MSM_SiteMap_Test();

		// Create posts for the last num_days days.
		$dates = array();
		$date = time();
		for ( $i = 0; $i < $this->num_days; $i++ ) {
			$date = strtotime( "-1 day", $date );
			$dates[] = date( 'Y', $date ) . '-' . date( 'm', $date ) . '-' . date( 'd', $date );
		}

		$this->test_base->create_dummy_posts( $dates );
		$this->assertCount( $this->num_days, $this->test_base->posts );
				$this->test_base->build_sitemaps();
	}

	/**
	 * Remove the sample posts and the sitemap posts
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

}

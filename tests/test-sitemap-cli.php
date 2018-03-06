<?php
/**
 * WP_Test_Sitemap_CLI
 *
 * @package Metro_Sitemap/unit_tests
 */

require_once( 'msm-sitemap-test.php' );

require_once( dirname( __FILE__ ) . '/includes/mock-wp-cli.php' );
require_once( dirname( __FILE__ ) . '/../includes/wp-cli.php' );

/**
 * Unit Tests to validate CLI command
 *
 * @author davidbinovec
 */
class WP_Test_Sitemap_CLI extends WP_UnitTestCase {

	/**
	 * Create posts across a number of years
	 *
	 * @var int
	 */
	private $num_years_data = 3;

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

		// Add a post for each day in the last x years.
		$dates = array();
		$date = time();
		for ( $i = 0; $i < $this->num_years_data; $i++ ) {
			// Add a post for x years ago.
			$dates[] = date( 'Y', $date ) . '-' . date( 'm', $date ) . '-' . date( 'd', $date ) . ' 00:00:00';
			$date = strtotime( '-1 year', $date );
		}

		$this->test_base->create_dummy_posts( $dates );

		$this->assertCount( $this->num_years_data, $this->test_base->posts );
		$this->test_base->build_sitemaps();
	}

	/**
	 * Remove created posts, sitemaps and options
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
	 * Call protected/private method of a class.
	 *
	 * @param object $object    Instantiated object that we will run method on.
	 * @param string $method_name Method name to call.
	 * @param array  $parameters Array of parameters to pass into method.
	 *
	 * @return mixed Method return.
	 */
	public function invoke_method( &$object, $method_name, array $parameters = array() ) {
		$reflection = new \ReflectionClass( get_class( $object ) );
		$method = $reflection->getMethod( $method_name );
		$method->setAccessible( true );

		return $method->invokeArgs( $object, $parameters );
	}

	/**
	 * Provides test data for calculating maximum number of days in year.
	 *
	 * @return array
	 */
	public function dates_provider() {
		return array(
			array( 2, 2016, 29 ),
			array( 2, 2017, 28 ),
			array( 1, 2015, 31 ),
			array( 3, 2014, 31 ),
			array( 4, 2012, 30 ),
			array( 12, 2011, 31 ),
			array( 11, 2010, 30 ),
		);
	}

	/**
	 * Test that robots.txt has sitemap indexes for all years when sitemaps by year are enabled
	 *
	 * @dataProvider dates_provider
	 * @param $month Month in a year.
	 * @param $year Year.
	 * @param $expected Expected number of days in given month/year.
	 */
	public function test_cal_days_in_month( $month, $year, $expected ) {

		$cli = new Metro_Sitemap_CLI();

		$max_days = $this->invoke_method( $cli, 'cal_days_in_month', array( $month, $year ) );

		// Check that we've indexed the proper total number of URLs.
		$this->assertEquals( $expected, $max_days );
	}
}


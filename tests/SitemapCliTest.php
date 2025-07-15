<?php
/**
 * SitemapCliTest
 *
 * @package Metro_Sitemap/unit_tests
 */

namespace Automattic\MSM_Sitemap\Tests;

require_once __DIR__ . '/Includes/mock-wp-cli.php';
require_once __DIR__ . '/../includes/wp-cli.php';

/**
 * Validate CLI command.
 */
final class SitemapCliTest extends TestCase {

	/**
	 * Create posts across some years
	 *
	 * @var int
	 */
	private $num_years_data = 3;

	/**
	 * Generate posts and build initial sitemaps
	 */
	public function setUp(): void {
		parent::setUp();
		$this->add_a_post_for_each_of_the_last_x_years( $this->num_years_data );

		$this->assertPostCount( $this->num_years_data );
		$this->build_sitemaps();
	}

	/**
	 * Call a protected/private method of a class.
	 *
	 * @param object $object      Instantiated object that we will run the method on.
	 * @param string $method_name Method name to call.
	 * @param array  $parameters  Array of parameters to pass into the method.
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
	 * Provides test data for calculating the maximum number of days in a month.
	 *
	 * @return array
	 */
	public function dates_provider(): iterable {
		yield 'Regular January' => array(
			'month' => 1,
			'year' => 2025,
			'days' => 31
		);

		yield 'Regular February' => array(
			'month' => 2,
			'year' => 2025,
			'days' => 28
		);

		yield 'Leap Year February' => array(
			'month' => 2,
			'year' => 2024,
			'days' => 29
		);

		yield 'Regular April' => array(
			'month' => 4,
			'year' => 2025,
			'days' => 30
		);
	}

	/**
	 * Test that the correct number of days is used for a month/year.
	 *
	 * @dataProvider dates_provider
	 * @param $month    Month in a year.
	 * @param $year     Year.
	 * @param $expected Expected number of days in given month/year.
	 */
	public function test_cal_days_in_month( $month, $year, $expected ) {

		$cli = new \Metro_Sitemap_CLI();

		$max_days = $this->invoke_method( $cli, 'cal_days_in_month', array( $month, $year ) );

		// Check that we've indexed the proper total number of URLs.
		$this->assertEquals( $expected, $max_days );
	}
}


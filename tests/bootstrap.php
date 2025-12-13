<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package Automattic\MSM_Sitemap
 */

declare(strict_types=1);

namespace Automattic\MSM_Sitemap\Tests;

use Yoast\WPTestUtils\WPIntegration;

require_once dirname( __DIR__ ) . '/vendor/yoast/wp-test-utils/src/WPIntegration/bootstrap-functions.php';

$_tests_dir = WPIntegration\get_path_to_wp_test_dir();

if ( empty( $_tests_dir ) ) {
	echo 'ERROR: Could not find WordPress test library directory.' . PHP_EOL;
	echo 'Make sure wp-env is running: npm run wp-env start' . PHP_EOL;
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin(): void {
	require dirname( __DIR__ ) . '/msm-sitemap.php';
}

\tests_add_filter( 'muplugins_loaded', __NAMESPACE__ . '\\_manually_load_plugin' );

// Make sure the Composer autoload file has been generated.
WPIntegration\check_composer_autoload_exists();

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';

/*
 * Register the custom autoloader to overload the PHPUnit MockObject classes when running on PHP 8.
 *
 * This function has to be called _last_, after the WP test bootstrap to make sure it registers
 * itself in FRONT of the Composer autoload (which also prepends itself to the autoload queue).
 */
WPIntegration\register_mockobject_autoloader();

// Add custom test case.
require __DIR__ . '/TestCase.php';

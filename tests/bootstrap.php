<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package Automattic\MSM_Sitemap
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests;

use Yoast\WPTestUtils\WPIntegration;

// Composer autoloader.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Check for a `--testsuite unit` arg when calling phpunit.
$argv_local = $GLOBALS['argv'] ?? array();
$key        = (int) array_search( '--testsuite', $argv_local, true );
$is_unit    = false;

// Check for --testsuite unit (two separate args).
if ( $key && isset( $argv_local[ $key + 1 ] ) && 'unit' === $argv_local[ $key + 1 ] ) {
	$is_unit = true;
}

// Check for --testsuite=unit (single arg with equals).
foreach ( $argv_local as $arg ) {
	if ( '--testsuite=unit' === $arg ) {
		$is_unit = true;
		break;
	}
}

if ( $is_unit ) {
	// Unit tests don't need WordPress - just load the test case.
	require_once __DIR__ . '/Unit/TestCase.php';
	return;
}

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
	// Load mock WP-CLI classes before the plugin to ensure they're available
	// when the autoloader tries to load CLI classes
	require_once __DIR__ . '/Includes/mock-wp-cli.php';

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

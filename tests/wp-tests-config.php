<?php
/**
 * WordPress test suite config.
 *
 * Loads the config that wp-env generates, then gives the tests their own
 * table prefix. wp-env runs as a single environment, so the tests share the
 * development site's database, and the test suite drops and reinstalls every
 * core table with its prefix before each run. A separate prefix leaves the
 * development site's tables alone.
 *
 * @package Automattic\MSM_Sitemap
 */

require getenv( 'WP_TESTS_DIR' ) . '/wp-tests-config.php';

$table_prefix = 'wptests_'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- The test suite reads it from this file.

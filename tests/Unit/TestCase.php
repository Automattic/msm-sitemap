<?php
/**
 * Unit test case base class.
 *
 * @package Automattic\MSM_Sitemap\Tests\Unit
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Unit;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Base test case class for unit tests.
 *
 * Unit tests should not require WordPress and should test
 * individual classes/methods in isolation.
 */
abstract class TestCase extends PHPUnitTestCase {
	// Add shared setup/teardown methods for unit tests here.
}

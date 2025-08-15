<?php
/**
 * NotificationsTest
 *
 * @package Automattic\MSM_Sitemap\Tests
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Admin;

use Automattic\MSM_Sitemap\Infrastructure\WordPress\Admin\Notifications;

/**
 * Unit Tests for Admin\Notifications class
 */
class NotificationsTest extends \Automattic\MSM_Sitemap\Tests\TestCase {

	/**
	 * Set up the test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		// Clear any existing output
		ob_clean();
	}

	/**
	 * Clean up after tests.
	 */
	public function tearDown(): void {
		// Clean up any output
		ob_clean();
		parent::tearDown();
	}

	/**
	 * Test that show_success() renders success notification.
	 */
	public function test_show_success(): void {
		ob_start();
		Notifications::show_success( 'Test success message' );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Test success message', $output );
		$this->assertStringContainsString( 'notice-success', $output );
		$this->assertStringContainsString( 'notice', $output );
	}

	/**
	 * Test that show_error() renders error notification.
	 */
	public function test_show_error(): void {
		ob_start();
		Notifications::show_error( 'Test error message' );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Test error message', $output );
		$this->assertStringContainsString( 'notice-error', $output );
		$this->assertStringContainsString( 'notice', $output );
	}

	/**
	 * Test that show_warning() renders warning notification.
	 */
	public function test_show_warning(): void {
		ob_start();
		Notifications::show_warning( 'Test warning message' );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Test warning message', $output );
		$this->assertStringContainsString( 'notice-warning', $output );
		$this->assertStringContainsString( 'notice', $output );
	}

	/**
	 * Test that show_info() renders info notification.
	 */
	public function test_show_info(): void {
		ob_start();
		Notifications::show_info( 'Test info message' );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Test info message', $output );
		$this->assertStringContainsString( 'notice-info', $output );
		$this->assertStringContainsString( 'notice', $output );
	}

	/**
	 * Test that render_notification() renders correct HTML structure.
	 */
	public function test_render_notification_structure(): void {
		ob_start();
		Notifications::show_success( 'Test message' );
		$output = ob_get_clean();

		// Check for proper HTML structure
		$this->assertStringContainsString( '<div', $output );
		$this->assertStringContainsString( 'class="notice notice-success', $output );
		$this->assertStringContainsString( '<p>Test message</p>', $output );
		$this->assertStringContainsString( '</div>', $output );
	}

	/**
	 * Test that multiple notifications can be displayed.
	 */
	public function test_multiple_notifications(): void {
		ob_start();
		Notifications::show_success( 'Success message' );
		Notifications::show_error( 'Error message' );
		Notifications::show_warning( 'Warning message' );
		Notifications::show_info( 'Info message' );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Success message', $output );
		$this->assertStringContainsString( 'Error message', $output );
		$this->assertStringContainsString( 'Warning message', $output );
		$this->assertStringContainsString( 'Info message', $output );
		$this->assertStringContainsString( 'notice-success', $output );
		$this->assertStringContainsString( 'notice-error', $output );
		$this->assertStringContainsString( 'notice-warning', $output );
		$this->assertStringContainsString( 'notice-info', $output );
	}

	/**
	 * Test that notifications can be displayed without errors.
	 */
	public function test_notifications_display_without_errors(): void {
		// This should not throw any errors
		Notifications::show_success( 'Test message' );
		Notifications::show_error( 'Test message' );
		Notifications::show_warning( 'Test message' );
		Notifications::show_info( 'Test message' );
		$this->assertTrue( true ); // If we get here, all methods worked
	}
} 

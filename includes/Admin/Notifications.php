<?php
/**
 * Admin Notifications Handler
 *
 * @package Automattic\MSM_Sitemap\Admin
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Admin;

/**
 * Handles admin notifications for the MSM Sitemap plugin.
 *
 * Provides convenience methods for displaying different types of notifications
 * following WordPress core standards for admin notices.
 */
class Notifications {
	/**
	 * Show a success notification.
	 *
	 * @param string $message The message to display.
	 */
	public static function show_success( string $message ): void {
		self::render_notification( $message, 'success' );
	}

	/**
	 * Show an error notification.
	 *
	 * @param string $message The message to display.
	 */
	public static function show_error( string $message ): void {
		self::render_notification( $message, 'error' );
	}

	/**
	 * Show a warning notification.
	 *
	 * @param string $message The message to display.
	 */
	public static function show_warning( string $message ): void {
		self::render_notification( $message, 'warning' );
	}

	/**
	 * Show an info notification.
	 *
	 * @param string $message The message to display.
	 */
	public static function show_info( string $message ): void {
		self::render_notification( $message, 'info' );
	}

	/**
	 * Render a notification with proper WordPress admin notice markup.
	 *
	 * @param string $message The message to display.
	 * @param string $type    The notification type (success, error, warning, info).
	 */
	private static function render_notification( string $message, string $type ): void {
		$class = 'notice notice-' . $type;
		printf(
			'<div class="%1$s"><p>%2$s</p></div>',
			esc_attr( $class ),
			wp_kses( $message, wp_kses_allowed_html( 'post' ) )
		);
	}
} 

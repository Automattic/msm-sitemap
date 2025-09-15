<?php
/**
 * Event Dispatcher
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\Events
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Infrastructure\Events;

/**
 * Simple event dispatcher for managing event listeners.
 */
class EventDispatcher {

	/**
	 * Array of event listeners, indexed by event class name.
	 *
	 * @var array<string, callable[]>
	 */
	private array $listeners = array();

	/**
	 * Add a listener for a specific event type.
	 *
	 * @param string   $event_class The fully qualified class name of the event.
	 * @param callable $listener    The listener function or method to call.
	 * @return void
	 */
	public function add_listener( string $event_class, callable $listener ): void {
		if ( ! isset( $this->listeners[ $event_class ] ) ) {
			$this->listeners[ $event_class ] = array();
		}

		$this->listeners[ $event_class ][] = $listener;
	}

	/**
	 * Dispatch an event to all registered listeners.
	 *
	 * @param object $event The event object to dispatch.
	 * @return void
	 */
	public function dispatch( object $event ): void {
		$event_class = get_class( $event );

		if ( ! isset( $this->listeners[ $event_class ] ) ) {
			return;
		}

		foreach ( $this->listeners[ $event_class ] as $listener ) {
			try {
				$listener( $event );
			} catch ( \Exception $e ) {
				// Log the error but don't stop other listeners
				error_log(
					sprintf(
						'Error in event listener for %s: %s',
						$event_class,
						$e->getMessage()
					)
				);
			}
		}
	}
}

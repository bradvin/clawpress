<?php
/**
 * Null transport implementation.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Transports;

defined( 'ABSPATH' ) || exit;

/**
 * Transport that intentionally drops all events.
 */
final class Null_Transport implements Agent_Transport {
	/**
	 * Emit one event.
	 *
	 * @param array<string,mixed> $event Event payload.
	 */
	public function emit( array $event ): void {
		unset( $event );
	}

	/**
	 * Close transport.
	 */
	public function close(): void {}
}

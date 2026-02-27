<?php
/**
 * Agent transport contract.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Transports;

defined( 'ABSPATH' ) || exit;

/**
 * Transport interface for runtime event delivery.
 */
interface Agent_Transport {
	/**
	 * Emit a runtime event.
	 *
	 * @param array<string,mixed> $event Event payload.
	 */
	public function emit( array $event ): void;

	/**
	 * Close transport resources.
	 */
	public function close(): void;
}

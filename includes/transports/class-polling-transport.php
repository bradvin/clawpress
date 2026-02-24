<?php
/**
 * Polling transport implementation.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Transports;

use ClawPress\Helpers\Agent_Event_Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Append-only polling transport backed by agent events.
 */
final class Polling_Transport implements Agent_Transport {
	/**
	 * Event helper.
	 *
	 * @var Agent_Event_Helper
	 */
	private Agent_Event_Helper $event_helper;

	/**
	 * Run id.
	 *
	 * @var int
	 */
	private int $run_id;

	/**
	 * Session id.
	 *
	 * @var int
	 */
	private int $session_id;

	/**
	 * Last emitted event id.
	 *
	 * @var int
	 */
	private int $last_event_id = 0;

	/**
	 * Constructor.
	 *
	 * @param int $run_id Run ID.
	 * @param int $session_id Session ID.
	 */
	public function __construct( int $run_id, int $session_id ) {
		$this->event_helper = Agent_Event_Helper::get_instance();
		$this->run_id       = $run_id;
		$this->session_id   = $session_id;
	}

	/**
	 * Emit one event.
	 *
	 * @param array<string,mixed> $event Event payload.
	 */
	public function emit( array $event ): void {
		$event_type = isset( $event['type'] ) ? (string) $event['type'] : 'agent.event';
		$payload    = isset( $event['payload'] ) && is_array( $event['payload'] )
			? $event['payload']
			: [];

		$event_id = $this->event_helper->emit(
			$event_type,
			[
				'run_id'     => $this->run_id,
				'session_id' => $this->session_id,
				'payload'    => $payload,
			]
		);

		if ( $event_id > 0 ) {
			$this->last_event_id = $event_id;
		}
	}

	/**
	 * Close transport.
	 */
	public function close(): void {}

	/**
	 * Get last emitted event id.
	 */
	public function get_last_event_id(): int {
		return $this->last_event_id;
	}
}

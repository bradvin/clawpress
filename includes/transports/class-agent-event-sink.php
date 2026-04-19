<?php
/**
 * Agent event sink.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Transports;

use ClawPress\Helpers\Agent_Event_Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Delivers runtime events to an optional live callback and optional persisted event log.
 */
final class Agent_Event_Sink {
	/**
	 * Optional live event callback.
	 *
	 * @var callable|null
	 */
	private $live_event_callback;

	/**
	 * Optional event helper for persisted events.
	 *
	 * @var Agent_Event_Helper|null
	 */
	private ?Agent_Event_Helper $event_helper = null;

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
	 * Last persisted event id.
	 *
	 * @var int
	 */
	private int $last_event_id = 0;

	/**
	 * Whether token deltas should also be persisted.
	 *
	 * @var bool
	 */
	private bool $persist_delta_events;

	/**
	 * Constructor.
	 *
	 * @param callable|null $live_event_callback Optional live event callback.
	 * @param int           $run_id Run ID.
	 * @param int           $session_id Session ID.
	 * @param bool          $persist_delta_events Whether token deltas should be persisted.
	 */
	public function __construct( ?callable $live_event_callback = null, int $run_id = 0, int $session_id = 0, bool $persist_delta_events = false ) {
		$this->live_event_callback = $live_event_callback;
		$this->run_id              = $run_id;
		$this->session_id          = $session_id;
		$this->persist_delta_events = $persist_delta_events;

		if ( $run_id > 0 || $session_id > 0 ) {
			$this->event_helper = Agent_Event_Helper::get_instance();
		}
	}

	/**
	 * Emit one runtime event.
	 *
	 * @param array<string,mixed> $event Event payload.
	 */
	public function emit( array $event ): void {
		if ( null !== $this->live_event_callback ) {
			call_user_func( $this->live_event_callback, $event );
		}

		if ( ! $this->should_persist_event( $event ) ) {
			return;
		}

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
	 * Get the last persisted event id.
	 */
	public function get_last_event_id(): int {
		return $this->last_event_id;
	}

	/**
	 * Close the sink.
	 */
	public function close(): void {}

	/**
	 * Determine whether an event should be persisted.
	 *
	 * @param array<string,mixed> $event Event payload.
	 */
	private function should_persist_event( array $event ): bool {
		if ( null === $this->event_helper ) {
			return false;
		}

		if ( $this->persist_delta_events ) {
			return true;
		}

		return 'agent.llm.delta' !== (string) ( $event['type'] ?? '' );
	}
}

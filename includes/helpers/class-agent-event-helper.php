<?php
/**
 * Agent event helper.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Helpers;

use ClawPress\Stores\Agent_Event_Store;

defined( 'ABSPATH' ) || exit;

/**
 * Helper for append-only agent run/session event emission and reads.
 */
final class Agent_Event_Helper {
	/**
	 * Singleton instance.
	 *
	 * @var ?self
	 */
	private static ?self $instance = null;

	/**
	 * Event store instance for DB access.
	 *
	 * @var Agent_Event_Store
	 */
	private Agent_Event_Store $store;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->store = Agent_Event_Store::get_instance();
	}

	/**
	 * Get singleton instance.
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Emit one append-only event.
	 *
	 * @param string              $event_type Event type name.
	 * @param array<string,mixed> $args Optional event payload.
	 */
	public function emit( string $event_type, array $args = [] ): int {
		$normalized_event_type = $this->sanitize_event_type( $event_type );
		if ( '' === $normalized_event_type ) {
			return 0;
		}

		$payload         = isset( $args['payload'] ) && is_array( $args['payload'] )
			? $args['payload']
			: [];
		$encoded_payload = null;

		if ( [] !== $payload ) {
			$payload_json = wp_json_encode( $payload );
			if ( false !== $payload_json ) {
				$encoded_payload = $payload_json;
			}
		}

		$run_id     = isset( $args['run_id'] ) ? (int) $args['run_id'] : 0;
		$session_id = isset( $args['session_id'] ) ? (int) $args['session_id'] : 0;

		return $this->store->insert_event(
			[
				'run_id'         => $run_id > 0 ? $run_id : null,
				'session_id'     => $session_id > 0 ? $session_id : null,
				'event_type'     => $normalized_event_type,
				'payload_json'   => $encoded_payload,
				'created_at_gmt' => isset( $args['created_at_gmt'] ) ? (string) $args['created_at_gmt'] : gmdate( 'Y-m-d H:i:s' ),
			]
		);
	}

	/**
	 * Emit a standardized tool-call event.
	 *
	 * @param string              $tool_name Tool name.
	 * @param string              $ability_name Ability name.
	 * @param int                 $requesting_user_id Requesting user ID.
	 * @param int                 $execution_user_id Execution user ID.
	 * @param string              $status Tool execution status.
	 * @param string              $args_hash Hash of tool arguments.
	 * @param array<string,mixed> $payload Tool result payload.
	 * @param array<string,mixed> $context Optional event context.
	 */
	public function emit_tool_call(
		string $tool_name,
		string $ability_name,
		int $requesting_user_id,
		int $execution_user_id,
		string $status,
		string $args_hash,
		array $payload,
		array $context = []
	): int {
		return $this->emit(
			'tool_call',
			[
				'run_id'     => isset( $context['run_id'] ) ? (int) $context['run_id'] : 0,
				'session_id' => isset( $context['session_id'] ) ? (int) $context['session_id'] : 0,
				'payload'    => [
					'tool_name'          => $tool_name,
					'ability_name'       => $ability_name,
					'status'             => strtolower( trim( sanitize_text_field( $status ) ) ),
					'args_hash'          => $args_hash,
					'success'            => ! empty( $payload['success'] ),
					'result'             => $payload['result'] ?? null,
					'error'              => $payload['error'] ?? null,
					'requesting_user_id' => $requesting_user_id > 0 ? $requesting_user_id : null,
					'execution_user_id'  => $execution_user_id > 0 ? $execution_user_id : null,
				],
			]
		);
	}

	/**
	 * Get run-scoped incremental events.
	 *
	 * @param int $run_id Run identifier.
	 * @param int $after_event_id Cursor of last delivered event.
	 * @param int $limit Maximum rows to return.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_run_events( int $run_id, int $after_event_id = 0, int $limit = 100 ): array {
		if ( $run_id <= 0 ) {
			return [];
		}

		return $this->normalize_event_rows(
			$this->store->get_events(
				[
					'run_id'         => $run_id,
					'after_event_id' => $after_event_id,
					'limit'          => $limit,
				]
			)
		);
	}

	/**
	 * Get session-scoped incremental events.
	 *
	 * @param int $session_id Session identifier.
	 * @param int $after_event_id Cursor of last delivered event.
	 * @param int $limit Maximum rows to return.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_session_events( int $session_id, int $after_event_id = 0, int $limit = 100 ): array {
		if ( $session_id <= 0 ) {
			return [];
		}

		return $this->normalize_event_rows(
			$this->store->get_events(
				[
					'session_id'     => $session_id,
					'after_event_id' => $after_event_id,
					'limit'          => $limit,
				]
			)
		);
	}

	/**
	 * Normalize one event row.
	 *
	 * @param array<string,mixed> $row DB row.
	 * @return array<string,mixed>
	 */
	private function normalize_event_row( array $row ): array {
		$payload = [];
		if ( isset( $row['payload_json'] ) && is_string( $row['payload_json'] ) && '' !== trim( $row['payload_json'] ) ) {
			$decoded = json_decode( $row['payload_json'], true );
			if ( is_array( $decoded ) ) {
				$payload = $decoded;
			}
		}

		return [
			'event_id'       => isset( $row['id'] ) ? (int) $row['id'] : 0,
			'run_id'         => isset( $row['run_id'] ) && null !== $row['run_id'] ? (int) $row['run_id'] : null,
			'session_id'     => isset( $row['session_id'] ) && null !== $row['session_id'] ? (int) $row['session_id'] : null,
			'event_type'     => isset( $row['event_type'] ) ? (string) $row['event_type'] : 'event',
			'payload'        => $payload,
			'created_at_gmt' => isset( $row['created_at_gmt'] ) ? (string) $row['created_at_gmt'] : '',
		];
	}

	/**
	 * Normalize DB rows into API-facing event payloads.
	 *
	 * @param array<int,array<string,mixed>> $rows Raw DB rows.
	 * @return array<int,array<string,mixed>>
	 */
	private function normalize_event_rows( array $rows ): array {
		return array_values(
			array_map( [ $this, 'normalize_event_row' ], $rows )
		);
	}

	/**
	 * Normalize event type.
	 *
	 * @param string $event_type Raw event type.
	 */
	private function sanitize_event_type( string $event_type ): string {
		$event_type = strtolower( trim( sanitize_text_field( $event_type ) ) );
		$event_type = preg_replace( '/[^a-z0-9._:-]/', '', $event_type );
		return '' !== (string) $event_type ? (string) $event_type : '';
	}
}

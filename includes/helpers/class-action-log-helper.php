<?php
/**
 * Action/event logging helper.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Helpers;

use ClawPress\Stores\Action_Log_Store;

defined( 'ABSPATH' ) || exit;

/**
 * Central action log helper for writing/querying action/event records.
 */
final class Action_Log_Helper {
	/**
	 * Supported log status values.
	 *
	 * @var array<int,string>
	 */
	private const SUPPORTED_STATUSES = [ 'debug', 'info', 'success', 'warning', 'error' ];

	/**
	 * Singleton instance.
	 *
	 * @var ?self
	 */
	private static ?self $instance = null;

	/**
	 * Store instance for DB access.
	 *
	 * @var Action_Log_Store
	 */
	private Action_Log_Store $store;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->store = Action_Log_Store::get_instance();
	}

	/**
	 * Register the generic tool-call listener.
	 */
	public static function register_tool_call_logging_hook(): void {
		add_action( 'clawpress_tool_call_logged', [ __CLASS__, 'handle_tool_call_logged' ] );
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
	 * Persist a summarized action-log row for a generic tool call.
	 *
	 * @param array<string,mixed> $event Generic tool-call event payload.
	 */
	public static function handle_tool_call_logged( array $event ): void {
		$tool_name          = isset( $event['tool_name'] ) ? sanitize_key( (string) $event['tool_name'] ) : '';
		$ability_name       = isset( $event['ability_name'] ) ? sanitize_text_field( (string) $event['ability_name'] ) : '';
		$status             = isset( $event['status'] ) ? sanitize_key( (string) $event['status'] ) : 'info';
		$args_hash          = isset( $event['args_hash'] ) ? sanitize_text_field( (string) $event['args_hash'] ) : '';
		$args               = isset( $event['args'] ) && is_array( $event['args'] ) ? $event['args'] : [];
		$payload            = isset( $event['payload'] ) && is_array( $event['payload'] ) ? $event['payload'] : [];
		$event_context      = isset( $event['event_context'] ) && is_array( $event['event_context'] ) ? $event['event_context'] : [];
		$requesting_user_id = isset( $event['requesting_user_id'] ) ? (int) $event['requesting_user_id'] : 0;
		$execution_user_id  = isset( $event['execution_user_id'] ) ? (int) $event['execution_user_id'] : 0;

		if ( '' === $tool_name ) {
			return;
		}

		self::get_instance()->log_event(
			$tool_name,
			[
				'event_type'         => 'tool_call',
				'status'             => $status,
				'message'            => self::build_tool_call_message( $tool_name, $payload ),
				'requesting_user_id' => $requesting_user_id,
				'execution_user_id'  => $execution_user_id,
				'context'            => self::build_tool_call_context(
					$tool_name,
					$ability_name,
					$args_hash,
					$args,
					$payload,
					$event_context,
					$requesting_user_id,
					$execution_user_id
				),
			]
		);
	}

	/**
	 * Persist one action/event log record.
	 *
	 * @param string              $action_name Action or command name.
	 * @param array<string,mixed> $args        Optional log payload.
	 */
	public function log_event( string $action_name, array $args = [] ): bool {
		$normalized_action_name = $this->sanitize_action_name( $action_name );
		if ( '' === $normalized_action_name ) {
			return false;
		}

		$event_type = $this->sanitize_event_type(
			isset( $args['event_type'] ) ? (string) $args['event_type'] : 'event'
		);
		$status     = $this->sanitize_status(
			isset( $args['status'] ) ? (string) $args['status'] : 'info'
		);
		$message    = isset( $args['message'] )
			? clawpress_sanitize_multiline_text( $args['message'] )
			: '';

		$context = isset( $args['context'] ) && is_array( $args['context'] )
			? $args['context']
			: [];

		$requesting_user_id = isset( $args['requesting_user_id'] ) ? (int) $args['requesting_user_id'] : 0;
		if ( $requesting_user_id <= 0 ) {
			$requesting_user_id = (int) get_current_user_id();
		}

		$execution_user_id = isset( $args['execution_user_id'] ) ? (int) $args['execution_user_id'] : 0;

		$encoded_context = null;
		if ( [] !== $context ) {
			$context_json = wp_json_encode( $context );
			if ( false !== $context_json ) {
				$encoded_context = $context_json;
			}
		}

		return $this->store->insert_log(
			[
				'event_type'         => $event_type,
				'action_name'        => $normalized_action_name,
				'status'             => $status,
				'message'            => '' !== $message ? $message : null,
				'requesting_user_id' => $requesting_user_id > 0 ? $requesting_user_id : null,
				'execution_user_id'  => $execution_user_id > 0 ? $execution_user_id : null,
				'context'            => $encoded_context,
			]
		);
	}

	/**
	 * Fetch recent log rows.
	 *
	 * @param array<string,mixed> $args Optional query filters.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_recent_logs( array $args = [] ): array {
		$limit  = isset( $args['limit'] ) ? (int) $args['limit'] : 50;
		$offset = isset( $args['offset'] ) ? (int) $args['offset'] : 0;
		$limit  = $limit > 0 ? min( $limit, 500 ) : 50;
		$offset = $offset >= 0 ? $offset : 0;

		$query_args = [
			'limit'  => $limit,
			'offset' => $offset,
		];

		if ( isset( $args['event_type'] ) && '' !== trim( (string) $args['event_type'] ) ) {
			$event_type = $this->sanitize_event_type( (string) $args['event_type'] );
			if ( '' !== $event_type ) {
				$query_args['event_type'] = $event_type;
			}
		}

		if ( isset( $args['status'] ) ) {
			$status = $this->sanitize_status( (string) $args['status'] );
			if ( '' !== $status ) {
				$query_args['status'] = $status;
			}
		}

		if ( isset( $args['requesting_user_id'] ) && (int) $args['requesting_user_id'] > 0 ) {
			$query_args['requesting_user_id'] = (int) $args['requesting_user_id'];
		}

		if ( isset( $args['execution_user_id'] ) && (int) $args['execution_user_id'] > 0 ) {
			$query_args['execution_user_id'] = (int) $args['execution_user_id'];
		}

		$rows = $this->store->get_recent_logs( $query_args );

		return array_values(
			array_map( [ $this, 'normalize_log_row' ], $rows )
		);
	}

	/**
	 * Fetch grouped event-type counts.
	 *
	 * @return array<string,int>
	 */
	public function get_log_counts_by_type(): array {
		$rows   = $this->store->get_log_counts_by_type();
		$counts = [];

		foreach ( $rows as $row ) {
			$event_type = isset( $row['event_type'] ) ? $this->sanitize_event_type( (string) $row['event_type'] ) : '';
			if ( '' === $event_type ) {
				continue;
			}

			$counts[ $event_type ] = max( 0, (int) ( $row['total'] ?? 0 ) );
		}

		return $counts;
	}

	/**
	 * Fetch total count for one event type or all logs.
	 *
	 * @param string $event_type Optional event type filter.
	 */
	public function get_log_count( string $event_type = '' ): int {
		$normalized_event_type = '' !== trim( $event_type )
			? $this->sanitize_event_type( $event_type )
			: '';

		return $this->store->get_log_count( $normalized_event_type );
	}

	/**
	 * Delete all action logs.
	 */
	public function delete_all_logs(): int {
		return $this->store->delete_all_logs();
	}

	/**
	 * Normalize one log row payload.
	 *
	 * @param array<string,mixed> $row Database row.
	 * @return array<string,mixed>
	 */
	private function normalize_log_row( array $row ): array {
		$context = [];
		if ( isset( $row['context'] ) && is_string( $row['context'] ) && '' !== trim( $row['context'] ) ) {
			$decoded = json_decode( $row['context'], true );
			if ( is_array( $decoded ) ) {
				$context = $decoded;
			}
		}

		return [
			'id'                 => isset( $row['id'] ) ? (int) $row['id'] : 0,
			'event_type'         => isset( $row['event_type'] ) ? (string) $row['event_type'] : 'event',
			'action_name'        => isset( $row['action_name'] ) ? (string) $row['action_name'] : '',
			'status'             => isset( $row['status'] ) ? (string) $row['status'] : 'info',
			'message'            => isset( $row['message'] ) ? (string) $row['message'] : '',
			'requesting_user_id' => isset( $row['requesting_user_id'] ) && null !== $row['requesting_user_id']
				? (int) $row['requesting_user_id']
				: null,
			'execution_user_id'  => isset( $row['execution_user_id'] ) && null !== $row['execution_user_id']
				? (int) $row['execution_user_id']
				: null,
			'context'            => $context,
			'created_at'         => isset( $row['created_at'] ) ? (string) $row['created_at'] : '',
		];
	}

	/**
	 * Build a human-readable message for a tool call summary row.
	 *
	 * @param string              $tool_name Tool name.
	 * @param array<string,mixed> $payload Tool execution payload.
	 */
	private static function build_tool_call_message( string $tool_name, array $payload ): string {
		$error_message = isset( $payload['error']['message'] ) ? trim( (string) $payload['error']['message'] ) : '';

		if ( ! empty( $payload['requires_confirmation'] ) ) {
			return sprintf(
				/* translators: %s: tool name */
				__( 'Tool %s requires confirmation.', 'clawpress' ),
				$tool_name
			);
		}

		if ( ! empty( $payload['success'] ) && ! empty( $payload['degraded'] ) ) {
			return sprintf(
				/* translators: %s: tool name */
				__( 'Tool %s completed with degraded output.', 'clawpress' ),
				$tool_name
			);
		}

		if ( ! empty( $payload['success'] ) ) {
			return sprintf(
				/* translators: %s: tool name */
				__( 'Tool %s completed successfully.', 'clawpress' ),
				$tool_name
			);
		}

		if ( '' !== $error_message ) {
			return sprintf(
				/* translators: 1: tool name, 2: error message */
				__( 'Tool %1$s failed: %2$s', 'clawpress' ),
				$tool_name,
				$error_message
			);
		}

		if ( isset( $payload['policy'] ) && is_array( $payload['policy'] ) ) {
			return sprintf(
				/* translators: %s: tool name */
				__( 'Tool %s was blocked by policy.', 'clawpress' ),
				$tool_name
			);
		}

		return sprintf(
			/* translators: %s: tool name */
			__( 'Tool %s failed.', 'clawpress' ),
			$tool_name
		);
	}

	/**
	 * Build normalized context for a generic tool-call log row.
	 *
	 * @param string              $tool_name Tool name.
	 * @param string              $ability_name Ability name.
	 * @param string              $args_hash Arguments hash.
	 * @param array<string,mixed> $args Tool arguments.
	 * @param array<string,mixed> $payload Tool execution payload.
	 * @param array<string,mixed> $event_context Event context.
	 * @param int                 $requesting_user_id Requesting user ID.
	 * @param int                 $execution_user_id Execution user ID.
	 * @return array<string,mixed>
	 */
	private static function build_tool_call_context(
		string $tool_name,
		string $ability_name,
		string $args_hash,
		array $args,
		array $payload,
		array $event_context,
		int $requesting_user_id,
		int $execution_user_id
	): array {
		$context = [
			'tool'               => $tool_name,
			'ability'            => $ability_name,
			'args_hash'          => $args_hash,
			'request'            => $args,
			'response'           => $payload,
			'requesting_user_id' => $requesting_user_id > 0 ? $requesting_user_id : null,
			'execution_user_id'  => $execution_user_id > 0 ? $execution_user_id : null,
			'run_id'             => isset( $event_context['run_id'] ) ? max( 0, (int) $event_context['run_id'] ) : 0,
			'session_id'         => isset( $event_context['session_id'] ) ? max( 0, (int) $event_context['session_id'] ) : 0,
			'requires_confirmation' => ! empty( $payload['requires_confirmation'] ),
			'degraded'           => ! empty( $payload['degraded'] ),
		];

		if ( isset( $payload['policy'] ) && is_array( $payload['policy'] ) ) {
			$context['policy'] = $payload['policy'];
		}

		if ( isset( $payload['error'] ) && is_array( $payload['error'] ) ) {
			$context['error'] = [
				'code'    => isset( $payload['error']['code'] ) ? (string) $payload['error']['code'] : '',
				'message' => isset( $payload['error']['message'] ) ? (string) $payload['error']['message'] : '',
			];
		}

		if ( isset( $payload['result'] ) && is_array( $payload['result'] ) ) {
			$result_keys = array_keys( $payload['result'] );
			if ( [] !== $result_keys ) {
				$context['result_keys'] = array_values(
					array_filter(
						array_map(
							static fn( $key ): string => is_string( $key ) ? sanitize_key( $key ) : '',
							$result_keys
						)
					)
				);
			}
		}

		if ( 'web_fetch' === $tool_name ) {
			$result              = isset( $payload['result'] ) && is_array( $payload['result'] ) ? $payload['result'] : [];
			$context['fetcher']  = isset( $result['fetcher'] ) && '' !== trim( (string) $result['fetcher'] )
				? (string) $result['fetcher']
				: ( isset( $args['fetcher'] ) ? strtolower( trim( (string) $args['fetcher'] ) ) : 'wp' );
			$context['url']      = isset( $result['url'] ) && '' !== trim( (string) $result['url'] )
				? (string) $result['url']
				: ( isset( $args['url'] ) ? trim( (string) $args['url'] ) : '' );
			$context['method']   = isset( $result['method'] ) && '' !== trim( (string) $result['method'] )
				? strtoupper( trim( (string) $result['method'] ) )
				: ( isset( $args['method'] ) ? strtoupper( trim( (string) $args['method'] ) ) : 'GET' );
			$context['status_code'] = isset( $result['status_code'] ) ? (int) $result['status_code'] : null;
			$context['truncated']   = ! empty( $result['truncated'] );
			$context['body_bytes']  = isset( $result['body_bytes'] ) ? (int) $result['body_bytes'] : null;
		}

		if ( 0 === $context['run_id'] ) {
			unset( $context['run_id'] );
		}

		if ( 0 === $context['session_id'] ) {
			unset( $context['session_id'] );
		}

		return $context;
	}

	/**
	 * Normalize event type.
	 *
	 * @param string $event_type Raw event type.
	 */
	private function sanitize_event_type( string $event_type ): string {
		$event_type = strtolower( trim( sanitize_text_field( $event_type ) ) );
		$event_type = preg_replace( '/[^a-z0-9._:-]/', '', $event_type );
		return '' !== (string) $event_type ? (string) $event_type : 'event';
	}

	/**
	 * Normalize action name.
	 *
	 * @param string $action_name Raw action name.
	 */
	private function sanitize_action_name( string $action_name ): string {
		$action_name = strtolower( trim( sanitize_text_field( $action_name ) ) );
		$action_name = preg_replace( '/[^a-z0-9._:\/-]/', '', $action_name );
		return (string) $action_name;
	}

	/**
	 * Normalize status string.
	 *
	 * @param string $status Raw status value.
	 */
	private function sanitize_status( string $status ): string {
		$status = strtolower( trim( sanitize_text_field( $status ) ) );

		if ( in_array( $status, self::SUPPORTED_STATUSES, true ) ) {
			return $status;
		}

		return 'info';
	}
}

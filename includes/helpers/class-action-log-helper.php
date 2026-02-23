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
	 */
	private Action_Log_Store $store;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->store = Action_Log_Store::get_instance();
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
	 * Resolve full action log table name.
	 */
	public function get_table_name(): string {
		return $this->store->get_table_name();
	}

	/**
	 * Create/update action log table schema.
	 */
	public function create_table(): bool {
		return $this->store->create_table();
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
		if ( $requesting_user_id <= 0 && function_exists( 'get_current_user_id' ) ) {
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

		if ( isset( $args['event_type'] ) ) {
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

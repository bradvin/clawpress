<?php
/**
 * Action/event logging helper.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Central action log helper for writing/querying action/event records.
 */
final class Action_Log_Helper {
	/**
	 * Database table suffix.
	 */
	private const TABLE_SUFFIX = 'clawpress_action_logs';

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
	 * Constructor.
	 */
	private function __construct() {}

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
		global $wpdb;

		if ( ! $this->is_wpdb_ready( $wpdb ) ) {
			return self::TABLE_SUFFIX;
		}

		return $wpdb->prefix . self::TABLE_SUFFIX;
	}

	/**
	 * Create/update action log table schema.
	 */
	public function create_table(): bool {
		global $wpdb;

		if ( ! $this->is_wpdb_ready( $wpdb ) ) {
			return false;
		}

		$table_name      = $this->get_table_name();
		$charset_collate = method_exists( $wpdb, 'get_charset_collate' )
			? (string) $wpdb->get_charset_collate()
			: '';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_type varchar(64) NOT NULL DEFAULT 'event',
			action_name varchar(191) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'info',
			message text NULL,
			requesting_user_id bigint(20) unsigned NULL,
			execution_user_id bigint(20) unsigned NULL,
			context longtext NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY event_type (event_type),
			KEY action_name (action_name),
			KEY status (status),
			KEY requesting_user_id (requesting_user_id),
			KEY execution_user_id (execution_user_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		if ( ! function_exists( 'dbDelta' ) ) {
			return false;
		}

		dbDelta( $sql );
		return true;
	}

	/**
	 * Persist one action/event log record.
	 *
	 * @param string              $action_name Action or command name.
	 * @param array<string,mixed> $args        Optional log payload.
	 */
	public function log_event( string $action_name, array $args = [] ): bool {
		global $wpdb;

		if ( ! $this->is_wpdb_ready( $wpdb ) || ! method_exists( $wpdb, 'insert' ) ) {
			return false;
		}

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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- centralized logging insert for plugin action ledger.
		$inserted = $wpdb->insert(
			$this->get_table_name(),
			[
				'event_type'         => $event_type,
				'action_name'        => $normalized_action_name,
				'status'             => $status,
				'message'            => '' !== $message ? $message : null,
				'requesting_user_id' => $requesting_user_id > 0 ? $requesting_user_id : null,
				'execution_user_id'  => $execution_user_id > 0 ? $execution_user_id : null,
				'context'            => $encoded_context,
			],
			[
				'%s',
				'%s',
				'%s',
				'%s',
				'%d',
				'%d',
				'%s',
			]
		);

		return false !== $inserted;
	}

	/**
	 * Fetch recent log rows.
	 *
	 * @param array<string,mixed> $args Optional query filters.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_recent_logs( array $args = [] ): array {
		global $wpdb;

		if ( ! $this->is_wpdb_ready( $wpdb ) || ! method_exists( $wpdb, 'prepare' ) || ! method_exists( $wpdb, 'get_results' ) ) {
			return [];
		}

		$limit  = isset( $args['limit'] ) ? (int) $args['limit'] : 50;
		$offset = isset( $args['offset'] ) ? (int) $args['offset'] : 0;
		$limit  = $limit > 0 ? min( $limit, 500 ) : 50;
		$offset = $offset >= 0 ? $offset : 0;

		$where_clauses = [];
		$where_values  = [];

		if ( isset( $args['event_type'] ) ) {
			$event_type = $this->sanitize_event_type( (string) $args['event_type'] );
			if ( '' !== $event_type ) {
				$where_clauses[] = 'event_type = %s';
				$where_values[]  = $event_type;
			}
		}

		if ( isset( $args['status'] ) ) {
			$status = $this->sanitize_status( (string) $args['status'] );
			if ( '' !== $status ) {
				$where_clauses[] = 'status = %s';
				$where_values[]  = $status;
			}
		}

		if ( isset( $args['requesting_user_id'] ) && (int) $args['requesting_user_id'] > 0 ) {
			$where_clauses[] = 'requesting_user_id = %d';
			$where_values[]  = (int) $args['requesting_user_id'];
		}

		if ( isset( $args['execution_user_id'] ) && (int) $args['execution_user_id'] > 0 ) {
			$where_clauses[] = 'execution_user_id = %d';
			$where_values[]  = (int) $args['execution_user_id'];
		}

		$where_sql      = [] !== $where_clauses ? 'WHERE ' . implode( ' AND ', $where_clauses ) : '';
		$where_values[] = $limit;
		$where_values[] = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is fixed plugin-owned identifier.
		$query = "SELECT id, event_type, action_name, status, message, requesting_user_id, execution_user_id, context, created_at
			FROM {$this->get_table_name()}
			{$where_sql}
			ORDER BY id DESC
			LIMIT %d OFFSET %d";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- query is prepared via `$wpdb->prepare()` on this line.
		$prepared_query = $wpdb->prepare( $query, $where_values );
		if ( ! is_string( $prepared_query ) || '' === $prepared_query ) {
			return [];
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- log queries are intentional and already bounded.
		$rows = $wpdb->get_results( $prepared_query, 'ARRAY_A' );
		if ( ! is_array( $rows ) ) {
			return [];
		}

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

	/**
	 * Check whether a usable `$wpdb` object is present.
	 *
	 * @param mixed $wpdb Candidate wpdb object.
	 */
	private function is_wpdb_ready( $wpdb ): bool {
		return is_object( $wpdb ) && isset( $wpdb->prefix );
	}
}

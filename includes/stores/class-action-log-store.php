<?php
/**
 * Action/event logging store.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Stores;

defined( 'ABSPATH' ) || exit;

/**
 * Database store for action/event records.
 */
final class Action_Log_Store {
	/**
	 * Database table suffix.
	 */
	private const TABLE_SUFFIX = 'clawpress_action_logs';

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
	 * @param array<string,mixed> $data Insert payload.
	 */
	public function insert_log( array $data ): bool {
		global $wpdb;

		if ( ! $this->is_wpdb_ready( $wpdb ) || ! method_exists( $wpdb, 'insert' ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- centralized logging insert for plugin action ledger.
		$inserted = $wpdb->insert(
			$this->get_table_name(),
			[
				'event_type'         => isset( $data['event_type'] ) ? (string) $data['event_type'] : 'event',
				'action_name'        => isset( $data['action_name'] ) ? (string) $data['action_name'] : '',
				'status'             => isset( $data['status'] ) ? (string) $data['status'] : 'info',
				'message'            => $data['message'] ?? null,
				'requesting_user_id' => $data['requesting_user_id'] ?? null,
				'execution_user_id'  => $data['execution_user_id'] ?? null,
				'context'            => $data['context'] ?? null,
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
	 * @param array<string,mixed> $args Query filters.
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

		$event_type         = isset( $args['event_type'] ) ? trim( (string) $args['event_type'] ) : '';
		$status             = isset( $args['status'] ) ? trim( (string) $args['status'] ) : '';
		$requesting_user_id = isset( $args['requesting_user_id'] ) ? max( 0, (int) $args['requesting_user_id'] ) : 0;
		$execution_user_id  = isset( $args['execution_user_id'] ) ? max( 0, (int) $args['execution_user_id'] ) : 0;

		$prepared_query = $wpdb->prepare(
			"SELECT id, event_type, action_name, status, message, requesting_user_id, execution_user_id, context, created_at
				FROM %i
				WHERE (%s = '' OR event_type = %s)
					AND (%s = '' OR status = %s)
					AND (%d = 0 OR requesting_user_id = %d)
					AND (%d = 0 OR execution_user_id = %d)
				ORDER BY id DESC
				LIMIT %d OFFSET %d",
			$this->get_table_name(),
			$event_type,
			$event_type,
			$status,
			$status,
			$requesting_user_id,
			$requesting_user_id,
			$execution_user_id,
			$execution_user_id,
			$limit,
			$offset
		);

		if ( ! is_string( $prepared_query ) || '' === $prepared_query ) {
			return [];
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- log queries are intentional and already bounded.
		$rows = $wpdb->get_results( $prepared_query, 'ARRAY_A' );
		return is_array( $rows ) ? array_values( $rows ) : [];
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

<?php
/**
 * Agent event persistence store.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Stores;

defined( 'ABSPATH' ) || exit;

/**
 * Database store for append-only agent run/session events.
 */
final class Agent_Event_Store {
	/**
	 * Database table suffix.
	 */
	private const TABLE_SUFFIX = 'clawpress_agent_events';

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
	 * Resolve full event table name.
	 */
	public function get_table_name(): string {
		global $wpdb;

		if ( ! $this->is_wpdb_ready( $wpdb ) ) {
			return self::TABLE_SUFFIX;
		}

		return $wpdb->prefix . self::TABLE_SUFFIX;
	}

	/**
	 * Create/update event table schema.
	 */
	public function create_table(): bool {
		global $wpdb;

		if ( ! $this->is_wpdb_ready( $wpdb ) ) {
			return false;
		}

		$charset_collate = method_exists( $wpdb, 'get_charset_collate' )
			? (string) $wpdb->get_charset_collate()
			: '';

		$sql = "CREATE TABLE {$this->get_table_name()} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			run_id bigint(20) unsigned NULL,
			session_id bigint(20) unsigned NULL,
			event_type varchar(64) NOT NULL,
			payload_json longtext NULL,
			created_at_gmt datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY run_id_id (run_id, id),
			KEY session_id_id (session_id, id),
			KEY event_type (event_type),
			KEY created_at_gmt (created_at_gmt)
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
	 * Insert one event row.
	 *
	 * @param array<string,mixed> $data Event payload.
	 */
	public function insert_event( array $data ): int {
		global $wpdb;

		if ( ! $this->is_wpdb_ready( $wpdb ) || ! method_exists( $wpdb, 'insert' ) ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- centralized append-only event insert.
		$inserted = $wpdb->insert(
			$this->get_table_name(),
			[
				'run_id'         => $data['run_id'] ?? null,
				'session_id'     => $data['session_id'] ?? null,
				'event_type'     => isset( $data['event_type'] ) ? (string) $data['event_type'] : 'event',
				'payload_json'   => $data['payload_json'] ?? null,
				'created_at_gmt' => isset( $data['created_at_gmt'] ) ? (string) $data['created_at_gmt'] : gmdate( 'Y-m-d H:i:s' ),
			],
			[ '%d', '%d', '%s', '%s', '%s' ]
		);

		if ( false === $inserted || ! isset( $wpdb->insert_id ) ) {
			return 0;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Fetch event rows incrementally.
	 *
	 * @param array<string,mixed> $args Query filters.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_events( array $args = [] ): array {
		global $wpdb;

		if ( ! $this->is_wpdb_ready( $wpdb ) || ! method_exists( $wpdb, 'prepare' ) || ! method_exists( $wpdb, 'get_results' ) ) {
			return [];
		}

		$limit = isset( $args['limit'] ) ? (int) $args['limit'] : 100;
		$limit = $limit > 0 ? min( $limit, 500 ) : 100;
		$after = isset( $args['after_event_id'] ) ? (int) $args['after_event_id'] : 0;
		$after = $after > 0 ? $after : 0;

		$run_id     = isset( $args['run_id'] ) ? max( 0, (int) $args['run_id'] ) : 0;
		$session_id = isset( $args['session_id'] ) ? max( 0, (int) $args['session_id'] ) : 0;
		$event_type = isset( $args['event_type'] ) ? trim( (string) $args['event_type'] ) : '';

		$prepared_query = $wpdb->prepare(
			"SELECT id, run_id, session_id, event_type, payload_json, created_at_gmt
				FROM %i
				WHERE id > %d
					AND (%d = 0 OR run_id = %d)
					AND (%d = 0 OR session_id = %d)
					AND (%s = '' OR event_type = %s)
				ORDER BY id ASC
				LIMIT %d",
			$this->get_table_name(),
			$after,
			$run_id,
			$run_id,
			$session_id,
			$session_id,
			$event_type,
			$event_type,
			$limit
		);

		if ( ! is_string( $prepared_query ) || '' === $prepared_query ) {
			return [];
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- bounded incremental event read.
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

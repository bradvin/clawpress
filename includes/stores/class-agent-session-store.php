<?php
/**
 * Agent session persistence store.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Stores;

defined( 'ABSPATH' ) || exit;

/**
 * Database store for persistent agent session state.
 */
final class Agent_Session_Store {
	/**
	 * Database table suffix.
	 */
	private const TABLE_SUFFIX = 'clawpress_agent_sessions';

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
	 * Resolve full session table name.
	 */
	public function get_table_name(): string {
		global $wpdb;

		if ( ! is_object( $wpdb ) || ! isset( $wpdb->prefix ) ) {
			return self::TABLE_SUFFIX;
		}

		return $wpdb->prefix . self::TABLE_SUFFIX;
	}

	/**
	 * Create/update session table schema.
	 */
	public function create_table(): bool {
		global $wpdb;

		if ( ! is_object( $wpdb ) || ! isset( $wpdb->prefix ) ) {
			return false;
		}

		$charset_collate = method_exists( $wpdb, 'get_charset_collate' )
			? (string) $wpdb->get_charset_collate()
			: '';

		$sql = "CREATE TABLE {$this->get_table_name()} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			uuid char(36) NOT NULL,
			status varchar(32) NOT NULL DEFAULT 'active',
			trigger_type varchar(32) NOT NULL DEFAULT 'chat',
			requesting_user_id bigint(20) unsigned NULL,
			execution_user_id bigint(20) unsigned NULL,
			policy_profile varchar(64) NULL,
			last_run_at_gmt datetime NULL,
			next_run_at_gmt datetime NULL,
			last_run_status varchar(32) NULL,
			consecutive_failures int(11) NOT NULL DEFAULT 0,
			created_at_gmt datetime NOT NULL,
			updated_at_gmt datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uuid (uuid),
			KEY status_next_run_at_gmt (status, next_run_at_gmt),
			KEY trigger_type (trigger_type)
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
	 * Insert one session row.
	 *
	 * @param array<string,mixed> $data Session payload.
	 */
	public function insert_session( array $data ): int {
		global $wpdb;

		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'insert' ) ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Centralized repository insert.
		$wpdb->insert(
			$this->get_table_name(),
			[
				'uuid'                 => isset( $data['uuid'] ) ? (string) $data['uuid'] : '',
				'status'               => isset( $data['status'] ) ? (string) $data['status'] : 'active',
				'trigger_type'         => isset( $data['trigger_type'] ) ? (string) $data['trigger_type'] : 'chat',
				'requesting_user_id'   => $data['requesting_user_id'] ?? null,
				'execution_user_id'    => $data['execution_user_id'] ?? null,
				'policy_profile'       => $data['policy_profile'] ?? null,
				'last_run_at_gmt'      => $data['last_run_at_gmt'] ?? null,
				'next_run_at_gmt'      => $data['next_run_at_gmt'] ?? null,
				'last_run_status'      => $data['last_run_status'] ?? null,
				'consecutive_failures' => isset( $data['consecutive_failures'] ) ? (int) $data['consecutive_failures'] : 0,
				'created_at_gmt'       => isset( $data['created_at_gmt'] ) ? (string) $data['created_at_gmt'] : gmdate( 'Y-m-d H:i:s' ),
				'updated_at_gmt'       => isset( $data['updated_at_gmt'] ) ? (string) $data['updated_at_gmt'] : gmdate( 'Y-m-d H:i:s' ),
			],
			[
				'%s',
				'%s',
				'%s',
				'%d',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%d',
				'%s',
				'%s',
			]
		);

		if ( ! isset( $wpdb->insert_id ) ) {
			return 0;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update parent session state after run completion.
	 */
	public function update_run_completion( int $session_id, string $run_status, ?string $next_run_at_gmt, string $updated_at_gmt ): bool {
		global $wpdb;

		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'prepare' ) || ! method_exists( $wpdb, 'query' ) ) {
			return false;
		}

		$table_name = $this->get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is fixed plugin-owned identifier.
		$query = $wpdb->prepare(
			"UPDATE {$table_name}
			SET
				last_run_at_gmt = %s,
				last_run_status = %s,
				consecutive_failures = CASE
					WHEN %s = 'success' THEN 0
					ELSE consecutive_failures + 1
				END,
				next_run_at_gmt = %s,
				updated_at_gmt = %s
			WHERE id = %d",
			$updated_at_gmt,
			$run_status,
			$run_status,
			$next_run_at_gmt,
			$updated_at_gmt,
			$session_id
		);

		if ( ! is_string( $query ) || '' === $query ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- prepared bounded update on primary-key id.
		$updated = $wpdb->query( $query );

		return false !== $updated;
	}
}

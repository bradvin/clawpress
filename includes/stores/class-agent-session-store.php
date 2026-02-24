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
			status varchar(32) NOT NULL DEFAULT 'idle',
			trigger_type varchar(32) NOT NULL DEFAULT 'chat',
			requesting_user_id bigint(20) unsigned NULL,
			execution_user_id bigint(20) unsigned NULL,
			policy_profile varchar(64) NULL,
			last_run_at_gmt datetime NULL,
			next_run_at_gmt datetime NULL,
			last_run_status varchar(32) NULL,
			consecutive_failures int(11) NOT NULL DEFAULT 0,
			lease_owner varchar(64) NULL,
			lease_token char(64) NULL,
			lease_acquired_at_gmt datetime NULL,
			lease_expires_at_gmt datetime NULL,
			created_at_gmt datetime NOT NULL,
			updated_at_gmt datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uuid (uuid),
			KEY status_next_run_at_gmt (status, next_run_at_gmt),
			KEY trigger_type (trigger_type),
			KEY lease_expires_at_gmt (lease_expires_at_gmt)
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
				'uuid'                  => isset( $data['uuid'] ) ? (string) $data['uuid'] : '',
				'status'                => isset( $data['status'] ) ? (string) $data['status'] : 'idle',
				'trigger_type'          => isset( $data['trigger_type'] ) ? (string) $data['trigger_type'] : 'chat',
				'requesting_user_id'    => $data['requesting_user_id'] ?? null,
				'execution_user_id'     => $data['execution_user_id'] ?? null,
				'policy_profile'        => $data['policy_profile'] ?? null,
				'last_run_at_gmt'       => $data['last_run_at_gmt'] ?? null,
				'next_run_at_gmt'       => $data['next_run_at_gmt'] ?? null,
				'last_run_status'       => $data['last_run_status'] ?? null,
				'consecutive_failures'  => isset( $data['consecutive_failures'] ) ? (int) $data['consecutive_failures'] : 0,
				'lease_owner'           => $data['lease_owner'] ?? null,
				'lease_token'           => $data['lease_token'] ?? null,
				'lease_acquired_at_gmt' => $data['lease_acquired_at_gmt'] ?? null,
				'lease_expires_at_gmt'  => $data['lease_expires_at_gmt'] ?? null,
				'created_at_gmt'        => isset( $data['created_at_gmt'] ) ? (string) $data['created_at_gmt'] : gmdate( 'Y-m-d H:i:s' ),
				'updated_at_gmt'        => isset( $data['updated_at_gmt'] ) ? (string) $data['updated_at_gmt'] : gmdate( 'Y-m-d H:i:s' ),
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
				'%s',
				'%s',
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
	 * Fetch one session by ID.
	 *
	 * @param int $session_id Session ID.
	 * @return array<string,mixed>
	 */
	public function get_session( int $session_id ): array {
		global $wpdb;

		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'prepare' ) || ! method_exists( $wpdb, 'get_row' ) ) {
			return [];
		}

		$table_name = $this->get_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is fixed plugin-owned identifier.
		$query = $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $session_id );
		if ( ! is_string( $query ) || '' === $query ) {
			return [];
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- bounded primary-key lookup.
		$row = $wpdb->get_row( $query, 'ARRAY_A' );
		return is_array( $row ) ? $row : [];
	}

	/**
	 * Compare-and-swap session lease claim update.
	 *
	 * @param int                 $session_id Session identifier.
	 * @param string              $current_status Expected status.
	 * @param string|null         $current_lease_expires_at_gmt Expected lease expiry when reclaiming.
	 * @param array<string,mixed> $data Update data.
	 * @param bool                $is_stale Whether this claim is a stale reclaim.
	 * @return int|false
	 */
	public function update_claim( int $session_id, string $current_status, ?string $current_lease_expires_at_gmt, array $data, bool $is_stale ) {
		global $wpdb;

		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'update' ) ) {
			return false;
		}

		$where        = [
			'id'     => $session_id,
			'status' => $current_status,
		];
		$where_format = [ '%d', '%s' ];

		if ( $is_stale ) {
			$where['lease_expires_at_gmt'] = $current_lease_expires_at_gmt;
			$where_format[]                = '%s';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- bounded compare-and-swap style update.
		return $wpdb->update(
			$this->get_table_name(),
			[
				'status'                => isset( $data['status'] ) ? (string) $data['status'] : 'running',
				'lease_owner'           => isset( $data['lease_owner'] ) ? (string) $data['lease_owner'] : null,
				'lease_token'           => isset( $data['lease_token'] ) ? (string) $data['lease_token'] : null,
				'lease_acquired_at_gmt' => isset( $data['lease_acquired_at_gmt'] ) ? (string) $data['lease_acquired_at_gmt'] : null,
				'lease_expires_at_gmt'  => isset( $data['lease_expires_at_gmt'] ) ? (string) $data['lease_expires_at_gmt'] : null,
				'updated_at_gmt'        => isset( $data['updated_at_gmt'] ) ? (string) $data['updated_at_gmt'] : null,
			],
			$where,
			[ '%s', '%s', '%s', '%s', '%s', '%s' ],
			$where_format
		);
	}

	/**
	 * Release a session lease by token.
	 *
	 * @param int                 $session_id Session identifier.
	 * @param string              $lease_token Lease token guard.
	 * @param array<string,mixed> $data Update data.
	 * @return int|false
	 */
	public function update_release( int $session_id, string $lease_token, array $data ) {
		global $wpdb;

		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'update' ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- bounded repository update.
		return $wpdb->update(
			$this->get_table_name(),
			[
				'status'                => isset( $data['status'] ) ? (string) $data['status'] : 'idle',
				'lease_owner'           => null,
				'lease_token'           => null,
				'lease_acquired_at_gmt' => null,
				'lease_expires_at_gmt'  => null,
				'updated_at_gmt'        => isset( $data['updated_at_gmt'] ) ? (string) $data['updated_at_gmt'] : null,
			],
			[
				'id'          => $session_id,
				'lease_token' => $lease_token,
			],
			[ '%s', '%s', '%s', '%s', '%s', '%s' ],
			[ '%d', '%s' ]
		);
	}

	/**
	 * Update parent session state after run completion.
	 *
	 * @param int         $session_id Session identifier.
	 * @param string      $run_status Terminal run status.
	 * @param string|null $next_run_at_gmt Optional next-run timestamp.
	 * @param string      $updated_at_gmt Update timestamp (UTC).
	 */
	public function update_run_completion( int $session_id, string $run_status, ?string $next_run_at_gmt, string $updated_at_gmt ): bool {
		global $wpdb;

		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'prepare' ) || ! method_exists( $wpdb, 'query' ) ) {
			return false;
		}

		$table_name = $this->get_table_name();
		$query      = $wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is fixed plugin-owned identifier.
			"UPDATE {$table_name}
				SET
					last_run_at_gmt = %s,
					last_run_status = %s,
					consecutive_failures = CASE
						WHEN %s IN ('success', 'done', 'requires_confirmation') THEN 0
						WHEN %s = 'paused' THEN consecutive_failures
						ELSE consecutive_failures + 1
					END,
					next_run_at_gmt = %s,
					updated_at_gmt = %s
				WHERE id = %d",
			$updated_at_gmt,
			$run_status,
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

<?php
/**
 * Agent run persistence store.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Stores;

defined( 'ABSPATH' ) || exit;

/**
 * Database store for agent runs.
 */
final class Agent_Run_Store {
	/**
	 * Database table suffix.
	 */
	private const TABLE_SUFFIX = 'clawpress_agent_runs';

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
	 * Resolve full run table name.
	 */
	public function get_table_name(): string {
		global $wpdb;

		if ( ! is_object( $wpdb ) || ! isset( $wpdb->prefix ) ) {
			return self::TABLE_SUFFIX;
		}

		return $wpdb->prefix . self::TABLE_SUFFIX;
	}

	/**
	 * Create/update run table schema.
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
			session_id bigint(20) unsigned NOT NULL,
			run_uuid char(36) NOT NULL,
			status varchar(32) NOT NULL DEFAULT 'queued',
			claimed_by varchar(64) NULL,
			lock_token char(64) NULL,
			lock_acquired_at_gmt datetime NULL,
			lock_expires_at_gmt datetime NULL,
			attempt int(11) NOT NULL DEFAULT 1,
			started_at_gmt datetime NULL,
			finished_at_gmt datetime NULL,
			error_code varchar(128) NULL,
			error_message text NULL,
			meta_json longtext NULL,
			created_at_gmt datetime NOT NULL,
			updated_at_gmt datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY run_uuid (run_uuid),
			KEY session_status (session_id, status),
			KEY status_lock_expires_at_gmt (status, lock_expires_at_gmt),
			KEY claimed_by (claimed_by)
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
	 * Insert a queued run row.
	 */
	public function insert_run( int $session_id, string $run_uuid, string $created_at_gmt ): int {
		global $wpdb;

		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'insert' ) ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- centralized repository insert.
		$wpdb->insert(
			$this->get_table_name(),
			[
				'session_id'     => $session_id,
				'run_uuid'       => $run_uuid,
				'status'         => 'queued',
				'attempt'        => 1,
				'created_at_gmt' => $created_at_gmt,
				'updated_at_gmt' => $created_at_gmt,
			],
			[ '%d', '%s', '%s', '%d', '%s', '%s' ]
		);

		return isset( $wpdb->insert_id ) ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Compare-and-swap claim update for a run.
	 *
	 * @param int                   $run_id Run identifier.
	 * @param string                $current_status Expected status.
	 * @param string|null           $current_lock_expires_at_gmt Expected lock expiry when reclaiming.
	 * @param array<string,mixed>   $data Update data.
	 * @param bool                  $is_stale Whether this claim is a stale reclaim.
	 * @return int|false
	 */
	public function update_claim(
		int $run_id,
		string $current_status,
		?string $current_lock_expires_at_gmt,
		array $data,
		bool $is_stale
	) {
		global $wpdb;

		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'update' ) ) {
			return false;
		}

		$where        = [
			'id'     => $run_id,
			'status' => $current_status,
		];
		$where_format = [ '%d', '%s' ];

		if ( $is_stale ) {
			$where['lock_expires_at_gmt'] = $current_lock_expires_at_gmt;
			$where_format[]               = '%s';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- bounded compare-and-swap style update.
		return $wpdb->update(
			$this->get_table_name(),
			[
				'status'               => isset( $data['status'] ) ? (string) $data['status'] : 'running',
				'claimed_by'           => isset( $data['claimed_by'] ) ? (string) $data['claimed_by'] : null,
				'lock_token'           => isset( $data['lock_token'] ) ? (string) $data['lock_token'] : null,
				'lock_acquired_at_gmt' => isset( $data['lock_acquired_at_gmt'] ) ? (string) $data['lock_acquired_at_gmt'] : null,
				'lock_expires_at_gmt'  => isset( $data['lock_expires_at_gmt'] ) ? (string) $data['lock_expires_at_gmt'] : null,
				'attempt'              => isset( $data['attempt'] ) ? (int) $data['attempt'] : 1,
				'started_at_gmt'       => isset( $data['started_at_gmt'] ) ? (string) $data['started_at_gmt'] : null,
				'updated_at_gmt'       => isset( $data['updated_at_gmt'] ) ? (string) $data['updated_at_gmt'] : null,
			],
			$where,
			[ '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ],
			$where_format
		);
	}

	/**
	 * Complete a run with lock-token guard.
	 *
	 * @param int                 $run_id Run identifier.
	 * @param string              $lock_token Lock token guard.
	 * @param array<string,mixed> $data Completion data.
	 * @return int|false
	 */
	public function update_completion( int $run_id, string $lock_token, array $data ) {
		global $wpdb;

		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'update' ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- bounded repository completion update.
		return $wpdb->update(
			$this->get_table_name(),
			[
				'status'               => isset( $data['status'] ) ? (string) $data['status'] : null,
				'lock_token'           => null,
				'claimed_by'           => null,
				'lock_acquired_at_gmt' => null,
				'lock_expires_at_gmt'  => null,
				'finished_at_gmt'      => isset( $data['finished_at_gmt'] ) ? (string) $data['finished_at_gmt'] : null,
				'error_code'           => $data['error_code'] ?? null,
				'error_message'        => $data['error_message'] ?? null,
				'meta_json'            => $data['meta_json'] ?? null,
				'updated_at_gmt'       => isset( $data['updated_at_gmt'] ) ? (string) $data['updated_at_gmt'] : null,
			],
			[
				'id'         => $run_id,
				'lock_token' => $lock_token,
			],
			[ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ],
			[ '%d', '%s' ]
		);
	}

	/**
	 * Fetch one run by id.
	 *
	 * @param int $run_id Run identifier.
	 * @return array<string,mixed>
	 */
	public function get_run( int $run_id ): array {
		global $wpdb;

		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'prepare' ) || ! method_exists( $wpdb, 'get_row' ) ) {
			return [];
		}

		$table_name = $this->get_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is fixed plugin-owned identifier.
		$query = $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $run_id );
		if ( ! is_string( $query ) || '' === $query ) {
			return [];
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- bounded primary-key lookup.
		$row = $wpdb->get_row( $query, 'ARRAY_A' );
		return is_array( $row ) ? $row : [];
	}

	/**
	 * Begin transaction.
	 */
	public function begin_transaction(): bool {
		global $wpdb;

		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'query' ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- bounded transaction control statement.
		return false !== $wpdb->query( 'START TRANSACTION' );
	}

	/**
	 * Commit transaction.
	 */
	public function commit_transaction(): bool {
		global $wpdb;

		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'query' ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- bounded transaction control statement.
		return false !== $wpdb->query( 'COMMIT' );
	}

	/**
	 * Roll back transaction.
	 */
	public function rollback_transaction(): void {
		global $wpdb;

		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'query' ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- bounded transaction control statement.
		$wpdb->query( 'ROLLBACK' );
	}
}

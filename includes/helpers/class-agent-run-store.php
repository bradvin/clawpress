<?php
/**
 * Agent run persistence + lock helper.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Run store with claim/lock lifecycle methods.
 */
final class Agent_Run_Store {
	/**
	 * Database table suffix.
	 */
	private const TABLE_SUFFIX = 'clawpress_agent_runs';

	/**
	 * Allowed terminal run statuses.
	 *
	 * @var array<int,string>
	 */
	private const TERMINAL_STATUSES = [ 'success', 'failed', 'cancelled', 'canceled' ];

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
	 * Create a queued run.
	 *
	 * @param int $session_id Parent session identifier.
	 */
	public function create_run( int $session_id ): int {
		global $wpdb;

		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'insert' ) ) {
			return 0;
		}

		$now = gmdate( 'Y-m-d H:i:s' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Centralized repository insert.
		$wpdb->insert(
			$this->get_table_name(),
			[
				'session_id'     => $session_id,
				'run_uuid'       => $this->generate_uuid(),
				'status'         => 'queued',
				'attempt'        => 1,
				'created_at_gmt' => $now,
				'updated_at_gmt' => $now,
			],
			[ '%d', '%s', '%s', '%d', '%s', '%s' ]
		);

		return isset( $wpdb->insert_id ) ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Claim one run for a worker.
	 *
	 * @param int    $run_id            Run identifier.
	 * @param string $worker_id         Worker claim id.
	 * @param int    $lease_ttl_seconds Lease TTL in seconds.
	 * @return array<string,mixed>
	 */
	public function claim_run( int $run_id, string $worker_id, int $lease_ttl_seconds = 120 ): array {
		global $wpdb;

		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'update' ) ) {
			return [
				'claimed' => false,
				'reason'  => 'wpdb_unavailable',
			];
		}

		$run = $this->get_run( $run_id );
		if ( [] === $run ) {
			return [
				'claimed' => false,
				'reason'  => 'run_not_found',
			];
		}

		$now        = gmdate( 'Y-m-d H:i:s' );
		$lock_token = hash( 'sha256', uniqid( $worker_id . ':', true ) );
		$expires_at = gmdate( 'Y-m-d H:i:s', strtotime( $now ) + max( 1, $lease_ttl_seconds ) );

		$is_stale = 'running' === (string) $run['status']
			&& isset( $run['lock_expires_at_gmt'] )
			&& is_string( $run['lock_expires_at_gmt'] )
			&& '' !== $run['lock_expires_at_gmt']
			&& strtotime( $run['lock_expires_at_gmt'] ) < strtotime( $now );

		if ( 'queued' !== (string) $run['status'] && ! $is_stale ) {
			return [
				'claimed' => false,
				'reason'  => 'not_claimable',
			];
		}

		$next_attempt = (int) ( $run['attempt'] ?? 1 );
		if ( $is_stale ) {
			++$next_attempt;
		}

		$where = [
			'id'     => $run_id,
			'status' => (string) $run['status'],
		];
		if ( $is_stale ) {
			$where['lock_expires_at_gmt'] = (string) $run['lock_expires_at_gmt'];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- bounded compare-and-swap style update.
		$updated = $wpdb->update(
			$this->get_table_name(),
			[
				'status'               => 'running',
				'claimed_by'           => $worker_id,
				'lock_token'           => $lock_token,
				'lock_acquired_at_gmt' => $now,
				'lock_expires_at_gmt'  => $expires_at,
				'attempt'              => $next_attempt,
				'started_at_gmt'       => $now,
				'updated_at_gmt'       => $now,
			],
			$where,
			[ '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ],
			array_fill( 0, count( $where ), '%s' )
		);

		if ( false === $updated || 0 === $updated ) {
			return [
				'claimed' => false,
				'reason'  => 'claim_collision',
			];
		}

		return [
			'claimed'    => true,
			'run_id'     => $run_id,
			'lock_token' => $lock_token,
			'attempt'    => $next_attempt,
			'reclaimed'  => $is_stale,
		];
	}

	/**
	 * Complete a run and update parent session state.
	 *
	 * @param int                 $run_id Run identifier.
	 * @param string              $lock_token Lock token from claim response.
	 * @param string              $status Terminal status.
	 * @param array<string,mixed> $args Optional completion details.
	 */
	public function complete_run( int $run_id, string $lock_token, string $status, array $args = [] ): bool {
		global $wpdb;

		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'update' ) || ! method_exists( $wpdb, 'query' ) ) {
			return false;
		}
		if ( ! in_array( $status, self::TERMINAL_STATUSES, true ) ) {
			return false;
		}

		if ( ! $this->begin_transaction() ) {
			return false;
		}

		$run = $this->get_run( $run_id );
		if ( [] === $run || 'running' !== (string) $run['status'] ) {
			$this->rollback_transaction();
			return false;
		}

		if ( (string) ( $run['lock_token'] ?? '' ) !== $lock_token ) {
			$this->rollback_transaction();
			return false;
		}

		$meta_json = null;
		if ( isset( $args['meta'] ) && is_array( $args['meta'] ) ) {
			$encoded   = wp_json_encode( $args['meta'] );
			$meta_json = false === $encoded ? null : $encoded;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- bounded repository completion update.
		$updated = $wpdb->update(
			$this->get_table_name(),
			[
				'status'               => $status,
				'lock_token'           => null,
				'claimed_by'           => null,
				'lock_acquired_at_gmt' => null,
				'lock_expires_at_gmt'  => null,
				'finished_at_gmt'      => gmdate( 'Y-m-d H:i:s' ),
				'error_code'           => isset( $args['error_code'] ) ? (string) $args['error_code'] : null,
				'error_message'        => isset( $args['error_message'] ) ? (string) $args['error_message'] : null,
				'meta_json'            => $meta_json,
				'updated_at_gmt'       => gmdate( 'Y-m-d H:i:s' ),
			],
			[
				'id'         => $run_id,
				'lock_token' => $lock_token,
			],
			[ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ],
			[ '%d', '%s' ]
		);

		if ( false === $updated || 0 === $updated ) {
			$this->rollback_transaction();
			return false;
		}

		$session_updated = Agent_Session_Store::get_instance()->apply_run_completion(
			(int) $run['session_id'],
			$status,
			isset( $args['next_run_at_gmt'] ) ? (string) $args['next_run_at_gmt'] : null
		);

		if ( ! $session_updated ) {
			$this->rollback_transaction();
			return false;
		}

		if ( ! $this->commit_transaction() ) {
			$this->rollback_transaction();
			return false;
		}

		return true;
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
	 * Generate uuid-like id without WP dependency.
	 */
	private function generate_uuid(): string {
		$seed = md5( uniqid( '', true ) );
		return sprintf(
			'%s-%s-%s-%s-%s',
			substr( $seed, 0, 8 ),
			substr( $seed, 8, 4 ),
			substr( $seed, 12, 4 ),
			substr( $seed, 16, 4 ),
			substr( $seed, 20, 12 )
		);
	}

	/**
	 * Begin transaction for multi-table completion updates.
	 */
	private function begin_transaction(): bool {
		global $wpdb;

		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'query' ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- bounded transaction control statement.
		return false !== $wpdb->query( 'START TRANSACTION' );
	}

	/**
	 * Commit transaction for completion updates.
	 */
	private function commit_transaction(): bool {
		global $wpdb;

		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'query' ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- bounded transaction control statement.
		return false !== $wpdb->query( 'COMMIT' );
	}

	/**
	 * Roll back completion transaction.
	 */
	private function rollback_transaction(): void {
		global $wpdb;

		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'query' ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- bounded transaction control statement.
		$wpdb->query( 'ROLLBACK' );
	}
}

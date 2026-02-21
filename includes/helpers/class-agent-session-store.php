<?php
/**
 * Agent session persistence helper.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Session store for persistent agent thread state.
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
	 * Create one session row.
	 *
	 * @param array<string,mixed> $args Session payload.
	 */
	public function create_session( array $args = [] ): int {
		global $wpdb;

		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'insert' ) ) {
			return 0;
		}

		$now = gmdate( 'Y-m-d H:i:s' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Centralized repository insert.
		$wpdb->insert(
			$this->get_table_name(),
			[
				'uuid'                 => isset( $args['uuid'] ) ? (string) $args['uuid'] : $this->generate_uuid(),
				'status'               => isset( $args['status'] ) ? (string) $args['status'] : 'active',
				'trigger_type'         => isset( $args['trigger_type'] ) ? (string) $args['trigger_type'] : 'chat',
				'requesting_user_id'   => isset( $args['requesting_user_id'] ) ? (int) $args['requesting_user_id'] : null,
				'execution_user_id'    => isset( $args['execution_user_id'] ) ? (int) $args['execution_user_id'] : null,
				'policy_profile'       => isset( $args['policy_profile'] ) ? (string) $args['policy_profile'] : null,
				'last_run_at_gmt'      => null,
				'next_run_at_gmt'      => isset( $args['next_run_at_gmt'] ) ? (string) $args['next_run_at_gmt'] : null,
				'last_run_status'      => null,
				'consecutive_failures' => 0,
				'created_at_gmt'       => $now,
				'updated_at_gmt'       => $now,
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
	 *
	 * @param int         $session_id      Session identifier.
	 * @param string      $run_status      Terminal run status.
	 * @param string|null $next_run_at_gmt Optional next run timestamp.
	 */
	public function apply_run_completion( int $session_id, string $run_status, ?string $next_run_at_gmt = null ): bool {
		global $wpdb;

		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_row' ) || ! method_exists( $wpdb, 'update' ) ) {
			return false;
		}

		$table_name = $this->get_table_name();
		$query      = 'SELECT consecutive_failures FROM ' . $table_name . ' WHERE id = ' . (int) $session_id;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- bounded primary-key lookup by sanitized integer id.
		$row = $wpdb->get_row( $query, 'ARRAY_A' );
		if ( ! is_array( $row ) ) {
			return false;
		}

		$failures = (int) ( $row['consecutive_failures'] ?? 0 );
		if ( 'success' === $run_status ) {
			$failures = 0;
		} else {
			++$failures;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- bounded repository update.
		$updated = $wpdb->update(
			$this->get_table_name(),
			[
				'last_run_at_gmt'      => gmdate( 'Y-m-d H:i:s' ),
				'last_run_status'      => $run_status,
				'consecutive_failures' => $failures,
				'next_run_at_gmt'      => $next_run_at_gmt,
				'updated_at_gmt'       => gmdate( 'Y-m-d H:i:s' ),
			],
			[ 'id' => $session_id ],
			[ '%s', '%s', '%d', '%s', '%s' ],
			[ '%d' ]
		);

		return false !== $updated;
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
}

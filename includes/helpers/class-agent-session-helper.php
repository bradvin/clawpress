<?php
/**
 * Agent session helper.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Helpers;

use ClawPress\Stores\Agent_Session_Store as Agent_Session_DB_Store;

defined( 'ABSPATH' ) || exit;

/**
 * Helper for agent session lifecycle and defaults.
 */
final class Agent_Session_Helper {
	/**
	 * Singleton instance.
	 *
	 * @var ?self
	 */
	private static ?self $instance = null;

	/**
	 * Session store instance for DB access.
	 */
	private Agent_Session_DB_Store $store;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->store = Agent_Session_DB_Store::get_instance();
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
	 * Resolve full session table name.
	 */
	public function get_table_name(): string {
		return $this->store->get_table_name();
	}

	/**
	 * Create/update session table schema.
	 */
	public function create_table(): bool {
		return $this->store->create_table();
	}

	/**
	 * Create one session row.
	 *
	 * @param array<string,mixed> $args Session payload.
	 */
	public function create_session( array $args = [] ): int {
		$now = gmdate( 'Y-m-d H:i:s' );

		return $this->store->insert_session(
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
			]
		);
	}

	/**
	 * Update parent session state after run completion.
	 *
	 * @param int         $session_id      Session identifier.
	 * @param string      $run_status      Terminal run status.
	 * @param string|null $next_run_at_gmt Optional next run timestamp.
	 */
	public function apply_run_completion( int $session_id, string $run_status, ?string $next_run_at_gmt = null ): bool {
		return $this->store->update_run_completion(
			$session_id,
			$run_status,
			$next_run_at_gmt,
			gmdate( 'Y-m-d H:i:s' )
		);
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

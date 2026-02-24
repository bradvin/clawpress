<?php
/**
 * Agent session helper.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Helpers;

use ClawPress\Stores\Agent_Session_Store;

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
	 *
	 * @var Agent_Session_Store
	 */
	private Agent_Session_Store $store;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->store = Agent_Session_Store::get_instance();
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
	 * Create one session row.
	 *
	 * @param array<string,mixed> $args Session payload.
	 */
	public function create_session( array $args = [] ): int {
		$now = gmdate( 'Y-m-d H:i:s' );

		return $this->store->insert_session(
			[
				'uuid'                 => isset( $args['uuid'] ) ? (string) $args['uuid'] : $this->generate_uuid(),
				'status'               => isset( $args['status'] ) ? (string) $args['status'] : 'idle',
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
	 * Fetch one session row by ID.
	 *
	 * @param int $session_id Session identifier.
	 * @return array<string,mixed>
	 */
	public function get_session( int $session_id ): array {
		return $this->store->get_session( $session_id );
	}

	/**
	 * Claim session lease for one worker.
	 *
	 * @param int    $session_id Session identifier.
	 * @param string $worker_id Worker claim id.
	 * @param int    $lease_ttl_seconds Lease TTL in seconds.
	 * @return array<string,mixed>
	 */
	public function claim_session( int $session_id, string $worker_id, int $lease_ttl_seconds = 120 ): array {
		$session = $this->get_session( $session_id );
		if ( [] === $session ) {
			return [
				'claimed' => false,
				'reason'  => 'session_not_found',
			];
		}

		$current_status = isset( $session['status'] ) ? (string) $session['status'] : 'idle';
		$now            = gmdate( 'Y-m-d H:i:s' );
		$is_stale       = 'running' === $current_status
			&& isset( $session['lease_expires_at_gmt'] )
			&& is_string( $session['lease_expires_at_gmt'] )
			&& '' !== $session['lease_expires_at_gmt']
			&& strtotime( $session['lease_expires_at_gmt'] ) < strtotime( $now );

		if ( 'idle' !== $current_status && ! $is_stale ) {
			return [
				'claimed' => false,
				'reason'  => 'not_claimable',
			];
		}

		$lease_token = hash( 'sha256', uniqid( $worker_id . ':session:', true ) );
		$expires_at  = gmdate( 'Y-m-d H:i:s', strtotime( $now ) + max( 1, $lease_ttl_seconds ) );
		$updated     = $this->store->update_claim(
			$session_id,
			$current_status,
			$is_stale ? (string) $session['lease_expires_at_gmt'] : null,
			[
				'status'                => 'running',
				'lease_owner'           => $worker_id,
				'lease_token'           => $lease_token,
				'lease_acquired_at_gmt' => $now,
				'lease_expires_at_gmt'  => $expires_at,
				'updated_at_gmt'        => $now,
			],
			$is_stale
		);

		if ( false === $updated || 0 === $updated ) {
			return [
				'claimed' => false,
				'reason'  => 'claim_collision',
			];
		}

		return [
			'claimed'     => true,
			'session_id'  => $session_id,
			'lease_token' => $lease_token,
			'reclaimed'   => $is_stale,
		];
	}

	/**
	 * Release session lease.
	 *
	 * @param int    $session_id Session identifier.
	 * @param string $lease_token Lease token from claim response.
	 * @param string $status New session status.
	 */
	public function release_session( int $session_id, string $lease_token, string $status = 'idle' ): bool {
		$updated = $this->store->update_release(
			$session_id,
			$lease_token,
			[
				'status'         => $status,
				'updated_at_gmt' => gmdate( 'Y-m-d H:i:s' ),
			]
		);

		return false !== $updated && $updated > 0;
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

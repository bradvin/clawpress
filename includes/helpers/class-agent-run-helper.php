<?php
/**
 * Agent run persistence + lock helper.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Helpers;

use ClawPress\Stores\Agent_Run_Store as Agent_Run_DB_Store;

defined( 'ABSPATH' ) || exit;

/**
 * Run store with claim/lock lifecycle methods.
 */
final class Agent_Run_Helper {
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
	 * Run store instance for DB access.
	 */
	private Agent_Run_DB_Store $store;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->store = Agent_Run_DB_Store::get_instance();
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
	 * Resolve full run table name.
	 */
	public function get_table_name(): string {
		return $this->store->get_table_name();
	}

	/**
	 * Create/update run table schema.
	 */
	public function create_table(): bool {
		return $this->store->create_table();
	}

	/**
	 * Create a queued run.
	 *
	 * @param int $session_id Parent session identifier.
	 */
	public function create_run( int $session_id ): int {
		$now = gmdate( 'Y-m-d H:i:s' );
		return $this->store->insert_run( $session_id, $this->generate_uuid(), $now );
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

		$updated = $this->store->update_claim(
			$run_id,
			(string) $run['status'],
			$is_stale ? (string) $run['lock_expires_at_gmt'] : null,
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
			$is_stale
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
		if ( ! in_array( $status, self::TERMINAL_STATUSES, true ) ) {
			return false;
		}

		if ( ! $this->store->begin_transaction() ) {
			return false;
		}

		$run = $this->get_run( $run_id );
		if ( [] === $run || 'running' !== (string) $run['status'] ) {
			$this->store->rollback_transaction();
			return false;
		}

		if ( (string) ( $run['lock_token'] ?? '' ) !== $lock_token ) {
			$this->store->rollback_transaction();
			return false;
		}

		$meta_json = null;
		if ( isset( $args['meta'] ) && is_array( $args['meta'] ) ) {
			$encoded   = wp_json_encode( $args['meta'] );
			$meta_json = false === $encoded ? null : $encoded;
		}

		$updated = $this->store->update_completion(
			$run_id,
			$lock_token,
			[
				'status'               => $status,
				'finished_at_gmt'      => gmdate( 'Y-m-d H:i:s' ),
				'error_code'           => isset( $args['error_code'] ) ? (string) $args['error_code'] : null,
				'error_message'        => isset( $args['error_message'] ) ? (string) $args['error_message'] : null,
				'meta_json'            => $meta_json,
				'updated_at_gmt'       => gmdate( 'Y-m-d H:i:s' ),
			]
		);

		if ( false === $updated || 0 === $updated ) {
			$this->store->rollback_transaction();
			return false;
		}

		$session_updated = Agent_Session_Helper::get_instance()->apply_run_completion(
			(int) $run['session_id'],
			$status,
			isset( $args['next_run_at_gmt'] ) ? (string) $args['next_run_at_gmt'] : null
		);

		if ( ! $session_updated ) {
			$this->store->rollback_transaction();
			return false;
		}

		if ( ! $this->store->commit_transaction() ) {
			$this->store->rollback_transaction();
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
		return $this->store->get_run( $run_id );
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

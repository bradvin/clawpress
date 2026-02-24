<?php
/**
 * Agent run persistence + lock helper.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Helpers;

use ClawPress\Stores\Agent_Run_Store;

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
	private const TERMINAL_STATUSES = [
		'success',
		'failed',
		'cancelled',
		'canceled',
		'done',
		'error',
		'timeout',
		'requires_confirmation',
	];

	/**
	 * Statuses that can be manually re-enqueued.
	 *
	 * @var array<int,string>
	 */
	private const ENQUEUEABLE_STATUSES = [
		'success',
		'done',
		'failed',
		'cancelled',
		'canceled',
		'error',
		'timeout',
		'requires_confirmation',
	];

	/**
	 * Singleton instance.
	 *
	 * @var ?self
	 */
	private static ?self $instance = null;

	/**
	 * Run store instance for DB access.
	 *
	 * @var Agent_Run_Store
	 */
	private Agent_Run_Store $store;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->store = Agent_Run_Store::get_instance();
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
	 * Create a run row.
	 *
	 * @param int                 $session_id Parent session identifier.
	 * @param array<string,mixed> $args Optional run args.
	 */
	public function create_run( int $session_id, array $args = [] ): int {
		$idempotency_key = isset( $args['idempotency_key'] ) ? trim( (string) $args['idempotency_key'] ) : '';
		if ( $session_id > 0 && '' !== $idempotency_key ) {
			$existing_run = $this->get_run_by_idempotency_key( $session_id, $idempotency_key );
			if ( [] !== $existing_run && isset( $existing_run['id'] ) ) {
				return (int) $existing_run['id'];
			}
		}

		$now       = gmdate( 'Y-m-d H:i:s' );
		$meta      = isset( $args['meta'] ) && is_array( $args['meta'] ) ? $args['meta'] : [];
		$meta_json = [] !== $meta ? wp_json_encode( $meta ) : null;
		if ( false === $meta_json ) {
			$meta_json = null;
		}

		$resume_cursor_json = null;
		if ( isset( $args['resume_cursor'] ) ) {
			$encoded_resume     = wp_json_encode( $args['resume_cursor'] );
			$resume_cursor_json = false === $encoded_resume ? null : $encoded_resume;
		}

			$run_id = $this->store->insert_run(
				[
					'session_id'         => $session_id,
					'run_uuid'           => isset( $args['run_uuid'] ) ? (string) $args['run_uuid'] : $this->generate_uuid(),
				'trigger_type'       => isset( $args['trigger_type'] ) ? (string) $args['trigger_type'] : 'chat',
					'transport_mode'     => isset( $args['transport_mode'] ) ? (string) $args['transport_mode'] : 'polling',
					'status'             => isset( $args['status'] ) ? (string) $args['status'] : 'queued',
					'attempt'            => isset( $args['attempt'] ) ? max( 1, (int) $args['attempt'] ) : 1,
					'retry_count'        => isset( $args['retry_count'] ) ? max( 0, (int) $args['retry_count'] ) : 0,
					'max_attempts'       => isset( $args['max_attempts'] ) ? max( 1, (int) $args['max_attempts'] ) : 5,
				'next_retry_at_gmt'  => isset( $args['next_retry_at_gmt'] ) ? (string) $args['next_retry_at_gmt'] : null,
				'resume_cursor_json' => $resume_cursor_json,
				'meta_json'          => $meta_json,
				'idempotency_key'    => isset( $args['idempotency_key'] ) ? (string) $args['idempotency_key'] : null,
				'created_at_gmt'     => $now,
					'updated_at_gmt'     => $now,
				]
			);

			if ( $run_id > 0 ) {
				return $run_id;
			}

			// Handle write races for idempotent run creation (duplicate-key insert in concurrent request).
			if ( $session_id > 0 && '' !== $idempotency_key ) {
				$existing_run = $this->get_run_by_idempotency_key( $session_id, $idempotency_key );
				if ( [] !== $existing_run && isset( $existing_run['id'] ) ) {
					return (int) $existing_run['id'];
				}
			}

			return 0;
		}

	/**
	 * Fetch one run by idempotency key.
	 *
	 * @param int    $session_id Session identifier.
	 * @param string $idempotency_key Idempotency key.
	 * @return array<string,mixed>
	 */
	public function get_run_by_idempotency_key( int $session_id, string $idempotency_key ): array {
		$row = $this->store->get_run_by_idempotency_key( $session_id, $idempotency_key );
		if ( [] === $row ) {
			return [];
		}

		return $this->normalize_run_row( $row );
	}

	/**
	 * Re-enqueue an existing run.
	 *
	 * @param int $run_id Run identifier.
	 */
	public function enqueue_run( int $run_id ): bool {
		$run = $this->get_run( $run_id );
		if ( [] === $run ) {
			return false;
		}

		$current_status = isset( $run['status'] ) ? (string) $run['status'] : 'queued';
		if ( ! $this->is_enqueueable_status( $current_status ) ) {
			return false;
		}

		$updated = $this->store->update_enqueue( $run_id, $current_status, gmdate( 'Y-m-d H:i:s' ) );
		return false !== $updated && $updated > 0;
	}

	/**
	 * Claim next runnable run for a worker.
	 *
	 * @param string $worker_id Worker claim id.
	 * @param int    $lease_ttl_seconds Lease TTL in seconds.
	 * @param int    $scan_limit Max queued rows to scan.
	 * @return array<string,mixed>
	 */
	public function claim_next_runnable_run( string $worker_id, int $lease_ttl_seconds = 120, int $scan_limit = 20 ): array {
		$runnable_runs = $this->store->get_runnable_runs( $scan_limit, gmdate( 'Y-m-d H:i:s' ) );
		if ( [] === $runnable_runs ) {
			return [
				'claimed' => false,
				'reason'  => 'no_runnable_runs',
			];
		}

		foreach ( $runnable_runs as $run ) {
			$run_id = isset( $run['id'] ) ? (int) $run['id'] : 0;
			if ( $run_id <= 0 ) {
				continue;
			}

			$claim = $this->claim_run( $run_id, $worker_id, $lease_ttl_seconds );
			if ( ! empty( $claim['claimed'] ) ) {
				$claim['run'] = $this->get_run( $run_id );
				return $claim;
			}
		}

		return [
			'claimed' => false,
			'reason'  => 'claim_collision',
		];
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

		$now              = gmdate( 'Y-m-d H:i:s' );
		$lock_token       = hash( 'sha256', uniqid( $worker_id . ':', true ) );
		$expires_at       = gmdate( 'Y-m-d H:i:s', strtotime( $now ) + max( 1, $lease_ttl_seconds ) );
		$current_status   = isset( $run['status'] ) ? (string) $run['status'] : 'queued';
		$is_stale         = 'running' === $current_status
			&& isset( $run['lock_expires_at_gmt'] )
			&& is_string( $run['lock_expires_at_gmt'] )
			&& '' !== $run['lock_expires_at_gmt']
			&& strtotime( $run['lock_expires_at_gmt'] ) < strtotime( $now );
		$is_claimable_now = in_array( $current_status, [ 'queued', 'paused' ], true );

		if ( ! $is_claimable_now && ! $is_stale ) {
			return [
				'claimed' => false,
				'reason'  => 'not_claimable',
			];
		}

		$next_attempt = (int) ( $run['attempt'] ?? 1 );
		if ( $is_stale || 'paused' === $current_status ) {
			$next_attempt = max( 1, $next_attempt + 1 );
		}

		$updated = $this->store->update_claim(
			$run_id,
			$current_status,
			$is_stale ? (string) $run['lock_expires_at_gmt'] : null,
			[
				'status'               => 'running',
				'claimed_by'           => $worker_id,
				'lock_token'           => $lock_token,
				'lock_acquired_at_gmt' => $now,
				'lock_expires_at_gmt'  => $expires_at,
				'attempt'              => $next_attempt,
				'started_at_gmt'       => isset( $run['started_at_gmt'] ) && null !== $run['started_at_gmt']
					? (string) $run['started_at_gmt']
					: $now,
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
	 * Persist paused/in-progress slice state for a claimed run.
	 *
	 * @param int                 $run_id Run identifier.
	 * @param string              $lock_token Lock token from claim response.
	 * @param array<string,mixed> $args Progress details.
	 */
	public function pause_run( int $run_id, string $lock_token, array $args = [] ): bool {
		$run = $this->get_run( $run_id );
		if ( [] === $run || 'running' !== (string) $run['status'] ) {
			return false;
		}

		if ( (string) ( $run['lock_token'] ?? '' ) !== $lock_token ) {
			return false;
		}

		$resume_cursor_json = null;
		if ( array_key_exists( 'resume_cursor', $args ) ) {
			$encoded_resume     = wp_json_encode( $args['resume_cursor'] );
			$resume_cursor_json = false === $encoded_resume ? null : $encoded_resume;
		}

		$meta_json = null;
		if ( isset( $args['meta'] ) && is_array( $args['meta'] ) ) {
			$encoded_meta = wp_json_encode( $args['meta'] );
			$meta_json    = false === $encoded_meta ? null : $encoded_meta;
		}

			$updated = $this->store->update_progress(
				$run_id,
				$lock_token,
				[
					'status'             => isset( $args['status'] ) ? (string) $args['status'] : 'paused',
					'next_retry_at_gmt'  => isset( $args['next_retry_at_gmt'] ) ? (string) $args['next_retry_at_gmt'] : gmdate( 'Y-m-d H:i:s' ),
					'resume_cursor_json' => $resume_cursor_json,
					'meta_json'          => $meta_json,
					'retry_count'        => isset( $args['retry_count'] )
						? max( 0, (int) $args['retry_count'] )
						: (int) ( $run['retry_count'] ?? 0 ),
					'updated_at_gmt'     => gmdate( 'Y-m-d H:i:s' ),
				]
			);

		if ( false === $updated || 0 === $updated ) {
			return false;
		}

		Agent_Session_Helper::get_instance()->apply_run_completion( (int) $run['session_id'], 'paused', isset( $args['next_retry_at_gmt'] ) ? (string) $args['next_retry_at_gmt'] : null );
		return true;
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
			$encoded_meta = wp_json_encode( $args['meta'] );
			$meta_json    = false === $encoded_meta ? null : $encoded_meta;
		}

		$resume_cursor_json = null;
		if ( array_key_exists( 'resume_cursor', $args ) ) {
			$encoded_resume     = wp_json_encode( $args['resume_cursor'] );
			$resume_cursor_json = false === $encoded_resume ? null : $encoded_resume;
		}

		$updated = $this->store->update_completion(
			$run_id,
			$lock_token,
			[
				'status'             => $status,
				'finished_at_gmt'    => gmdate( 'Y-m-d H:i:s' ),
				'next_retry_at_gmt'  => isset( $args['next_retry_at_gmt'] ) ? (string) $args['next_retry_at_gmt'] : null,
				'resume_cursor_json' => $resume_cursor_json,
				'error_code'         => isset( $args['error_code'] ) ? (string) $args['error_code'] : null,
				'error_message'      => isset( $args['error_message'] ) ? (string) $args['error_message'] : null,
				'meta_json'          => $meta_json,
				'updated_at_gmt'     => gmdate( 'Y-m-d H:i:s' ),
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
		$row = $this->store->get_run( $run_id );
		if ( [] === $row ) {
			return [];
		}

		return $this->normalize_run_row( $row );
	}

	/**
	 * Build status summary for run polling.
	 *
	 * @param int $run_id Run identifier.
	 * @return array<string,mixed>
	 */
	public function get_run_status_summary( int $run_id ): array {
		$run = $this->get_run( $run_id );
		if ( [] === $run ) {
			return [];
		}

		return [
			'run_id'            => isset( $run['id'] ) ? (int) $run['id'] : 0,
			'session_id'        => isset( $run['session_id'] ) ? (int) $run['session_id'] : 0,
			'run_uuid'          => isset( $run['run_uuid'] ) ? (string) $run['run_uuid'] : '',
			'status'            => isset( $run['status'] ) ? (string) $run['status'] : 'queued',
			'trigger_type'      => isset( $run['trigger_type'] ) ? (string) $run['trigger_type'] : 'chat',
				'transport_mode'    => isset( $run['transport_mode'] ) ? (string) $run['transport_mode'] : 'polling',
				'attempt'           => isset( $run['attempt'] ) ? (int) $run['attempt'] : 1,
				'retry_count'       => isset( $run['retry_count'] ) ? (int) $run['retry_count'] : 0,
				'max_attempts'      => isset( $run['max_attempts'] ) ? (int) $run['max_attempts'] : 5,
			'next_retry_at_gmt' => $run['next_retry_at_gmt'] ?? null,
			'started_at_gmt'    => $run['started_at_gmt'] ?? null,
			'finished_at_gmt'   => $run['finished_at_gmt'] ?? null,
			'error_code'        => $run['error_code'] ?? null,
			'error_message'     => $run['error_message'] ?? null,
			'resume_cursor'     => $run['resume_cursor'] ?? null,
			'meta'              => $run['meta'] ?? null,
		];
	}

	/**
	 * Check whether a run status can be re-enqueued manually.
	 *
	 * @param string $status Run status.
	 */
	public function is_enqueueable_status( string $status ): bool {
		return in_array( strtolower( trim( $status ) ), self::ENQUEUEABLE_STATUSES, true );
	}

	/**
	 * Normalize JSON-backed run fields.
	 *
	 * @param array<string,mixed> $row Raw DB row.
	 * @return array<string,mixed>
	 */
	private function normalize_run_row( array $row ): array {
		if ( ! isset( $row['retry_count'] ) ) {
			$row['retry_count'] = 0;
		}

		if ( isset( $row['resume_cursor_json'] ) && is_string( $row['resume_cursor_json'] ) && '' !== trim( $row['resume_cursor_json'] ) ) {
			$decoded = json_decode( $row['resume_cursor_json'], true );
			if ( is_array( $decoded ) ) {
				$row['resume_cursor'] = $decoded;
			}
		}

		if ( isset( $row['meta_json'] ) && is_string( $row['meta_json'] ) && '' !== trim( $row['meta_json'] ) ) {
			$decoded = json_decode( $row['meta_json'], true );
			if ( is_array( $decoded ) ) {
				$row['meta'] = $decoded;
			}
		}

		return $row;
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

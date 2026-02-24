<?php
/**
 * In-memory wpdb stub for agent runtime tests.
 *
 * @package ClawPress\Tests
 */

declare( strict_types=1 );

namespace ClawPress\Tests\Support;

/**
 * In-memory wpdb replacement covering agent session/run/event stores.
 */
final class Agent_Runtime_Wpdb {
	public string $prefix = 'wp_';

	public int $insert_id = 0;

	/** @var array<int,array<string,mixed>> */
	public array $sessions = [];

	/** @var array<int,array<string,mixed>> */
	public array $runs = [];

	/** @var array<int,array<string,mixed>> */
	public array $events = [];

	/** @var array<int,mixed> */
	public array $last_prepare_args = [];

	public bool $fail_session_update = false;

	public bool $simulate_idempotency_race = false;

	public string $last_error = '';

	private bool $in_transaction = false;

	/** @var array{sessions:array<int,array<string,mixed>>,runs:array<int,array<string,mixed>>,events:array<int,array<string,mixed>>,insert_id:int}|null */
	private ?array $transaction_snapshot = null;

	public function get_charset_collate(): string {
		return 'DEFAULT CHARSET=utf8mb4';
	}

	/**
	 * @param string              $table Table name.
	 * @param array<string,mixed> $data Data payload.
	 * @param array<int,string>   $format Format list.
	 * @return int|false
	 */
	public function insert( string $table, array $data, array $format ) {
		unset( $format );
		$this->last_error = '';

		if ( false !== strpos( $table, 'agent_runs' ) ) {
			$idempotency_key = isset( $data['idempotency_key'] ) ? trim( (string) $data['idempotency_key'] ) : '';
			$session_id      = isset( $data['session_id'] ) ? (int) $data['session_id'] : 0;
			if ( $session_id > 0 && '' !== $idempotency_key ) {
				$existing_run = $this->find_run_by_idempotency_key( $session_id, $idempotency_key );
				if ( null === $existing_run && $this->simulate_idempotency_race ) {
					$this->simulate_idempotency_race = false;
					++$this->insert_id;
					$data['id']                    = $this->insert_id;
					$this->runs[ $this->insert_id ] = $data;
					$this->last_error              = 'Duplicate entry for key session_idempotency_key';
					return false;
				}

				if ( null !== $existing_run ) {
					$this->last_error = 'Duplicate entry for key session_idempotency_key';
					return false;
				}
			}
		}

		++$this->insert_id;
		$data['id'] = $this->insert_id;

		if ( false !== strpos( $table, 'agent_sessions' ) ) {
			$this->sessions[ $this->insert_id ] = $data;
			return 1;
		}

		if ( false !== strpos( $table, 'agent_runs' ) ) {
			$this->runs[ $this->insert_id ] = $data;
			return 1;
		}

		if ( false !== strpos( $table, 'agent_events' ) ) {
			$this->events[ $this->insert_id ] = $data;
			return 1;
		}

		return false;
	}

	/**
	 * @param string                    $table Table name.
	 * @param array<string,mixed>       $data Data payload.
	 * @param array<string,int|string>  $where Where payload.
	 * @param array<int,string>|null    $format Formats.
	 * @param array<int,string>|null    $where_format Where formats.
	 * @return int|false
	 */
	public function update( string $table, array $data, array $where, ?array $format = null, ?array $where_format = null ) {
		unset( $format, $where_format );

		$target = false !== strpos( $table, 'agent_sessions' ) ? 'sessions' : ( false !== strpos( $table, 'agent_runs' ) ? 'runs' : '' );
		if ( '' === $target ) {
			return false;
		}

		if ( $this->fail_session_update && 'sessions' === $target ) {
			return false;
		}

		$updated = 0;
		foreach ( $this->{$target} as $id => $row ) {
			$matches = true;
			foreach ( $where as $key => $value ) {
				$current = $row[ $key ] ?? null;
				if ( (string) $current !== (string) $value ) {
					$matches = false;
					break;
				}
			}

			if ( ! $matches ) {
				continue;
			}

			$this->{$target}[ $id ] = array_merge( $row, $data );
			++$updated;
		}

		return $updated;
	}

	/**
	 * @return int|false
	 */
	public function query( string $sql ) {
		$sql_upper = strtoupper( trim( $sql ) );

		if ( 'START TRANSACTION' === $sql_upper ) {
			$this->in_transaction       = true;
			$this->transaction_snapshot = [
				'sessions'  => $this->sessions,
				'runs'      => $this->runs,
				'events'    => $this->events,
				'insert_id' => $this->insert_id,
			];
			return 1;
		}

		if ( 'COMMIT' === $sql_upper ) {
			$this->in_transaction       = false;
			$this->transaction_snapshot = null;
			return 1;
		}

		if ( 'ROLLBACK' === $sql_upper ) {
			if ( $this->in_transaction && is_array( $this->transaction_snapshot ) ) {
				$this->sessions  = $this->transaction_snapshot['sessions'];
				$this->runs      = $this->transaction_snapshot['runs'];
				$this->events    = $this->transaction_snapshot['events'];
				$this->insert_id = $this->transaction_snapshot['insert_id'];
			}

			$this->in_transaction       = false;
			$this->transaction_snapshot = null;
			return 1;
		}

		if ( str_starts_with( $sql_upper, 'UPDATE' ) && false !== strpos( $sql_upper, 'AGENT_SESSIONS' ) ) {
			if ( $this->fail_session_update ) {
				return false;
			}

			$has_paused_clause = count( $this->last_prepare_args ) >= 7;
			$session_id_index  = $has_paused_clause ? 6 : 5;
			$next_run_index    = $has_paused_clause ? 4 : 3;
			$updated_index     = $has_paused_clause ? 5 : 4;
			$session_id        = isset( $this->last_prepare_args[ $session_id_index ] ) ? (int) $this->last_prepare_args[ $session_id_index ] : 0;
			if ( $session_id <= 0 || ! isset( $this->sessions[ $session_id ] ) ) {
				return 0;
			}

			$run_status = isset( $this->last_prepare_args[1] ) ? (string) $this->last_prepare_args[1] : '';
			$failures   = (int) ( $this->sessions[ $session_id ]['consecutive_failures'] ?? 0 );

			if ( in_array( $run_status, [ 'success', 'done', 'requires_confirmation' ], true ) ) {
				$failures = 0;
			} elseif ( 'paused' !== $run_status ) {
				++$failures;
			}

			$this->sessions[ $session_id ]['last_run_at_gmt']      = isset( $this->last_prepare_args[0] ) ? (string) $this->last_prepare_args[0] : null;
			$this->sessions[ $session_id ]['last_run_status']      = $run_status;
			$this->sessions[ $session_id ]['consecutive_failures'] = $failures;
			$this->sessions[ $session_id ]['next_run_at_gmt']      = $this->last_prepare_args[ $next_run_index ] ?? null;
			$this->sessions[ $session_id ]['updated_at_gmt']       = isset( $this->last_prepare_args[ $updated_index ] ) ? (string) $this->last_prepare_args[ $updated_index ] : null;
			return 1;
		}

		return false;
	}

	/**
	 * @param string              $query Query string.
	 * @param array<int,mixed>|mixed ...$args Prepare args.
	 */
	public function prepare( string $query, ...$args ): string {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}

		$this->last_prepare_args = $args;
		return $query;
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public function get_row( string $query, string $output ) {
		unset( $output );

		if ( false !== strpos( $query, 'idempotency_key = %s' ) ) {
			$session_id      = isset( $this->last_prepare_args[0] ) ? (int) $this->last_prepare_args[0] : 0;
			$idempotency_key = isset( $this->last_prepare_args[1] ) ? (string) $this->last_prepare_args[1] : '';
			if ( $session_id <= 0 || '' === $idempotency_key ) {
				return null;
			}

			$matching = array_values(
				array_filter(
					$this->runs,
					static fn( array $row ): bool => (int) ( $row['session_id'] ?? 0 ) === $session_id
						&& (string) ( $row['idempotency_key'] ?? '' ) === $idempotency_key
				)
			);

			if ( [] === $matching ) {
				return null;
			}

			usort(
				$matching,
				static fn( array $left, array $right ): int => (int) ( $right['id'] ?? 0 ) <=> (int) ( $left['id'] ?? 0 )
			);

			return $matching[0];
		}

		$id = isset( $this->last_prepare_args[0] ) ? (int) $this->last_prepare_args[0] : 0;
		if ( $id <= 0 ) {
			return null;
		}

		if ( false !== strpos( $query, 'agent_sessions' ) ) {
			return $this->sessions[ $id ] ?? null;
		}

		if ( false !== strpos( $query, 'agent_runs' ) ) {
			return $this->runs[ $id ] ?? null;
		}

		if ( false !== strpos( $query, 'agent_events' ) ) {
			return $this->events[ $id ] ?? null;
		}

		return null;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function get_results( string $query, string $output ): array {
		unset( $output );

		if ( false !== strpos( $query, 'agent_runs' ) ) {
			return $this->get_runnable_runs();
		}

		if ( false !== strpos( $query, 'agent_events' ) ) {
			return $this->get_events_rows( $query );
		}

		return [];
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function get_runnable_runs(): array {
		$now_gmt = isset( $this->last_prepare_args[0] ) ? (string) $this->last_prepare_args[0] : gmdate( 'Y-m-d H:i:s' );
		$limit   = isset( $this->last_prepare_args[2] ) ? (int) $this->last_prepare_args[2] : ( isset( $this->last_prepare_args[1] ) ? (int) $this->last_prepare_args[1] : 20 );
		$limit   = max( 1, $limit );
		$now_ts  = strtotime( $now_gmt );
		if ( false === $now_ts ) {
			$now_ts = time();
		}

		$rows = array_values(
			array_filter(
				$this->runs,
					static function ( array $row ) use ( $now_ts ): bool {
						$status = isset( $row['status'] ) ? (string) $row['status'] : '';
						if ( in_array( $status, [ 'queued', 'paused' ], true ) ) {
							$retry_at = isset( $row['next_retry_at_gmt'] ) ? (string) $row['next_retry_at_gmt'] : '';
							if ( '' === $retry_at ) {
								return true;
							}

							$retry_ts = strtotime( $retry_at );
							if ( false === $retry_ts ) {
								return true;
							}

							return $retry_ts <= $now_ts;
						}

						if ( 'running' === $status ) {
							$lock_expires_at = isset( $row['lock_expires_at_gmt'] ) ? (string) $row['lock_expires_at_gmt'] : '';
							if ( '' === $lock_expires_at ) {
								return false;
							}

							$lock_expires_ts = strtotime( $lock_expires_at );
							if ( false === $lock_expires_ts ) {
								return false;
							}

							return $lock_expires_ts <= $now_ts;
						}

						return false;
					}
				)
			);

		usort(
			$rows,
			static function ( array $left, array $right ): int {
				$left_ts  = strtotime( (string) ( $left['created_at_gmt'] ?? '' ) ) ?: 0;
				$right_ts = strtotime( (string) ( $right['created_at_gmt'] ?? '' ) ) ?: 0;
				if ( $left_ts === $right_ts ) {
					return (int) ( $left['id'] ?? 0 ) <=> (int) ( $right['id'] ?? 0 );
				}

				return $left_ts <=> $right_ts;
			}
		);

		return array_slice( $rows, 0, $limit );
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function get_events_rows( string $query ): array {
		$arg_index = 0;
		$after_id  = isset( $this->last_prepare_args[ $arg_index ] ) ? (int) $this->last_prepare_args[ $arg_index ] : 0;
		++$arg_index;

		$run_id = null;
		if ( false !== strpos( $query, 'run_id = %d' ) ) {
			$run_id = isset( $this->last_prepare_args[ $arg_index ] ) ? (int) $this->last_prepare_args[ $arg_index ] : null;
			++$arg_index;
		}

		$session_id = null;
		if ( false !== strpos( $query, 'session_id = %d' ) ) {
			$session_id = isset( $this->last_prepare_args[ $arg_index ] ) ? (int) $this->last_prepare_args[ $arg_index ] : null;
			++$arg_index;
		}

		$event_type = null;
		if ( false !== strpos( $query, 'event_type = %s' ) ) {
			$event_type = isset( $this->last_prepare_args[ $arg_index ] ) ? (string) $this->last_prepare_args[ $arg_index ] : null;
			++$arg_index;
		}

		$limit = isset( $this->last_prepare_args[ $arg_index ] ) ? (int) $this->last_prepare_args[ $arg_index ] : 100;
		$limit = max( 1, $limit );

		$rows = array_values(
			array_filter(
				$this->events,
				static function ( array $row ) use ( $after_id, $run_id, $session_id, $event_type ): bool {
					$id = isset( $row['id'] ) ? (int) $row['id'] : 0;
					if ( $id <= $after_id ) {
						return false;
					}

					if ( null !== $run_id && (int) ( $row['run_id'] ?? 0 ) !== $run_id ) {
						return false;
					}

					if ( null !== $session_id && (int) ( $row['session_id'] ?? 0 ) !== $session_id ) {
						return false;
					}

					if ( null !== $event_type && (string) ( $row['event_type'] ?? '' ) !== $event_type ) {
						return false;
					}

					return true;
				}
			)
		);

		usort(
			$rows,
			static fn( array $left, array $right ): int => (int) ( $left['id'] ?? 0 ) <=> (int) ( $right['id'] ?? 0 )
		);

		return array_slice( $rows, 0, $limit );
	}

	/**
	 * Find run by session + idempotency key.
	 *
	 * @param int    $session_id Session identifier.
	 * @param string $idempotency_key Idempotency key.
	 * @return array<string,mixed>|null
	 */
	private function find_run_by_idempotency_key( int $session_id, string $idempotency_key ): ?array {
		$matching = array_values(
			array_filter(
				$this->runs,
				static fn( array $row ): bool => (int) ( $row['session_id'] ?? 0 ) === $session_id
					&& (string) ( $row['idempotency_key'] ?? '' ) === $idempotency_key
			)
		);

		if ( [] === $matching ) {
			return null;
		}

		usort(
			$matching,
			static fn( array $left, array $right ): int => (int) ( $right['id'] ?? 0 ) <=> (int) ( $left['id'] ?? 0 )
		);

		return $matching[0];
	}
}

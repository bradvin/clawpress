<?php
/**
 * Server-side command confirmation storage.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Commands;

defined( 'ABSPATH' ) || exit;

/**
 * Per-user confirmation token store for destructive command flows.
 */
final class Command_Confirmation_Store {
	/**
	 * Option key prefix.
	 */
	private const OPTION_PREFIX = 'clawpress_command_confirmation_';

	/**
	 * Tool-batch option key prefix.
	 */
	private const TOOL_BATCH_OPTION_PREFIX = 'clawpress_tool_confirmation_batch_';

	/**
	 * Agents API pending-action kind for tool batches.
	 */
	private const TOOL_BATCH_PENDING_ACTION_KIND = 'clawpress.tool_batch';

	/**
	 * Confirmation token TTL in seconds.
	 */
	private const TOKEN_TTL = 300;

	/**
	 * Issue and persist a confirmation token.
	 *
	 * @param string   $action Action identifier.
	 * @param int|null $user_id User ID.
	 * @return array{token:string,expires_at:int}
	 */
	public function issue_confirmation( string $action, ?int $user_id = null ): array {
		$record = [
			'action'     => $action,
			'token'      => $this->generate_token(),
			'expires_at' => time() + self::TOKEN_TTL,
		];

		update_option( $this->get_option_key( $user_id ), $record );

		return [
			'token'      => $record['token'],
			'expires_at' => $record['expires_at'],
		];
	}

	/**
	 * Consume and validate a confirmation token.
	 *
	 * @param string      $action Action identifier.
	 * @param string|null $token Token value.
	 * @param int|null    $user_id User ID.
	 */
	public function consume_confirmation( string $action, ?string $token, ?int $user_id = null ): bool {
		$record = get_option( $this->get_option_key( $user_id ), [] );
		$this->clear_confirmation( $user_id );

		if ( ! is_array( $record ) ) {
			return false;
		}

		if ( ! isset( $record['action'], $record['token'], $record['expires_at'] ) ) {
			return false;
		}

		if ( ! is_string( $record['action'] ) || ! is_string( $record['token'] ) || ! is_numeric( $record['expires_at'] ) ) {
			return false;
		}

		if ( $action !== $record['action'] ) {
			return false;
		}

		if ( (int) $record['expires_at'] < time() ) {
			return false;
		}

		if ( null === $token || '' === trim( $token ) ) {
			return false;
		}

		return hash_equals( $record['token'], trim( $token ) );
	}

	/**
	 * Issue and persist one active tool-confirmation batch for a user.
	 *
	 * @param array<int,array<string,mixed>> $tool_calls Pending tool calls.
	 * @param int|null                       $user_id User ID.
	 * @return array{batch_id:string,created_at:int,expires_at:int,calls:array<int,array<string,mixed>>}
	 */
	public function issue_tool_batch( array $tool_calls, ?int $user_id = null ): array {
		$calls = $this->normalize_tool_batch_calls( $tool_calls );

		$record = [
			'batch_id'   => $this->generate_batch_id(),
			'created_at' => time(),
			'expires_at' => time() + self::TOKEN_TTL,
			'calls'      => $calls,
		];

		if ( $this->store_tool_batch_pending_action( $record, $user_id ) ) {
			update_option( $this->get_tool_batch_option_key( $user_id ), [] );
			return $record;
		}

		update_option( $this->get_tool_batch_option_key( $user_id ), $record );

		return $record;
	}

	/**
	 * Resolve active tool-confirmation batch for a user.
	 *
	 * @param int|null $user_id User ID.
	 * @return array{batch_id:string,created_at:int,expires_at:int,calls:array<int,array<string,mixed>>}|null
	 */
	public function get_tool_batch( ?int $user_id = null ): ?array {
		$pending_action_record = $this->get_tool_batch_from_pending_action_store( $user_id );
		if ( null !== $pending_action_record ) {
			return $pending_action_record;
		}

		$record = $this->normalize_tool_batch_record(
			get_option( $this->get_tool_batch_option_key( $user_id ), [] )
		);
		if ( null === $record ) {
			return null;
		}

		if ( $record['expires_at'] < time() ) {
			$this->clear_tool_batch( $user_id );
			return null;
		}

		return $record;
	}

	/**
	 * Consume the active tool-confirmation batch.
	 *
	 * @param string|null $batch_id Optional expected batch ID.
	 * @param int|null    $user_id User ID.
	 * @return array{batch_id:string,created_at:int,expires_at:int,calls:array<int,array<string,mixed>>}|null
	 */
	public function consume_tool_batch( ?string $batch_id = null, ?int $user_id = null ): ?array {
		$record = $this->get_tool_batch( $user_id );
		if ( null === $record ) {
			return null;
		}

		$expected_batch_id = null === $batch_id ? '' : strtolower( trim( $batch_id ) );
		if ( '' !== $expected_batch_id && ! hash_equals( $record['batch_id'], $expected_batch_id ) ) {
			return null;
		}

		if ( ! $this->record_tool_batch_resolution( $record['batch_id'], true, $user_id ) ) {
			$this->clear_tool_batch( $user_id );
		} else {
			update_option( $this->get_tool_batch_option_key( $user_id ), [] );
		}

		return $record;
	}

	/**
	 * Clear active tool-confirmation batch.
	 *
	 * @param int|null $user_id User ID.
	 */
	public function clear_tool_batch( ?int $user_id = null ): void {
		$pending_action_record = $this->get_tool_batch_from_pending_action_store( $user_id );
		if ( null !== $pending_action_record ) {
			$store = $this->get_pending_action_store();
			if ( is_object( $store ) ) {
				$store->delete( $pending_action_record['batch_id'] );
			}
		}

		update_option( $this->get_tool_batch_option_key( $user_id ), [] );
	}

	/**
	 * Clear pending confirmation record.
	 *
	 * @param int|null $user_id User ID.
	 */
	private function clear_confirmation( ?int $user_id = null ): void {
		update_option( $this->get_option_key( $user_id ), [] );
	}

	/**
	 * Resolve option key for user.
	 *
	 * @param int|null $user_id User ID.
	 */
	private function get_option_key( ?int $user_id = null ): string {
		$resolved_user_id = null === $user_id ? get_current_user_id() : $user_id;
		return self::OPTION_PREFIX . (int) $resolved_user_id;
	}

	/**
	 * Resolve option key for active tool-confirmation batch.
	 *
	 * @param int|null $user_id User ID.
	 */
	private function get_tool_batch_option_key( ?int $user_id = null ): string {
		$resolved_user_id = null === $user_id ? get_current_user_id() : $user_id;
		return self::TOOL_BATCH_OPTION_PREFIX . (int) $resolved_user_id;
	}

	/**
	 * Store a tool batch through Agents API pending actions when available.
	 *
	 * @param array{batch_id:string,created_at:int,expires_at:int,calls:array<int,array<string,mixed>>} $record Batch record.
	 * @param int|null $user_id User ID.
	 */
	private function store_tool_batch_pending_action( array $record, ?int $user_id = null ): bool {
		$store = $this->get_pending_action_store();
		if ( ! is_object( $store ) || ! class_exists( '\AgentsAPI\AI\Approvals\WP_Agent_Pending_Action' ) ) {
			return false;
		}

		try {
			$tool_count = count( $record['calls'] );
			$summary    = 1 === $tool_count
				? __( 'Confirm 1 destructive tool action.', 'clawpress' )
				: sprintf(
					/* translators: %d: number of pending destructive tool calls. */
					__( 'Confirm %d destructive tool actions.', 'clawpress' ),
					$tool_count
				);

			$action = \AgentsAPI\AI\Approvals\WP_Agent_Pending_Action::from_array(
				[
					'action_id'   => $record['batch_id'],
					'kind'        => self::TOOL_BATCH_PENDING_ACTION_KIND,
					'summary'     => $summary,
					'preview'     => [
						'calls' => $record['calls'],
					],
					'apply_input' => $record,
					'agent'       => 'clawpress',
					'creator'     => $this->get_pending_action_creator( $user_id ),
					'created_at'  => gmdate( 'c', $record['created_at'] ),
					'expires_at'  => gmdate( 'c', $record['expires_at'] ),
					'metadata'    => [
						'source'  => 'clawpress_tool_confirmation_batch',
						'user_id' => $this->resolve_user_id( $user_id ),
					],
				]
			);
		} catch ( \Throwable $throwable ) {
			unset( $throwable );
			return false;
		}

		return (bool) $store->store( $action );
	}

	/**
	 * Resolve the active tool batch through Agents API pending actions.
	 *
	 * @param int|null $user_id User ID.
	 * @return array{batch_id:string,created_at:int,expires_at:int,calls:array<int,array<string,mixed>>}|null
	 */
	private function get_tool_batch_from_pending_action_store( ?int $user_id = null ): ?array {
		$store = $this->get_pending_action_store();
		if ( ! is_object( $store ) ) {
			return null;
		}

		$actions = $store->list(
			[
				'status'  => 'pending',
				'kind'    => self::TOOL_BATCH_PENDING_ACTION_KIND,
				'agent'   => 'clawpress',
				'creator' => $this->get_pending_action_creator( $user_id ),
				'limit'   => 1,
			]
		);

		foreach ( $actions as $action ) {
			if ( ! is_object( $action ) || ! method_exists( $action, 'to_array' ) ) {
				continue;
			}

			$record = $this->normalize_tool_batch_record_from_pending_action( $action->to_array() );
			if ( null !== $record ) {
				return $record;
			}
		}

		return null;
	}

	/**
	 * Convert one pending action to a tool-batch record.
	 *
	 * @param array<string,mixed> $action Pending action payload.
	 * @return array{batch_id:string,created_at:int,expires_at:int,calls:array<int,array<string,mixed>>}|null
	 */
	private function normalize_tool_batch_record_from_pending_action( array $action ): ?array {
		$apply_input = isset( $action['apply_input'] ) && is_array( $action['apply_input'] )
			? $action['apply_input']
			: [];
		$record      = $this->normalize_tool_batch_record( $apply_input );

		if ( null === $record ) {
			return null;
		}

		if ( isset( $action['created_at'] ) && is_string( $action['created_at'] ) ) {
			$created_at = strtotime( $action['created_at'] );
			if ( false !== $created_at ) {
				$record['created_at'] = $created_at;
			}
		}

		if ( isset( $action['expires_at'] ) && is_string( $action['expires_at'] ) ) {
			$expires_at = strtotime( $action['expires_at'] );
			if ( false !== $expires_at ) {
				$record['expires_at'] = $expires_at;
			}
		}

		return $record;
	}

	/**
	 * Record a tool-batch pending-action resolution.
	 *
	 * @param string   $batch_id Batch ID.
	 * @param bool     $accepted Whether the batch was accepted.
	 * @param int|null $user_id User ID.
	 */
	private function record_tool_batch_resolution( string $batch_id, bool $accepted, ?int $user_id = null ): bool {
		$store = $this->get_pending_action_store();
		if ( ! is_object( $store ) || ! class_exists( '\AgentsAPI\AI\Approvals\WP_Agent_Approval_Decision' ) ) {
			return false;
		}

		$decision = $accepted
			? \AgentsAPI\AI\Approvals\WP_Agent_Approval_Decision::accepted()
			: \AgentsAPI\AI\Approvals\WP_Agent_Approval_Decision::rejected();

		return (bool) $store->record_resolution(
			$batch_id,
			$decision,
			$this->get_pending_action_creator( $user_id ),
			[
				'batch_id' => $batch_id,
			],
			null,
			[
				'source' => 'clawpress_tool_confirmation_batch',
			]
		);
	}

	/**
	 * Resolve the host-provided Agents API pending action store.
	 *
	 * @return object|null
	 */
	private function get_pending_action_store() {
		if ( ! interface_exists( '\AgentsAPI\AI\Approvals\WP_Agent_Pending_Action_Store' ) ) {
			return null;
		}

		$store = apply_filters(
			'wp_agent_pending_action_store',
			null,
			[
				'source' => 'clawpress_tool_confirmation_batch',
				'kind'   => self::TOOL_BATCH_PENDING_ACTION_KIND,
			]
		);

		return $store instanceof \AgentsAPI\AI\Approvals\WP_Agent_Pending_Action_Store ? $store : null;
	}

	/**
	 * Build the pending-action creator identifier for a user.
	 *
	 * @param int|null $user_id User ID.
	 */
	private function get_pending_action_creator( ?int $user_id = null ): string {
		return 'user:' . $this->resolve_user_id( $user_id );
	}

	/**
	 * Resolve a user ID.
	 *
	 * @param int|null $user_id User ID.
	 */
	private function resolve_user_id( ?int $user_id = null ): int {
		$resolved_user_id = null === $user_id ? get_current_user_id() : $user_id;
		return max( 0, (int) $resolved_user_id );
	}

	/**
	 * Normalize raw tool-batch record payload.
	 *
	 * @param mixed $record Raw record.
	 * @return array{batch_id:string,created_at:int,expires_at:int,calls:array<int,array<string,mixed>>}|null
	 */
	private function normalize_tool_batch_record( $record ): ?array {
		if ( ! is_array( $record ) ) {
			return null;
		}

		$batch_id   = isset( $record['batch_id'] ) ? strtolower( trim( (string) $record['batch_id'] ) ) : '';
		$created_at = isset( $record['created_at'] ) ? (int) $record['created_at'] : 0;
		$expires_at = isset( $record['expires_at'] ) ? (int) $record['expires_at'] : 0;
		$calls      = isset( $record['calls'] ) && is_array( $record['calls'] )
			? $this->normalize_tool_batch_calls( $record['calls'] )
			: [];

		if ( '' === $batch_id || $created_at <= 0 || $expires_at <= 0 || [] === $calls ) {
			return null;
		}

		return [
			'batch_id'   => $batch_id,
			'created_at' => $created_at,
			'expires_at' => $expires_at,
			'calls'      => $calls,
		];
	}

	/**
	 * Normalize one list of pending tool calls.
	 *
	 * @param array<int,mixed> $tool_calls Raw calls.
	 * @return array<int,array<string,mixed>>
	 */
	private function normalize_tool_batch_calls( array $tool_calls ): array {
		$normalized = [];

		foreach ( $tool_calls as $tool_call ) {
			$normalized_call = $this->normalize_tool_batch_call( $tool_call );
			if ( null === $normalized_call ) {
				continue;
			}

			$normalized[] = $normalized_call;
		}

		return $normalized;
	}

	/**
	 * Normalize one pending tool-call row.
	 *
	 * @param mixed $tool_call Raw tool call.
	 * @return array<string,mixed>|null
	 */
	private function normalize_tool_batch_call( $tool_call ): ?array {
		if ( ! is_array( $tool_call ) ) {
			return null;
		}

		$tool_name = isset( $tool_call['tool_name'] ) ? strtolower( trim( (string) $tool_call['tool_name'] ) ) : '';
		if ( '' === $tool_name ) {
			return null;
		}

		$args = [];
		if ( isset( $tool_call['args'] ) && is_array( $tool_call['args'] ) ) {
			$args = $tool_call['args'];
		} elseif ( isset( $tool_call['args'] ) && is_object( $tool_call['args'] ) ) {
			$args = (array) $tool_call['args'];
		}

		$ability_name = isset( $tool_call['ability_name'] )
			? trim( (string) $tool_call['ability_name'] )
			: '';

		return [
			'tool_name'    => $tool_name,
			'ability_name' => $ability_name,
			'args'         => $args,
		];
	}

	/**
	 * Build an opaque confirmation token.
	 */
	private function generate_token(): string {
		try {
			return bin2hex( random_bytes( 5 ) );
		} catch ( \Throwable $throwable ) {
			unset( $throwable );
			return substr( md5( uniqid( (string) wp_rand(), true ) ), 0, 10 );
		}
	}

	/**
	 * Build an opaque batch ID.
	 */
	private function generate_batch_id(): string {
		try {
			return bin2hex( random_bytes( 8 ) );
		} catch ( \Throwable $throwable ) {
			unset( $throwable );
			return substr( md5( uniqid( (string) wp_rand(), true ) ), 0, 16 );
		}
	}
}

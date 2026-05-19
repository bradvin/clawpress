<?php
/**
 * Agents API pending-action store adapter.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\AgentsAPI;

use AgentsAPI\AI\Approvals\WP_Agent_Approval_Decision;
use AgentsAPI\AI\Approvals\WP_Agent_Pending_Action;
use AgentsAPI\AI\Approvals\WP_Agent_Pending_Action_Resolver;
use AgentsAPI\AI\Approvals\WP_Agent_Pending_Action_Status;
use AgentsAPI\AI\Approvals\WP_Agent_Pending_Action_Store;

defined( 'ABSPATH' ) || exit;

/**
 * Option-backed pending-action store for ClawPress approval flows.
 */
final class Pending_Action_Store implements WP_Agent_Pending_Action_Store, WP_Agent_Pending_Action_Resolver {
	/**
	 * Option key used for durable pending actions.
	 */
	private const OPTION_KEY = 'clawpress_agents_api_pending_actions';

	/**
	 * Persist a pending action record.
	 */
	public function store( WP_Agent_Pending_Action $action ): bool {
		$actions                           = $this->read_actions();
		$actions[ $action->get_action_id() ] = $action->to_array();
		$this->write_actions( $actions );

		return true;
	}

	/**
	 * Retrieve a pending action by action ID.
	 */
	public function get( string $action_id, bool $include_resolved = false ): ?WP_Agent_Pending_Action {
		$this->expire();

		$action_id = trim( $action_id );
		if ( '' === $action_id ) {
			return null;
		}

		$actions = $this->read_actions();
		if ( ! isset( $actions[ $action_id ] ) || ! is_array( $actions[ $action_id ] ) ) {
			return null;
		}

		$action = $this->action_from_array( $actions[ $action_id ] );
		if ( null === $action ) {
			return null;
		}

		if ( ! $include_resolved && WP_Agent_Pending_Action_Status::PENDING !== $action->get_status() ) {
			return null;
		}

		return $action;
	}

	/**
	 * List pending action records.
	 *
	 * @return array<int,WP_Agent_Pending_Action>
	 */
	public function list( array $filters = [] ): array {
		$this->expire();

		$actions = [];
		foreach ( $this->read_actions() as $action_data ) {
			if ( ! is_array( $action_data ) ) {
				continue;
			}

			$action = $this->action_from_array( $action_data );
			if ( null === $action || ! $this->matches_filters( $action, $filters ) ) {
				continue;
			}

			$actions[] = $action;
		}

		usort(
			$actions,
			static fn( WP_Agent_Pending_Action $left, WP_Agent_Pending_Action $right ): int => strcmp( $right->get_created_at(), $left->get_created_at() )
		);

		$offset = isset( $filters['offset'] ) ? max( 0, (int) $filters['offset'] ) : 0;
		$limit  = isset( $filters['limit'] ) ? max( 0, (int) $filters['limit'] ) : 0;

		if ( $offset > 0 ) {
			$actions = array_slice( $actions, $offset );
		}

		if ( $limit > 0 ) {
			$actions = array_slice( $actions, 0, $limit );
		}

		return $actions;
	}

	/**
	 * Summarize pending action records.
	 *
	 * @return array<string,mixed>
	 */
	public function summary( array $filters = [] ): array {
		$actions   = $this->list( $filters );
		$by_status = [];
		$by_kind   = [];

		foreach ( $actions as $action ) {
			$status = $action->get_status();
			$kind   = $action->get_kind();

			$by_status[ $status ] = ( $by_status[ $status ] ?? 0 ) + 1;
			$by_kind[ $kind ]     = ( $by_kind[ $kind ] ?? 0 ) + 1;
		}

		return [
			'total'     => count( $actions ),
			'by_status' => $by_status,
			'by_kind'   => $by_kind,
		];
	}

	/**
	 * Record a terminal resolution while retaining audit fields.
	 *
	 * @param mixed|null $result JSON-serializable resolution result.
	 */
	public function record_resolution( string $action_id, WP_Agent_Approval_Decision $decision, string $resolver, $result = null, ?string $error = null, array $metadata = [] ): bool {
		$actions = $this->read_actions();
		if ( ! isset( $actions[ $action_id ] ) || ! is_array( $actions[ $action_id ] ) ) {
			return false;
		}

		$action = $this->action_from_array( $actions[ $action_id ] );
		if ( null === $action ) {
			return false;
		}

		$data                         = $action->to_array();
		$data['status']               = $decision->is_accepted() ? WP_Agent_Pending_Action_Status::ACCEPTED : WP_Agent_Pending_Action_Status::REJECTED;
		$data['resolved_at']          = $this->now();
		$data['resolver']             = '' !== trim( $resolver ) ? trim( $resolver ) : 'unknown';
		$data['resolution_result']    = $result;
		$data['resolution_error']     = null === $error || '' === trim( $error ) ? null : trim( $error );
		$data['resolution_metadata']  = $this->normalize_json_array( $metadata );
		$actions[ $action_id ]        = $data;
		$this->write_actions( $actions );

		return true;
	}

	/**
	 * Mark due pending actions as expired.
	 */
	public function expire( ?string $before = null ): int {
		$boundary_timestamp = null === $before || '' === trim( $before ) ? time() : strtotime( $before );
		if ( false === $boundary_timestamp ) {
			$boundary_timestamp = time();
		}

		$expired = 0;
		$actions = $this->read_actions();
		foreach ( $actions as $action_id => $action_data ) {
			if ( ! is_array( $action_data ) ) {
				continue;
			}

			$action = $this->action_from_array( $action_data );
			if ( null === $action || WP_Agent_Pending_Action_Status::PENDING !== $action->get_status() ) {
				continue;
			}

			$expires_at = $action->get_expires_at();
			if ( null === $expires_at || strtotime( $expires_at ) > $boundary_timestamp ) {
				continue;
			}

			$data                        = $action->to_array();
			$data['status']              = WP_Agent_Pending_Action_Status::EXPIRED;
			$data['resolved_at']         = $this->now();
			$data['resolver']            = 'system:expiry';
			$data['resolution_result']   = null;
			$data['resolution_error']    = __( 'Pending action expired.', 'clawpress' );
			$actions[ (string) $action_id ] = $data;
			++$expired;
		}

		if ( $expired > 0 ) {
			$this->write_actions( $actions );
		}

		return $expired;
	}

	/**
	 * Delete a pending action by ID.
	 */
	public function delete( string $action_id ): bool {
		$actions = $this->read_actions();
		if ( ! isset( $actions[ $action_id ] ) || ! is_array( $actions[ $action_id ] ) ) {
			return false;
		}

		$action = $this->action_from_array( $actions[ $action_id ] );
		if ( null === $action ) {
			unset( $actions[ $action_id ] );
			$this->write_actions( $actions );
			return true;
		}

		$data                      = $action->to_array();
		$data['status']            = WP_Agent_Pending_Action_Status::DELETED;
		$data['resolved_at']       = $this->now();
		$data['resolver']          = 'system:delete';
		$data['resolution_result'] = null;
		$actions[ $action_id ]     = $data;
		$this->write_actions( $actions );

		return true;
	}

	/**
	 * Resolve a pending action by identifier.
	 *
	 * @param mixed $payload Fresh resolver payload.
	 * @param mixed $context Optional caller context.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function resolve_pending_action( string $pending_action_id, WP_Agent_Approval_Decision $decision, string $resolver, array $payload = [], array $context = [] ): mixed {
		$action = $this->get( $pending_action_id );
		if ( null === $action ) {
			return new \WP_Error(
				'clawpress_pending_action_not_found',
				__( 'Pending action not found.', 'clawpress' )
			);
		}

		$result = [
			'action'  => $action->to_array(),
			'payload' => $payload,
			'context' => $context,
		];

		if ( ! $this->record_resolution( $pending_action_id, $decision, $resolver, $result, null, [ 'source' => 'agents_api' ] ) ) {
			return new \WP_Error(
				'clawpress_pending_action_resolution_failed',
				__( 'Unable to resolve pending action.', 'clawpress' )
			);
		}

		return $result;
	}

	/**
	 * Check whether an action matches list filters.
	 *
	 * @param array<string,mixed> $filters Filters.
	 */
	private function matches_filters( WP_Agent_Pending_Action $action, array $filters ): bool {
		foreach ( [ 'status', 'kind', 'agent', 'creator', 'resolver' ] as $field ) {
			if ( isset( $filters[ $field ] ) && '' !== (string) $filters[ $field ] && (string) $filters[ $field ] !== (string) $action->to_array()[ $field ] ) {
				return false;
			}
		}

		$workspace = $action->get_workspace();
		if ( isset( $filters['workspace_type'] ) && '' !== (string) $filters['workspace_type'] ) {
			if ( null === $workspace || (string) $filters['workspace_type'] !== $workspace->workspace_type ) {
				return false;
			}
		}

		if ( isset( $filters['workspace_id'] ) && '' !== (string) $filters['workspace_id'] ) {
			if ( null === $workspace || (string) $filters['workspace_id'] !== $workspace->workspace_id ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Create a pending action object from stored data.
	 *
	 * @param array<string,mixed> $data Stored data.
	 */
	private function action_from_array( array $data ): ?WP_Agent_Pending_Action {
		try {
			return WP_Agent_Pending_Action::from_array( $data );
		} catch ( \Throwable $throwable ) {
			unset( $throwable );
			return null;
		}
	}

	/**
	 * Read all persisted actions.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function read_actions(): array {
		$actions = get_option( self::OPTION_KEY, [] );
		return is_array( $actions ) ? $actions : [];
	}

	/**
	 * Persist all actions.
	 *
	 * @param array<string,array<string,mixed>> $actions Actions keyed by ID.
	 */
	private function write_actions( array $actions ): void {
		update_option( self::OPTION_KEY, $actions, false );
	}

	/**
	 * Normalize arbitrary metadata to JSON-safe data.
	 *
	 * @param array<string,mixed> $value Raw metadata.
	 * @return array<string,mixed>
	 */
	private function normalize_json_array( array $value ): array {
		$encoded = wp_json_encode( $value );
		if ( false === $encoded ) {
			return [];
		}

		$decoded = json_decode( (string) $encoded, true );
		return is_array( $decoded ) ? $decoded : [];
	}

	/**
	 * Current timestamp string.
	 */
	private function now(): string {
		return gmdate( 'c' );
	}
}

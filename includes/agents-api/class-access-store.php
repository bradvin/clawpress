<?php
/**
 * Agents API access-store adapter.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\AgentsAPI;

defined( 'ABSPATH' ) || exit;

/**
 * Exposes the ClawPress capability gate as an Agents API access store.
 */
final class Access_Store implements \WP_Agent_Access_Store {
	/**
	 * Create or update an access grant.
	 *
	 * ClawPress access is derived from WordPress capabilities, so explicit
	 * grant mutation is intentionally not persisted by this adapter.
	 */
	public function grant_access( \WP_Agent_Access_Grant $grant ): \WP_Agent_Access_Grant {
		return $grant;
	}

	/**
	 * Revoke a user's grant.
	 *
	 * Capability-derived ClawPress access cannot be revoked through this adapter.
	 */
	public function revoke_access( string $agent_id, int $user_id, ?string $workspace_id = null ): bool {
		unset( $agent_id, $user_id, $workspace_id );
		return false;
	}

	/**
	 * Fetch a user's effective ClawPress agent grant.
	 */
	public function get_access( string $agent_id, int $user_id, ?string $workspace_id = null ): ?\WP_Agent_Access_Grant {
		if ( ! $this->matches_agent( $agent_id ) || ! $this->user_can_access_clawpress( $user_id ) ) {
			return null;
		}

		return $this->build_grant( $user_id, $workspace_id );
	}

	/**
	 * List agent IDs accessible to a user at or above the optional role.
	 *
	 * @return array<int,string>
	 */
	public function get_agent_ids_for_user( int $user_id, ?string $minimum_role = null, ?string $workspace_id = null ): array {
		unset( $workspace_id );

		if ( ! $this->role_meets( $minimum_role ) || ! $this->user_can_access_clawpress( $user_id ) ) {
			return [];
		}

		return [ Agents_API::AGENT_SLUG ];
	}

	/**
	 * List known grants for the ClawPress agent.
	 *
	 * The capability source is not enumerable without a user query, so this
	 * returns the current user grant when it can be resolved.
	 *
	 * @return array<int,\WP_Agent_Access_Grant>
	 */
	public function get_users_for_agent( string $agent_id, ?string $workspace_id = null ): array {
		if ( ! $this->matches_agent( $agent_id ) ) {
			return [];
		}

		$user_id = get_current_user_id();
		if ( $user_id <= 0 || ! $this->user_can_access_clawpress( $user_id ) ) {
			return [];
		}

		return [ $this->build_grant( $user_id, $workspace_id ) ];
	}

	/**
	 * Check whether the requested agent is the bundled ClawPress agent.
	 */
	private function matches_agent( string $agent_id ): bool {
		return Agents_API::AGENT_SLUG === sanitize_key( $agent_id );
	}

	/**
	 * Check the existing ClawPress capability gate for a user.
	 */
	private function user_can_access_clawpress( int $user_id ): bool {
		return $user_id > 0 && clawpress_check_permissions_for_user( $user_id );
	}

	/**
	 * Check whether the derived admin grant satisfies a requested role.
	 */
	private function role_meets( ?string $minimum_role ): bool {
		$role = null === $minimum_role || '' === trim( $minimum_role )
			? \WP_Agent_Access_Grant::ROLE_VIEWER
			: $minimum_role;

		if ( ! \WP_Agent_Access_Grant::is_valid_role( $role ) ) {
			return false;
		}

		$roles          = \WP_Agent_Access_Grant::roles();
		$actual_index   = array_search( \WP_Agent_Access_Grant::ROLE_ADMIN, $roles, true );
		$required_index = array_search( $role, $roles, true );

		return false !== $actual_index && false !== $required_index && $actual_index >= $required_index;
	}

	/**
	 * Build the effective ClawPress access grant.
	 */
	private function build_grant( int $user_id, ?string $workspace_id ): \WP_Agent_Access_Grant {
		$capability = apply_filters( 'clawpress_permissions_capability', 'manage_options' );
		if ( empty( $capability ) ) {
			$capability = 'manage_options';
		}

		return new \WP_Agent_Access_Grant(
			Agents_API::AGENT_SLUG,
			$user_id,
			\WP_Agent_Access_Grant::ROLE_ADMIN,
			$workspace_id,
			null,
			null,
			null,
			[
				'source'     => 'clawpress_capability',
				'capability' => (string) $capability,
			]
		);
	}
}

<?php
/**
 * Tests for Agents API access-store integration.
 *
 * @package ClawPress\Tests
 */

declare( strict_types=1 );

namespace {
	if ( ! class_exists( 'WP_Agent_Access_Grant' ) ) {
		final class WP_Agent_Access_Grant {
			public const ROLE_ADMIN    = 'admin';
			public const ROLE_OPERATOR = 'operator';
			public const ROLE_VIEWER   = 'viewer';

			public function __construct(
				public readonly string $agent_id,
				public readonly int $user_id,
				public readonly string $role = self::ROLE_VIEWER,
				public readonly ?string $workspace_id = null,
				public readonly ?int $grant_id = null,
				public readonly ?int $granted_by_user_id = null,
				public readonly ?string $granted_at = null,
				public readonly array $metadata = [],
				public readonly ?string $audience_id = null
			) {}

			/**
			 * Return valid roles from lowest to highest privilege.
			 *
			 * @return array<int,string>
			 */
			public static function roles(): array {
				return [ self::ROLE_VIEWER, self::ROLE_OPERATOR, self::ROLE_ADMIN ];
			}

			public static function is_valid_role( string $role ): bool {
				return in_array( $role, self::roles(), true );
			}

			public function role_meets( string $minimum_role ): bool {
				$roles          = self::roles();
				$actual_index   = array_search( $this->role, $roles, true );
				$required_index = array_search( $minimum_role, $roles, true );

				return false !== $actual_index && false !== $required_index && $actual_index >= $required_index;
			}
		}
	}

	if ( ! interface_exists( 'WP_Agent_Access_Store' ) ) {
		interface WP_Agent_Access_Store {
			public function grant_access( WP_Agent_Access_Grant $grant ): WP_Agent_Access_Grant;

			public function revoke_access( string $agent_id, int $user_id, ?string $workspace_id = null ): bool;

			public function get_access( string $agent_id, int $user_id, ?string $workspace_id = null ): ?WP_Agent_Access_Grant;

			/**
			 * @return array<int,string>
			 */
			public function get_agent_ids_for_user( int $user_id, ?string $minimum_role = null, ?string $workspace_id = null ): array;

			/**
			 * @return array<int,WP_Agent_Access_Grant>
			 */
			public function get_users_for_agent( string $agent_id, ?string $workspace_id = null ): array;
		}
	}
}

namespace ClawPress\Tests\Unit {

use ClawPress\AgentsAPI\Access_Store;
use ClawPress\AgentsAPI\Agents_API;
use ClawPress\Tests\Support\TestCase;
use ClawPress\Tests\Support\WordPress_Stubs;

final class AgentsApiAccessStoreTest extends TestCase {
	public function test_get_access_projects_clawpress_capability_to_agent_grant(): void {
		$store = new Access_Store();
		$grant = $store->get_access( Agents_API::AGENT_SLUG, 1, 'site:1' );

		$this->assertInstanceOf( \WP_Agent_Access_Grant::class, $grant );
		$this->assertSame( Agents_API::AGENT_SLUG, $grant->agent_id );
		$this->assertSame( 1, $grant->user_id );
		$this->assertSame( 'site:1', $grant->workspace_id );
		$this->assertSame( \WP_Agent_Access_Grant::ROLE_ADMIN, $grant->role );
		$this->assertTrue( $grant->role_meets( \WP_Agent_Access_Grant::ROLE_OPERATOR ) );
		$this->assertSame( 'clawpress_capability', $grant->metadata['source'] );
		$this->assertSame( 'manage_options', $grant->metadata['capability'] );
	}

	public function test_get_access_rejects_other_agents_and_disallowed_users(): void {
		$store = new Access_Store();

		$this->assertNull( $store->get_access( 'other-agent', 1, null ) );

		WordPress_Stubs::$user_capabilities[2] = [
			'manage_options' => false,
		];

		$this->assertNull( $store->get_access( Agents_API::AGENT_SLUG, 2, null ) );
	}

	public function test_get_agent_ids_for_user_honors_minimum_role(): void {
		$store = new Access_Store();

		$this->assertSame(
			[ Agents_API::AGENT_SLUG ],
			$store->get_agent_ids_for_user( 1, \WP_Agent_Access_Grant::ROLE_VIEWER, null )
		);
		$this->assertSame(
			[ Agents_API::AGENT_SLUG ],
			$store->get_agent_ids_for_user( 1, \WP_Agent_Access_Grant::ROLE_ADMIN, null )
		);
		$this->assertSame( [], $store->get_agent_ids_for_user( 1, 'invalid-role', null ) );
	}
}
}

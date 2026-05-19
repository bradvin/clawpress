<?php
/**
 * Tests for Agents API conversation-store integration.
 *
 * @package ClawPress\Tests
 */

declare( strict_types=1 );

namespace AgentsAPI\Core\Workspace {
	if ( ! class_exists( WP_Agent_Workspace_Scope::class ) ) {
		final class WP_Agent_Workspace_Scope {
			public function __construct(
				public readonly string $workspace_type,
				public readonly string $workspace_id
			) {}

			public static function from_parts( string $workspace_type, string $workspace_id ): self {
				return new self( $workspace_type, $workspace_id );
			}
		}
	}
}

namespace AgentsAPI\Core\Database\Chat {
	use AgentsAPI\Core\Workspace\WP_Agent_Workspace_Scope;

	if ( ! interface_exists( WP_Agent_Conversation_Store::class ) ) {
		interface WP_Agent_Conversation_Store {
			public function create_session( WP_Agent_Workspace_Scope $workspace, int $user_id, string $agent_slug = '', array $metadata = [], string $context = 'chat' ): string;

			public function list_sessions( WP_Agent_Workspace_Scope $workspace, int $user_id, array $args = [] ): array;

			public function get_session( string $session_id ): ?array;

			public function update_session( string $session_id, array $messages, array $metadata = [], string $provider = '', string $model = '', ?string $provider_response_id = null ): bool;

			public function delete_session( string $session_id ): bool;

			public function get_recent_pending_session( WP_Agent_Workspace_Scope $workspace, int $user_id, int $seconds = 600, string $context = 'chat', ?int $token_id = null ): ?array;

			public function update_title( string $session_id, string $title ): bool;
		}
	}

	if ( ! interface_exists( WP_Agent_Principal_Conversation_Store::class ) ) {
		interface WP_Agent_Principal_Conversation_Store extends WP_Agent_Conversation_Store {
			public function create_session_for_owner( WP_Agent_Workspace_Scope $workspace, array $owner, string $agent_slug = '', array $metadata = [], string $context = 'chat' ): string;

			public function list_sessions_for_owner( WP_Agent_Workspace_Scope $workspace, array $owner, array $args = [] ): array;

			public function get_recent_pending_session_for_owner( WP_Agent_Workspace_Scope $workspace, array $owner, int $seconds = 600, string $context = 'chat', ?int $token_id = null ): ?array;
		}
	}

	if ( ! interface_exists( WP_Agent_Principal_Conversation_Session_Reader::class ) ) {
		interface WP_Agent_Principal_Conversation_Session_Reader extends WP_Agent_Principal_Conversation_Store {
			public function get_session_for_owner( WP_Agent_Workspace_Scope $workspace, array $owner, string $session_id ): ?array;
		}
	}

	if ( ! interface_exists( WP_Agent_Conversation_Lock::class ) ) {
		interface WP_Agent_Conversation_Lock {
			public function acquire_session_lock( string $session_id, int $ttl_seconds = 300 ): ?string;

			public function release_session_lock( string $session_id, string $lock_token ): bool;
		}
	}
}

namespace ClawPress\Tests\Unit {

use AgentsAPI\Core\Database\Chat\WP_Agent_Conversation_Lock;
use AgentsAPI\Core\Database\Chat\WP_Agent_Principal_Conversation_Session_Reader;
use AgentsAPI\Core\Workspace\WP_Agent_Workspace_Scope;
use ClawPress\AgentsAPI\Conversation_Store;
use ClawPress\Tests\Support\TestCase;
use ClawPress\Tests\Support\WordPress_Stubs;

final class AgentsApiConversationStoreTest extends TestCase {
	public function test_store_supports_principal_owned_sessions(): void {
		$store     = new Conversation_Store();
		$workspace = WP_Agent_Workspace_Scope::from_parts( 'site', 'example.test' );
		$owner     = [
			'type' => 'token',
			'key'  => 'token-session-1',
		];

		$this->assertInstanceOf( WP_Agent_Principal_Conversation_Session_Reader::class, $store );
		$this->assertInstanceOf( WP_Agent_Conversation_Lock::class, $store );

		$session_id = $store->create_session_for_owner(
			$workspace,
			$owner,
			'clawpress',
			[
				'token_id' => 123,
				'status'   => 'pending',
			],
			'chat'
		);

		$session = $store->get_session_for_owner( $workspace, $owner, $session_id );

		$this->assertIsArray( $session );
		$this->assertSame( 'token', $session['owner_type'] );
		$this->assertSame( 'token-session-1', $session['owner_key'] );
		$this->assertSame( 'token', $session['principal_owner_type'] );
		$this->assertSame( 'token-session-1', $session['principal_owner_key'] );
		$this->assertSame( 0, $session['user_id'] );
		$this->assertSame( 'clawpress', $session['agent_slug'] );
		$this->assertSame( 'chat', $session['context'] );

		$sessions = $store->list_sessions_for_owner( $workspace, $owner, [ 'include_messages' => true ] );

		$this->assertCount( 1, $sessions );
		$this->assertSame( $session_id, $sessions[0]['session_id'] );
	}

	public function test_get_session_for_owner_rejects_other_owners_and_workspaces(): void {
		$store     = new Conversation_Store();
		$workspace = WP_Agent_Workspace_Scope::from_parts( 'site', 'example.test' );
		$owner     = [
			'type' => 'audience',
			'key'  => 'public',
		];

		$session_id = $store->create_session_for_owner( $workspace, $owner );

		$this->assertNull(
			$store->get_session_for_owner(
				$workspace,
				[
					'type' => 'audience',
					'key'  => 'private',
				],
				$session_id
			)
		);
		$this->assertNull(
			$store->get_session_for_owner(
				WP_Agent_Workspace_Scope::from_parts( 'site', 'other.test' ),
				$owner,
				$session_id
			)
		);
	}

	public function test_legacy_user_sessions_are_projected_as_user_owned_sessions(): void {
		$store     = new Conversation_Store();
		$workspace = WP_Agent_Workspace_Scope::from_parts( 'site', 'example.test' );

		$session_id = $store->create_session(
			$workspace,
			7,
			'clawpress',
			[
				'status' => 'pending',
			]
		);

		$session = $store->get_session_for_owner(
			$workspace,
			[
				'type' => 'user',
				'key'  => '7',
			],
			$session_id
		);

		$this->assertIsArray( $session );
		$this->assertSame( 7, $session['user_id'] );
		$this->assertSame( 'user', $session['owner_type'] );
		$this->assertSame( '7', $session['owner_key'] );
		$this->assertSame( $session_id, $store->list_sessions( $workspace, 7 )[0]['session_id'] );
	}

	public function test_existing_user_sessions_without_owner_fields_still_match_user_owner(): void {
		$workspace = WP_Agent_Workspace_Scope::from_parts( 'site', 'example.test' );
		$store     = new Conversation_Store();

		WordPress_Stubs::$options['clawpress_agents_api_conversations'] = [
			'legacy-session-1' => [
				'session_id'     => 'legacy-session-1',
				'workspace_type' => 'site',
				'workspace_id'   => 'example.test',
				'user_id'        => 7,
				'agent_slug'     => 'clawpress',
				'title'          => '',
				'messages'       => [],
				'metadata'       => [],
				'context'        => 'chat',
				'created_at'     => '2026-05-19T00:00:00+00:00',
				'updated_at'     => '2026-05-19T00:00:00+00:00',
			],
		];

		$this->assertSame( 'legacy-session-1', $store->list_sessions( $workspace, 7 )[0]['session_id'] );
		$this->assertSame(
			'legacy-session-1',
			$store->get_session_for_owner(
				$workspace,
				[
					'type' => 'user',
					'key'  => '7',
				],
				'legacy-session-1'
			)['session_id']
		);
	}

	public function test_recent_pending_session_is_owner_scoped(): void {
		$store     = new Conversation_Store();
		$workspace = WP_Agent_Workspace_Scope::from_parts( 'site', 'example.test' );
		$owner     = [
			'type' => 'token',
			'key'  => 'token-session-1',
		];

		$session_id = $store->create_session_for_owner(
			$workspace,
			$owner,
			'clawpress',
			[
				'token_id' => 321,
				'status'   => 'processing',
			]
		);
		$store->create_session_for_owner(
			$workspace,
			[
				'type' => 'token',
				'key'  => 'token-session-2',
			],
			'clawpress',
			[
				'token_id' => 321,
				'status'   => 'processing',
			]
		);

		$pending = $store->get_recent_pending_session_for_owner( $workspace, $owner, 600, 'chat', 321 );

		$this->assertIsArray( $pending );
		$this->assertSame( $session_id, $pending['session_id'] );
		$this->assertNull( $store->get_recent_pending_session_for_owner( $workspace, $owner, 600, 'chat', 999 ) );
	}

	public function test_session_lock_prevents_concurrent_updates_until_released(): void {
		$store      = new Conversation_Store();
		$workspace  = WP_Agent_Workspace_Scope::from_parts( 'site', 'example.test' );
		$session_id = $store->create_session( $workspace, 7 );

		$lock_token = $store->acquire_session_lock( $session_id, 300 );

		$this->assertIsString( $lock_token );
		$this->assertNotSame( '', $lock_token );
		$this->assertNull( $store->acquire_session_lock( $session_id, 300 ) );
		$this->assertFalse( $store->release_session_lock( $session_id, 'wrong-token' ) );
		$this->assertTrue( $store->release_session_lock( $session_id, (string) $lock_token ) );
		$this->assertIsString( $store->acquire_session_lock( $session_id, 300 ) );
	}

	public function test_expired_session_lock_can_be_reclaimed(): void {
		$store      = new Conversation_Store();
		$workspace  = WP_Agent_Workspace_Scope::from_parts( 'site', 'example.test' );
		$session_id = $store->create_session( $workspace, 7 );

		WordPress_Stubs::$options['clawpress_agents_api_conversations'][ $session_id ]['lock_token']      = 'expired-token';
		WordPress_Stubs::$options['clawpress_agents_api_conversations'][ $session_id ]['lock_expires_at'] = '2000-01-01T00:00:00+00:00';

		$lock_token = $store->acquire_session_lock( $session_id, 300 );

		$this->assertIsString( $lock_token );
		$this->assertNotSame( 'expired-token', $lock_token );
		$this->assertFalse( $store->release_session_lock( $session_id, 'expired-token' ) );
		$this->assertTrue( $store->release_session_lock( $session_id, (string) $lock_token ) );
	}
}
}

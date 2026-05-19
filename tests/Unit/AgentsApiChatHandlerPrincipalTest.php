<?php
/**
 * Tests for principal-aware Agents API chat persistence.
 *
 * @package ClawPress\Tests
 */

declare( strict_types=1 );

namespace AgentsAPI\AI {
	if ( ! class_exists( WP_Agent_Execution_Principal::class ) ) {
		final class WP_Agent_Execution_Principal {
			public const AUTH_SOURCE_AUDIENCE = 'audience';
			public const OWNER_TYPE_AUDIENCE  = 'audience';
			public const REQUEST_CONTEXT_CHAT = 'chat';

			public function __construct(
				public readonly int $acting_user_id,
				public readonly string $effective_agent_id,
				public readonly string $auth_source,
				public readonly string $request_context,
				public readonly ?string $owner_type = null,
				public readonly ?string $owner_key = null
			) {}

			/**
			 * @param array<string,mixed> $principal Raw principal fields.
			 */
			public static function from_array( array $principal ): self {
				return new self(
					isset( $principal['acting_user_id'] ) ? (int) $principal['acting_user_id'] : 0,
					isset( $principal['effective_agent_id'] ) ? (string) $principal['effective_agent_id'] : '',
					isset( $principal['auth_source'] ) ? (string) $principal['auth_source'] : '',
					isset( $principal['request_context'] ) ? (string) $principal['request_context'] : '',
					array_key_exists( 'owner_type', $principal ) && null !== $principal['owner_type'] ? (string) $principal['owner_type'] : null,
					array_key_exists( 'owner_key', $principal ) && null !== $principal['owner_key'] ? (string) $principal['owner_key'] : null
				);
			}

			/**
			 * @param array<string,mixed> $request_context Request context.
			 */
			public static function resolve( array $request_context = [] ): ?self {
				unset( $request_context );
				return null;
			}

			/**
			 * @return array{type:string,key:string}|null
			 */
			public function conversation_owner(): ?array {
				if ( null === $this->owner_type || null === $this->owner_key ) {
					return null;
				}

				return [
					'type' => $this->owner_type,
					'key'  => $this->owner_key,
				];
			}
		}
	}
}

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

	if ( ! class_exists( WP_Agent_Conversation_Sessions::class ) ) {
		final class WP_Agent_Conversation_Sessions {
			/** @var object|null */
			public static ?object $store = null;

			/**
			 * @param array<string,mixed> $context Store context.
			 */
			public static function get_store( array $context = [] ): ?object {
				unset( $context );
				return self::$store;
			}
		}
	}
}

namespace ClawPress\Tests\Unit {

use AgentsAPI\AI\WP_Agent_Execution_Principal;
use AgentsAPI\Core\Database\Chat\WP_Agent_Conversation_Sessions;
use ClawPress\AgentsAPI\Agents_API;
use ClawPress\AgentsAPI\Chat_Handler;
use ClawPress\AgentsAPI\Conversation_Store;
use ClawPress\Helpers\Chat_Helper;
use ClawPress\Tests\Support\TestCase;

final class AgentsApiChatHandlerPrincipalTest extends TestCase {
	protected function tearDown(): void {
		WP_Agent_Conversation_Sessions::$store = null;
		parent::tearDown();
	}

	public function test_handler_persists_chat_turn_under_principal_owner(): void {
		$store = new Conversation_Store();
		WP_Agent_Conversation_Sessions::$store = $store;

		$handler = new Chat_Handler(
			Chat_Helper::create_for_testing(
				null,
				static fn(): string => 'Audience reply.',
				static fn( array $settings ): array => [
					'provider' => 'openai',
					'model'    => 'gpt-4.1-mini',
				]
			)
		);

		$response = $handler->handle(
			[
				'agent'     => Agents_API::AGENT_SLUG,
				'message'   => 'Hello from an audience session',
				'principal' => new WP_Agent_Execution_Principal(
					0,
					Agents_API::AGENT_SLUG,
					WP_Agent_Execution_Principal::AUTH_SOURCE_AUDIENCE,
					WP_Agent_Execution_Principal::REQUEST_CONTEXT_CHAT,
					WP_Agent_Execution_Principal::OWNER_TYPE_AUDIENCE,
					'browser-session-1'
				),
			]
		);

		$this->assertIsArray( $response );
		$session = $store->get_session( $response['session_id'] );

		$this->assertIsArray( $session );
		$this->assertSame( 0, $session['user_id'] );
		$this->assertSame( 'audience', $session['owner_type'] );
		$this->assertSame( 'browser-session-1', $session['owner_key'] );
		$this->assertCount( 2, $session['messages'] );
		$this->assertSame( 'Hello from an audience session', $session['messages'][0]['content'] );
		$this->assertSame( 'Audience reply.', $session['messages'][1]['content'] );
	}
}
}

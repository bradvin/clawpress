<?php
/**
 * Canonical Agents API chat handler adapter.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\AgentsAPI;

use AgentsAPI\AI\WP_Agent_Execution_Principal;
use AgentsAPI\AI\WP_Agent_Message;
use AgentsAPI\Core\Database\Chat\WP_Agent_Conversation_Sessions;
use AgentsAPI\Core\Database\Chat\WP_Agent_Principal_Conversation_Session_Reader;
use AgentsAPI\Core\Database\Chat\WP_Agent_Principal_Conversation_Store;
use AgentsAPI\Core\Workspace\WP_Agent_Workspace_Scope;
use ClawPress\Helpers\Chat_Helper;
use Throwable;

defined( 'ABSPATH' ) || exit;

/**
 * Adapts ClawPress chat turns to the Agents API agents/chat contract.
 */
final class Chat_Handler {
	/**
	 * ClawPress chat runtime helper.
	 *
	 * @var Chat_Helper
	 */
	private Chat_Helper $chat_helper;

	/**
	 * Constructor.
	 *
	 * @param Chat_Helper|null $chat_helper Optional helper override.
	 */
	public function __construct( ?Chat_Helper $chat_helper = null ) {
		$this->chat_helper = $chat_helper ?? Chat_Helper::get_instance();
	}

	/**
	 * Handle one canonical agents/chat request.
	 *
	 * @param array<string,mixed> $input Canonical agents/chat input.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function handle( array $input ) {
		$agent = isset( $input['agent'] ) ? sanitize_key( (string) $input['agent'] ) : '';
		if ( Agents_API::AGENT_SLUG !== $agent ) {
			return new \WP_Error(
				'clawpress_agents_chat_unsupported_agent',
				__( 'ClawPress can only handle chat requests for the ClawPress agent.', 'clawpress' )
			);
		}

		$message = isset( $input['message'] ) ? trim( (string) $input['message'] ) : '';
		if ( '' === $message ) {
			return new \WP_Error(
				'clawpress_agents_chat_empty_message',
				__( 'Message is required.', 'clawpress' )
			);
		}

		$payload = $this->chat_helper->generate_ai_reply(
			$message,
			[
				'transport_mode' => 'polling',
			]
		);

		$reply = isset( $payload['reply'] ) ? trim( (string) $payload['reply'] ) : '';
		if ( '' === $reply ) {
			$reply = $this->chat_helper->build_offline_reply( $message );
		}

		$completed  = $this->is_completed( $payload );
		$metadata   = $this->build_metadata( $payload, $input, $completed );
		$session_id = $this->persist_turn( $input, $message, $reply, $metadata, $completed );

		if ( '' === $session_id ) {
			$session_id = $this->resolve_fallback_session_id( $input, $message );
		}

		return [
			'session_id' => $session_id,
			'reply'      => $reply,
			'messages'   => [
				[
					'role'    => 'assistant',
					'content' => $reply,
				],
			],
			'completed'  => $completed,
			'metadata'   => $metadata,
		];
	}

	/**
	 * Determine whether the turn is terminal from the channel perspective.
	 *
	 * @param array<string,mixed> $payload ClawPress reply payload.
	 */
	private function is_completed( array $payload ): bool {
		$status = isset( $payload['status'] ) ? strtolower( trim( (string) $payload['status'] ) ) : '';
		$mode   = isset( $payload['mode'] ) ? strtolower( trim( (string) $payload['mode'] ) ) : '';

		if ( in_array( $status, [ 'in_progress', 'requires_confirmation' ], true ) || 'in_progress' === $mode ) {
			return false;
		}

		$card = isset( $payload['card'] ) && is_array( $payload['card'] ) ? $payload['card'] : [];
		return 'user_confirmation' !== (string) ( $card['type'] ?? '' );
	}

	/**
	 * Build canonical metadata from the ClawPress reply payload.
	 *
	 * @param array<string,mixed> $payload ClawPress reply payload.
	 * @param array<string,mixed> $input Canonical agents/chat input.
	 * @param bool                $completed Whether the turn is terminal.
	 * @return array<string,mixed>
	 */
	private function build_metadata( array $payload, array $input, bool $completed ): array {
		$metadata = [
			'source'    => 'clawpress',
			'agent'     => Agents_API::AGENT_SLUG,
			'completed' => $completed,
			'mode'      => isset( $payload['mode'] ) ? (string) $payload['mode'] : 'offline',
			'provider'  => isset( $payload['provider'] ) && '' !== (string) $payload['provider'] ? (string) $payload['provider'] : null,
			'model'     => isset( $payload['model'] ) && '' !== (string) $payload['model'] ? (string) $payload['model'] : null,
		];

		foreach ( [ 'suggestions', 'card', 'command', 'error', 'context', 'tool_calls' ] as $key ) {
			if ( isset( $payload[ $key ] ) && is_array( $payload[ $key ] ) ) {
				$metadata[ $key ] = $payload[ $key ];
			}
		}

		foreach ( [ 'events_cursor', 'status' ] as $key ) {
			if ( isset( $payload[ $key ] ) ) {
				$metadata[ $key ] = $payload[ $key ];
			}
		}

		if ( isset( $payload['run_id'] ) ) {
			$metadata['clawpress_run_id'] = (int) $payload['run_id'];
		}

		if ( isset( $payload['session_id'] ) ) {
			$metadata['clawpress_session_id'] = (int) $payload['session_id'];
		}

		if ( isset( $input['client_context'] ) && is_array( $input['client_context'] ) ) {
			$metadata['client_context'] = $input['client_context'];
		}

		if ( isset( $input['attachments'] ) && is_array( $input['attachments'] ) && [] !== $input['attachments'] ) {
			$metadata['attachments'] = array_values( $input['attachments'] );
		}

		return $metadata;
	}

	/**
	 * Persist the turn to the generic Agents API conversation store when available.
	 *
	 * @param array<string,mixed> $input Canonical agents/chat input.
	 * @param string              $message User message.
	 * @param string              $reply Assistant reply.
	 * @param array<string,mixed> $metadata Reply metadata.
	 * @param bool                $completed Whether the turn is terminal.
	 */
	private function persist_turn( array $input, string $message, string $reply, array $metadata, bool $completed ): string {
		$store     = $this->get_conversation_store();
		$workspace = $this->get_workspace_scope();
		if ( ! is_object( $store ) || ! is_object( $workspace ) ) {
			return '';
		}

		$principal = $this->resolve_execution_principal( $input );
		$owner     = $this->resolve_conversation_owner( $principal );
		$user_id   = $this->resolve_conversation_user_id( $principal, $owner );

		$session_id = isset( $input['session_id'] ) && is_string( $input['session_id'] )
			? trim( sanitize_text_field( $input['session_id'] ) )
			: '';
		$session    = '' !== $session_id ? $this->get_store_session( $store, $workspace, $owner, $session_id ) : null;

		if ( '' === $session_id || ! is_array( $session ) ) {
			$session_id = $this->create_store_session(
				$store,
				$workspace,
				$owner,
				$user_id,
				Agents_API::AGENT_SLUG,
				[
					'source' => 'clawpress_agents_chat',
					'status' => 'processing',
				],
				'chat'
			);
			$session    = '' !== $session_id ? $this->get_store_session( $store, $workspace, $owner, $session_id ) : null;
		}

		if ( '' === $session_id || ! is_array( $session ) ) {
			return '';
		}

		$messages   = isset( $session['messages'] ) && is_array( $session['messages'] ) ? $session['messages'] : [];
		$messages[] = $this->build_message(
			'user',
			$message,
			[
				'source' => 'agents_chat',
			]
		);
		$messages[] = $this->build_message(
			'assistant',
			$reply,
			array_merge(
				[
					'source' => 'clawpress',
				],
				$metadata
			)
		);

		$session_metadata = isset( $session['metadata'] ) && is_array( $session['metadata'] ) ? $session['metadata'] : [];
		$session_metadata = array_merge(
			$session_metadata,
			[
				'source'        => 'clawpress_agents_chat',
				'status'        => $completed ? 'complete' : 'pending',
				'message_count' => count( $messages ),
				'last_turn'     => $metadata,
			]
		);

		$provider = isset( $metadata['provider'] ) && is_string( $metadata['provider'] ) ? $metadata['provider'] : '';
		$model    = isset( $metadata['model'] ) && is_string( $metadata['model'] ) ? $metadata['model'] : '';

		return $store->update_session( $session_id, $messages, $session_metadata, $provider, $model, null )
			? $session_id
			: '';
	}

	/**
	 * Resolve the Agents API execution principal for this chat turn.
	 *
	 * @param array<string,mixed> $input Canonical agents/chat input.
	 * @return object|null
	 */
	private function resolve_execution_principal( array $input ): ?object {
		if ( ! class_exists( WP_Agent_Execution_Principal::class ) ) {
			return null;
		}

		if ( isset( $input['principal'] ) && $input['principal'] instanceof WP_Agent_Execution_Principal ) {
			return $input['principal'];
		}

		if ( isset( $input['principal'] ) && is_array( $input['principal'] ) ) {
			try {
				return WP_Agent_Execution_Principal::from_array( $input['principal'] );
			} catch ( Throwable $throwable ) {
				unset( $throwable );
				return null;
			}
		}

		try {
			return WP_Agent_Execution_Principal::resolve(
				[
					'agent'           => Agents_API::AGENT_SLUG,
					'request_context' => WP_Agent_Execution_Principal::REQUEST_CONTEXT_CHAT,
					'source'          => 'clawpress_agents_chat',
					'client_context'  => isset( $input['client_context'] ) && is_array( $input['client_context'] ) ? $input['client_context'] : [],
				]
			);
		} catch ( Throwable $throwable ) {
			unset( $throwable );
			return null;
		}
	}

	/**
	 * Resolve a canonical conversation owner from an execution principal.
	 *
	 * @param object|null $principal Execution principal.
	 * @return array{type:string,key:string}|null
	 */
	private function resolve_conversation_owner( ?object $principal ): ?array {
		if ( ! $principal instanceof WP_Agent_Execution_Principal ) {
			return null;
		}

		$owner = $principal->conversation_owner();
		if ( ! is_array( $owner ) ) {
			return null;
		}

		$type = isset( $owner['type'] ) ? sanitize_key( (string) $owner['type'] ) : '';
		$key  = isset( $owner['key'] ) ? trim( sanitize_text_field( (string) $owner['key'] ) ) : '';
		if ( '' === $type || '' === $key ) {
			return null;
		}

		return [
			'type' => $type,
			'key'  => $key,
		];
	}

	/**
	 * Resolve the legacy user ID used when a store is not principal-owner-aware.
	 *
	 * @param object|null                      $principal Execution principal.
	 * @param array{type:string,key:string}|null $owner Canonical owner.
	 */
	private function resolve_conversation_user_id( ?object $principal, ?array $owner ): int {
		if ( $principal instanceof WP_Agent_Execution_Principal ) {
			return max( 0, (int) $principal->acting_user_id );
		}

		if ( null !== $owner && 'user' === $owner['type'] && is_numeric( $owner['key'] ) ) {
			return max( 0, (int) $owner['key'] );
		}

		return function_exists( 'get_current_user_id' ) ? max( 0, (int) get_current_user_id() ) : 0;
	}

	/**
	 * Read a session through the most specific store contract available.
	 *
	 * @param object                           $store Conversation store.
	 * @param WP_Agent_Workspace_Scope         $workspace Workspace scope.
	 * @param array{type:string,key:string}|null $owner Canonical owner.
	 * @param string                           $session_id Session ID.
	 * @return array<string,mixed>|null
	 */
	private function get_store_session( object $store, WP_Agent_Workspace_Scope $workspace, ?array $owner, string $session_id ): ?array {
		if ( null !== $owner && $store instanceof WP_Agent_Principal_Conversation_Session_Reader ) {
			$session = $store->get_session_for_owner( $workspace, $owner, $session_id );
			return is_array( $session ) ? $session : null;
		}

		if ( ! method_exists( $store, 'get_session' ) ) {
			return null;
		}

		$session = $store->get_session( $session_id );
		return is_array( $session ) ? $session : null;
	}

	/**
	 * Create a session through the most specific store contract available.
	 *
	 * @param object                           $store Conversation store.
	 * @param WP_Agent_Workspace_Scope         $workspace Workspace scope.
	 * @param array{type:string,key:string}|null $owner Canonical owner.
	 * @param int                              $user_id Legacy user ID fallback.
	 * @param string                           $agent_slug Agent slug.
	 * @param array<string,mixed>              $metadata Session metadata.
	 * @param string                           $context Session context.
	 */
	private function create_store_session( object $store, WP_Agent_Workspace_Scope $workspace, ?array $owner, int $user_id, string $agent_slug, array $metadata, string $context ): string {
		if ( null !== $owner && $store instanceof WP_Agent_Principal_Conversation_Store ) {
			return $store->create_session_for_owner( $workspace, $owner, $agent_slug, $metadata, $context );
		}

		if ( null !== $owner && 'user' !== $owner['type'] ) {
			return '';
		}

		if ( ! method_exists( $store, 'create_session' ) ) {
			return '';
		}

		return (string) $store->create_session( $workspace, $user_id, $agent_slug, $metadata, $context );
	}

	/**
	 * Resolve the host-provided Agents API conversation store.
	 *
	 * @return object|null
	 */
	private function get_conversation_store() {
		if ( ! class_exists( WP_Agent_Conversation_Sessions::class ) ) {
			return null;
		}

		$store = WP_Agent_Conversation_Sessions::get_store(
			[
				'source' => 'clawpress_agents_chat',
				'mode'   => 'chat',
			]
		);

		return is_object( $store ) ? $store : null;
	}

	/**
	 * Build the workspace scope for canonical chat transcripts.
	 *
	 * @return object|null
	 */
	private function get_workspace_scope() {
		if ( ! class_exists( WP_Agent_Workspace_Scope::class ) ) {
			return null;
		}

		$blog_id = function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 1;

		return WP_Agent_Workspace_Scope::from_parts( 'site', (string) max( 1, $blog_id ) );
	}

	/**
	 * Build a canonical Agents API message envelope.
	 *
	 * @param string              $role Message role.
	 * @param string              $content Message content.
	 * @param array<string,mixed> $metadata Message metadata.
	 * @return array<string,mixed>
	 */
	private function build_message( string $role, string $content, array $metadata ): array {
		if ( class_exists( WP_Agent_Message::class ) ) {
			$message = WP_Agent_Message::text( $role, $content, $metadata );
		} else {
			$message = [
				'schema'   => 'agents-api.message',
				'version'  => 1,
				'type'     => 'text',
				'role'     => $role,
				'content'  => $content,
				'payload'  => [],
				'metadata' => $metadata,
			];
		}

		$message['id'] = sprintf( 'msg-%d-%s', (int) round( microtime( true ) * 1000 ), substr( md5( $role . $content . uniqid( '', true ) ), 0, 8 ) );

		return $message;
	}

	/**
	 * Resolve a canonical session ID when the transcript store is unavailable.
	 *
	 * @param array<string,mixed> $input Canonical agents/chat input.
	 * @param string              $message User message.
	 */
	private function resolve_fallback_session_id( array $input, string $message ): string {
		if ( isset( $input['session_id'] ) && is_string( $input['session_id'] ) && '' !== trim( $input['session_id'] ) ) {
			return trim( sanitize_text_field( $input['session_id'] ) );
		}

		$client_context = isset( $input['client_context'] ) && is_array( $input['client_context'] ) ? $input['client_context'] : [];
		if ( isset( $client_context['caller_session_id'] ) && is_string( $client_context['caller_session_id'] ) && '' !== trim( $client_context['caller_session_id'] ) ) {
			return trim( sanitize_text_field( $client_context['caller_session_id'] ) );
		}

		return sprintf( 'clawpress-chat-%s', substr( md5( $message . microtime( true ) ), 0, 16 ) );
	}
}

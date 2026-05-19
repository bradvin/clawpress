<?php
/**
 * Chat history helper.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Helpers;

use AgentsAPI\AI\WP_Agent_Message;
use AgentsAPI\Core\Database\Chat\WP_Agent_Conversation_Sessions;
use AgentsAPI\Core\Workspace\WP_Agent_Workspace_Scope;

defined( 'ABSPATH' ) || exit;

/**
 * Per-user chat history helper.
 */
final class Chat_History_Helper {
	/**
	 * Maximum number of persisted history items.
	 */
	private const HISTORY_LIMIT = 50;

	/**
	 * Conversation-session option prefix.
	 */
	private const SESSION_OPTION_PREFIX = 'clawpress_chat_history_session_';

	/**
	 * Singleton instance.
	 *
	 * @var ?self
	 */
	private static ?self $instance = null;

	/**
	 * Constructor.
	 */
	private function __construct() {}

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
	 * Get normalized history items for a user.
	 *
	 * @param int|null $user_id User ID.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_history_items( ?int $user_id = null ): array {
		$agents_api_items = $this->get_agents_api_history_items( $user_id );
		if ( null !== $agents_api_items ) {
			return $agents_api_items;
		}

		$items = get_option( $this->get_history_option_key( $user_id ), [] );
		if ( ! is_array( $items ) ) {
			return [];
		}

		$normalized = [];
		foreach ( $items as $index => $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$normalized[] = $this->normalize_history_item( $item, (int) $index );
		}

		return $normalized;
	}

	/**
	 * Append a message to a user's history.
	 *
	 * @param string                              $role Message role.
	 * @param string                              $content Message content.
	 * @param array<string,mixed>|null            $card Optional card metadata.
	 * @param array<int,array<string,mixed>>|null $tool_calls Optional tool-call trace rows.
	 * @param int|null                            $user_id User ID.
	 */
	public function append_history_message( string $role, string $content, ?array $card = null, ?array $tool_calls = null, ?int $user_id = null ): void {
		$items      = $this->get_history_items( $user_id );
		$created_at = (int) round( microtime( true ) * 1000 );

		$item = [
			'id'        => sprintf( 'msg-%d-%d', $created_at, count( $items ) + 1 ),
			'role'      => $role,
			'content'   => $content,
			'createdAt' => $created_at,
		];
		$card = $this->normalize_card( $card );
		if ( null !== $card ) {
			$item['card'] = $card;
		}

		$normalized_tool_calls = $this->normalize_tool_calls( $tool_calls );
		if ( [] !== $normalized_tool_calls ) {
			$item['tool_calls'] = $normalized_tool_calls;
		}

		$items[] = $item;

		if ( count( $items ) > self::HISTORY_LIMIT ) {
			$items = array_slice( $items, -self::HISTORY_LIMIT );
		}

		if ( $this->persist_agents_api_history_items( $items, $user_id ) ) {
			return;
		}

		update_option( $this->get_history_option_key( $user_id ), $items );
	}

	/**
	 * Clear a user's chat history.
	 *
	 * @param int|null $user_id User ID.
	 */
	public function clear_history_items( ?int $user_id = null ): void {
		$store      = $this->get_agents_api_conversation_store();
		$session_id = $this->get_agents_api_session_id( $user_id );
		if ( is_object( $store ) && '' !== $session_id ) {
			$store->delete_session( $session_id );
			update_option( $this->get_agents_api_session_option_key( $user_id ), '' );
		}

		update_option( $this->get_history_option_key( $user_id ), [] );
	}

	/**
	 * Read history items through the Agents API conversation store.
	 *
	 * @param int|null $user_id User ID.
	 * @return array<int,array<string,mixed>>|null Null when Agents API storage is unavailable.
	 */
	private function get_agents_api_history_items( ?int $user_id = null ): ?array {
		$store      = $this->get_agents_api_conversation_store();
		$session_id = $this->get_agents_api_session_id( $user_id );
		if ( ! is_object( $store ) || '' === $session_id ) {
			return null;
		}

		$session = $store->get_session( $session_id );
		if ( ! is_array( $session ) || ! isset( $session['messages'] ) || ! is_array( $session['messages'] ) ) {
			return null;
		}

		$items = [];
		foreach ( $session['messages'] as $index => $message ) {
			$item = $this->normalize_agents_api_message_for_history( $message, (int) $index );
			if ( null === $item ) {
				continue;
			}

			$items[] = $item;
		}

		if ( count( $items ) > self::HISTORY_LIMIT ) {
			$items = array_slice( $items, -self::HISTORY_LIMIT );
		}

		return $items;
	}

	/**
	 * Persist history items through the Agents API conversation store.
	 *
	 * @param array<int,array<string,mixed>> $items History items.
	 * @param int|null                       $user_id User ID.
	 */
	private function persist_agents_api_history_items( array $items, ?int $user_id = null ): bool {
		$store     = $this->get_agents_api_conversation_store();
		$workspace = $this->get_agents_api_workspace_scope();
		if ( ! is_object( $store ) || ! is_object( $workspace ) ) {
			return false;
		}

		$resolved_user_id = $this->resolve_user_id( $user_id );
		$session_id       = $this->get_agents_api_session_id( $user_id );
		$session          = '' !== $session_id ? $store->get_session( $session_id ) : null;

		if ( '' === $session_id || ! is_array( $session ) ) {
			$session_id = $store->create_session(
				$workspace,
				$resolved_user_id,
				'clawpress',
				[
					'source' => 'clawpress_chat_history',
					'status' => 'complete',
				],
				'chat'
			);

			if ( '' === $session_id ) {
				return false;
			}

			update_option( $this->get_agents_api_session_option_key( $user_id ), $session_id );
		}

		$metadata = is_array( $session ) && isset( $session['metadata'] ) && is_array( $session['metadata'] )
			? $session['metadata']
			: [];

		$metadata['source']        = 'clawpress_chat_history';
		$metadata['status']        = 'complete';
		$metadata['message_count'] = count( $items );

		return (bool) $store->update_session(
			$session_id,
			$this->convert_history_items_to_agents_api_messages( $items ),
			$metadata,
			'',
			'',
			null
		);
	}

	/**
	 * Resolve the host-provided Agents API conversation store.
	 *
	 * @return object|null
	 */
	private function get_agents_api_conversation_store() {
		if ( ! class_exists( WP_Agent_Conversation_Sessions::class ) ) {
			return null;
		}

		$store = WP_Agent_Conversation_Sessions::get_store(
			[
				'source' => 'clawpress_chat_history',
				'mode'   => 'chat',
			]
		);

		return is_object( $store ) ? $store : null;
	}

	/**
	 * Build the generic workspace scope for chat history transcripts.
	 *
	 * @return object|null
	 */
	private function get_agents_api_workspace_scope() {
		if ( ! class_exists( WP_Agent_Workspace_Scope::class ) ) {
			return null;
		}

		$blog_id = function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 1;

		return WP_Agent_Workspace_Scope::from_parts( 'site', (string) max( 1, $blog_id ) );
	}

	/**
	 * Convert history rows to Agents API message envelopes.
	 *
	 * @param array<int,array<string,mixed>> $items History items.
	 * @return array<int,array<string,mixed>>
	 */
	private function convert_history_items_to_agents_api_messages( array $items ): array {
		$messages = [];

		foreach ( $items as $index => $item ) {
			$normalized = $this->normalize_history_item( $item, (int) $index );
			$metadata   = [
				'clawpress_history_id'         => $normalized['id'],
				'clawpress_history_created_at' => $normalized['createdAt'],
			];

			if ( null !== $normalized['card'] ) {
				$metadata['card'] = $normalized['card'];
			}

			if ( [] !== $normalized['tool_calls'] ) {
				$metadata['tool_calls'] = $normalized['tool_calls'];
			}

			if ( class_exists( WP_Agent_Message::class ) ) {
				$message = WP_Agent_Message::text( $normalized['role'], $normalized['content'], $metadata );
			} else {
				$message = [
					'schema'   => 'agents-api.message',
					'version'  => 1,
					'type'     => 'text',
					'role'     => $normalized['role'],
					'content'  => $normalized['content'],
					'payload'  => [],
					'metadata' => $metadata,
				];
			}

			$message['id'] = $normalized['id'];
			$messages[]    = $message;
		}

		return $messages;
	}

	/**
	 * Convert an Agents API message envelope to the ClawPress history shape.
	 *
	 * @param mixed $message Raw message.
	 * @return array<string,mixed>|null
	 */
	private function normalize_agents_api_message_for_history( $message, int $index ): ?array {
		if ( ! is_array( $message ) ) {
			return null;
		}

		if ( isset( $message['createdAt'] ) || isset( $message['tool_calls'] ) || isset( $message['card'] ) ) {
			return $this->normalize_history_item( $message, $index );
		}

		$metadata = isset( $message['metadata'] ) && is_array( $message['metadata'] ) ? $message['metadata'] : [];
		$role     = isset( $message['role'] ) && is_string( $message['role'] ) ? $message['role'] : 'system';
		if ( 'model' === $role ) {
			$role = 'assistant';
		}

		$content = isset( $message['content'] ) ? (string) $message['content'] : '';
		$created = isset( $metadata['clawpress_history_created_at'] ) && is_numeric( $metadata['clawpress_history_created_at'] )
			? (int) $metadata['clawpress_history_created_at']
			: $index + 1;
		$id      = isset( $metadata['clawpress_history_id'] ) && is_string( $metadata['clawpress_history_id'] ) && '' !== $metadata['clawpress_history_id']
			? $metadata['clawpress_history_id']
			: ( isset( $message['id'] ) && is_string( $message['id'] ) && '' !== $message['id'] ? $message['id'] : sprintf( 'msg-%d-%d', $created, $index ) );

		return $this->normalize_history_item(
			[
				'id'         => $id,
				'role'       => $role,
				'content'    => $content,
				'createdAt'  => $created,
				'card'       => isset( $metadata['card'] ) && is_array( $metadata['card'] ) ? $metadata['card'] : null,
				'tool_calls' => isset( $metadata['tool_calls'] ) && is_array( $metadata['tool_calls'] ) ? $metadata['tool_calls'] : [],
			],
			$index
		);
	}

	/**
	 * Build the option key for a user's history.
	 *
	 * @param int|null $user_id User ID.
	 */
	private function get_history_option_key( ?int $user_id = null ): string {
		$resolved_user_id = $this->resolve_user_id( $user_id );
		return sprintf( 'clawpress_chat_history_%d', $resolved_user_id );
	}

	/**
	 * Build the option key storing the current generic conversation session ID.
	 *
	 * @param int|null $user_id User ID.
	 */
	private function get_agents_api_session_option_key( ?int $user_id = null ): string {
		return self::SESSION_OPTION_PREFIX . $this->resolve_user_id( $user_id );
	}

	/**
	 * Read the generic conversation session ID for a user.
	 *
	 * @param int|null $user_id User ID.
	 */
	private function get_agents_api_session_id( ?int $user_id = null ): string {
		$session_id = get_option( $this->get_agents_api_session_option_key( $user_id ), '' );
		return is_string( $session_id ) ? trim( sanitize_text_field( $session_id ) ) : '';
	}

	/**
	 * Resolve the target user ID.
	 *
	 * @param int|null $user_id User ID.
	 */
	private function resolve_user_id( ?int $user_id = null ): int {
		$resolved_user_id = null === $user_id ? get_current_user_id() : $user_id;
		return max( 0, (int) $resolved_user_id );
	}

	/**
	 * Normalize one persisted history item.
	 *
	 * @param array<string,mixed> $item Raw item.
	 * @param int                 $index Item index.
	 * @return array<string,mixed>
	 */
	private function normalize_history_item( array $item, int $index ): array {
		$role = isset( $item['role'] ) && is_string( $item['role'] ) ? $item['role'] : 'system';
		if ( ! in_array( $role, [ 'user', 'assistant', 'system' ], true ) ) {
			$role = 'system';
		}

		$content = isset( $item['content'] ) ? (string) $item['content'] : '';
		$created = isset( $item['createdAt'] ) && is_numeric( $item['createdAt'] )
			? (int) $item['createdAt']
			: $index + 1;
		$id      = isset( $item['id'] ) && is_string( $item['id'] ) && '' !== $item['id']
			? $item['id']
			: sprintf( 'msg-%d-%d', $created, $index );

		return [
			'id'         => $id,
			'role'       => $role,
			'content'    => $content,
			'createdAt'  => $created,
			'card'       => $this->normalize_card( isset( $item['card'] ) && is_array( $item['card'] ) ? $item['card'] : null ),
			'tool_calls' => $this->normalize_tool_calls( isset( $item['tool_calls'] ) && is_array( $item['tool_calls'] ) ? $item['tool_calls'] : null ),
		];
	}

	/**
	 * Normalize card payload.
	 *
	 * @param array<string,mixed>|null $card Raw card payload.
	 * @return array<string,mixed>|null
	 */
	private function normalize_card( ?array $card ): ?array {
		if ( ! is_array( $card ) ) {
			return null;
		}

		$type = isset( $card['type'] ) ? strtolower( sanitize_text_field( (string) $card['type'] ) ) : '';
		$type = (string) preg_replace( '/[^a-z0-9_\-]/', '', $type );
		if ( '' === $type ) {
			return null;
		}

		$normalized = [ 'type' => $type ];
		if ( isset( $card['data'] ) && is_array( $card['data'] ) ) {
			$normalized['data'] = $card['data'];
		}

		return $normalized;
	}

	/**
	 * Normalize tool-call trace payload for persistence.
	 *
	 * @param array<int,array<string,mixed>>|null $tool_calls Raw tool-call trace rows.
	 * @return array<int,array<string,mixed>>
	 */
	private function normalize_tool_calls( ?array $tool_calls ): array {
		if ( ! is_array( $tool_calls ) ) {
			return [];
		}

		$normalized = [];
		foreach ( $tool_calls as $tool_call ) {
			if ( ! is_array( $tool_call ) ) {
				continue;
			}

			$normalized_row = $this->normalize_tool_call_row( $tool_call );
			if ( null === $normalized_row ) {
				continue;
			}

			$normalized[] = $normalized_row;
		}

		return $normalized;
	}

	/**
	 * Normalize one persisted tool-call row.
	 *
	 * @param array<string,mixed> $tool_call Raw tool-call row.
	 * @return array<string,mixed>|null
	 */
	private function normalize_tool_call_row( array $tool_call ): ?array {
		$name = isset( $tool_call['name'] ) ? strtolower( sanitize_text_field( (string) $tool_call['name'] ) ) : '';
		$name = (string) preg_replace( '/[^a-z0-9_\-]/', '', $name );
		if ( '' === $name ) {
			return null;
		}

		$ability     = isset( $tool_call['ability'] ) ? sanitize_text_field( (string) $tool_call['ability'] ) : '';
		$status      = isset( $tool_call['status'] ) ? strtolower( sanitize_text_field( (string) $tool_call['status'] ) ) : 'success';
		$status      = in_array( $status, [ 'success', 'error', 'requires_confirmation' ], true ) ? $status : 'success';
		$message     = isset( $tool_call['message'] ) ? sanitize_text_field( (string) $tool_call['message'] ) : '';
		$args        = isset( $tool_call['args'] ) && is_array( $tool_call['args'] ) ? $tool_call['args'] : [];
		$round       = isset( $tool_call['round'] ) ? max( 1, (int) $tool_call['round'] ) : 1;
		$sequence    = isset( $tool_call['sequence'] ) ? max( 1, (int) $tool_call['sequence'] ) : 1;
		$recorded_at = isset( $tool_call['recorded_at'] ) && is_numeric( $tool_call['recorded_at'] )
			? max( 0, (int) $tool_call['recorded_at'] )
			: null;

		return [
			'name'                  => $name,
			'ability'               => '' !== $ability ? $ability : null,
			'args'                  => $args,
			'status'                => $status,
			'requires_confirmation' => isset( $tool_call['requires_confirmation'] )
				? (bool) $tool_call['requires_confirmation']
				: ( 'requires_confirmation' === $status ),
			'message'               => '' !== $message ? $message : null,
			'round'                 => $round,
			'sequence'              => $sequence,
			'recorded_at'           => $recorded_at,
		];
	}
}

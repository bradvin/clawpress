<?php
/**
 * Chat history helper.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Helpers;

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
	 * @param string                   $role Message role.
	 * @param string                   $content Message content.
	 * @param array<string,mixed>|null      $card Optional card metadata.
	 * @param array<int,array<string,mixed>>|null $tool_calls Optional tool-call trace rows.
	 * @param int|null                      $user_id User ID.
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

		update_option( $this->get_history_option_key( $user_id ), $items );
	}

	/**
	 * Clear a user's chat history.
	 *
	 * @param int|null $user_id User ID.
	 */
	public function clear_history_items( ?int $user_id = null ): void {
		update_option( $this->get_history_option_key( $user_id ), [] );
	}

	/**
	 * Build the option key for a user's history.
	 *
	 * @param int|null $user_id User ID.
	 */
	private function get_history_option_key( ?int $user_id = null ): string {
		$resolved_user_id = null === $user_id ? get_current_user_id() : $user_id;
		return sprintf( 'clawpress_chat_history_%d', $resolved_user_id );
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

		$ability = isset( $tool_call['ability'] ) ? sanitize_text_field( (string) $tool_call['ability'] ) : '';
		$status  = isset( $tool_call['status'] ) ? strtolower( sanitize_text_field( (string) $tool_call['status'] ) ) : 'success';
		$status  = in_array( $status, [ 'success', 'error', 'requires_confirmation' ], true ) ? $status : 'success';
		$message = isset( $tool_call['message'] ) ? sanitize_text_field( (string) $tool_call['message'] ) : '';
		$args    = isset( $tool_call['args'] ) && is_array( $tool_call['args'] ) ? $tool_call['args'] : [];
		$round    = isset( $tool_call['round'] ) ? max( 1, (int) $tool_call['round'] ) : 1;
		$sequence = isset( $tool_call['sequence'] ) ? max( 1, (int) $tool_call['sequence'] ) : 1;
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

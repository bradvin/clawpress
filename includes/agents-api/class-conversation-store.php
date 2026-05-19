<?php
/**
 * Agents API conversation-store adapter.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\AgentsAPI;

use AgentsAPI\Core\Database\Chat\WP_Agent_Conversation_Store;
use AgentsAPI\Core\Workspace\WP_Agent_Workspace_Scope;

defined( 'ABSPATH' ) || exit;

/**
 * Option-backed transcript store for Agents API generic conversation sessions.
 */
final class Conversation_Store implements WP_Agent_Conversation_Store {
	/**
	 * Option key used for generic Agents API transcripts.
	 */
	private const OPTION_KEY = 'clawpress_agents_api_conversations';

	/**
	 * Create a new transcript session.
	 */
	public function create_session( WP_Agent_Workspace_Scope $workspace, int $user_id, string $agent_slug = '', array $metadata = [], string $context = 'chat' ): string {
		$session_id = $this->generate_session_id();
		$now        = $this->now();
		$sessions   = $this->read_sessions();

		$sessions[ $session_id ] = [
			'session_id'           => $session_id,
			'workspace_type'       => $workspace->workspace_type,
			'workspace_id'         => $workspace->workspace_id,
			'user_id'              => max( 0, $user_id ),
			'agent_slug'           => sanitize_key( $agent_slug ),
			'title'                => '',
			'messages'             => [],
			'metadata'             => $this->normalize_json_array( $metadata ),
			'provider'             => '',
			'model'                => '',
			'provider_response_id' => null,
			'context'              => sanitize_key( $context ),
			'mode'                 => sanitize_key( $context ),
			'created_at'           => $now,
			'updated_at'           => $now,
			'last_read_at'         => null,
			'expires_at'           => null,
		];

		$this->write_sessions( $sessions );

		return $session_id;
	}

	/**
	 * List sessions.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function list_sessions( WP_Agent_Workspace_Scope $workspace, int $user_id, array $args = [] ): array {
		$include_messages = ! empty( $args['include_messages'] );
		$agent_slug       = isset( $args['agent_slug'] ) ? sanitize_key( (string) $args['agent_slug'] ) : '';
		$context          = isset( $args['context'] ) ? sanitize_key( (string) $args['context'] ) : '';
		$limit            = isset( $args['limit'] ) ? max( 0, (int) $args['limit'] ) : 20;
		$offset           = isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0;

		$rows = array_values(
			array_filter(
				$this->read_sessions(),
				static function ( array $row ) use ( $workspace, $user_id, $agent_slug, $context ): bool {
					if ( (string) ( $row['workspace_type'] ?? '' ) !== $workspace->workspace_type ) {
						return false;
					}

					if ( (string) ( $row['workspace_id'] ?? '' ) !== $workspace->workspace_id ) {
						return false;
					}

					if ( (int) ( $row['user_id'] ?? 0 ) !== max( 0, $user_id ) ) {
						return false;
					}

					if ( '' !== $agent_slug && (string) ( $row['agent_slug'] ?? '' ) !== $agent_slug ) {
						return false;
					}

					return '' === $context || (string) ( $row['context'] ?? '' ) === $context;
				}
			)
		);

		usort(
			$rows,
			static fn( array $left, array $right ): int => strcmp( (string) ( $right['updated_at'] ?? '' ), (string) ( $left['updated_at'] ?? '' ) )
		);

		if ( $offset > 0 ) {
			$rows = array_slice( $rows, $offset );
		}

		if ( $limit > 0 ) {
			$rows = array_slice( $rows, 0, $limit );
		}

		if ( ! $include_messages ) {
			$rows = array_map(
				static function ( array $row ): array {
					$row['messages'] = [];
					return $row;
				},
				$rows
			);
		}

		return $rows;
	}

	/**
	 * Get one session.
	 */
	public function get_session( string $session_id ): ?array {
		$sessions = $this->read_sessions();
		$row      = $sessions[ $session_id ] ?? null;
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Replace transcript messages and metadata.
	 */
	public function update_session( string $session_id, array $messages, array $metadata = [], string $provider = '', string $model = '', ?string $provider_response_id = null ): bool {
		$sessions = $this->read_sessions();
		if ( ! isset( $sessions[ $session_id ] ) || ! is_array( $sessions[ $session_id ] ) ) {
			return false;
		}

		$sessions[ $session_id ]['messages']             = $this->normalize_json_array( $messages );
		$sessions[ $session_id ]['metadata']             = $this->normalize_json_array( $metadata );
		$sessions[ $session_id ]['provider']             = sanitize_key( $provider );
		$sessions[ $session_id ]['model']                = sanitize_text_field( $model );
		$sessions[ $session_id ]['provider_response_id'] = null === $provider_response_id ? null : sanitize_text_field( $provider_response_id );
		$sessions[ $session_id ]['updated_at']           = $this->now();

		$this->write_sessions( $sessions );

		return true;
	}

	/**
	 * Delete one session.
	 */
	public function delete_session( string $session_id ): bool {
		$sessions = $this->read_sessions();
		unset( $sessions[ $session_id ] );
		$this->write_sessions( $sessions );

		return true;
	}

	/**
	 * Find a recent pending session.
	 */
	public function get_recent_pending_session( WP_Agent_Workspace_Scope $workspace, int $user_id, int $seconds = 600, string $context = 'chat', ?int $token_id = null ): ?array {
		$cutoff = time() - max( 1, $seconds );
		$rows   = $this->list_sessions(
			$workspace,
			$user_id,
			[
				'include_messages' => true,
				'context'          => $context,
				'limit'            => 0,
			]
		);

		foreach ( $rows as $row ) {
			$created_at = strtotime( (string) ( $row['created_at'] ?? '' ) );
			if ( false === $created_at || $created_at < $cutoff ) {
				continue;
			}

			$metadata = isset( $row['metadata'] ) && is_array( $row['metadata'] ) ? $row['metadata'] : [];
			if ( null !== $token_id && (int) ( $metadata['token_id'] ?? 0 ) !== $token_id ) {
				continue;
			}

			$messages = isset( $row['messages'] ) && is_array( $row['messages'] ) ? $row['messages'] : [];
			$status   = isset( $metadata['status'] ) ? (string) $metadata['status'] : '';
			if ( [] === $messages || in_array( $status, [ 'pending', 'processing' ], true ) ) {
				return $row;
			}
		}

		return null;
	}

	/**
	 * Update a session title.
	 */
	public function update_title( string $session_id, string $title ): bool {
		$sessions = $this->read_sessions();
		if ( ! isset( $sessions[ $session_id ] ) || ! is_array( $sessions[ $session_id ] ) ) {
			return false;
		}

		$sessions[ $session_id ]['title']      = sanitize_text_field( $title );
		$sessions[ $session_id ]['updated_at'] = $this->now();
		$this->write_sessions( $sessions );

		return true;
	}

	/**
	 * Read all persisted sessions.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function read_sessions(): array {
		$sessions = get_option( self::OPTION_KEY, [] );
		return is_array( $sessions ) ? $sessions : [];
	}

	/**
	 * Persist all sessions.
	 *
	 * @param array<string,array<string,mixed>> $sessions Sessions keyed by ID.
	 */
	private function write_sessions( array $sessions ): void {
		update_option( self::OPTION_KEY, $sessions, false );
	}

	/**
	 * Normalize an array to JSON-safe data.
	 *
	 * @param array<mixed> $value Raw array.
	 * @return array<mixed>
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
	 * Generate a session ID.
	 */
	private function generate_session_id(): string {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return (string) wp_generate_uuid4();
		}

		return sprintf( 'clawpress-%s-%s', time(), random_int( 100000, 999999 ) );
	}

	/**
	 * Current timestamp string.
	 */
	private function now(): string {
		return gmdate( 'c' );
	}
}

<?php
/**
 * Agents API conversation-store adapter.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\AgentsAPI;

use AgentsAPI\Core\Database\Chat\WP_Agent_Principal_Conversation_Session_Reader;
use AgentsAPI\Core\Workspace\WP_Agent_Workspace_Scope;

defined( 'ABSPATH' ) || exit;

/**
 * Option-backed transcript store for Agents API generic conversation sessions.
 */
final class Conversation_Store implements WP_Agent_Principal_Conversation_Session_Reader {
	/**
	 * Option key used for generic Agents API transcripts.
	 */
	private const OPTION_KEY = 'clawpress_agents_api_conversations';

	/**
	 * Create a new transcript session.
	 */
	public function create_session( WP_Agent_Workspace_Scope $workspace, int $user_id, string $agent_slug = '', array $metadata = [], string $context = 'chat' ): string {
		return $this->create_session_for_owner(
			$workspace,
			[
				'type' => 'user',
				'key'  => (string) max( 0, $user_id ),
			],
			$agent_slug,
			$metadata,
			$context
		);
	}

	/**
	 * Create a new transcript session for a canonical principal owner.
	 *
	 * @param array{type:string,key:string} $owner Canonical principal owner.
	 */
	public function create_session_for_owner( WP_Agent_Workspace_Scope $workspace, array $owner, string $agent_slug = '', array $metadata = [], string $context = 'chat' ): string {
		$session_id = $this->generate_session_id();
		$now        = $this->now();
		$sessions   = $this->read_sessions();
		$owner      = $this->normalize_owner( $owner );
		$user_id    = 'user' === $owner['type'] && is_numeric( $owner['key'] ) ? max( 0, (int) $owner['key'] ) : 0;

		$sessions[ $session_id ] = [
			'session_id'           => $session_id,
			'workspace_type'       => $workspace->workspace_type,
			'workspace_id'         => $workspace->workspace_id,
			'user_id'              => $user_id,
			'owner_type'           => $owner['type'],
			'owner_key'            => $owner['key'],
			'principal_owner_type' => $owner['type'],
			'principal_owner_key'  => $owner['key'],
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
		return $this->list_sessions_for_owner(
			$workspace,
			[
				'type' => 'user',
				'key'  => (string) max( 0, $user_id ),
			],
			$args
		);
	}

	/**
	 * List transcript sessions for one workspace/principal-owner pair.
	 *
	 * @param array{type:string,key:string} $owner Canonical principal owner.
	 * @return array<int,array<string,mixed>>
	 */
	public function list_sessions_for_owner( WP_Agent_Workspace_Scope $workspace, array $owner, array $args = [] ): array {
		$include_messages = ! empty( $args['include_messages'] );
		$agent_slug       = isset( $args['agent_slug'] ) ? sanitize_key( (string) $args['agent_slug'] ) : '';
		$context          = isset( $args['context'] ) ? sanitize_key( (string) $args['context'] ) : '';
		$limit            = isset( $args['limit'] ) ? max( 0, (int) $args['limit'] ) : 20;
		$offset           = isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0;
		$owner            = $this->normalize_owner( $owner );

		$rows = array_values(
			array_filter(
				$this->read_sessions(),
				function ( array $row ) use ( $workspace, $owner, $agent_slug, $context ): bool {
					if ( ! $this->session_matches_workspace( $row, $workspace ) ) {
						return false;
					}

					if ( ! $this->session_matches_owner( $row, $owner ) ) {
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
	 * Read one transcript session for a canonical principal owner.
	 *
	 * @param array{type:string,key:string} $owner Canonical principal owner.
	 */
	public function get_session_for_owner( WP_Agent_Workspace_Scope $workspace, array $owner, string $session_id ): ?array {
		$session = $this->get_session( $session_id );
		if ( ! is_array( $session ) ) {
			return null;
		}

		return $this->session_matches_workspace( $session, $workspace ) && $this->session_matches_owner( $session, $this->normalize_owner( $owner ) )
			? $session
			: null;
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
		return $this->get_recent_pending_session_for_owner(
			$workspace,
			[
				'type' => 'user',
				'key'  => (string) max( 0, $user_id ),
			],
			$seconds,
			$context,
			$token_id
		);
	}

	/**
	 * Find a recent pending session for a canonical principal owner.
	 *
	 * @param array{type:string,key:string} $owner Canonical principal owner.
	 */
	public function get_recent_pending_session_for_owner( WP_Agent_Workspace_Scope $workspace, array $owner, int $seconds = 600, string $context = 'chat', ?int $token_id = null ): ?array {
		$cutoff = time() - max( 1, $seconds );
		$rows   = $this->list_sessions_for_owner(
			$workspace,
			$owner,
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
	 * Normalize a canonical principal owner.
	 *
	 * @param array<string,mixed> $owner Raw owner payload.
	 * @return array{type:string,key:string}
	 */
	private function normalize_owner( array $owner ): array {
		$type = isset( $owner['type'] ) ? sanitize_key( (string) $owner['type'] ) : '';
		$key  = isset( $owner['key'] ) ? trim( sanitize_text_field( (string) $owner['key'] ) ) : '';

		return [
			'type' => '' !== $type ? $type : 'user',
			'key'  => $key,
		];
	}

	/**
	 * Check whether a row belongs to a workspace.
	 *
	 * @param array<string,mixed> $row Session row.
	 */
	private function session_matches_workspace( array $row, WP_Agent_Workspace_Scope $workspace ): bool {
		return (string) ( $row['workspace_type'] ?? '' ) === $workspace->workspace_type
			&& (string) ( $row['workspace_id'] ?? '' ) === $workspace->workspace_id;
	}

	/**
	 * Check whether a row belongs to a principal owner.
	 *
	 * @param array<string,mixed>        $row Session row.
	 * @param array{type:string,key:string} $owner Canonical principal owner.
	 */
	private function session_matches_owner( array $row, array $owner ): bool {
		$row_owner_type = isset( $row['owner_type'] ) ? (string) $row['owner_type'] : ( isset( $row['principal_owner_type'] ) ? (string) $row['principal_owner_type'] : '' );
		$row_owner_key  = isset( $row['owner_key'] ) ? (string) $row['owner_key'] : ( isset( $row['principal_owner_key'] ) ? (string) $row['principal_owner_key'] : '' );

		if ( '' !== $row_owner_type || '' !== $row_owner_key ) {
			return $row_owner_type === $owner['type'] && $row_owner_key === $owner['key'];
		}

		return 'user' === $owner['type'] && (int) ( $row['user_id'] ?? 0 ) === (int) $owner['key'];
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

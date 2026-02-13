<?php
/**
 * Chat REST controller.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\RestAPI\Controllers;

use Throwable;
use WordPress\AiClient\AiClient;

defined( 'ABSPATH' ) || exit;

/**
 * Chat endpoints controller.
 */
final class Chat_Controller implements Route_Controller {
	/**
	 * Maximum number of chat messages persisted per user.
	 */
	private const HISTORY_LIMIT = 50;

	/**
	 * Message reply generator callback.
	 *
	 * @var callable(string):array<string,mixed>
	 */
	private $reply_generator;

	/**
	 * Constructor.
	 *
	 * @param callable(string):array<string,mixed>|null $reply_generator Optional reply generator callback.
	 */
	public function __construct( ?callable $reply_generator = null ) {
		$this->reply_generator = $reply_generator ?? [ $this, 'generate_ai_reply' ];
	}

	/**
	 * Register chat endpoints.
	 */
	public function register_routes(): void {
		register_rest_route(
			'clawpress/v1',
			'/chat/message',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'send_message' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => [
					'message' => [
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);

		register_rest_route(
			'clawpress/v1',
			'/chat/history',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_history' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			]
		);
	}

	/**
	 * Validate endpoint permissions.
	 */
	public function permissions_check(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Handle a chat message request.
	 *
	 * @param \WP_REST_Request $request The request object.
	 */
	public function send_message( \WP_REST_Request $request ): \WP_REST_Response {
		$message = trim( (string) $request->get_param( 'message' ) );
		if ( '' === $message ) {
			return new \WP_REST_Response(
				[
					'error' => 'Message is required.',
				],
				400
			);
		}

		$reply_payload = call_user_func( $this->reply_generator, $message );
		$reply         = isset( $reply_payload['reply'] ) ? trim( (string) $reply_payload['reply'] ) : '';
		if ( '' === $reply ) {
			$reply = $this->build_offline_reply( $message );
		}

		$this->append_history_message( 'user', $message );
		$this->append_history_message( 'assistant', $reply );

		return new \WP_REST_Response(
			[
				'message' => $message,
				'reply'   => $reply,
				'meta'    => [
					'mode'     => isset( $reply_payload['mode'] ) ? (string) $reply_payload['mode'] : 'offline',
					'provider' => isset( $reply_payload['provider'] ) && '' !== (string) $reply_payload['provider']
						? (string) $reply_payload['provider']
						: null,
					'model'    => isset( $reply_payload['model'] ) && '' !== (string) $reply_payload['model']
						? (string) $reply_payload['model']
						: null,
				],
			],
			200
		);
	}

	/**
	 * Return chat history.
	 */
	public function get_history(): \WP_REST_Response {
		return new \WP_REST_Response(
			[
				'items' => $this->get_history_items(),
			],
			200
		);
	}

	/**
	 * Generate a model reply via the PHP AI client.
	 *
	 * @param string $message User message.
	 * @return array<string,mixed> Reply payload.
	 */
	private function generate_ai_reply( string $message ): array {
		$provider = $this->resolve_provider();
		$model    = $this->resolve_model();

		if ( '' === $provider ) {
			return [
				'reply'    => $this->build_offline_reply( $message ),
				'mode'     => 'offline',
				'provider' => null,
				'model'    => null,
			];
		}

		try {
			$builder = AiClient::prompt( $message )->usingProvider( $provider );
			if ( '' !== $model ) {
				$builder = $builder->usingModelPreference( [ $provider, $model ] );
			}

			$reply = trim( $builder->generateText() );
			if ( '' === $reply ) {
				return [
					'reply'    => $this->build_offline_reply( $message ),
					'mode'     => 'offline',
					'provider' => $provider,
					'model'    => '' !== $model ? $model : null,
				];
			}

			return [
				'reply'    => $reply,
				'mode'     => 'online',
				'provider' => $provider,
				'model'    => '' !== $model ? $model : null,
			];
		} catch ( Throwable $throwable ) {
			unset( $throwable );

			return [
				'reply'    => $this->build_offline_reply( $message ),
				'mode'     => 'offline',
				'provider' => $provider,
				'model'    => '' !== $model ? $model : null,
			];
		}
	}

	/**
	 * Resolve provider from settings or available credentials.
	 */
	private function resolve_provider(): string {
		$settings = $this->get_settings();
		if ( isset( $settings['provider'] ) && is_string( $settings['provider'] ) ) {
			$candidate = strtolower( trim( $settings['provider'] ) );
			if ( isset( $this->get_provider_credentials_map()[ $candidate ] ) ) {
				return $candidate;
			}
		}

		foreach ( $this->get_provider_credentials_map() as $provider => $credential_key ) {
			if ( $this->has_credential( $credential_key ) ) {
				return $provider;
			}
		}

		return '';
	}

	/**
	 * Resolve preferred model from settings.
	 */
	private function resolve_model(): string {
		$settings = $this->get_settings();
		if ( ! isset( $settings['model'] ) || ! is_string( $settings['model'] ) ) {
			return '';
		}

		return trim( $settings['model'] );
	}

	/**
	 * Get provider to credential-key map.
	 *
	 * @return array<string,string>
	 */
	private function get_provider_credentials_map(): array {
		return [
			'openai'    => 'OPENAI_API_KEY',
			'anthropic' => 'ANTHROPIC_API_KEY',
			'google'    => 'GOOGLE_API_KEY',
		];
	}

	/**
	 * Check whether an environment variable/constant has a non-empty value.
	 *
	 * @param string $key Credential key.
	 */
	private function has_credential( string $key ): bool {
		$env_value = getenv( $key );
		if ( false !== $env_value && '' !== trim( (string) $env_value ) ) {
			return true;
		}

		if ( ! defined( $key ) ) {
			return false;
		}

		$constant_value = constant( $key );
		if ( ! is_scalar( $constant_value ) ) {
			return false;
		}

		return '' !== trim( (string) $constant_value );
	}

	/**
	 * Build deterministic offline fallback response.
	 *
	 * @param string $message User message.
	 */
	private function build_offline_reply( string $message ): string {
		return sprintf(
			'Offline mode: no configured AI provider was available. You said: "%s"',
			$message
		);
	}

	/**
	 * Get plugin settings.
	 *
	 * @return array<string,mixed>
	 */
	private function get_settings(): array {
		$settings = get_option( 'clawpress_settings', [] );
		return is_array( $settings ) ? $settings : [];
	}

	/**
	 * Get current user history option key.
	 */
	private function get_history_option_key(): string {
		return sprintf( 'clawpress_chat_history_%d', get_current_user_id() );
	}

	/**
	 * Get normalized history items.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function get_history_items(): array {
		$items = get_option( $this->get_history_option_key(), [] );
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
	 * Normalize a single history item.
	 *
	 * @param array<string,mixed> $item History item.
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
			'id'        => $id,
			'role'      => $role,
			'content'   => $content,
			'createdAt' => $created,
		];
	}

	/**
	 * Append a message to user history.
	 *
	 * @param string $role Message role.
	 * @param string $content Message content.
	 */
	private function append_history_message( string $role, string $content ): void {
		$items      = $this->get_history_items();
		$created_at = (int) round( microtime( true ) * 1000 );

		$items[] = [
			'id'        => sprintf( 'msg-%d-%d', $created_at, count( $items ) + 1 ),
			'role'      => $role,
			'content'   => $content,
			'createdAt' => $created_at,
		];

		if ( count( $items ) > self::HISTORY_LIMIT ) {
			$items = array_slice( $items, -self::HISTORY_LIMIT );
		}

		update_option( $this->get_history_option_key(), $items );
	}
}

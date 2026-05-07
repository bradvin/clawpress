<?php
/**
 * Chat REST controller.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\RestAPI\Controllers;

use ClawPress\Commands\Commands;
use ClawPress\Helpers\Action_Log_Helper;
use ClawPress\Helpers\Chat_Helper;
use ClawPress\Helpers\Chat_History_Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Chat endpoints controller.
 */
final class Chat_Controller implements Route_Controller {
	/**
	 * Message reply generator callback.
	 *
	 * @var callable(string):array<string,mixed>
	 */
	private $reply_generator;

	/**
	 * Chat history helper.
	 *
	 * @var Chat_History_Helper
	 */
	private Chat_History_Helper $history_helper;

	/**
	 * Chat helper.
	 *
	 * @var Chat_Helper
	 */
	private Chat_Helper $chat_helper;

	/**
	 * Action log helper.
	 *
	 * @var Action_Log_Helper
	 */
	private Action_Log_Helper $action_log_helper;

	/**
	 * Offline command engine.
	 *
	 * @var Commands
	 */
	private Commands $commands;

	/**
	 * Constructor.
	 *
	 * @param callable(string):array<string,mixed>|null $reply_generator Optional reply generator callback.
	 */
	public function __construct( ?callable $reply_generator = null ) {
		$this->chat_helper       = Chat_Helper::get_instance();
		$this->history_helper    = Chat_History_Helper::get_instance();
		$this->action_log_helper = Action_Log_Helper::get_instance();
		$this->commands          = new Commands();
		$this->reply_generator   = $reply_generator ?? [ $this->chat_helper, 'generate_ai_reply' ];
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
				'permission_callback' => 'clawpress_check_permissions',
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
			'/chat/stream',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'send_stream_message' ],
				'permission_callback' => 'clawpress_check_permissions',
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
				'permission_callback' => 'clawpress_check_permissions',
			]
		);
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
					'error' => __( 'Message is required.', 'clawpress' ),
				],
				400
			);
		}

		return new \WP_REST_Response(
			$this->build_chat_response_data(
				$message,
				$this->resolve_reply_payload( $message )
			),
			200
		);
	}

	/**
	 * Handle a streamed chat request.
	 *
	 * @param \WP_REST_Request $request The request object.
	 */
	public function send_stream_message( \WP_REST_Request $request ): \WP_REST_Response {
		$message = trim( (string) $request->get_param( 'message' ) );
		if ( '' === $message ) {
			return new \WP_REST_Response(
				[
					'error' => __( 'Message is required.', 'clawpress' ),
				],
				400
			);
		}

		$saw_delta            = false;
		$stream_event_callback = function ( array $event ) use ( &$saw_delta ): void {
			if ( $this->emit_stream_transport_event( $event ) ) {
				$saw_delta = true;
			}
		};

		$this->start_stream_response();

		try {
			$response_data = $this->build_chat_response_data(
				$message,
				$this->resolve_reply_payload( $message, true, $stream_event_callback )
			);

			$this->emit_stream_response_data( $response_data, $saw_delta );
		} catch ( \Throwable $throwable ) {
			self::send_stream_frame(
				'error',
				[
					'error' => trim( sanitize_text_field( $throwable->getMessage() ) ) ?: __( 'Chat request failed.', 'clawpress' ),
					'type'  => 'request',
				]
			);
		}

		exit;
	}

	/**
	 * Resolve the reply payload for one chat message.
	 *
	 * @param string               $message User message.
	 * @param bool                 $streaming Whether streaming transport is requested.
	 * @param callable|null        $stream_event_callback Optional live stream event callback.
	 * @return array<string,mixed>
	 */
	private function resolve_reply_payload( string $message, bool $streaming = false, ?callable $stream_event_callback = null ): array {
		$command_payload = $this->commands->maybe_dispatch( $message );
		if ( is_array( $command_payload ) ) {
			return $command_payload;
		}

		if ( $streaming && $this->is_default_reply_generator() ) {
			return $this->chat_helper->generate_ai_reply(
				$message,
				[
					'transport_mode'        => 'streaming',
					'stream_event_callback' => $stream_event_callback,
				]
			);
		}

		return call_user_func( $this->reply_generator, $message );
	}

	/**
	 * Determine whether the controller is using the default chat-helper reply generator.
	 */
	private function is_default_reply_generator(): bool {
		return is_array( $this->reply_generator )
			&& isset( $this->reply_generator[0], $this->reply_generator[1] )
			&& $this->reply_generator[0] === $this->chat_helper
			&& 'generate_ai_reply' === (string) $this->reply_generator[1];
	}

	/**
	 * Normalize, persist, and log one chat response payload.
	 *
	 * @param string              $message User message.
	 * @param array<string,mixed> $reply_payload Raw reply payload.
	 * @return array<string,mixed>
	 */
	private function build_chat_response_data( string $message, array $reply_payload ): array {
		$reply = isset( $reply_payload['reply'] ) ? trim( (string) $reply_payload['reply'] ) : '';
		if ( '' === $reply ) {
			$reply = $this->chat_helper->build_offline_reply( $message );
		}

		$command_meta         = isset( $reply_payload['command'] ) && is_array( $reply_payload['command'] )
			? $reply_payload['command']
			: [];
		$card_meta            = isset( $reply_payload['card'] ) && is_array( $reply_payload['card'] )
			? $reply_payload['card']
			: null;
		$tool_calls_meta      = isset( $reply_payload['tool_calls'] ) && is_array( $reply_payload['tool_calls'] )
			? array_map(
				static function ( $tool_call ): array {
					$normalized = is_array( $tool_call ) ? $tool_call : [];
					if ( ! isset( $normalized['recorded_at'] ) ) {
						$normalized['recorded_at'] = (int) round( microtime( true ) * 1000 );
					}

					return $normalized;
				},
				array_values( $reply_payload['tool_calls'] )
			)
			: null;
		$error_meta           = isset( $reply_payload['error'] ) && is_array( $reply_payload['error'] )
			? $reply_payload['error']
			: null;
		$clear_history_effect = isset( $command_meta['effects']['clear_history'] ) && true === $command_meta['effects']['clear_history'];
		$is_command_response  = [] !== $command_meta;
		$is_error_response    = null !== $error_meta;

		if ( ! $clear_history_effect ) {
			$this->history_helper->append_history_message( 'user', $message );
			$this->history_helper->append_history_message(
				( $is_command_response || $is_error_response ) ? 'system' : 'assistant',
				$reply,
				$card_meta,
				$tool_calls_meta
			);
		}

		$this->log_action_event(
			$message,
			$reply,
			$command_meta,
			$error_meta,
			isset( $reply_payload['mode'] ) ? (string) $reply_payload['mode'] : 'offline',
			isset( $reply_payload['provider'] ) ? (string) $reply_payload['provider'] : '',
			isset( $reply_payload['model'] ) ? (string) $reply_payload['model'] : ''
		);

		return [
			'message' => $message,
			'reply'   => $reply,
			'meta'    => [
				'mode'          => isset( $reply_payload['mode'] ) ? (string) $reply_payload['mode'] : 'offline',
				'provider'      => isset( $reply_payload['provider'] ) && '' !== (string) $reply_payload['provider']
					? (string) $reply_payload['provider']
					: null,
				'model'         => isset( $reply_payload['model'] ) && '' !== (string) $reply_payload['model']
					? (string) $reply_payload['model']
					: null,
				'suggestions'   => isset( $reply_payload['suggestions'] ) && is_array( $reply_payload['suggestions'] )
					? array_values( $reply_payload['suggestions'] )
					: null,
				'card'          => isset( $reply_payload['card'] ) && is_array( $reply_payload['card'] )
					? $reply_payload['card']
					: null,
				'command'       => isset( $reply_payload['command'] ) && is_array( $reply_payload['command'] )
					? $reply_payload['command']
					: null,
				'error'         => isset( $reply_payload['error'] ) && is_array( $reply_payload['error'] )
					? $reply_payload['error']
					: null,
				'context'       => isset( $reply_payload['context'] ) && is_array( $reply_payload['context'] )
					? $reply_payload['context']
					: null,
				'tool_calls'    => $tool_calls_meta,
				'run_id'        => isset( $reply_payload['run_id'] ) ? (int) $reply_payload['run_id'] : null,
				'session_id'    => isset( $reply_payload['session_id'] ) ? (int) $reply_payload['session_id'] : null,
				'events_cursor' => isset( $reply_payload['events_cursor'] ) ? (int) $reply_payload['events_cursor'] : null,
				'status'        => isset( $reply_payload['status'] ) ? (string) $reply_payload['status'] : null,
			],
		];
	}

	/**
	 * Emit one transport event into the SSE response.
	 *
	 * @param array<string,mixed> $event Transport event payload.
	 * @return bool Whether a delta frame was emitted.
	 */
	private function emit_stream_transport_event( array $event ): bool {
		$event_type = isset( $event['type'] ) ? (string) $event['type'] : '';
		$payload    = isset( $event['payload'] ) && is_array( $event['payload'] )
			? $event['payload']
			: [];

		if ( 'agent.llm.delta' === $event_type ) {
			$text = isset( $payload['text'] ) ? (string) $payload['text'] : '';
			if ( '' === $text ) {
				return false;
			}

			self::send_stream_frame(
				'delta',
				[
					'text' => $text,
				]
			);

			return true;
		}

		if ( 'agent.tool_call' !== $event_type ) {
			return false;
		}

		$status = isset( $payload['status'] ) ? strtolower( trim( (string) $payload['status'] ) ) : 'success';
		if ( ! in_array( $status, [ 'success', 'error', 'requires_confirmation' ], true ) ) {
			$status = 'success';
		}

		$tool_name = isset( $payload['tool_name'] ) ? sanitize_text_field( (string) $payload['tool_name'] ) : '';
		$ability   = isset( $payload['ability_name'] ) ? sanitize_text_field( (string) $payload['ability_name'] ) : '';
		$message   = isset( $payload['message'] ) ? sanitize_text_field( (string) $payload['message'] ) : '';

		self::send_stream_frame(
			'tool_call',
			[
				'call' => [
					'name'                  => '' !== $tool_name ? $tool_name : $ability,
					'tool_name'             => '' !== $tool_name ? $tool_name : null,
					'ability_name'          => '' !== $ability ? $ability : null,
					'status'                => $status,
					'message'               => '' !== $message ? $message : null,
					'round'                 => isset( $payload['round'] ) ? max( 1, (int) $payload['round'] ) : 1,
					'sequence'              => isset( $payload['sequence'] ) ? max( 1, (int) $payload['sequence'] ) : 1,
					'requires_confirmation' => 'requires_confirmation' === $status,
				],
			]
		);

		return false;
	}

	/**
	 * Emit the normalized chat response through the SSE stream.
	 *
	 * @param array<string,mixed> $response_data Normalized response payload.
	 * @param bool                $saw_delta Whether live token deltas were emitted.
	 */
	private function emit_stream_response_data( array $response_data, bool $saw_delta ): void {
		$meta                = isset( $response_data['meta'] ) && is_array( $response_data['meta'] )
			? $response_data['meta']
			: [];
		$reply               = isset( $response_data['reply'] ) ? trim( (string) $response_data['reply'] ) : '';
		$is_command_response = isset( $meta['command']['name'] ) && is_string( $meta['command']['name'] ) && '' !== trim( $meta['command']['name'] );
		$role                = $is_command_response ? 'system' : 'assistant';

		if ( isset( $meta['command']['effects']['clear_history'] ) && true === $meta['command']['effects']['clear_history'] ) {
			self::send_stream_frame( 'history_reset', [] );
		}

		if ( isset( $meta['suggestions'] ) && is_array( $meta['suggestions'] ) ) {
			self::send_stream_frame(
				'suggestions',
				[
					'items' => array_values( $meta['suggestions'] ),
				]
			);
		}

		if ( isset( $meta['context'] ) && is_array( $meta['context'] ) ) {
			self::send_stream_frame(
				'context_usage',
				[
					'context' => $meta['context'],
				]
			);
		}

		if ( isset( $meta['error'] ) && is_array( $meta['error'] ) ) {
			self::send_stream_frame(
				'error',
				[
					'error' => isset( $meta['error']['message'] ) && is_string( $meta['error']['message'] ) && '' !== trim( $meta['error']['message'] )
						? trim( $meta['error']['message'] )
						: __( 'Chat request failed.', 'clawpress' ),
					'type'  => isset( $meta['error']['type'] ) && is_string( $meta['error']['type'] ) && '' !== trim( $meta['error']['type'] )
						? trim( $meta['error']['type'] )
						: 'provider',
					'card'  => isset( $meta['card'] ) && is_array( $meta['card'] ) ? $meta['card'] : null,
				]
			);
		} elseif ( isset( $meta['card'] ) && is_array( $meta['card'] ) ) {
			self::send_stream_frame(
				'response_card',
				[
					'card' => $meta['card'],
					'text' => $reply,
					'role' => $role,
				]
			);
		} elseif ( '' !== $reply && ( ! $saw_delta || 'system' === $role ) ) {
			self::send_stream_frame(
				'response_message',
				[
					'text' => $reply,
					'role' => $role,
				]
			);
		}

		if ( 'in_progress' === (string) ( $meta['status'] ?? '' ) ) {
			self::send_stream_frame(
				'in_progress',
				[
					'run_id'        => isset( $meta['run_id'] ) ? (int) $meta['run_id'] : 0,
					'events_cursor' => isset( $meta['events_cursor'] ) ? (int) $meta['events_cursor'] : 0,
					'status'        => 'in_progress',
					'initial_reply' => $reply,
				]
			);
		}
	}

	/**
	 * Start the streaming response.
	 */
	private function start_stream_response(): void {
		ignore_user_abort( true );
		// phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- SSE responses may need to outlive the default request timeout.
		set_time_limit( 0 );

		while ( ob_get_level() ) {
			ob_end_clean();
		}

		nocache_headers();
		header( 'Content-Type: text/event-stream; charset=' . get_option( 'blog_charset' ) );
		header( 'Cache-Control: no-cache, no-transform' );
		header( 'X-Accel-Buffering: no' );
		header( 'Connection: keep-alive' );
		header( 'Content-Encoding: identity' );
		header( 'X-Content-Type-Options: nosniff' );

		// phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- SSE responses need server-side compression disabled.
		@ini_set( 'zlib.output_compression', '0' );
		// phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- SSE responses need server-side buffering disabled.
		@ini_set( 'output_buffering', '0' );
		// phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- SSE responses need immediate flushing.
		@ini_set( 'implicit_flush', '1' );
		// phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- SSE responses should avoid configured output handlers.
		@ini_set( 'output_handler', '' );

		if ( function_exists( 'apache_setenv' ) ) {
			@apache_setenv( 'no-gzip', '1' );
			@apache_setenv( 'dont-vary', '1' );
		}

		if ( function_exists( 'ob_implicit_flush' ) ) {
			@ob_implicit_flush( true );
		}

		echo esc_html( ':' . str_repeat( ' ', 4096 ) . "\n\n" );
		flush();
	}

	/**
	 * Send one SSE frame to the browser.
	 *
	 * @param string               $type Event type.
	 * @param array<string,mixed>  $payload Event payload.
	 */
	private static function send_stream_frame( string $type, array $payload ): void {
		$type  = self::sanitize_stream_event_type( $type );
		$frame = wp_json_encode(
			[
				'type'    => $type,
				'payload' => $payload,
			]
		);

		if ( ! is_string( $frame ) ) {
			return;
		}

		echo esc_html( "event: {$type}\n" );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SSE data is JSON encoded and must remain raw text/event-stream content.
		echo 'data: ' . $frame . "\n\n";
		echo esc_html( ':' . str_repeat( ' ', 2048 ) . "\n\n" );

		if ( function_exists( 'ob_flush' ) ) {
			@ob_flush();
		}

		flush();
	}

	/**
	 * Sanitize an SSE event type.
	 *
	 * @param string $type Event type.
	 */
	private static function sanitize_stream_event_type( string $type ): string {
		$sanitized = preg_replace( '/[^A-Za-z0-9_.-]/', '', $type );
		if ( is_string( $sanitized ) && '' !== $sanitized ) {
			return $sanitized;
		}

		return 'message';
	}

	/**
	 * Write action/event log record for chat processing.
	 *
	 * @param string                   $message User message.
	 * @param string                   $reply Reply content.
	 * @param array<string,mixed>      $command_meta Command metadata.
	 * @param array<string,mixed>|null $error_meta Error metadata.
	 * @param string                   $mode Reply mode.
	 * @param string                   $provider Provider identifier.
	 * @param string                   $model Model identifier.
	 */
	private function log_action_event(
		string $message,
		string $reply,
		array $command_meta,
		?array $error_meta,
		string $mode,
		string $provider,
		string $model
	): void {
		$is_command  = [] !== $command_meta;
		$action_name = $is_command && isset( $command_meta['name'] )
			? (string) $command_meta['name']
			: 'chat.message';

		$status = null !== $error_meta
			? 'error'
			: ( ( isset( $command_meta['error'] ) && true === $command_meta['error'] ) ? 'warning' : 'success' );

		$this->action_log_helper->log_event(
			$action_name,
			[
				'event_type' => $is_command ? 'command' : 'message',
				'status'     => $status,
				'message'    => $reply,
				'context'    => [
					'mode'      => $mode,
					'provider'  => '' !== $provider ? $provider : null,
					'model'     => '' !== $model ? $model : null,
					'user_text' => $message,
					'error'     => $error_meta,
				],
			]
		);
	}

	/**
	 * Return chat history.
	 */
	public function get_history(): \WP_REST_Response {
		return new \WP_REST_Response(
			[
				'items' => $this->history_helper->get_history_items(),
			],
			200
		);
	}
}

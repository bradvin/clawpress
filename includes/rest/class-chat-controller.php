<?php
/**
 * Chat REST controller.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\RestAPI\Controllers;

use ClawPress\Commands\Commands;
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
		$this->chat_helper     = Chat_Helper::get_instance();
		$this->history_helper  = Chat_History_Helper::get_instance();
		$this->commands        = new Commands();
		$this->reply_generator = $reply_generator ?? [ $this->chat_helper, 'generate_ai_reply' ];
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

		$command_payload = $this->commands->maybe_dispatch( $message );
		$reply_payload   = is_array( $command_payload )
			? $command_payload
			: call_user_func( $this->reply_generator, $message );

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
		$clear_history_effect = isset( $command_meta['effects']['clear_history'] ) && true === $command_meta['effects']['clear_history'];
		$is_command_response  = [] !== $command_meta;

		if ( ! $clear_history_effect ) {
			$this->history_helper->append_history_message( 'user', $message );
			$this->history_helper->append_history_message( $is_command_response ? 'system' : 'assistant', $reply, $card_meta );
		}

		return new \WP_REST_Response(
			[
				'message' => $message,
				'reply'   => $reply,
				'meta'    => [
					'mode'        => isset( $reply_payload['mode'] ) ? (string) $reply_payload['mode'] : 'offline',
					'provider'    => isset( $reply_payload['provider'] ) && '' !== (string) $reply_payload['provider']
						? (string) $reply_payload['provider']
						: null,
					'model'       => isset( $reply_payload['model'] ) && '' !== (string) $reply_payload['model']
						? (string) $reply_payload['model']
						: null,
					'suggestions' => isset( $reply_payload['suggestions'] ) && is_array( $reply_payload['suggestions'] )
						? array_values( $reply_payload['suggestions'] )
						: null,
					'card'        => isset( $reply_payload['card'] ) && is_array( $reply_payload['card'] )
						? $reply_payload['card']
						: null,
					'command'     => isset( $reply_payload['command'] ) && is_array( $reply_payload['command'] )
						? $reply_payload['command']
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
				'items' => $this->history_helper->get_history_items(),
			],
			200
		);
	}
}

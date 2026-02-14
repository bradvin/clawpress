<?php
/**
 * Chat REST controller.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\RestAPI\Controllers;

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
	 * Constructor.
	 *
	 * @param callable(string):array<string,mixed>|null $reply_generator Optional reply generator callback.
	 */
	public function __construct( ?callable $reply_generator = null ) {
		$this->chat_helper     = Chat_Helper::get_instance();
		$this->history_helper  = Chat_History_Helper::get_instance();
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
					'error' => 'Message is required.',
				],
				400
			);
		}

		$reply_payload = call_user_func( $this->reply_generator, $message );
		$reply         = isset( $reply_payload['reply'] ) ? trim( (string) $reply_payload['reply'] ) : '';
		if ( '' === $reply ) {
			$reply = $this->chat_helper->build_offline_reply( $message );
		}

		$this->history_helper->append_history_message( 'user', $message );
		$this->history_helper->append_history_message( 'assistant', $reply );

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
				'items' => $this->history_helper->get_history_items(),
			],
			200
		);
	}
}

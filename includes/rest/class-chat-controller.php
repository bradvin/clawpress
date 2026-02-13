<?php
/**
 * Chat REST controller.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\RestAPI\Controllers;

defined( 'ABSPATH' ) || exit;

/**
 * Chat endpoints controller.
 */
final class Chat_Controller implements Route_Controller {
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
		$message = (string) $request->get_param( 'message' );

		return new \WP_REST_Response(
			[
				'message' => $message,
				'reply'   => 'Chat endpoint received your message.',
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
				'items' => [],
			],
			200
		);
	}
}

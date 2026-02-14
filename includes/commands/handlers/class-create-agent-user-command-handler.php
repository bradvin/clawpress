<?php
/**
 * /create-agent-user command handler.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Commands\Handlers;

use ClawPress\Commands\Command_Handler;
use ClawPress\Commands\Command_Request;
use ClawPress\Commands\Command_Response;
use ClawPress\Helpers\User_Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Hidden command to create a dedicated agent user.
 */
final class Create_Agent_User_Command_Handler implements Command_Handler {
	/**
	 * User helper.
	 *
	 * @var User_Helper
	 */
	private User_Helper $user_helper;

	/**
	 * Constructor.
	 *
	 * @param User_Helper $user_helper User helper.
	 */
	public function __construct( User_Helper $user_helper ) {
		$this->user_helper = $user_helper;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_command(): string {
		return '/create-agent-user';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return __( 'Create a dedicated contributor user for the agent.', 'clawpress' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_usage(): string {
		return '/create-agent-user';
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_destructive(): bool {
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_default_suggestions(): array {
		return [];
	}

	/**
	 * {@inheritDoc}
	 */
	public function handle( Command_Request $request ): Command_Response {
		if ( '' !== $request->get_argument( 0 ) ) {
			return Command_Response::error(
				sprintf(
					/* translators: %s: expected command usage */
					__( 'Invalid usage. Expected: `%s`', 'clawpress' ),
					$this->get_usage()
				),
				$this->get_command(),
				false,
				false,
				[],
				[ '/status', '/help' ]
			);
		}

		$result = $this->user_helper->create_agent_user();
		if ( empty( $result['success'] ) ) {
			$error_message = isset( $result['error'] ) && is_string( $result['error'] ) && '' !== trim( $result['error'] )
				? $result['error']
				: __( 'Could not create agent user.', 'clawpress' );

			return Command_Response::error(
				sprintf(
					/* translators: %s: error message */
					__( 'Failed creating agent user: %s', 'clawpress' ),
					$error_message
				),
				$this->get_command(),
				false,
				false,
				[],
				[ '/status', '/help' ]
			);
		}

		$user_id      = isset( $result['user_id'] ) ? (int) $result['user_id'] : 0;
		$user_login   = isset( $result['user_login'] ) ? sanitize_text_field( (string) $result['user_login'] ) : '';
		$user_email   = isset( $result['user_email'] ) ? sanitize_text_field( (string) $result['user_email'] ) : '';
		$display_name = isset( $result['display_name'] ) ? sanitize_text_field( (string) $result['display_name'] ) : '';
		$role         = isset( $result['role'] ) ? sanitize_text_field( (string) $result['role'] ) : '';

		return Command_Response::success(
			implode(
				"\n",
				[
					__( 'Agent user created.', 'clawpress' ),
					sprintf(
						/* translators: %d: user ID */
						__( '- User ID: %d', 'clawpress' ),
						$user_id
					),
					sprintf(
						/* translators: %s: username */
						__( '- Login: %s', 'clawpress' ),
						$user_login
					),
					sprintf(
						/* translators: %s: email address */
						__( '- Email: %s', 'clawpress' ),
						$user_email
					),
					sprintf(
						/* translators: %s: display name */
						__( '- Display name: %s', 'clawpress' ),
						$display_name
					),
					sprintf(
						/* translators: %s: role name */
						__( '- Role: %s', 'clawpress' ),
						$role
					),
				]
			),
			$this->get_command(),
			false,
			false,
			[],
			[
				sprintf( '/settings agent_user_id %d', $user_id ),
				'/status',
				'/help',
			]
		);
	}
}

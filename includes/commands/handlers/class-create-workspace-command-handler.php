<?php
/**
 * /create-workspace command handler.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Commands\Handlers;

use ClawPress\Commands\Command_Handler;
use ClawPress\Commands\Command_Request;
use ClawPress\Commands\Command_Response;
use ClawPress\Helpers\Settings_Helper;
use ClawPress\Helpers\Workspace_Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Hidden command to create a workspace for the configured agent user.
 */
final class Create_Workspace_Command_Handler implements Command_Handler {
	/**
	 * Settings helper.
	 *
	 * @var Settings_Helper
	 */
	private Settings_Helper $settings_helper;

	/**
	 * Workspace helper.
	 *
	 * @var Workspace_Helper
	 */
	private Workspace_Helper $workspace_helper;

	/**
	 * Constructor.
	 *
	 * @param Settings_Helper  $settings_helper Settings helper.
	 * @param Workspace_Helper $workspace_helper Workspace helper.
	 */
	public function __construct( Settings_Helper $settings_helper, Workspace_Helper $workspace_helper ) {
		$this->settings_helper  = $settings_helper;
		$this->workspace_helper = $workspace_helper;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_command(): string {
		return '/create-workspace';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return __( 'Create a secure workspace for the configured agent user.', 'clawpress' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_usage(): string {
		return '/create-workspace';
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
	 *
	 * @param Command_Request $request Command request.
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

		$settings      = $this->settings_helper->get_settings();
		$agent_user_id = $this->settings_helper->resolve_agent_user_id( $settings );

		if ( $agent_user_id <= 0 ) {
			return Command_Response::error(
				__( 'Agent user is not configured. Set one with `/settings agent_user_id <user-id>` first.', 'clawpress' ),
				$this->get_command(),
				false,
				false,
				[],
				[ '/status', '/help' ]
			);
		}

		$result = $this->workspace_helper->create_workspace_for_agent_user( $agent_user_id );
		if ( empty( $result['success'] ) ) {
			$error_message = isset( $result['error'] ) && is_string( $result['error'] ) && '' !== trim( $result['error'] )
				? $result['error']
				: __( 'Could not create workspace.', 'clawpress' );

			return Command_Response::error(
				sprintf(
					/* translators: %s: error message */
					__( 'Failed creating workspace: %s', 'clawpress' ),
					$error_message
				),
				$this->get_command(),
				false,
				false,
				[],
				[ '/status', '/help' ]
			);
		}

		$workspace_path = isset( $result['workspace_path'] ) ? sanitize_text_field( (string) $result['workspace_path'] ) : '';

		return Command_Response::success(
			implode(
				"\n",
				[
					__( 'Workspace ready.', 'clawpress' ),
					sprintf(
						/* translators: %d: user ID */
						__( '- Agent user ID: %d', 'clawpress' ),
						$agent_user_id
					),
					sprintf(
						/* translators: %s: workspace path */
						__( '- Path: %s', 'clawpress' ),
						$workspace_path
					),
				]
			),
			$this->get_command(),
			false,
			false,
			[],
			[ '/status', '/help' ]
		);
	}
}

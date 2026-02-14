<?php
/**
 * /tools command handler.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Commands\Handlers;

use ClawPress\Commands\Command_Handler;
use ClawPress\Commands\Command_Request;
use ClawPress\Commands\Command_Response;
use ClawPress\Helpers\Status_Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Tools list command.
 */
final class Tools_Command_Handler implements Command_Handler {
	/**
	 * Status helper.
	 *
	 * @var Status_Helper
	 */
	private Status_Helper $status_helper;

	/**
	 * Constructor.
	 *
	 * @param Status_Helper $status_helper Status helper.
	 */
	public function __construct( Status_Helper $status_helper ) {
		$this->status_helper = $status_helper;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_command(): string {
		return '/tools';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return 'List available tools/actions and whether they are enabled.';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_usage(): string {
		return '/tools list';
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
	public function handle( Command_Request $request ): Command_Response {
		$subcommand = strtolower( $request->get_argument( 0 ) );
		if ( 'list' !== $subcommand ) {
			return Command_Response::error(
				sprintf( 'Invalid usage. Expected: `%s`', $this->get_usage() ),
				$this->get_command()
			);
		}

		$status = $this->status_helper->get_current_status();
		$online = 'online' === (string) ( $status['mode'] ?? 'offline' );

		$lines = [
			'Available tools/actions:',
			'- offline_commands: enabled',
			sprintf( '- online_chat: %s', $online ? 'enabled' : 'disabled (provider/model not configured)' ),
			'- tool_execution: disabled (planned in a later level)',
		];

		return Command_Response::success( implode( "\n", $lines ), $this->get_command() );
	}
}

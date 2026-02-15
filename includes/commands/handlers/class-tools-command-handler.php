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
use ClawPress\Helpers\Abilities_Helper;
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
	 * Abilities helper.
	 *
	 * @var Abilities_Helper
	 */
	private Abilities_Helper $abilities_helper;

	/**
	 * Constructor.
	 *
	 * @param Status_Helper    $status_helper Status helper.
	 * @param Abilities_Helper $abilities_helper Abilities helper.
	 */
	public function __construct( Status_Helper $status_helper, Abilities_Helper $abilities_helper ) {
		$this->status_helper    = $status_helper;
		$this->abilities_helper = $abilities_helper;
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
		return __( 'List available tools/actions and whether they are enabled.', 'clawpress' );
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
	public function get_default_suggestions(): array {
		return [ '/tools list' ];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Command_Request $request Command request.
	 */
	public function handle( Command_Request $request ): Command_Response {
		$subcommand = strtolower( $request->get_argument( 0 ) );
		if ( 'list' !== $subcommand ) {
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
				[ '/tools list', '/status', '/help' ]
			);
		}

		$status             = $this->status_helper->get_current_status();
		$online             = 'online' === (string) ( $status['mode'] ?? 'offline' );
		$online_chat_status = $online
			? __( 'enabled', 'clawpress' )
			: __( 'disabled (provider/model not configured)', 'clawpress' );

		$lines         = [
			__( 'Available tools/actions:', 'clawpress' ),
			__( '- offline_commands: enabled', 'clawpress' ),
			sprintf(
				/* translators: %s: online chat status */
				__( '- online_chat: %s', 'clawpress' ),
				$online_chat_status
			),
		];
		$tool_rows     = $this->abilities_helper->get_tool_status_list();
		$enabled_count = 0;

		foreach ( $tool_rows as $row ) {
			if ( empty( $row['registered'] ) ) {
				continue;
			}

			++$enabled_count;
			$lines[] = sprintf(
				/* translators: 1: tool name, 2: safety class */
				__( '- %1$s: enabled (%2$s)', 'clawpress' ),
				(string) $row['tool_name'],
				(string) $row['safety_class']
			);
		}

		if ( 0 === $enabled_count ) {
			$lines[] = __( '- tool_execution: unavailable (no registered abilities)', 'clawpress' );
		}

		return Command_Response::success(
			implode( "\n", $lines ),
			$this->get_command(),
			false,
			false,
			[],
			[ '/status', '/help', '/site info' ]
		);
	}
}

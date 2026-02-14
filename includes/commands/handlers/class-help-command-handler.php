<?php
/**
 * /help command handler.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Commands\Handlers;

use ClawPress\Commands\Command_Handler;
use ClawPress\Commands\Command_Registry;
use ClawPress\Commands\Command_Request;
use ClawPress\Commands\Command_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Help command.
 */
final class Help_Command_Handler implements Command_Handler {
	/**
	 * Registry reference.
	 *
	 * @var Command_Registry
	 */
	private Command_Registry $registry;

	/**
	 * Constructor.
	 *
	 * @param Command_Registry $registry Command registry.
	 */
	public function __construct( Command_Registry $registry ) {
		$this->registry = $registry;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_command(): string {
		return '/help';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return 'List available offline commands.';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_usage(): string {
		return '/help';
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_destructive(): bool {
		return false;
	}

	/**
	 * Build deterministic help text.
	 */
	public function build_help_text(): string {
		$lines = [
			'Available commands:',
		];

		foreach ( $this->registry->get_registered_handlers() as $handler ) {
			$lines[] = sprintf( '- %s: %s', $handler->get_usage(), $handler->get_description() );
		}

		$lines[] = 'Tip: `/memory clear` requires confirmation and will return a tokenized re-run command.';

		return implode( "\n", $lines );
	}

	/**
	 * {@inheritDoc}
	 */
	public function handle( Command_Request $request ): Command_Response {
		unset( $request );

		return Command_Response::success( $this->build_help_text(), $this->get_command() );
	}
}

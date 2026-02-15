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
		return __( 'List available offline commands.', 'clawpress' );
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
	 * {@inheritDoc}
	 */
	public function get_default_suggestions(): array {
		return [ '/help' ];
	}

	/**
	 * Build deterministic help text.
	 */
	public function build_help_text(): string {
		$lines = [
			__( 'Available commands:', 'clawpress' ),
		];

		foreach ( $this->registry->get_visible_handlers() as $handler ) {
			$lines[] = sprintf(
				/* translators: 1: command usage, 2: command description */
				__( '- %1$s: %2$s', 'clawpress' ),
				$handler->get_usage(),
				$handler->get_description()
			);
		}

		return implode( "\n", $lines );
	}

	/**
	 * Default help suggestions.
	 *
	 * @return array<int,string>
	 */
	public function get_help_suggestions(): array {
		return [
			'/status',
			'/clear',
			'/setup resume',
			'/memory list',
			'/site info',
			'/tools list',
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public function handle( Command_Request $request ): Command_Response {
		unset( $request );

		return Command_Response::success(
			$this->build_help_text(),
			$this->get_command(),
			false,
			false,
			[],
			$this->get_help_suggestions()
		);
	}
}

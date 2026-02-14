<?php
/**
 * Command handler contract.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Commands;

defined( 'ABSPATH' ) || exit;

/**
 * Shared command handler interface.
 */
interface Command_Handler {
	/**
	 * Command token (e.g. `/help`).
	 */
	public function get_command(): string;

	/**
	 * One-line help description.
	 */
	public function get_description(): string;

	/**
	 * Usage guidance.
	 */
	public function get_usage(): string;

	/**
	 * Whether this command can perform destructive actions.
	 */
	public function is_destructive(): bool;

	/**
	 * Default suggestions this command contributes in offline mode.
	 *
	 * @return array<int,string>
	 */
	public function get_default_suggestions(): array;

	/**
	 * Execute the command.
	 *
	 * @param Command_Request $request Parsed request.
	 */
	public function handle( Command_Request $request ): Command_Response;
}

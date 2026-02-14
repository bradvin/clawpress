<?php
/**
 * Command registry.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Commands;

defined( 'ABSPATH' ) || exit;

/**
 * Stores command handlers and metadata.
 */
final class Command_Registry {
	/**
	 * Command entry map.
	 *
	 * @var array<string,array{handler:Command_Handler,destructive:bool,visible:bool}>
	 */
	private array $entries = [];

	/**
	 * Register a command handler.
	 *
	 * @param Command_Handler $handler Command handler.
	 * @param bool            $is_destructive Whether command is destructive.
	 * @param bool            $is_visible Whether command should appear in help text.
	 */
	public function register( Command_Handler $handler, bool $is_destructive = false, bool $is_visible = true ): void {
		$this->entries[ strtolower( $handler->get_command() ) ] = [
			'handler'     => $handler,
			'destructive' => $is_destructive,
			'visible'     => $is_visible,
		];
	}

	/**
	 * Resolve a command handler.
	 *
	 * @param string $command Command token.
	 */
	public function get_handler( string $command ): ?Command_Handler {
		$command = strtolower( trim( $command ) );
		if ( '' === $command ) {
			return null;
		}

		return $this->entries[ $command ]['handler'] ?? null;
	}

	/**
	 * Determine whether command is registered as destructive.
	 *
	 * @param string $command Command token.
	 */
	public function is_destructive( string $command ): bool {
		$command = strtolower( trim( $command ) );
		if ( '' === $command || ! isset( $this->entries[ $command ] ) ) {
			return false;
		}

		return (bool) $this->entries[ $command ]['destructive'];
	}

	/**
	 * Return all registered handlers sorted by command.
	 *
	 * @return array<string,Command_Handler>
	 */
	public function get_registered_handlers(): array {
		$handlers = [];

		foreach ( $this->entries as $command => $entry ) {
			$handlers[ $command ] = $entry['handler'];
		}

		ksort( $handlers );
		return $handlers;
	}

	/**
	 * Return visible command handlers sorted by command.
	 *
	 * @return array<string,Command_Handler>
	 */
	public function get_visible_handlers(): array {
		$handlers = [];

		foreach ( $this->entries as $command => $entry ) {
			if ( ! $entry['visible'] ) {
				continue;
			}

			$handlers[ $command ] = $entry['handler'];
		}

		ksort( $handlers );
		return $handlers;
	}
}

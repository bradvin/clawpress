<?php
/**
 * Offline command parser and dispatcher.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Commands;

use ClawPress\Commands\Handlers\Clear_Command_Handler;
use ClawPress\Commands\Handlers\Help_Command_Handler;
use ClawPress\Commands\Handlers\Memory_Command_Handler;
use ClawPress\Commands\Handlers\Onboarding_Command_Handler;
use ClawPress\Commands\Handlers\Site_Command_Handler;
use ClawPress\Commands\Handlers\Status_Command_Handler;
use ClawPress\Commands\Handlers\Tools_Command_Handler;
use ClawPress\Helpers\Chat_History_Helper;
use ClawPress\Helpers\Settings_Helper;
use ClawPress\Helpers\Status_Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Command module.
 */
final class Commands {
	/**
	 * Command registry.
	 *
	 * @var Command_Registry
	 */
	private Command_Registry $registry;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->registry = new Command_Registry();

		$settings_helper    = Settings_Helper::get_instance();
		$status_helper      = Status_Helper::get_instance();
		$history_helper     = Chat_History_Helper::get_instance();
		$confirmation_store = new Command_Confirmation_Store();

		$this->registry->register( new Help_Command_Handler( $this->registry ), false );
		$this->registry->register( new Status_Command_Handler( $status_helper ), false );
		$this->registry->register( new Onboarding_Command_Handler( $settings_helper ), false );
		$this->registry->register( new Memory_Command_Handler( $settings_helper, $confirmation_store ), true );
		$this->registry->register( new Clear_Command_Handler( $history_helper ), true );
		$this->registry->register( new Site_Command_Handler(), false );
		$this->registry->register( new Tools_Command_Handler( $status_helper ), false );
	}

	/**
	 * Parse and dispatch a command from a raw message.
	 *
	 * @param string $message Incoming chat message.
	 * @return array<string,mixed>|null
	 */
	public function maybe_dispatch( string $message ): ?array {
		$request = Command_Request::from_message( $message );
		if ( null === $request ) {
			return null;
		}

		$response = $this->dispatch( $request );
		$reply    = clawpress_sanitize_multiline_text( $response->get_text() );

		return [
			'reply'       => $reply,
			'mode'        => 'offline',
			'provider'    => null,
			'model'       => null,
			'suggestions' => $response->get_suggestions(),
			'card'        => $response->get_card(),
			'command'     => [
				'name'                  => $response->get_command(),
				'error'                 => $response->is_error(),
				'destructive'           => $response->is_destructive(),
				'requires_confirmation' => $response->requires_confirmation(),
				'effects'               => $response->get_effects(),
			],
		];
	}

	/**
	 * Dispatch parsed command request.
	 *
	 * @param Command_Request $request Parsed request.
	 */
	private function dispatch( Command_Request $request ): Command_Response {
		$handler = $this->registry->get_handler( $request->get_command() );
		if ( null === $handler ) {
			return Command_Response::error(
				sprintf(
					/* translators: 1: command name, 2: help text */
					__( "Unknown command: `%1\$s`.\n\n%2\$s", 'clawpress' ),
					$request->get_command(),
					$this->get_help_text()
				),
				'/help',
				false,
				false,
				[],
				[ '/help' ]
			);
		}

		$response = $handler->handle( $request );
		if ( ! $response->is_error() || '/help' === $request->get_command() ) {
			return $response;
		}

		return Command_Response::error(
			$response->get_text(),
			$response->get_command(),
			$response->is_destructive(),
			$response->requires_confirmation(),
			$response->get_effects(),
			[] !== $response->get_suggestions()
				? $response->get_suggestions()
				: $this->get_help_suggestions()
		);
	}

	/**
	 * Build help text fallback.
	 */
	private function get_help_text(): string {
		return __( 'Use `/help` to view available commands.', 'clawpress' );
	}

	/**
	 * Resolve help suggestions fallback.
	 *
	 * @return array<int,string>
	 */
	private function get_help_suggestions(): array {
		$handler = $this->registry->get_handler( '/help' );
		if ( $handler instanceof Help_Command_Handler ) {
			return $handler->get_help_suggestions();
		}

		return [];
	}

	/**
	 * Resolve default offline suggestions from registered handlers.
	 *
	 * @return array<int,string>
	 */
	public function get_default_suggestions(): array {
		$suggestions = [];

		foreach ( $this->registry->get_registered_handlers() as $handler ) {
			$suggestions = array_merge( $suggestions, $handler->get_default_suggestions() );
		}

		$seen               = [];
		$unique_suggestions = [];

		foreach ( $suggestions as $suggestion ) {
			$normalized = trim( (string) $suggestion );
			if ( '' === $normalized || isset( $seen[ $normalized ] ) ) {
				continue;
			}

			$seen[ $normalized ]  = true;
			$unique_suggestions[] = $normalized;
		}

		return array_values( $unique_suggestions );
	}
}

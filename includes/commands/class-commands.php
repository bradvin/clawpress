<?php
/**
 * Offline command parser and dispatcher.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Commands;

use ClawPress\Commands\Handlers\Help_Command_Handler;
use ClawPress\Commands\Handlers\Memory_Command_Handler;
use ClawPress\Commands\Handlers\Onboarding_Command_Handler;
use ClawPress\Commands\Handlers\Site_Command_Handler;
use ClawPress\Commands\Handlers\Status_Command_Handler;
use ClawPress\Commands\Handlers\Tools_Command_Handler;
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
		$confirmation_store = new Command_Confirmation_Store();

		$this->registry->register( new Help_Command_Handler( $this->registry ), false );
		$this->registry->register( new Status_Command_Handler( $status_helper ), false );
		$this->registry->register( new Onboarding_Command_Handler( $settings_helper ), false );
		$this->registry->register( new Memory_Command_Handler( $settings_helper, $confirmation_store ), true );
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
			'reply'    => $reply,
			'mode'     => 'offline',
			'provider' => null,
			'model'    => null,
			'command'  => [
				'name'                  => $response->get_command(),
				'error'                 => $response->is_error(),
				'destructive'           => $response->is_destructive(),
				'requires_confirmation' => $response->requires_confirmation(),
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
				sprintf( "Unknown command: `%s`.\n\n%s", $request->get_command(), $this->get_help_text() ),
				'/help'
			);
		}

		$response = $handler->handle( $request );
		if ( ! $response->is_error() || '/help' === $request->get_command() ) {
			return $response;
		}

		return Command_Response::error(
			trim( $response->get_text() ) . "\n\n" . $this->get_help_text(),
			$response->get_command(),
			$response->is_destructive(),
			$response->requires_confirmation()
		);
	}

	/**
	 * Build help text fallback.
	 */
	private function get_help_text(): string {
		$handler = $this->registry->get_handler( '/help' );
		if ( $handler instanceof Help_Command_Handler ) {
			return $handler->build_help_text();
		}

		return 'Use `/help` to view available commands.';
	}
}

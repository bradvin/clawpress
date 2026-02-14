<?php
/**
 * /status command handler.
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
 * Status command.
 */
final class Status_Command_Handler implements Command_Handler {
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
		return '/status';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return 'Show plugin, provider, memory, and permissions status.';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_usage(): string {
		return '/status';
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
		if ( '' !== $request->get_argument( 0 ) ) {
			return Command_Response::error(
				sprintf( 'Invalid usage. Expected: `%s`', $this->get_usage() ),
				$this->get_command()
			);
		}

		$status = $this->status_helper->get_current_status();
		$lines  = [
			'ClawPress status:',
			sprintf( '- Mode: %s', ucfirst( (string) ( $status['mode'] ?? 'offline' ) ) ),
			sprintf( '- Provider: %s', (string) ( $status['provider']['id'] ?? 'not configured' ) ),
			sprintf( '- Model: %s', (string) ( $status['model']['id'] ?? 'not configured' ) ),
			sprintf( '- Memory enabled: %s', ! empty( $status['memory']['enabled'] ) ? 'yes' : 'no' ),
			sprintf( '- Execution user configured: %s', ! empty( $status['execution_user']['configured'] ) ? 'yes' : 'no' ),
			sprintf( '- Onboarding completed: %s', ! empty( $status['onboarding']['completed'] ) ? 'yes' : 'no' ),
			sprintf( '- Permissions (manage_options): %s', ! empty( $status['permissions']['can_manage_options'] ) ? 'yes' : 'no' ),
			sprintf( '- Plugin version: %s', (string) ( $status['plugin']['version'] ?? 'unknown' ) ),
		];

		return Command_Response::success( implode( "\n", $lines ), $this->get_command() );
	}
}

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
		return __( 'Show plugin, provider, memory, and permissions status.', 'clawpress' );
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
	public function get_default_suggestions(): array {
		return [ '/status' ];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Command_Request $request Command request.
	 */
	public function handle( Command_Request $request ): Command_Response {
		if ( '' !== $request->get_argument( 0 ) ) {
			return Command_Response::error(
				__( 'Invalid usage. No arguments expected.', 'clawpress' ),
				$this->get_command(),
				false,
				false,
				[],
				[ '/status' ]
			);
		}

		$status      = $this->status_helper->get_current_status();
		$mode        = (string) ( $status['mode'] ?? 'offline' );
		$mode        = 'online' === $mode ? __( 'Online', 'clawpress' ) : __( 'Offline', 'clawpress' );
		$yes         = __( 'yes', 'clawpress' );
		$no          = __( 'no', 'clawpress' );
		$empty       = __( 'not configured', 'clawpress' );
		$lines       = [
			__( 'ClawPress status:', 'clawpress' ),
			sprintf(
				/* translators: %s: mode label */
				__( '- Mode: %s', 'clawpress' ),
				$mode
			),
			sprintf(
				/* translators: %s: provider identifier */
				__( '- Provider: %s', 'clawpress' ),
				(string) ( $status['provider']['id'] ?? $empty )
			),
			sprintf(
				/* translators: %s: model identifier */
				__( '- Model: %s', 'clawpress' ),
				(string) ( $status['model']['id'] ?? $empty )
			),
			sprintf(
				/* translators: %s: yes/no memory state */
				__( '- Memory enabled: %s', 'clawpress' ),
				! empty( $status['memory']['enabled'] ) ? $yes : $no
			),
			sprintf(
				/* translators: %s: yes/no agent user state */
				__( '- Agent user configured: %s', 'clawpress' ),
				! empty( $status['agent_user']['configured'] ) ? $yes : $no
			),
			sprintf(
				/* translators: %s: yes/no setup completion state */
				__( '- Setup completed: %s', 'clawpress' ),
				! empty( $status['setup']['completed'] ) ? $yes : $no
			),
		];
		$suggestions = 'offline' === (string) ( $status['mode'] ?? 'offline' )
			? [ '/help', '/setup resume', '/site info', '/tools list', '/clear' ]
			: [ '/help', '/site info', '/tools list' ];

		return Command_Response::success(
			implode( "\n", $lines ),
			$this->get_command(),
			false,
			false,
			[],
			$suggestions
		);
	}
}

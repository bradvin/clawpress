<?php
/**
 * /clear command handler.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Commands\Handlers;

use ClawPress\Commands\Command_Handler;
use ClawPress\Commands\Command_Request;
use ClawPress\Commands\Command_Response;
use ClawPress\Helpers\Chat_History_Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Clear current user's chat history.
 */
final class Clear_Command_Handler implements Command_Handler {
	/**
	 * Chat history helper.
	 *
	 * @var Chat_History_Helper
	 */
	private Chat_History_Helper $history_helper;

	/**
	 * Constructor.
	 *
	 * @param Chat_History_Helper $history_helper Chat history helper.
	 */
	public function __construct( Chat_History_Helper $history_helper ) {
		$this->history_helper = $history_helper;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_command(): string {
		return '/clear';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return __( 'Clear your current chat history.', 'clawpress' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_usage(): string {
		return '/clear';
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_destructive(): bool {
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_default_suggestions(): array {
		return [ '/clear' ];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Command_Request $request Command request.
	 */
	public function handle( Command_Request $request ): Command_Response {
		if ( '' !== $request->get_argument( 0 ) ) {
			return Command_Response::error(
				sprintf(
					/* translators: %s: expected command usage */
					__( 'Invalid usage. Expected: `%s`', 'clawpress' ),
					$this->get_usage()
				),
				$this->get_command(),
				$this->is_destructive(),
				false,
				[],
				[
					'/clear',
					'/help',
					'/status',
				]
			);
		}

		$this->history_helper->clear_history_items();

		return Command_Response::success(
			__( 'Chat history cleared.', 'clawpress' ),
			$this->get_command(),
			$this->is_destructive(),
			false,
			[
				'clear_history' => true,
			],
			[
				'/help',
				'/status',
				'/site info',
			]
		);
	}
}

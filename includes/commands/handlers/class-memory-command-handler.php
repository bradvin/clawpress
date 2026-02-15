<?php
/**
 * /memory command handler.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Commands\Handlers;

use ClawPress\Commands\Command_Confirmation_Store;
use ClawPress\Commands\Command_Handler;
use ClawPress\Commands\Command_Request;
use ClawPress\Commands\Command_Response;
use ClawPress\Helpers\Memory_Helper;
use ClawPress\Helpers\Settings_Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Memory command.
 */
final class Memory_Command_Handler implements Command_Handler {
	/**
	 * Maximum number of items shown in list output.
	 */
	private const MEMORY_LIST_LIMIT = 20;

	/**
	 * Settings helper.
	 *
	 * @var Settings_Helper
	 */
	private Settings_Helper $settings_helper;

	/**
	 * Confirmation token store.
	 *
	 * @var Command_Confirmation_Store
	 */
	private Command_Confirmation_Store $confirmation_store;

	/**
	 * Memory helper.
	 *
	 * @var Memory_Helper
	 */
	private Memory_Helper $memory_helper;

	/**
	 * Constructor.
	 *
	 * @param Settings_Helper            $settings_helper Settings helper.
	 * @param Command_Confirmation_Store $confirmation_store Confirmation store.
	 * @param Memory_Helper              $memory_helper Memory helper.
	 */
	public function __construct(
		Settings_Helper $settings_helper,
		Command_Confirmation_Store $confirmation_store,
		Memory_Helper $memory_helper
	) {
		$this->settings_helper    = $settings_helper;
		$this->confirmation_store = $confirmation_store;
		$this->memory_helper      = $memory_helper;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_command(): string {
		return '/memory';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return __( 'List memory entries or clear memory with confirmation.', 'clawpress' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_usage(): string {
		return '/memory list|clear';
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
		return [ '/memory list' ];
	}

	/**
	 * Dispatch a memory command action.
	 *
	 * @param Command_Request $request Parsed command request.
	 */
	public function handle( Command_Request $request ): Command_Response {
		$action = strtolower( $request->get_argument( 0 ) );
		if ( '' === $action ) {
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
				[ '/memory list', '/help', '/status' ]
			);
		}

		switch ( $action ) {
			case 'list':
				return $this->list_memory();
			case 'clear':
				return $this->clear_memory( $request );
			default:
				return Command_Response::error(
					sprintf(
							/* translators: %s: expected command usage */
						__( 'Invalid memory action. Expected: `%s`', 'clawpress' ),
						$this->get_usage()
					),
					$this->get_command(),
					$this->is_destructive(),
					false,
					[],
					[ '/memory list', '/help', '/status' ]
				);
		}
	}

	/**
	 * Return deterministic memory list output.
	 */
	private function list_memory(): Command_Response {
		$settings = $this->settings_helper->get_settings();
		if ( ! $this->settings_helper->get_memory_enabled( $settings ) ) {
			return Command_Response::success(
				__( 'Memory is disabled. Enable memory in settings to store and list entries.', 'clawpress' ),
				$this->get_command(),
				false,
				false,
				[],
				[ '/status', '/help' ]
			);
		}

		$memory_rows = $this->memory_helper->list_memories( self::MEMORY_LIST_LIMIT );
		if ( [] === $memory_rows ) {
			return Command_Response::success(
				__( 'No memory entries found.', 'clawpress' ),
				$this->get_command(),
				false,
				false,
				[],
				[ '/memory clear', '/status', '/help' ]
			);
		}

		$lines = [
			sprintf(
				/* translators: %d: number of memory entries */
				__( 'Memory entries (%d):', 'clawpress' ),
				count( $memory_rows )
			),
		];

		foreach ( $memory_rows as $index => $memory_row ) {
			$filename = isset( $memory_row['filename'] ) ? (string) $memory_row['filename'] : '';
			$content  = isset( $memory_row['content'] ) ? trim( (string) $memory_row['content'] ) : '';
			$content  = str_replace( [ "\r\n", "\r", "\n" ], ' ', $content );
			if ( strlen( $content ) > 120 ) {
				$content = substr( $content, 0, 117 ) . '...';
			}

			if ( '' === $filename ) {
				$lines[] = sprintf( '%d. %s', $index + 1, $content );
				continue;
			}

			$lines[] = sprintf( '%d. %s: %s', $index + 1, $filename, $content );
		}

		return Command_Response::success(
			implode( "\n", $lines ),
			$this->get_command(),
			false,
			false,
			[],
			[ '/memory clear', '/status', '/help' ]
		);
	}

	/**
	 * Clear memory entries with server-side confirmation.
	 *
	 * @param Command_Request $request Parsed command request.
	 */
	private function clear_memory( Command_Request $request ): Command_Response {
		$settings = $this->settings_helper->get_settings();
		if ( ! $this->settings_helper->get_memory_enabled( $settings ) ) {
			return Command_Response::success(
				__( 'Memory is disabled, so there is nothing to clear.', 'clawpress' ),
				$this->get_command(),
				$this->is_destructive(),
				false,
				[],
				[ '/memory list', '/status', '/help' ]
			);
		}

		if ( $this->settings_helper->resolve_agent_user_id( $settings ) <= 0 ) {
			return Command_Response::error(
				__( 'Setup required: configure an agent user before running `/memory clear`.', 'clawpress' ),
				$this->get_command(),
				$this->is_destructive(),
				false,
				[],
				[ '/setup resume', '/status', '/help' ]
			);
		}

		$confirmation_token = $this->extract_confirmation_token( $request );
		if ( ! $this->confirmation_store->consume_confirmation( 'memory.clear', $confirmation_token ) ) {
			$issued_confirmation = $this->confirmation_store->issue_confirmation( 'memory.clear' );

			return Command_Response::success(
				sprintf(
					/* translators: %s: confirmation command to rerun */
					__( 'Confirmation required. Re-run `%s` within 5 minutes to clear memory.', 'clawpress' ),
					'/memory clear --confirm=' . $issued_confirmation['token']
				),
				$this->get_command(),
				$this->is_destructive(),
				true,
				[],
				[
					'/memory clear --confirm=' . $issued_confirmation['token'],
					'/memory list',
					'/help',
				]
			);
		}

		$this->memory_helper->clear_memories();

		return Command_Response::success(
			__( 'Memory cleared.', 'clawpress' ),
			$this->get_command(),
			$this->is_destructive(),
			false,
			[],
			[ '/memory list', '/status', '/help' ]
		);
	}

	/**
	 * Resolve confirmation token from known argument patterns.
	 *
	 * @param Command_Request $request Parsed command request.
	 */
	private function extract_confirmation_token( Command_Request $request ): ?string {
		$option_token = $request->get_option_value( 'confirm' );
		if ( null !== $option_token ) {
			return $option_token;
		}

		$second_argument = strtolower( $request->get_argument( 1 ) );
		if ( 'confirm' === $second_argument ) {
			$token = trim( $request->get_argument( 2 ) );
			return '' !== $token ? $token : null;
		}

		return null;
	}
}

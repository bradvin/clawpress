<?php
/**
 * /create-agent-files command handler.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Commands\Handlers;

use ClawPress\Commands\Command_Handler;
use ClawPress\Commands\Command_Request;
use ClawPress\Commands\Command_Response;
use ClawPress\Helpers\Agent_File_Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Hidden command to bootstrap agent file posts from templates.
 */
final class Create_Agent_Files_Command_Handler implements Command_Handler {
	/**
	 * Agent file helper.
	 *
	 * @var Agent_File_Helper
	 */
	private Agent_File_Helper $agent_file_helper;

	/**
	 * Constructor.
	 *
	 * @param Agent_File_Helper $agent_file_helper Agent file helper.
	 */
	public function __construct( Agent_File_Helper $agent_file_helper ) {
		$this->agent_file_helper = $agent_file_helper;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_command(): string {
		return '/create-agent-files';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return __( 'Create default agent-file posts from templates.', 'clawpress' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_usage(): string {
		return '/create-agent-files';
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
		return [];
	}

	/**
	 * {@inheritDoc}
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
				false,
				false,
				[],
				[ '/status', '/help' ]
			);
		}

		$result = $this->agent_file_helper->create_default_agent_files_from_templates();
		$created_count = isset( $result['created'] ) && is_array( $result['created'] ) ? count( $result['created'] ) : 0;
		$skipped_count = isset( $result['skipped'] ) && is_array( $result['skipped'] ) ? count( $result['skipped'] ) : 0;
		$error_count   = isset( $result['errors'] ) && is_array( $result['errors'] ) ? count( $result['errors'] ) : 0;

		if ( $error_count > 0 ) {
			$first_error = isset( $result['errors'][0]['error'] ) ? sanitize_text_field( (string) $result['errors'][0]['error'] ) : __( 'unknown error', 'clawpress' );

			return Command_Response::error(
				sprintf(
					/* translators: 1: created count, 2: skipped count, 3: error count, 4: first error detail */
					__( 'Agent file bootstrap failed. Created: %1$d, Skipped: %2$d, Errors: %3$d. First error: %4$s', 'clawpress' ),
					$created_count,
					$skipped_count,
					$error_count,
					$first_error
				),
				$this->get_command(),
				false,
				false,
				[],
				[ '/status', '/help' ]
			);
		}

		return Command_Response::success(
			sprintf(
				/* translators: 1: created count, 2: skipped count */
				__( 'Agent files ready. Created: %1$d, Skipped: %2$d.', 'clawpress' ),
				$created_count,
				$skipped_count
			),
			$this->get_command(),
			false,
			false,
			[],
			[ '/status', '/help' ]
		);
	}
}

<?php
/**
 * /reset command handler.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Commands\Handlers;

use ClawPress\Commands\Command_Handler;
use ClawPress\Commands\Command_Request;
use ClawPress\Commands\Command_Response;
use ClawPress\Helpers\Workspace_Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Reset all ClawPress user meta for the current user.
 */
final class Reset_Command_Handler implements Command_Handler {
	/**
	 * ClawPress meta keys preserved during reset.
	 *
	 * @var array<int,string>
	 */
	private const PRESERVED_META_KEYS = [
		Workspace_Helper::USER_META_WORKSPACE_HASH,
	];

	/**
	 * {@inheritDoc}
	 */
	public function get_command(): string {
		return '/reset';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return __( 'Reset all stored ClawPress user meta.', 'clawpress' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_usage(): string {
		return '/reset';
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
				$this->is_destructive(),
				false,
				[],
				[ '/reset', '/help', '/status' ]
			);
		}

		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return Command_Response::error(
				__( 'Unable to resolve current user.', 'clawpress' ),
				$this->get_command(),
				$this->is_destructive(),
				false,
				[],
				[ '/help', '/status' ]
			);
		}

		$all_meta = get_user_meta( $user_id );
		if ( ! is_array( $all_meta ) ) {
			$all_meta = [];
		}

		$deleted_count = 0;

		foreach ( array_keys( $all_meta ) as $meta_key ) {
			if ( ! is_string( $meta_key ) || 0 !== strpos( $meta_key, 'clawpress_' ) ) {
				continue;
			}

			if ( in_array( $meta_key, self::PRESERVED_META_KEYS, true ) ) {
				continue;
			}

			if ( delete_user_meta( $user_id, $meta_key ) ) {
				++$deleted_count;
			}
		}

		return Command_Response::success(
			sprintf(
				/* translators: %d: number of user meta keys removed */
				__( 'Reset complete. Removed %d ClawPress user meta entries.', 'clawpress' ),
				$deleted_count
			),
			$this->get_command(),
			$this->is_destructive(),
			false,
			[],
			[ '/status', '/help' ]
		);
	}
}

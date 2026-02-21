<?php
/**
 * /site command handler.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Commands\Handlers;

use ClawPress\Commands\Command_Handler;
use ClawPress\Commands\Command_Request;
use ClawPress\Commands\Command_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Site info command.
 */
final class Site_Command_Handler implements Command_Handler {
	/**
	 * {@inheritDoc}
	 */
	public function get_command(): string {
		return '/site';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return __( 'Show site name, URL, WordPress version, and plugin version.', 'clawpress' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_usage(): string {
		return '/site info';
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
		return [ '/site info' ];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Command_Request $request Command request.
	 */
	public function handle( Command_Request $request ): Command_Response {
		$subcommand = strtolower( $request->get_argument( 0 ) );
		if ( 'info' !== $subcommand ) {
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
				[ '/site info', '/status', '/help' ]
			);
		}

		$site_name      = function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'name' ) : __( 'WordPress Site', 'clawpress' );
		$site_url       = function_exists( 'home_url' ) ? (string) home_url( '/' ) : '';
		$wp_version     = function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'version' ) : __( 'unknown', 'clawpress' );
		$plugin_version = defined( 'CLAWPRESS_VERSION' ) ? (string) CLAWPRESS_VERSION : __( 'unknown', 'clawpress' );
		$unknown_value  = __( 'unknown', 'clawpress' );

		$lines = [
			__( 'Site info:', 'clawpress' ),
			sprintf(
				/* translators: %s: site name */
				__( '- Name: %s', 'clawpress' ),
				'' !== $site_name ? $site_name : $unknown_value
			),
			sprintf(
				/* translators: %s: site URL */
				__( '- URL: %s', 'clawpress' ),
				'' !== $site_url ? $site_url : $unknown_value
			),
			sprintf(
				/* translators: %s: WordPress version */
				__( '- WordPress: %s', 'clawpress' ),
				'' !== $wp_version ? $wp_version : $unknown_value
			),
			sprintf(
				/* translators: %s: ClawPress plugin version */
				__( '- ClawPress: %s', 'clawpress' ),
				'' !== $plugin_version ? $plugin_version : $unknown_value
			),
		];

		return Command_Response::success(
			implode( "\n", $lines ),
			$this->get_command(),
			false,
			false,
			[],
			[ '/status', '/tools list', '/help' ]
		);
	}
}

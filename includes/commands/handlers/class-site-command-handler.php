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
		return 'Show site name, URL, WordPress version, and plugin version.';
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
	 */
	public function handle( Command_Request $request ): Command_Response {
		$subcommand = strtolower( $request->get_argument( 0 ) );
		if ( 'info' !== $subcommand ) {
			return Command_Response::error(
				sprintf( 'Invalid usage. Expected: `%s`', $this->get_usage() ),
				$this->get_command(),
				false,
				false,
				[],
				[ '/site info', '/status', '/help' ]
			);
		}

		$site_name      = function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'name' ) : 'WordPress Site';
		$site_url       = function_exists( 'home_url' ) ? (string) home_url( '/' ) : '';
		$wp_version     = function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'version' ) : 'unknown';
		$plugin_version = defined( 'CLAWPRESS_VERSION' ) ? (string) CLAWPRESS_VERSION : 'unknown';

		$lines = [
			'Site info:',
			sprintf( '- Name: %s', '' !== $site_name ? $site_name : 'unknown' ),
			sprintf( '- URL: %s', '' !== $site_url ? $site_url : 'unknown' ),
			sprintf( '- WordPress: %s', '' !== $wp_version ? $wp_version : 'unknown' ),
			sprintf( '- ClawPress: %s', '' !== $plugin_version ? $plugin_version : 'unknown' ),
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

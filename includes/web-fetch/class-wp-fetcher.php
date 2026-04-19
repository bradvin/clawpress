<?php
/**
 * Default WordPress HTTP API fetcher.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\WebFetch;

defined( 'ABSPATH' ) || exit;

/**
 * Built-in fetcher using WordPress HTTP functions.
 */
final class WP_Fetcher implements Fetcher_Interface {
	/**
	 * Get fetcher slug.
	 */
	public function get_slug(): string {
		return 'wp';
	}

	/**
	 * Execute a fetch request through the WordPress HTTP API.
	 *
	 * @param array<string,mixed> $request Normalized request payload.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function fetch( array $request ) {
		$response = wp_remote_request(
			(string) $request['url'],
			[
				'method'      => (string) $request['method'],
				'timeout'     => (int) $request['timeout'],
				'redirection' => (int) $request['redirection'],
				'headers'     => isset( $request['headers'] ) && is_array( $request['headers'] )
					? $request['headers']
					: [],
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return [
			'status_code'    => (int) wp_remote_retrieve_response_code( $response ),
			'status_message' => (string) wp_remote_retrieve_response_message( $response ),
			'headers'        => wp_remote_retrieve_headers( $response ),
			'body'           => (string) wp_remote_retrieve_body( $response ),
		];
	}
}

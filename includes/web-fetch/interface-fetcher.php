<?php
/**
 * Web fetcher interface.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\WebFetch;

defined( 'ABSPATH' ) || exit;

/**
 * Contract for configurable web fetch providers.
 */
interface Fetcher_Interface {
	/**
	 * Get fetcher slug.
	 */
	public function get_slug(): string;

	/**
	 * Execute one normalized fetch request.
	 *
	 * @param array<string,mixed> $request Normalized request payload.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function fetch( array $request );
}

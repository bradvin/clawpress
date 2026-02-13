<?php
/**
 * REST route controller contract.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\RestAPI\Controllers;

defined( 'ABSPATH' ) || exit;

/**
 * Route controller interface.
 */
interface Route_Controller {
	/**
	 * Register REST routes for the controller.
	 */
	public function register_routes(): void;
}

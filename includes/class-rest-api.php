<?php
/**
 * REST API endpoints for ClawPress.
 *
 * This file provides a template for custom REST API endpoints.
 *
 * NOTE: The DataViews demo uses @wordpress/core-data with WordPress pages,
 * which uses the built-in WordPress REST API. You only need custom endpoints
 * for operations not covered by core (e.g., custom business logic, aggregations).
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\RestAPI;

use ClawPress\RestAPI\Controllers\Chat_Controller;
use ClawPress\RestAPI\Controllers\Agent_Run_Controller;
use ClawPress\RestAPI\Controllers\Panel_State_Controller;
use ClawPress\RestAPI\Controllers\Route_Controller;
use ClawPress\RestAPI\Controllers\Settings_Controller;
use ClawPress\RestAPI\Controllers\Status_Controller;

defined( 'ABSPATH' ) || exit;

/**
 * REST API module.
 */
final class Rest_API {
	/**
	 * Registered route controllers.
	 *
	 * @var array<int,Route_Controller>
	 */
	private array $controllers = [];

	/**
	 * Register all REST API hooks.
	 */
	public function __construct() {
		$this->controllers = [
			new Settings_Controller(),
			new Status_Controller(),
			new Panel_State_Controller(),
			new Chat_Controller(),
			new Agent_Run_Controller(),
		];

		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register custom REST API endpoints.
	 */
	public function register_routes(): void {
		foreach ( $this->controllers as $controller ) {
			$controller->register_routes();
		}
	}
}

<?php
/**
 * Status REST controller.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\RestAPI\Controllers;

use ClawPress\Helpers\Status_Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Status endpoints controller.
 */
final class Status_Controller implements Route_Controller {
	/**
	 * Status helper.
	 *
	 * @var Status_Helper
	 */
	private Status_Helper $status_helper;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->status_helper = Status_Helper::get_instance();
	}

	/**
	 * Register status endpoints.
	 */
	public function register_routes(): void {
		register_rest_route(
			'clawpress/v1',
			'/status',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_status' ],
				'permission_callback' => 'clawpress_check_permissions',
			]
		);
	}

	/**
	 * Return deterministic plugin status envelope.
	 */
	public function get_status(): \WP_REST_Response {
		return new \WP_REST_Response( $this->status_helper->get_current_status(), 200 );
	}
}

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

defined( 'ABSPATH' ) || exit;

/**
 * REST API module.
 */
final class Rest_API {
	/**
	 * Register all REST API hooks.
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register custom REST API endpoints.
	 */
	public function register_routes(): void {
		register_rest_route(
			'clawpress/v1',
			'/settings',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_settings' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			]
		);

		register_rest_route(
			'clawpress/v1',
			'/settings',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'update_settings' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => [
					'option_name'  => [
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					],
					'option_value' => [
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);
	}

	/**
	 * Validate endpoint permissions.
	 */
	public function permissions_check(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get plugin settings.
	 */
	public function get_settings(): \WP_REST_Response {
		$settings = [
			'clawpress_settings' => get_option( 'clawpress_settings', '' ),
		];

		return new \WP_REST_Response( $settings, 200 );
	}

	/**
	 * Update plugin settings.
	 *
	 * @param \WP_REST_Request $request The request object.
	 */
	public function update_settings( \WP_REST_Request $request ): \WP_REST_Response {
		$option_name  = $request->get_param( 'option_name' );
		$option_value = $request->get_param( 'option_value' );

		$allowed_options = [ 'clawpress_settings' ];
		if ( ! in_array( $option_name, $allowed_options, true ) ) {
			return new \WP_REST_Response(
				[ 'error' => 'Invalid option name' ],
				400
			);
		}

		update_option( $option_name, $option_value );

		return new \WP_REST_Response(
			[
				'success' => true,
				'option'  => $option_name,
				'value'   => $option_value,
			],
			200
		);
	}
}

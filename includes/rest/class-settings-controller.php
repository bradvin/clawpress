<?php
/**
 * Settings REST controller.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\RestAPI\Controllers;

use ClawPress\Helpers\Settings_Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Settings endpoints controller.
 */
final class Settings_Controller implements Route_Controller {
	/**
	 * Settings helper.
	 *
	 * @var Settings_Helper
	 */
	private Settings_Helper $settings_helper;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->settings_helper = Settings_Helper::get_instance();
	}

	/**
	 * Register settings endpoints.
	 */
	public function register_routes(): void {
		register_rest_route(
			'clawpress/v1',
			'/settings',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_settings' ],
				'permission_callback' => 'clawpress_check_permissions',
			]
		);

		register_rest_route(
			'clawpress/v1',
			'/settings',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'update_settings' ],
				'permission_callback' => 'clawpress_check_permissions',
				'args'                => [
					'provider'        => [
						'required'          => false,
						'sanitize_callback' => 'clawpress_sanitize_provider',
					],
					'model'           => [
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					],
					'request_timeout' => [
						'required'          => false,
						'validate_callback' => 'clawpress_validate_request_timeout',
						'sanitize_callback' => 'clawpress_sanitize_request_timeout',
					],
					'agent_user_id'   => [
						'required'          => false,
						'validate_callback' => 'clawpress_validate_int',
						'sanitize_callback' => 'clawpress_sanitize_int',
					],
					'memory_enabled'  => [
						'required'          => false,
						'sanitize_callback' => 'clawpress_sanitize_boolean',
					],
					'setup_completed' => [
						'required'          => false,
						'sanitize_callback' => 'clawpress_sanitize_boolean',
					],
				],
			]
		);
	}

	/**
	 * Get plugin settings.
	 */
	public function get_settings(): \WP_REST_Response {
		$settings = $this->settings_helper->get_settings();

		return new \WP_REST_Response(
			[
				'settings' => $settings,
			],
			200
		);
	}

	/**
	 * Update plugin settings.
	 *
	 * @param \WP_REST_Request $request The request object.
	 */
	public function update_settings( \WP_REST_Request $request ): \WP_REST_Response {
		$agent_user_id = $request->get_param( 'agent_user_id' );

		$result = $this->settings_helper->update_settings(
			[
				'provider'        => $request->get_param( 'provider' ),
				'model'           => $request->get_param( 'model' ),
				'request_timeout' => $request->get_param( 'request_timeout' ),
				'agent_user_id'   => $agent_user_id,
				'memory_enabled'  => $request->get_param( 'memory_enabled' ),
				'setup_completed' => $request->get_param( 'setup_completed' ),
			]
		);

		if ( isset( $result['error'] ) ) {
			return new \WP_REST_Response(
				[
					'error' => (string) $result['error'],
				],
				400
			);
		}

		return new \WP_REST_Response(
			[
				'success'  => true,
				'settings' => isset( $result['settings'] ) && is_array( $result['settings'] )
					? $result['settings']
					: $this->settings_helper->get_settings(),
			],
			200
		);
	}
}

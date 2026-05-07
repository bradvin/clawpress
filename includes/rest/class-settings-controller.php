<?php
/**
 * Settings REST controller.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\RestAPI\Controllers;

use ClawPress\Helpers\Model_Helper;
use ClawPress\Helpers\Provider_Helper;
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
	 * Model helper.
	 *
	 * @var Model_Helper
	 */
	private Model_Helper $model_helper;

	/**
	 * Provider helper.
	 *
	 * @var Provider_Helper
	 */
	private Provider_Helper $provider_helper;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->settings_helper = Settings_Helper::get_instance();
		$this->model_helper    = Model_Helper::get_instance();
		$this->provider_helper = Provider_Helper::get_instance();
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
					'provider'          => [
						'required'          => false,
						'sanitize_callback' => 'clawpress_sanitize_provider',
					],
					'model'             => [
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					],
					'temperature'       => [
						'required'          => false,
						'validate_callback' => 'clawpress_validate_temperature',
						'sanitize_callback' => 'clawpress_sanitize_temperature',
					],
					'top_p'             => [
						'required'          => false,
						'validate_callback' => 'clawpress_validate_top_p',
						'sanitize_callback' => 'clawpress_sanitize_top_p',
					],
					'max_output_tokens' => [
						'required'          => false,
						'validate_callback' => 'clawpress_validate_max_output_tokens',
						'sanitize_callback' => 'clawpress_sanitize_max_output_tokens',
					],
					'frequency_penalty' => [
						'required'          => false,
						'validate_callback' => 'clawpress_validate_frequency_penalty',
						'sanitize_callback' => 'clawpress_sanitize_frequency_penalty',
					],
					'presence_penalty'  => [
						'required'          => false,
						'validate_callback' => 'clawpress_validate_presence_penalty',
						'sanitize_callback' => 'clawpress_sanitize_presence_penalty',
					],
					'request_timeout'   => [
						'required'          => false,
						'validate_callback' => 'clawpress_validate_request_timeout',
						'sanitize_callback' => 'clawpress_sanitize_request_timeout',
					],
					'agent_user_id'     => [
						'required'          => false,
						'validate_callback' => 'clawpress_validate_int',
						'sanitize_callback' => 'clawpress_sanitize_int',
					],
					'memory_enabled'    => [
						'required'          => false,
						'sanitize_callback' => 'clawpress_sanitize_boolean',
					],
					'setup_completed'   => [
						'required'          => false,
						'sanitize_callback' => 'clawpress_sanitize_boolean',
					],
				],
			]
		);

		register_rest_route(
			'clawpress/v1',
			'/providers/(?P<provider>[a-z0-9_-]+)/models',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_provider_models' ],
				'permission_callback' => 'clawpress_check_permissions',
				'args'                => [
					'provider' => [
						'required'          => true,
						'sanitize_callback' => 'clawpress_sanitize_provider',
						'validate_callback' => static function ( $value ): bool {
							return '' !== clawpress_sanitize_provider( $value );
						},
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
				'settings'      => $settings,
				'providers'     => $this->provider_helper->get_provider_options(),
				'models'        => $this->model_helper->get_all_discovered_options(),
				'model_catalog' => $this->model_helper->get_model_catalog(),
			],
			200
		);
	}

	/**
	 * Get model options for a provider.
	 *
	 * @param \WP_REST_Request $request The request object.
	 */
	public function get_provider_models( \WP_REST_Request $request ): \WP_REST_Response {
		$provider = clawpress_sanitize_provider( $request->get_param( 'provider' ) );

		return new \WP_REST_Response(
			$this->model_helper->get_options_for_provider( $provider ),
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
				'provider'          => $request->get_param( 'provider' ),
				'model'             => $request->get_param( 'model' ),
				'temperature'       => $request->get_param( 'temperature' ),
				'top_p'             => $request->get_param( 'top_p' ),
				'max_output_tokens' => $request->get_param( 'max_output_tokens' ),
				'frequency_penalty' => $request->get_param( 'frequency_penalty' ),
				'presence_penalty'  => $request->get_param( 'presence_penalty' ),
				'request_timeout'   => $request->get_param( 'request_timeout' ),
				'agent_user_id'     => $agent_user_id,
				'memory_enabled'    => $request->get_param( 'memory_enabled' ),
				'setup_completed'   => $request->get_param( 'setup_completed' ),
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

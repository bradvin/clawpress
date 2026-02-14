<?php
/**
 * Settings REST controller.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\RestAPI\Controllers;

use ClawPress\Helpers\Onboarding_Helper;
use ClawPress\Helpers\Settings_Helper;
use ClawPress\Helpers\Status_Helper;

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
	 * Status helper.
	 *
	 * @var Status_Helper
	 */
	private Status_Helper $status_helper;

	/**
	 * Onboarding helper.
	 *
	 * @var Onboarding_Helper
	 */
	private Onboarding_Helper $onboarding_helper;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->settings_helper   = Settings_Helper::get_instance();
		$this->status_helper     = Status_Helper::get_instance();
		$this->onboarding_helper = Onboarding_Helper::get_instance();
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
					'option_name'          => [
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					],
					'option_value'         => [
						'required' => false,
					],
					'provider'             => [
						'required'          => false,
						'sanitize_callback' => 'clawpress_sanitize_provider',
					],
					'model'                => [
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					],
					'execution_user_id'    => [
						'required'          => false,
						'validate_callback' => 'clawpress_validate_int',
						'sanitize_callback' => 'clawpress_sanitize_int',
					],
					'memory_enabled'       => [
						'required'          => false,
						'sanitize_callback' => 'clawpress_sanitize_boolean',
					],
					'onboarding_completed' => [
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
		$raw_settings = $this->settings_helper->get_raw_settings_option();
		$settings     = is_array( $raw_settings ) ? $raw_settings : [];

		return new \WP_REST_Response(
			[
				Settings_Helper::SETTINGS_OPTION => $raw_settings,
				'status_settings'                => $this->get_status_settings_payload( $settings ),
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
		$option_name = $request->get_param( 'option_name' );
		if ( is_string( $option_name ) && '' !== trim( $option_name ) ) {
			$result = $this->settings_helper->update_named_setting(
				trim( $option_name ),
				$request->get_param( 'option_value' )
			);

			if ( isset( $result['error'] ) ) {
				return new \WP_REST_Response(
					[
						'error' => (string) $result['error'],
					],
					400
				);
			}

			return new \WP_REST_Response( $result, 200 );
		}

		$settings = $this->settings_helper->get_settings();

		$provider             = $request->get_param( 'provider' );
		$model                = $request->get_param( 'model' );
		$execution_user_id    = $request->get_param( 'execution_user_id' );
		$memory_enabled       = $request->get_param( 'memory_enabled' );
		$onboarding_completed = $request->get_param( 'onboarding_completed' );

		$has_status_update = null !== $provider
			|| null !== $model
			|| null !== $execution_user_id
			|| null !== $memory_enabled
			|| null !== $onboarding_completed;

		if ( ! $has_status_update ) {
			return new \WP_REST_Response(
				[ 'error' => 'No settings provided' ],
				400
			);
		}

		if ( null !== $provider ) {
			$settings['provider'] = clawpress_sanitize_provider( $provider );
		}

		if ( null !== $model ) {
			$settings['model'] = sanitize_text_field( (string) $model );
		}

		if ( null !== $execution_user_id ) {
			$sanitized_execution_user_id   = clawpress_sanitize_int( $execution_user_id );
			$settings['execution_user_id'] = $sanitized_execution_user_id;
			$this->settings_helper->set_execution_user_id_option( $sanitized_execution_user_id );
		}

		if ( null !== $memory_enabled ) {
			$this->settings_helper->set_memory_enabled( clawpress_sanitize_boolean( $memory_enabled ) );
		}

		if ( null !== $onboarding_completed ) {
			$this->onboarding_helper->set_onboarding_complete( clawpress_sanitize_boolean( $onboarding_completed ) );
		}

		$this->settings_helper->update_settings( $settings );

		return new \WP_REST_Response(
			[
				'success'         => true,
				'status_settings' => $this->get_status_settings_payload( $settings ),
			],
			200
		);
	}

	/**
	 * Build status settings payload consumed by status endpoint concerns.
	 *
	 * @param array<string,mixed> $settings Current settings array.
	 * @return array<string,mixed>
	 */
	private function get_status_settings_payload( array $settings ): array {
		return $this->status_helper->build_status_settings_payload( $settings );
	}
}

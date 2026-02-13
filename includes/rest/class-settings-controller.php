<?php
/**
 * Settings REST controller.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\RestAPI\Controllers;

defined( 'ABSPATH' ) || exit;

/**
 * Settings endpoints controller.
 */
final class Settings_Controller implements Route_Controller {
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
					'option_name'          => [
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					],
					'option_value'         => [
						'required' => false,
					],
					'provider'             => [
						'required'          => false,
						'sanitize_callback' => [ $this, 'sanitize_provider' ],
					],
					'model'                => [
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					],
					'execution_user_id'    => [
						'required'          => false,
						'validate_callback' => [ $this, 'validate_execution_user_id' ],
						'sanitize_callback' => [ $this, 'sanitize_execution_user_id' ],
					],
					'memory_enabled'       => [
						'required'          => false,
						'sanitize_callback' => [ $this, 'sanitize_boolean' ],
					],
					'onboarding_completed' => [
						'required'          => false,
						'sanitize_callback' => [ $this, 'sanitize_boolean' ],
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
		$raw_settings = get_option( 'clawpress_settings', '' );
		$settings     = is_array( $raw_settings ) ? $raw_settings : [];

		$settings = [
			'clawpress_settings' => $raw_settings,
			'status_settings'    => $this->get_status_settings_payload( $settings ),
		];

		return new \WP_REST_Response( $settings, 200 );
	}

	/**
	 * Update plugin settings.
	 *
	 * @param \WP_REST_Request $request The request object.
	 */
	public function update_settings( \WP_REST_Request $request ): \WP_REST_Response {
		$option_name = $request->get_param( 'option_name' );
		if ( is_string( $option_name ) && '' !== trim( $option_name ) ) {
			return $this->update_named_setting(
				trim( $option_name ),
				$request->get_param( 'option_value' )
			);
		}

		$current_settings = get_option( 'clawpress_settings', [] );
		$settings         = is_array( $current_settings ) ? $current_settings : [];

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
			$settings['provider'] = $this->sanitize_provider( $provider );
		}

		if ( null !== $model ) {
			$settings['model'] = sanitize_text_field( (string) $model );
		}

		if ( null !== $execution_user_id ) {
			$sanitized_execution_user_id   = $this->sanitize_execution_user_id( $execution_user_id );
			$settings['execution_user_id'] = $sanitized_execution_user_id;
			update_option( 'clawpress_execution_user_id', $sanitized_execution_user_id );
		}

		if ( null !== $memory_enabled ) {
			update_option( 'clawpress_memory_enabled', $this->sanitize_boolean( $memory_enabled ) );
		}

		if ( null !== $onboarding_completed ) {
			update_user_meta(
				get_current_user_id(),
				'clawpress_onboarding_completed',
				$this->sanitize_boolean( $onboarding_completed ) ? '1' : '0'
			);
		}

		update_option( 'clawpress_settings', $settings );

		return new \WP_REST_Response(
			[
				'success'         => true,
				'status_settings' => $this->get_status_settings_payload( $settings ),
			],
			200
		);
	}

	/**
	 * Update a legacy named setting.
	 *
	 * @param string $option_name Setting option name.
	 * @param mixed  $option_value Setting value.
	 */
	private function update_named_setting( string $option_name, $option_value ): \WP_REST_Response {
		$allowed_options = [
			'clawpress_settings',
			'clawpress_memory_enabled',
			'clawpress_execution_user_id',
			'clawpress_onboarding_completed',
		];

		if ( ! in_array( $option_name, $allowed_options, true ) ) {
			return new \WP_REST_Response(
				[ 'error' => 'Invalid option name' ],
				400
			);
		}

		if ( 'clawpress_onboarding_completed' === $option_name ) {
			$normalized = $this->sanitize_boolean( $option_value );
			update_user_meta(
				get_current_user_id(),
				'clawpress_onboarding_completed',
				$normalized ? '1' : '0'
			);

			return new \WP_REST_Response(
				[
					'success' => true,
					'option'  => $option_name,
					'value'   => $normalized,
				],
				200
			);
		}

		if ( 'clawpress_execution_user_id' === $option_name ) {
			$normalized = $this->sanitize_execution_user_id( $option_value );
			update_option( $option_name, $normalized );

			$current_settings              = get_option( 'clawpress_settings', [] );
			$settings                      = is_array( $current_settings ) ? $current_settings : [];
			$settings['execution_user_id'] = $normalized;
			update_option( 'clawpress_settings', $settings );

			return new \WP_REST_Response(
				[
					'success' => true,
					'option'  => $option_name,
					'value'   => $normalized,
				],
				200
			);
		}

		if ( 'clawpress_memory_enabled' === $option_name ) {
			$normalized = $this->sanitize_boolean( $option_value );
			update_option( $option_name, $normalized );

			return new \WP_REST_Response(
				[
					'success' => true,
					'option'  => $option_name,
					'value'   => $normalized,
				],
				200
			);
		}

		$normalized = $option_value;
		if ( is_array( $normalized ) ) {
			if ( isset( $normalized['provider'] ) ) {
				$normalized['provider'] = $this->sanitize_provider( $normalized['provider'] );
			}

			if ( isset( $normalized['model'] ) ) {
				$normalized['model'] = sanitize_text_field( (string) $normalized['model'] );
			}

			if ( isset( $normalized['execution_user_id'] ) ) {
				$normalized['execution_user_id'] = $this->sanitize_execution_user_id( $normalized['execution_user_id'] );
				update_option( 'clawpress_execution_user_id', $normalized['execution_user_id'] );
			}
		}

		update_option( $option_name, $normalized );

		return new \WP_REST_Response(
			[
				'success' => true,
				'option'  => $option_name,
				'value'   => $normalized,
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
		$execution_user_id = isset( $settings['execution_user_id'] )
			? (int) $settings['execution_user_id']
			: (int) get_option( 'clawpress_execution_user_id', 0 );

		$provider = isset( $settings['provider'] ) && is_string( $settings['provider'] )
			? $this->sanitize_provider( $settings['provider'] )
			: '';
		$model    = isset( $settings['model'] ) && is_string( $settings['model'] )
			? sanitize_text_field( $settings['model'] )
			: '';

		return [
			'provider'             => $provider,
			'model'                => $model,
			'execution_user_id'    => $execution_user_id > 0 ? $execution_user_id : 0,
			'memory_enabled'       => (bool) get_option( 'clawpress_memory_enabled', false ),
			'onboarding_completed' => $this->is_onboarding_complete(),
		];
	}

	/**
	 * Validate execution user ID.
	 *
	 * @param mixed $value Raw value.
	 */
	public function validate_execution_user_id( $value ): bool {
		return is_numeric( $value ) && (int) $value >= 0;
	}

	/**
	 * Sanitize execution user ID.
	 *
	 * @param mixed $value Raw value.
	 */
	public function sanitize_execution_user_id( $value ): int {
		$user_id = (int) $value;
		return $user_id > 0 ? $user_id : 0;
	}

	/**
	 * Sanitize provider identifier.
	 *
	 * @param mixed $value Raw value.
	 */
	public function sanitize_provider( $value ): string {
		$provider = strtolower( sanitize_text_field( (string) $value ) );
		$allowed  = [ 'openai', 'anthropic', 'google' ];

		return in_array( $provider, $allowed, true ) ? $provider : '';
	}

	/**
	 * Sanitize boolean value.
	 *
	 * @param mixed $value Raw value.
	 */
	public function sanitize_boolean( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		return in_array( strtolower( (string) $value ), [ '1', 'true', 'yes', 'on' ], true );
	}

	/**
	 * Whether onboarding is marked complete for current user.
	 */
	private function is_onboarding_complete(): bool {
		$value = get_user_meta( get_current_user_id(), 'clawpress_onboarding_completed', true );

		if ( is_bool( $value ) ) {
			return $value;
		}

		return in_array( (string) $value, [ '1', 'true', 'yes' ], true );
	}
}

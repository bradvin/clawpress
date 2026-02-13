<?php
/**
 * Status REST controller.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\RestAPI\Controllers;

defined( 'ABSPATH' ) || exit;

/**
 * Status endpoints controller.
 */
final class Status_Controller implements Route_Controller {
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
				'permission_callback' => [ $this, 'permissions_check' ],
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
	 * Return deterministic plugin status envelope.
	 */
	public function get_status(): \WP_REST_Response {
		$provider_id = $this->resolve_provider();
		$model_id    = $this->resolve_model();

		$provider_configured = '' !== $provider_id && $this->has_provider_credentials( $provider_id );
		$model_configured    = '' !== $model_id;
		$execution_user_id   = $this->resolve_execution_user_id();

		return new \WP_REST_Response(
			[
				'mode'           => $provider_configured ? 'online' : 'offline',
				'provider'       => [
					'id'         => '' !== $provider_id ? $provider_id : null,
					'configured' => $provider_configured,
				],
				'model'          => [
					'id'         => '' !== $model_id ? $model_id : null,
					'configured' => $model_configured,
				],
				'onboarding'     => [
					'completed' => $this->is_onboarding_complete(),
				],
				'memory'         => [
					'enabled' => (bool) get_option( 'clawpress_memory_enabled', false ),
				],
				'execution_user' => [
					'id'         => $execution_user_id > 0 ? $execution_user_id : null,
					'configured' => $execution_user_id > 0,
				],
			],
			200
		);
	}

	/**
	 * Resolve provider from plugin settings.
	 */
	private function resolve_provider(): string {
		$settings = $this->get_settings();
		if ( ! isset( $settings['provider'] ) || ! is_string( $settings['provider'] ) ) {
			return '';
		}

		$provider = strtolower( trim( $settings['provider'] ) );
		return isset( $this->get_provider_credentials_map()[ $provider ] ) ? $provider : '';
	}

	/**
	 * Resolve model from plugin settings.
	 */
	private function resolve_model(): string {
		$settings = $this->get_settings();
		if ( ! isset( $settings['model'] ) || ! is_string( $settings['model'] ) ) {
			return '';
		}

		return trim( $settings['model'] );
	}

	/**
	 * Resolve configured execution user ID.
	 */
	private function resolve_execution_user_id(): int {
		$settings          = $this->get_settings();
		$settings_user_id  = isset( $settings['execution_user_id'] ) ? (int) $settings['execution_user_id'] : 0;
		$option_user_id    = (int) get_option( 'clawpress_execution_user_id', 0 );
		$execution_user_id = $settings_user_id > 0 ? $settings_user_id : $option_user_id;

		return $execution_user_id > 0 ? $execution_user_id : 0;
	}

	/**
	 * Whether onboarding is marked complete for the current user.
	 */
	private function is_onboarding_complete(): bool {
		$value = get_user_meta( get_current_user_id(), 'clawpress_onboarding_completed', true );

		if ( is_bool( $value ) ) {
			return $value;
		}

		return in_array( (string) $value, [ '1', 'true', 'yes' ], true );
	}

	/**
	 * Get plugin settings.
	 *
	 * @return array<string,mixed>
	 */
	private function get_settings(): array {
		$settings = get_option( 'clawpress_settings', [] );
		return is_array( $settings ) ? $settings : [];
	}

	/**
	 * Check whether credentials exist for a provider.
	 *
	 * @param string $provider Provider ID.
	 */
	private function has_provider_credentials( string $provider ): bool {
		$credential_keys = $this->get_provider_credentials_map();
		if ( ! isset( $credential_keys[ $provider ] ) ) {
			return false;
		}

		$key       = $credential_keys[ $provider ];
		$env_value = getenv( $key );
		if ( false !== $env_value && '' !== trim( (string) $env_value ) ) {
			return true;
		}

		if ( ! defined( $key ) ) {
			return false;
		}

		$constant_value = constant( $key );
		if ( ! is_scalar( $constant_value ) ) {
			return false;
		}

		return '' !== trim( (string) $constant_value );
	}

	/**
	 * Get provider to credential-key map.
	 *
	 * @return array<string,string>
	 */
	private function get_provider_credentials_map(): array {
		return [
			'openai'    => 'OPENAI_API_KEY',
			'anthropic' => 'ANTHROPIC_API_KEY',
			'google'    => 'GOOGLE_API_KEY',
		];
	}
}

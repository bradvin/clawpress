<?php
/**
 * Panel state REST controller.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\RestAPI\Controllers;

defined( 'ABSPATH' ) || exit;

/**
 * Panel state endpoints controller.
 */
final class Panel_State_Controller implements Route_Controller {
	/**
	 * User meta key for persisted panel state.
	 */
	private const USER_META_KEY = 'clawpress_panel_state';

	/**
	 * Register panel state endpoints.
	 */
	public function register_routes(): void {
		register_rest_route(
			'clawpress/v1',
			'/panel/state',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_panel_state' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			]
		);

		register_rest_route(
			'clawpress/v1',
			'/panel/state',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'update_panel_state' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => [
					'open'            => [
						'required'          => false,
						'sanitize_callback' => [ $this, 'sanitize_boolean' ],
					],
					'width'           => [
						'required'          => false,
						'validate_callback' => [ $this, 'validate_width' ],
						'sanitize_callback' => [ $this, 'sanitize_width' ],
					],
					'last_history_id' => [
						'required'          => false,
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
	 * Return panel state for current user.
	 */
	public function get_panel_state(): \WP_REST_Response {
		$state = $this->normalize_panel_state(
			get_user_meta( get_current_user_id(), self::USER_META_KEY, true )
		);

		return new \WP_REST_Response( $state, 200 );
	}

	/**
	 * Update panel state for current user.
	 *
	 * @param \WP_REST_Request $request Request object.
	 */
	public function update_panel_state( \WP_REST_Request $request ): \WP_REST_Response {
		$state = $this->normalize_panel_state(
			get_user_meta( get_current_user_id(), self::USER_META_KEY, true )
		);

		$open = $request->get_param( 'open' );
		if ( null !== $open ) {
			$state['open'] = $this->sanitize_boolean( $open );
		}

		$width = $request->get_param( 'width' );
		if ( null !== $width ) {
			$state['width'] = $this->sanitize_width( $width );
		}

		$last_history_id = $request->get_param( 'last_history_id' );
		if ( null !== $last_history_id ) {
			$state['last_history_id'] = sanitize_text_field( (string) $last_history_id );
		}

		update_user_meta( get_current_user_id(), self::USER_META_KEY, $state );

		return new \WP_REST_Response( $state, 200 );
	}

	/**
	 * Validate width parameter.
	 *
	 * @param mixed $value Width value.
	 */
	public function validate_width( $value ): bool {
		if ( ! is_numeric( $value ) ) {
			return false;
		}

		$width = (int) $value;
		return $width >= 280 && $width <= 1200;
	}

	/**
	 * Sanitize boolean value.
	 *
	 * @param mixed $value Value.
	 */
	public function sanitize_boolean( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		return in_array( strtolower( (string) $value ), [ '1', 'true', 'yes', 'on' ], true );
	}

	/**
	 * Sanitize and clamp width value.
	 *
	 * @param mixed $value Width value.
	 */
	public function sanitize_width( $value ): int {
		$width = (int) $value;
		if ( $width < 320 ) {
			return 320;
		}
		if ( $width > 960 ) {
			return 960;
		}

		return $width;
	}

	/**
	 * Normalize persisted state with defaults.
	 *
	 * @param mixed $state Raw state value.
	 * @return array<string,mixed>
	 */
	private function normalize_panel_state( $state ): array {
		$defaults = [
			'open'            => false,
			'width'           => 420,
			'last_history_id' => '',
		];

		if ( ! is_array( $state ) ) {
			return $defaults;
		}

		return [
			'open'            => isset( $state['open'] ) ? $this->sanitize_boolean( $state['open'] ) : $defaults['open'],
			'width'           => isset( $state['width'] ) ? $this->sanitize_width( $state['width'] ) : $defaults['width'],
			'last_history_id' => isset( $state['last_history_id'] ) ? sanitize_text_field( (string) $state['last_history_id'] ) : '',
		];
	}
}

<?php
/**
 * Abilities settings REST controller.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\RestAPI\Controllers;

use ClawPress\Helpers\Abilities_Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Abilities settings endpoints controller.
 */
final class Abilities_Settings_Controller implements Route_Controller {
	/**
	 * Abilities helper.
	 *
	 * @var Abilities_Helper
	 */
	private Abilities_Helper $abilities_helper;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->abilities_helper = Abilities_Helper::get_instance();
	}

	/**
	 * Register abilities settings endpoints.
	 */
	public function register_routes(): void {
		register_rest_route(
			'clawpress/v1',
			'/settings/abilites',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_abilities_settings' ],
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
			]
		);

		register_rest_route(
			'clawpress/v1',
			'/settings/abilites',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'update_abilities_settings' ],
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'args'                => [
					'abilities' => [
						'required'          => false,
						'validate_callback' => [ $this, 'validate_abilities_payload' ],
						'sanitize_callback' => [ $this, 'sanitize_abilities_payload' ],
					],
					'reset'     => [
						'required'          => false,
						'sanitize_callback' => 'clawpress_sanitize_boolean',
					],
				],
			]
		);
	}

	/**
	 * Get abilities settings state.
	 */
	public function get_abilities_settings(): \WP_REST_Response {
		return new \WP_REST_Response(
			$this->abilities_helper->get_ability_settings_state(),
			200
		);
	}

	/**
	 * Update abilities settings.
	 *
	 * @param \WP_REST_Request $request Request object.
	 */
	public function update_abilities_settings( \WP_REST_Request $request ): \WP_REST_Response {
		$reset = clawpress_sanitize_boolean( $request->get_param( 'reset' ) );

		if ( $reset ) {
			$enabled = $this->abilities_helper->reset_enabled_ability_ids_to_defaults();
			return new \WP_REST_Response(
				[
					'success'          => true,
					'reset'            => true,
					'enabled_abilities' => $enabled,
					'state'            => $this->abilities_helper->get_ability_settings_state(),
				],
				200
			);
		}

		$abilities = $request->get_param( 'abilities' );
		if ( ! is_array( $abilities ) ) {
			return new \WP_REST_Response(
				[
					'error' => __( 'No abilities payload provided.', 'clawpress' ),
				],
				400
			);
		}

		$enabled = $this->abilities_helper->set_enabled_ability_ids( $abilities );
		return new \WP_REST_Response(
			[
				'success'           => true,
				'reset'             => false,
				'enabled_abilities' => $enabled,
				'state'             => $this->abilities_helper->get_ability_settings_state(),
			],
			200
		);
	}

	/**
	 * Validate abilities payload.
	 *
	 * @param mixed $value Request value.
	 */
	public function validate_abilities_payload( $value ): bool {
		return null === $value || is_array( $value );
	}

	/**
	 * Sanitize abilities payload.
	 *
	 * @param mixed $value Request value.
	 * @return array<int,string>
	 */
	public function sanitize_abilities_payload( $value ): array {
		if ( ! is_array( $value ) ) {
			return [];
		}

		return array_values(
			array_map(
				static fn( $ability ): string => strtolower( trim( (string) $ability ) ),
				$value
			)
		);
	}
}

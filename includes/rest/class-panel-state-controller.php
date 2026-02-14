<?php
/**
 * Panel state REST controller.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\RestAPI\Controllers;

use ClawPress\Helpers\Panel_Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Panel state endpoints controller.
 */
final class Panel_State_Controller implements Route_Controller {
	/**
	 * Panel helper.
	 *
	 * @var Panel_Helper
	 */
	private Panel_Helper $panel_helper;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->panel_helper = Panel_Helper::get_instance();
	}

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
				'permission_callback' => 'clawpress_check_permissions',
			]
		);

		register_rest_route(
			'clawpress/v1',
			'/panel/state',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'update_panel_state' ],
				'permission_callback' => 'clawpress_check_permissions',
				'args'                => [
					'open'              => [
						'required'          => false,
						'sanitize_callback' => 'clawpress_sanitize_boolean',
					],
					'width'             => [
						'required'          => false,
						'validate_callback' => [ $this, 'validate_width' ],
						'sanitize_callback' => [ $this, 'sanitize_width' ],
					],
					'last_history_id'   => [
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					],
					'welcome_card_seen' => [
						'required'          => false,
						'sanitize_callback' => 'clawpress_sanitize_boolean',
					],
				],
			]
		);
	}

	/**
	 * Return panel state for current user.
	 */
	public function get_panel_state(): \WP_REST_Response {
		return new \WP_REST_Response( $this->panel_helper->get_panel_state(), 200 );
	}

	/**
	 * Update panel state for current user.
	 *
	 * @param \WP_REST_Request $request Request object.
	 */
	public function update_panel_state( \WP_REST_Request $request ): \WP_REST_Response {
		$state_updates = [];

		$open = $request->get_param( 'open' );
		if ( null !== $open ) {
			$state_updates['open'] = $open;
		}

		$width = $request->get_param( 'width' );
		if ( null !== $width ) {
			$state_updates['width'] = $width;
		}

		$last_history_id = $request->get_param( 'last_history_id' );
		if ( null !== $last_history_id ) {
			$state_updates['last_history_id'] = $last_history_id;
		}

		$welcome_card_seen = $request->get_param( 'welcome_card_seen' );
		if ( null !== $welcome_card_seen ) {
			$state_updates['welcome_card_seen'] = $welcome_card_seen;
		}

		$state = $this->panel_helper->update_panel_state( $state_updates );

		return new \WP_REST_Response( $state, 200 );
	}

	/**
	 * Validate width parameter.
	 *
	 * @param mixed $value Width value.
	 */
	public function validate_width( $value ): bool {
		return $this->panel_helper->validate_width( $value );
	}

	/**
	 * Sanitize and clamp width value.
	 *
	 * @param mixed $value Width value.
	 */
	public function sanitize_width( $value ): int {
		return $this->panel_helper->sanitize_width( $value );
	}
}

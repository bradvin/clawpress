<?php
/**
 * Tests for REST API module.
 *
 * @package ClawPress\Tests
 */

declare( strict_types=1 );

namespace ClawPress\Tests\Unit;

use ClawPress\RestAPI\Rest_API;
use ClawPress\RestAPI\Controllers\Chat_Controller;
use ClawPress\RestAPI\Controllers\Settings_Controller;
use ClawPress\Tests\Support\TestCase;
use ClawPress\Tests\Support\WordPress_Stubs;

final class RestApiTest extends TestCase {
	public function test_register_adds_rest_api_init_hook(): void {
		new Rest_API();

		$this->assertCount( 1, WordPress_Stubs::$actions );
		$this->assertSame( 'rest_api_init', WordPress_Stubs::$actions[0]['hook'] );
	}

	public function test_register_routes_registers_get_and_post_routes(): void {
		$rest_api = new Rest_API();
		$rest_api->register_routes();

		$this->assertCount( 4, WordPress_Stubs::$rest_routes );
		$this->assertSame( 'GET', WordPress_Stubs::$rest_routes[0]['args']['methods'] );
		$this->assertSame( 'POST', WordPress_Stubs::$rest_routes[1]['args']['methods'] );
		$this->assertSame( 'POST', WordPress_Stubs::$rest_routes[2]['args']['methods'] );
		$this->assertSame( 'GET', WordPress_Stubs::$rest_routes[3]['args']['methods'] );
	}

	public function test_settings_permissions_check_uses_manage_options_capability(): void {
		$settings_controller                    = new Settings_Controller();
		WordPress_Stubs::$can_manage_options = false;
		$this->assertFalse( $settings_controller->permissions_check() );

		WordPress_Stubs::$can_manage_options = true;
		$this->assertTrue( $settings_controller->permissions_check() );
	}

	public function test_get_settings_returns_current_option_value(): void {
		$settings_controller                        = new Settings_Controller();
		WordPress_Stubs::$options['clawpress_settings'] = 'saved-value';

		$response = $settings_controller->get_settings();

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			array( 'clawpress_settings' => 'saved-value' ),
			$response->get_data()
		);
	}

	public function test_update_settings_rejects_unknown_option(): void {
		$settings_controller = new Settings_Controller();
		$response            = $settings_controller->update_settings(
			new \WP_REST_Request(
				array(
					'option_name'  => 'invalid_option',
					'option_value' => 'value',
				)
			)
		);

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame(
			array( 'error' => 'Invalid option name' ),
			$response->get_data()
		);
	}

	public function test_update_settings_updates_allowed_option(): void {
		$settings_controller = new Settings_Controller();
		$response            = $settings_controller->update_settings(
			new \WP_REST_Request(
				array(
					'option_name'  => 'clawpress_settings',
					'option_value' => 'new-value',
				)
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'new-value', WordPress_Stubs::$options['clawpress_settings'] );
		$this->assertSame(
			array(
				'success' => true,
				'option'  => 'clawpress_settings',
				'value'   => 'new-value',
			),
				$response->get_data()
		);
	}

	public function test_chat_permissions_check_uses_manage_options_capability(): void {
		$chat_controller                        = new Chat_Controller();
		WordPress_Stubs::$can_manage_options = false;
		$this->assertFalse( $chat_controller->permissions_check() );

		WordPress_Stubs::$can_manage_options = true;
		$this->assertTrue( $chat_controller->permissions_check() );
	}

	public function test_chat_send_message_returns_message_and_reply(): void {
		$chat_controller = new Chat_Controller();

		$response = $chat_controller->send_message(
			new \WP_REST_Request(
				array(
					'message' => 'Hello lobster',
				)
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			array(
				'message' => 'Hello lobster',
				'reply'   => 'Chat endpoint received your message.',
			),
			$response->get_data()
		);
	}

	public function test_chat_get_history_returns_empty_items_array(): void {
		$chat_controller = new Chat_Controller();
		$response        = $chat_controller->get_history();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			array(
				'items' => array(),
			),
			$response->get_data()
		);
	}
}

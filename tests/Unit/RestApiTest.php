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
use ClawPress\RestAPI\Controllers\Panel_State_Controller;
use ClawPress\RestAPI\Controllers\Settings_Controller;
use ClawPress\RestAPI\Controllers\Status_Controller;
use ClawPress\Tests\Support\TestCase;
use ClawPress\Tests\Support\WordPress_Stubs;

final class RestApiTest extends TestCase {
	public function test_register_adds_rest_api_init_hook(): void {
		new Rest_API();

		$this->assertCount( 1, WordPress_Stubs::$actions );
		$this->assertSame( 'rest_api_init', WordPress_Stubs::$actions[0]['hook'] );
	}

	public function test_register_routes_registers_expected_mvp_routes(): void {
		$rest_api = new Rest_API();
		$rest_api->register_routes();

		$routes = array_map(
			static function ( array $route ): string {
				return $route['route'] . ':' . $route['args']['methods'];
			},
			WordPress_Stubs::$rest_routes
		);

		$this->assertCount( 7, WordPress_Stubs::$rest_routes );
		$this->assertContains( '/settings:GET', $routes );
		$this->assertContains( '/settings:POST', $routes );
		$this->assertContains( '/status:GET', $routes );
		$this->assertContains( '/panel/state:GET', $routes );
		$this->assertContains( '/panel/state:POST', $routes );
		$this->assertContains( '/chat/message:POST', $routes );
		$this->assertContains( '/chat/history:GET', $routes );
	}

	public function test_settings_routes_use_global_manage_options_permission_callback(): void {
		$settings_controller = new Settings_Controller();
		$settings_controller->register_routes();

		$settings_routes = array_values(
			array_filter(
				WordPress_Stubs::$rest_routes,
				static function ( array $route ): bool {
					return '/settings' === $route['route'];
				}
			)
		);

		$this->assertCount( 2, $settings_routes );
		$this->assertSame( 'clawpress_check_permissions', $settings_routes[0]['args']['permission_callback'] );
		$this->assertSame( 'clawpress_check_permissions', $settings_routes[1]['args']['permission_callback'] );
	}

	public function test_get_settings_returns_current_option_value(): void {
		$settings_controller                        = new Settings_Controller();
		WordPress_Stubs::$options['clawpress_settings'] = array(
			'provider' => 'openai',
			'model'    => 'gpt-4.1-mini',
		);

		$response = $settings_controller->get_settings();
		$data     = $response->get_data();

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			array(
				'provider'             => 'openai',
				'model'                => 'gpt-4.1-mini',
				'agent_user_id'        => 0,
				'memory_enabled'       => false,
				'onboarding_completed' => false,
			),
			$data['settings']
		);
	}

	public function test_update_settings_rejects_legacy_option_payload(): void {
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
			array( 'error' => 'No settings provided' ),
			$response->get_data()
		);
	}

	public function test_update_settings_updates_payload_fields(): void {
		$settings_controller = new Settings_Controller();
		$response            = $settings_controller->update_settings(
			new \WP_REST_Request(
				array(
					'provider'             => 'openai',
					'model'                => 'gpt-4.1-mini',
					'agent_user_id'        => 12,
					'memory_enabled'       => true,
					'onboarding_completed' => true,
				)
			)
		);
		$data = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( true, $data['success'] );
		$this->assertSame( 'openai', $data['settings']['provider'] );
		$this->assertSame( 'gpt-4.1-mini', $data['settings']['model'] );
		$this->assertSame( 12, $data['settings']['agent_user_id'] );
		$this->assertSame( true, $data['settings']['memory_enabled'] );
		$this->assertSame( true, $data['settings']['onboarding_completed'] );
		$this->assertSame( 'openai', WordPress_Stubs::$options['clawpress_settings']['provider'] );
		$this->assertSame( 12, WordPress_Stubs::$options['clawpress_settings']['agent_user_id'] );
		$this->assertSame( true, WordPress_Stubs::$options['clawpress_settings']['memory_enabled'] );
		$this->assertSame( true, WordPress_Stubs::$options['clawpress_settings']['onboarding_completed'] );
		$this->assertArrayNotHasKey( 'clawpress_agent_user_id', WordPress_Stubs::$options );
		$this->assertArrayNotHasKey( 'clawpress_memory_enabled', WordPress_Stubs::$options );
		$this->assertArrayNotHasKey( 1, WordPress_Stubs::$user_meta );
	}

	public function test_update_settings_rejects_empty_payload(): void {
		$settings_controller = new Settings_Controller();
		$response            = $settings_controller->update_settings( new \WP_REST_Request( array() ) );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( array( 'error' => 'No settings provided' ), $response->get_data() );
	}

	public function test_chat_routes_use_global_manage_options_permission_callback(): void {
		$chat_controller = new Chat_Controller();
		$chat_controller->register_routes();

		$chat_routes = array_values(
			array_filter(
				WordPress_Stubs::$rest_routes,
				static function ( array $route ): bool {
					return in_array( $route['route'], [ '/chat/message', '/chat/history' ], true );
				}
			)
		);

		$this->assertCount( 2, $chat_routes );
		$this->assertSame( 'clawpress_check_permissions', $chat_routes[0]['args']['permission_callback'] );
		$this->assertSame( 'clawpress_check_permissions', $chat_routes[1]['args']['permission_callback'] );
	}

	public function test_chat_send_message_returns_message_and_reply(): void {
		$chat_controller = new Chat_Controller(
			static function ( string $message ): array {
				return array(
					'reply'    => 'Stubbed reply: ' . $message,
					'mode'     => 'online',
					'provider' => 'openai',
					'model'    => 'gpt-4.1-mini',
				);
			}
		);

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
				'reply'   => 'Stubbed reply: Hello lobster',
				'meta'    => array(
					'mode'     => 'online',
					'provider' => 'openai',
					'model'    => 'gpt-4.1-mini',
					'suggestions' => null,
					'card'     => null,
					'command'  => null,
				),
			),
			$response->get_data()
		);
	}

	public function test_chat_command_dispatch_skips_reply_generator(): void {
		$reply_generator_calls = 0;
		$chat_controller       = new Chat_Controller(
			static function ( string $message ) use ( &$reply_generator_calls ): array {
				++$reply_generator_calls;

				return array(
					'reply'    => 'Unexpected model path: ' . $message,
					'mode'     => 'online',
					'provider' => 'openai',
					'model'    => 'gpt-4.1-mini',
				);
			}
		);

		$response = $chat_controller->send_message(
			new \WP_REST_Request(
				array(
					'message' => '/help',
				)
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 0, $reply_generator_calls );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'offline', $data['meta']['mode'] );
		$this->assertSame( '/help', $data['meta']['command']['name'] );
		$this->assertNotEmpty( $data['meta']['suggestions'] );
		$this->assertStringContainsString( 'Available commands:', $data['reply'] );
	}

	public function test_chat_unknown_command_returns_help_text(): void {
		$chat_controller = new Chat_Controller();
		$response        = $chat_controller->send_message(
			new \WP_REST_Request(
				array(
					'message' => '/does-not-exist',
				)
			)
		);
		$data            = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'offline', $data['meta']['mode'] );
		$this->assertSame( '/help', $data['meta']['command']['name'] );
		$this->assertNotEmpty( $data['meta']['suggestions'] );
		$this->assertStringContainsString( 'Unknown command', $data['reply'] );
		$this->assertStringContainsString( '/help', $data['reply'] );
	}

	public function test_memory_clear_requires_confirmation_and_clears_on_second_call(): void {
		WordPress_Stubs::$options['clawpress_settings'] = array(
			'memory_enabled'    => true,
			'agent_user_id' => 9,
		);
		WordPress_Stubs::$options['clawpress_memory_entries'] = array(
			'Entry A',
			'Entry B',
		);

		$chat_controller = new Chat_Controller();
		$first_response  = $chat_controller->send_message(
			new \WP_REST_Request(
				array(
					'message' => '/memory clear',
				)
			)
		);
		$first_data      = $first_response->get_data();

		$this->assertSame( 200, $first_response->get_status() );
		$this->assertSame( true, $first_data['meta']['command']['requires_confirmation'] );
		$this->assertStringContainsString( '--confirm=', $first_data['reply'] );

		preg_match( '/--confirm=([a-f0-9]+)/', $first_data['reply'], $matches );
		$this->assertNotEmpty( $matches[1] ?? '' );

		$second_response = $chat_controller->send_message(
			new \WP_REST_Request(
				array(
					'message' => '/memory clear --confirm=' . $matches[1],
				)
			)
		);
		$second_data     = $second_response->get_data();

		$this->assertSame( 200, $second_response->get_status() );
		$this->assertStringContainsString( 'Memory cleared.', $second_data['reply'] );
		$this->assertSame( array(), WordPress_Stubs::$options['clawpress_memory_entries'] );
	}

	public function test_clear_command_clears_history_and_is_not_repersisted(): void {
		$chat_controller = new Chat_Controller(
			static function ( string $message ): array {
				return array(
					'reply'    => 'Model: ' . $message,
					'mode'     => 'online',
					'provider' => 'openai',
					'model'    => 'gpt-4.1-mini',
				);
			}
		);

		$chat_controller->send_message(
			new \WP_REST_Request(
				array(
					'message' => 'first',
				)
			)
		);

		$clear_response = $chat_controller->send_message(
			new \WP_REST_Request(
				array(
					'message' => '/clear',
				)
			)
		);
		$clear_data     = $clear_response->get_data();

		$this->assertSame( 200, $clear_response->get_status() );
		$this->assertSame( '/clear', $clear_data['meta']['command']['name'] );
		$this->assertSame( true, $clear_data['meta']['command']['effects']['clear_history'] );

		$history_response = $chat_controller->get_history();
		$history_data     = $history_response->get_data();
		$this->assertSame( array(), $history_data['items'] );
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

	public function test_chat_send_message_persists_history_items(): void {
		$chat_controller = new Chat_Controller(
			static function ( string $message ): array {
				return array(
					'reply'    => 'Echo: ' . $message,
					'mode'     => 'online',
					'provider' => 'openai',
					'model'    => null,
				);
			}
		);

		$chat_controller->send_message(
			new \WP_REST_Request(
				array(
					'message' => 'Persist me',
				)
			)
		);

		$history_response = $chat_controller->get_history();
		$history_data     = $history_response->get_data();

		$this->assertSame( 200, $history_response->get_status() );
		$this->assertCount( 2, $history_data['items'] );
		$this->assertSame( 'user', $history_data['items'][0]['role'] );
		$this->assertSame( 'Persist me', $history_data['items'][0]['content'] );
		$this->assertSame( 'assistant', $history_data['items'][1]['role'] );
		$this->assertSame( 'Echo: Persist me', $history_data['items'][1]['content'] );
	}

	public function test_status_route_uses_global_manage_options_permission_callback(): void {
		$status_controller = new Status_Controller();
		$status_controller->register_routes();

		$status_routes = array_values(
			array_filter(
				WordPress_Stubs::$rest_routes,
				static function ( array $route ): bool {
					return '/status' === $route['route'];
				}
			)
		);

		$this->assertCount( 1, $status_routes );
		$this->assertSame( 'clawpress_check_permissions', $status_routes[0]['args']['permission_callback'] );
	}

	public function test_status_endpoint_returns_offline_by_default(): void {
		$status_controller = new Status_Controller();
		$response          = $status_controller->get_status();
		$data              = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'offline', $data['mode'] );
		$this->assertSame( null, $data['provider']['id'] );
		$this->assertSame( false, $data['provider']['configured'] );
		$this->assertSame( false, $data['memory']['enabled'] );
		$this->assertContains( '/help', $data['suggestions'] );
		$this->assertContains( '/clear', $data['suggestions'] );
	}

	public function test_status_endpoint_mode_matches_provider_configuration_state(): void {
		WordPress_Stubs::$options['clawpress_settings'] = array(
			'provider' => 'openai',
			'model'    => 'gpt-4.1-mini',
		);
		$status_controller = new Status_Controller();
		$response          = $status_controller->get_status();
		$data              = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'gpt-4.1-mini', $data['model']['id'] );

		if ( true === $data['provider']['configured'] ) {
			$this->assertSame( 'online', $data['mode'] );
			$this->assertSame( 'openai', $data['provider']['id'] );
		} else {
			$this->assertSame( 'offline', $data['mode'] );
			$this->assertSame( null, $data['provider']['id'] );
		}
	}

	public function test_panel_state_routes_use_global_manage_options_permission_callback(): void {
		$panel_state_controller = new Panel_State_Controller();
		$panel_state_controller->register_routes();

		$panel_routes = array_values(
			array_filter(
				WordPress_Stubs::$rest_routes,
				static function ( array $route ): bool {
					return '/panel/state' === $route['route'];
				}
			)
		);

		$this->assertCount( 2, $panel_routes );
		$this->assertSame( 'clawpress_check_permissions', $panel_routes[0]['args']['permission_callback'] );
		$this->assertSame( 'clawpress_check_permissions', $panel_routes[1]['args']['permission_callback'] );
	}

	public function test_panel_state_update_and_get_round_trip(): void {
		WordPress_Stubs::$current_user_id = 77;
		$panel_state_controller           = new Panel_State_Controller();

		$update_response = $panel_state_controller->update_panel_state(
			new \WP_REST_Request(
				array(
					'open'            => true,
					'width'           => 512,
					'last_history_id' => 'msg-abc',
					'welcome_card_seen' => true,
				)
			)
		);
		$get_response = $panel_state_controller->get_panel_state();

		$this->assertSame( 200, $update_response->get_status() );
		$this->assertSame( 200, $get_response->get_status() );
		$this->assertSame(
			array(
				'open'            => true,
				'width'           => 512,
				'last_history_id' => 'msg-abc',
				'welcome_card_seen' => true,
			),
			$get_response->get_data()
		);
	}
}

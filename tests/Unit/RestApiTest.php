<?php
/**
 * Tests for REST API module.
 *
 * @package ClawPress\Tests
 */

declare( strict_types=1 );

namespace ClawPress\Tests\Unit;

use ClawPress\Helpers\Memory_Helper;
use ClawPress\RestAPI\Rest_API;
use ClawPress\RestAPI\Controllers\Abilities_Settings_Controller;
use ClawPress\RestAPI\Controllers\Chat_Controller;
use ClawPress\RestAPI\Controllers\Logs_Controller;
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

		$this->assertCount( 18, WordPress_Stubs::$rest_routes );
		$this->assertContains( '/settings:GET', $routes );
		$this->assertContains( '/settings:POST', $routes );
		$this->assertContains( '/providers/(?P<provider>[a-z0-9_-]+)/models:GET', $routes );
		$this->assertContains( '/settings/abilites:GET', $routes );
		$this->assertContains( '/settings/abilites:POST', $routes );
		$this->assertContains( '/logs:GET', $routes );
		$this->assertContains( '/logs:DELETE', $routes );
		$this->assertContains( '/logs/linked-events:GET', $routes );
		$this->assertContains( '/status:GET', $routes );
		$this->assertContains( '/panel/state:GET', $routes );
		$this->assertContains( '/panel/state:POST', $routes );
		$this->assertContains( '/chat/message:POST', $routes );
		$this->assertContains( '/chat/history:GET', $routes );
		$this->assertContains( '/agent/runs:POST', $routes );
		$this->assertContains( '/agent/runs/(?P<run_id>\\d+)/enqueue:POST', $routes );
		$this->assertContains( '/agent/runs/(?P<run_id>\\d+):GET', $routes );
		$this->assertContains( '/agent/runs/(?P<run_id>\\d+)/events:GET', $routes );
		$this->assertContains( '/agent/spawn:POST', $routes );
	}

	public function test_abilities_settings_routes_require_manage_options_capability(): void {
		$controller = new Abilities_Settings_Controller();
		$controller->register_routes();

		$ability_routes = array_values(
			array_filter(
				WordPress_Stubs::$rest_routes,
				static function ( array $route ): bool {
					return '/settings/abilites' === $route['route'];
				}
			)
		);

		$this->assertCount( 2, $ability_routes );
		$this->assertTrue( is_callable( $ability_routes[0]['args']['permission_callback'] ) );
		$this->assertTrue( is_callable( $ability_routes[1]['args']['permission_callback'] ) );
		$this->assertTrue( call_user_func( $ability_routes[0]['args']['permission_callback'] ) );
		$this->assertTrue( call_user_func( $ability_routes[1]['args']['permission_callback'] ) );
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

	public function test_provider_models_route_uses_global_manage_options_permission_callback(): void {
		$settings_controller = new Settings_Controller();
		$settings_controller->register_routes();

		$model_routes = array_values(
			array_filter(
				WordPress_Stubs::$rest_routes,
				static function ( array $route ): bool {
					return '/providers/(?P<provider>[a-z0-9_-]+)/models' === $route['route'];
				}
			)
		);

		$this->assertCount( 1, $model_routes );
		$this->assertSame( 'clawpress_check_permissions', $model_routes[0]['args']['permission_callback'] );
	}

	public function test_logs_routes_use_global_manage_options_permission_callback(): void {
		$logs_controller = new Logs_Controller();
		$logs_controller->register_routes();

		$logs_routes = array_values(
			array_filter(
				WordPress_Stubs::$rest_routes,
				static function ( array $route ): bool {
					return '/logs' === $route['route'];
				}
			)
		);

		$this->assertCount( 3, $logs_routes );
		$this->assertSame( 'clawpress_check_permissions', $logs_routes[0]['args']['permission_callback'] );
		$this->assertSame( 'clawpress_check_permissions', $logs_routes[1]['args']['permission_callback'] );
		$this->assertSame( 'clawpress_check_permissions', $logs_routes[2]['args']['permission_callback'] );
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
				'provider'          => 'openai',
				'model'             => 'gpt-4.1-mini',
				'temperature'       => 0.2,
				'top_p'             => 0.9,
				'max_output_tokens' => 1200,
				'frequency_penalty' => 0.2,
				'presence_penalty'  => 0.0,
				'request_timeout'   => 45,
				'agent_user_id'     => 0,
				'memory_enabled'    => false,
				'setup_completed'   => false,
			),
			$data['settings']
		);
	}

	public function test_get_provider_models_returns_provider_options(): void {
		$settings_controller = new Settings_Controller();
		$response            = $settings_controller->get_provider_models(
			new \WP_REST_Request(
				array(
					'provider' => 'openai',
				)
			)
		);
		$data                = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertNotEmpty( $data );
		$this->assertSame( 'gpt-5.2-codex', $data[0]['id'] );
		$this->assertSame( 'GPT-5.2 Codex', $data[0]['label'] );
	}

	public function test_get_settings_backfills_new_generation_defaults_for_existing_settings(): void {
		$settings_controller                        = new Settings_Controller();
		WordPress_Stubs::$options['clawpress_settings'] = array(
			'provider'        => 'openai',
			'model'           => 'gpt-4.1-mini',
			'request_timeout' => 30,
		);

		$response = $settings_controller->get_settings();
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 30, $data['settings']['request_timeout'] );
		$this->assertSame( 0.2, $data['settings']['temperature'] );
		$this->assertSame( 0.9, $data['settings']['top_p'] );
		$this->assertSame( 1200, $data['settings']['max_output_tokens'] );
		$this->assertSame( 0.2, $data['settings']['frequency_penalty'] );
		$this->assertSame( 0.0, $data['settings']['presence_penalty'] );
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
					'provider'          => 'openai',
					'model'             => 'gpt-4.1-mini',
					'temperature'       => 0.4,
					'top_p'             => 0.8,
					'max_output_tokens' => 1300,
					'frequency_penalty' => 0.1,
					'presence_penalty'  => -0.1,
					'request_timeout'   => 50,
					'agent_user_id'     => 12,
					'memory_enabled'    => true,
					'setup_completed'   => true,
				)
			)
		);
		$data = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( true, $data['success'] );
		$this->assertSame( 'openai', $data['settings']['provider'] );
		$this->assertSame( 'gpt-4.1-mini', $data['settings']['model'] );
		$this->assertSame( 0.4, $data['settings']['temperature'] );
		$this->assertSame( 0.8, $data['settings']['top_p'] );
		$this->assertSame( 1300, $data['settings']['max_output_tokens'] );
		$this->assertSame( 0.1, $data['settings']['frequency_penalty'] );
		$this->assertSame( -0.1, $data['settings']['presence_penalty'] );
		$this->assertSame( 50, $data['settings']['request_timeout'] );
		$this->assertSame( 12, $data['settings']['agent_user_id'] );
		$this->assertSame( true, $data['settings']['memory_enabled'] );
		$this->assertSame( true, $data['settings']['setup_completed'] );
		$this->assertSame( 'openai', WordPress_Stubs::$options['clawpress_settings']['provider'] );
		$this->assertSame( 0.4, WordPress_Stubs::$options['clawpress_settings']['temperature'] );
		$this->assertSame( 0.8, WordPress_Stubs::$options['clawpress_settings']['top_p'] );
		$this->assertSame( 1300, WordPress_Stubs::$options['clawpress_settings']['max_output_tokens'] );
		$this->assertSame( 0.1, WordPress_Stubs::$options['clawpress_settings']['frequency_penalty'] );
		$this->assertSame( -0.1, WordPress_Stubs::$options['clawpress_settings']['presence_penalty'] );
		$this->assertSame( 50, WordPress_Stubs::$options['clawpress_settings']['request_timeout'] );
		$this->assertSame( 12, WordPress_Stubs::$options['clawpress_settings']['agent_user_id'] );
		$this->assertSame( true, WordPress_Stubs::$options['clawpress_settings']['memory_enabled'] );
		$this->assertSame( true, WordPress_Stubs::$options['clawpress_settings']['setup_completed'] );
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

	public function test_get_abilities_settings_returns_default_enabled_state_when_option_missing(): void {
		$controller = new Abilities_Settings_Controller();
		$response   = $controller->get_abilities_settings();
		$data       = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertIsArray( $data['abilities'] );
		$this->assertCount( 10, $data['abilities'] );
		$this->assertIsArray( $data['enabled_abilities'] );
		$this->assertCount( 10, $data['enabled_abilities'] );
		$this->assertContains( 'clawpress/file-read', $data['enabled_abilities'] );
	}

	public function test_get_abilities_settings_includes_non_clawpress_registered_abilities(): void {
		wp_register_ability(
			'vendor/custom-ability',
			[
				'label'               => 'Custom Ability',
				'description'         => 'Registered externally.',
				'input_schema'        => [],
				'permission_callback' => static fn(): bool => true,
				'execute_callback'    => static fn() => [ 'ok' => true ],
			]
		);

		$controller = new Abilities_Settings_Controller();
		$response   = $controller->get_abilities_settings();
		$data       = $response->get_data();

		$this->assertContains( 'vendor/custom-ability', array_column( $data['abilities'], 'ability_name' ) );
	}

	public function test_update_abilities_settings_saves_registered_ability_ids_only(): void {
		wp_register_ability(
			'vendor/custom-ability',
			[
				'label'               => 'Custom Ability',
				'description'         => 'Registered externally.',
				'input_schema'        => [],
				'permission_callback' => static fn(): bool => true,
				'execute_callback'    => static fn() => [ 'ok' => true ],
			]
		);

		$controller = new Abilities_Settings_Controller();
		$response   = $controller->update_abilities_settings(
			new \WP_REST_Request(
				[
					'abilities' => [
						'clawpress/file-read',
						'clawpress/file-write',
						'vendor/custom-ability',
						'clawpress/not-real',
					],
				]
			)
		);
		$data       = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $data['success'] );
		$this->assertSame(
			[
				'clawpress/file-read',
				'clawpress/file-write',
				'vendor/custom-ability',
			],
			$data['enabled_abilities']
		);
		$this->assertSame(
			[
				'clawpress/file-read',
				'clawpress/file-write',
				'vendor/custom-ability',
			],
			WordPress_Stubs::$options['clawpress_enabled_abilities']
		);
	}

	public function test_update_abilities_settings_reset_restores_defaults(): void {
		$controller = new Abilities_Settings_Controller();
		WordPress_Stubs::$options['clawpress_enabled_abilities'] = [
			'clawpress/file-read',
		];

		$response = $controller->update_abilities_settings(
			new \WP_REST_Request(
				[
					'reset' => true,
				]
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $data['success'] );
		$this->assertTrue( $data['reset'] );
		$this->assertCount( 10, $data['enabled_abilities'] );
	}

	public function test_chat_routes_use_global_manage_options_permission_callback(): void {
		$chat_controller = new Chat_Controller();
		$chat_controller->register_routes();

		$chat_routes = array_values(
			array_filter(
				WordPress_Stubs::$rest_routes,
				static function ( array $route ): bool {
					return in_array( $route['route'], [ '/chat/message', '/chat/stream', '/chat/history' ], true );
				}
			)
		);

		$this->assertCount( 3, $chat_routes );
		$this->assertSame( 'clawpress_check_permissions', $chat_routes[0]['args']['permission_callback'] );
		$this->assertSame( 'clawpress_check_permissions', $chat_routes[1]['args']['permission_callback'] );
		$this->assertSame( 'clawpress_check_permissions', $chat_routes[2]['args']['permission_callback'] );
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
					'error'    => null,
					'context'  => null,
					'tool_calls' => null,
					'run_id'   => null,
					'session_id' => null,
					'events_cursor' => null,
					'status'   => null,
					),
			),
			$response->get_data()
		);
	}

	public function test_chat_send_message_exposes_in_progress_run_metadata(): void {
		$chat_controller = new Chat_Controller(
			static function ( string $message ): array {
				return array(
					'reply'       => 'Still working: ' . $message,
					'mode'        => 'in_progress',
					'provider'    => 'openai',
					'model'       => 'gpt-4.1-mini',
					'run_id'      => 42,
					'session_id'  => 84,
					'events_cursor' => 123,
					'status'      => 'in_progress',
					);
				}
			);

		$response = $chat_controller->send_message(
			new \WP_REST_Request(
				array(
					'message' => 'Continue',
				)
			)
		);

		$data = $response->get_data();
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'in_progress', $data['meta']['mode'] );
		$this->assertSame( 42, $data['meta']['run_id'] );
		$this->assertSame( 84, $data['meta']['session_id'] );
		$this->assertSame( 123, $data['meta']['events_cursor'] );
		$this->assertSame( 'in_progress', $data['meta']['status'] );
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

	public function test_chat_send_message_returns_structured_error_meta(): void {
		$chat_controller = new Chat_Controller(
			static function ( string $message ): array {
				unset( $message );
				return array(
					'reply'    => 'AI request failed: timed out',
					'mode'     => 'error',
					'provider' => 'openai',
					'model'    => 'gpt-4.1-mini',
					'error'    => array(
						'type'    => 'timeout',
						'message' => 'timed out',
						'code'    => 28,
					),
					'card'     => array(
						'type' => 'error',
						'data' => array(
							'title'   => 'Request Error',
							'message' => 'timed out',
						),
					),
				);
			}
		);

		$response = $chat_controller->send_message(
			new \WP_REST_Request(
				array(
					'message' => 'trigger error',
				)
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'error', $data['meta']['mode'] );
		$this->assertSame( 'timeout', $data['meta']['error']['type'] );
		$this->assertSame( 'timed out', $data['meta']['error']['message'] );
		$this->assertSame( 'error', $data['meta']['card']['type'] );
	}

	public function test_memory_clear_requires_confirmation_and_clears_on_second_call(): void {
		WordPress_Stubs::$options['clawpress_settings'] = array(
			'memory_enabled'    => true,
			'agent_user_id' => 9,
		);
		$memory_helper = Memory_Helper::get_instance();
		$memory_helper->save_long_term_memory( 'Entry A' );
		$memory_helper->save_daily_memory( 'Entry B', strtotime( '2026-02-15 10:00:00 UTC' ) );

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
		$this->assertSame( array(), $memory_helper->list_memories( 0 ) );
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

	public function test_chat_send_message_persists_tool_calls_in_history(): void {
		$chat_controller = new Chat_Controller(
			static function ( string $message ): array {
				return array(
					'reply'      => 'Tools done: ' . $message,
					'mode'       => 'online',
					'provider'   => 'openai',
					'model'      => 'gpt-4.1-mini',
					'tool_calls' => array(
						array(
							'name' => 'file_read',
							'args' => array( 'path' => '/tmp/demo.txt' ),
							'status' => 'success',
							'message' => 'Read file.',
							'round' => 1,
							'sequence' => 1,
						),
						array(
							'name' => 'File_Delete',
							'args' => array( 'path' => '/tmp/old.txt' ),
							'requires_confirmation' => true,
							'status' => 'requires_confirmation',
							'round' => 1,
							'sequence' => 2,
						),
					),
				);
			}
		);

		$chat_controller->send_message(
			new \WP_REST_Request(
				array(
					'message' => 'Run tools',
				)
			)
		);

		$history_data = $chat_controller->get_history()->get_data();
		$this->assertCount( 2, $history_data['items'] );
		$this->assertSame( array(), $history_data['items'][0]['tool_calls'] );
		$this->assertCount( 2, $history_data['items'][1]['tool_calls'] );
		$this->assertSame( 'file_read', $history_data['items'][1]['tool_calls'][0]['name'] );
		$this->assertSame( 'success', $history_data['items'][1]['tool_calls'][0]['status'] );
		$this->assertSame( 'file_delete', $history_data['items'][1]['tool_calls'][1]['name'] );
		$this->assertSame( true, $history_data['items'][1]['tool_calls'][1]['requires_confirmation'] );
		$this->assertIsInt( $history_data['items'][1]['tool_calls'][0]['recorded_at'] );
		$this->assertGreaterThan( 0, $history_data['items'][1]['tool_calls'][0]['recorded_at'] );
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

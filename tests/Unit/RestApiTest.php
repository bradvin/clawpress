<?php
/**
 * Tests for REST API module.
 *
 * @package ClawPress\Tests
 */

declare( strict_types=1 );

namespace ClawPress\Tests\Unit;

use ClawPress\RestAPI\Rest_API;
use ClawPress\Tests\Support\TestCase;
use ClawPress\Tests\Support\WordPress_Stubs;

final class RestApiTest extends TestCase {
	public function test_register_adds_rest_api_init_hook(): void {
		Rest_API::init();

		$this->assertCount( 1, WordPress_Stubs::$actions );
		$this->assertSame( 'rest_api_init', WordPress_Stubs::$actions[0]['hook'] );
	}

	public function test_register_routes_registers_get_and_post_routes(): void {
		Rest_API::register_routes();

		$this->assertCount( 2, WordPress_Stubs::$rest_routes );
		$this->assertSame( 'GET', WordPress_Stubs::$rest_routes[0]['args']['methods'] );
		$this->assertSame( 'POST', WordPress_Stubs::$rest_routes[1]['args']['methods'] );
	}

	public function test_permissions_check_uses_manage_options_capability(): void {
		WordPress_Stubs::$can_manage_options = false;
		$this->assertFalse( Rest_API::permissions_check() );

		WordPress_Stubs::$can_manage_options = true;
		$this->assertTrue( Rest_API::permissions_check() );
	}

	public function test_get_settings_returns_current_option_value(): void {
		WordPress_Stubs::$options['clawpress_settings'] = 'saved-value';

		$response = Rest_API::get_settings();

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			array( 'clawpress_settings' => 'saved-value' ),
			$response->get_data()
		);
	}

	public function test_update_settings_rejects_unknown_option(): void {
		$response = Rest_API::update_settings(
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
		$response = Rest_API::update_settings(
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
}

<?php
/**
 * Tests for offline command parser and dispatcher.
 *
 * @package ClawPress\Tests
 */

declare( strict_types=1 );

namespace ClawPress\Tests\Unit;

use ClawPress\Commands\Command_Request;
use ClawPress\Commands\Commands;
use ClawPress\Tests\Support\TestCase;
use ClawPress\Tests\Support\WordPress_Stubs;

final class CommandsTest extends TestCase {
	public function test_command_request_ignores_non_command_messages(): void {
		$this->assertNull( Command_Request::from_message( 'hello world' ) );
		$this->assertNull( Command_Request::from_message( '  ' ) );
	}

	public function test_command_request_normalizes_whitespace_and_help_shortcut(): void {
		$request = Command_Request::from_message( "  /?\n\t " );

		$this->assertInstanceOf( Command_Request::class, $request );
		$this->assertSame( '/help', $request->get_command() );
		$this->assertSame( '/?', $request->get_normalized_message() );
	}

	public function test_dispatches_help_command_deterministically(): void {
		$commands = new Commands();
		$payload  = $commands->maybe_dispatch( '/help' );

		$this->assertIsArray( $payload );
		$this->assertSame( 'offline', $payload['mode'] );
		$this->assertSame( '/help', $payload['command']['name'] );
		$this->assertNotEmpty( $payload['suggestions'] );
		$this->assertStringContainsString( 'Available commands:', $payload['reply'] );
	}

	public function test_invalid_command_usage_returns_help_text(): void {
		$commands = new Commands();
		$payload  = $commands->maybe_dispatch( '/site' );

		$this->assertIsArray( $payload );
		$this->assertSame( true, $payload['command']['error'] );
		$this->assertStringContainsString( 'Invalid usage.', $payload['reply'] );
		$this->assertStringContainsString( 'Available commands:', $payload['reply'] );
	}

	public function test_memory_clear_is_blocked_when_agent_user_missing(): void {
		WordPress_Stubs::$options['clawpress_settings'] = array(
			'memory_enabled' => true,
			'agent_user_id'  => 0,
		);

		$commands = new Commands();
		$payload  = $commands->maybe_dispatch( '/memory clear' );

		$this->assertIsArray( $payload );
		$this->assertSame( true, $payload['command']['error'] );
		$this->assertStringContainsString( 'Setup required', $payload['reply'] );
	}

	public function test_clear_command_clears_current_user_chat_history(): void {
		WordPress_Stubs::$options['clawpress_chat_history_1'] = array(
			array(
				'id'        => 'msg-1',
				'role'      => 'user',
				'content'   => 'hello',
				'createdAt' => 1,
			),
		);

		$commands = new Commands();
		$payload  = $commands->maybe_dispatch( '/clear' );

		$this->assertIsArray( $payload );
		$this->assertSame( '/clear', $payload['command']['name'] );
		$this->assertSame( true, $payload['command']['effects']['clear_history'] );
		$this->assertContains( '/help', $payload['suggestions'] );
		$this->assertSame( array(), WordPress_Stubs::$options['clawpress_chat_history_1'] );
	}

	public function test_default_suggestions_are_derived_from_handlers(): void {
		$commands    = new Commands();
		$suggestions = $commands->get_default_suggestions();

		$this->assertContains( '/help', $suggestions );
		$this->assertContains( '/clear', $suggestions );
		$this->assertContains( '/status', $suggestions );
		$this->assertContains( '/setup resume', $suggestions );
		$this->assertContains( '/memory list', $suggestions );
		$this->assertContains( '/site info', $suggestions );
		$this->assertContains( '/test', $suggestions );
		$this->assertContains( '/tools list', $suggestions );
		$this->assertNotContains( '/reset', $suggestions );
	}

	public function test_reset_command_is_hidden_from_help_but_dispatchable(): void {
		WordPress_Stubs::$user_meta[1] = array(
			'clawpress_panel_state' => array( 'open' => true ),
			'clawpress_demo_meta'   => 'demo',
			'clawpress_workspace_hash' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
			'unrelated_meta'        => 'keep',
		);

		$commands      = new Commands();
		$help_payload  = $commands->maybe_dispatch( '/help' );
		$reset_payload = $commands->maybe_dispatch( '/reset' );

		$this->assertIsArray( $help_payload );
		$this->assertStringNotContainsString( '/reset', $help_payload['reply'] );

		$this->assertIsArray( $reset_payload );
		$this->assertSame( '/reset', $reset_payload['command']['name'] );
		$this->assertStringContainsString( 'Removed 2', $reset_payload['reply'] );
		$this->assertArrayNotHasKey( 'clawpress_panel_state', WordPress_Stubs::$user_meta[1] );
		$this->assertArrayNotHasKey( 'clawpress_demo_meta', WordPress_Stubs::$user_meta[1] );
		$this->assertSame( 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', WordPress_Stubs::$user_meta[1]['clawpress_workspace_hash'] );
		$this->assertSame( 'keep', WordPress_Stubs::$user_meta[1]['unrelated_meta'] );
	}

	public function test_settings_command_is_hidden_but_updates_provider_setting(): void {
		$commands       = new Commands();
		$settings_reply = $commands->maybe_dispatch( '/settings provider openai' );
		$help_payload   = $commands->maybe_dispatch( '/help' );

		$this->assertIsArray( $settings_reply );
		$this->assertSame( '/settings', $settings_reply['command']['name'] );
		$this->assertSame( false, $settings_reply['command']['error'] );
		$this->assertStringContainsString( 'Updated `provider` to `openai`.', $settings_reply['reply'] );
		$this->assertSame( 'openai', WordPress_Stubs::$options['clawpress_settings']['provider'] );

		$this->assertIsArray( $help_payload );
		$this->assertStringNotContainsString( '/settings', $help_payload['reply'] );
		$this->assertNotContains( '/settings', $commands->get_default_suggestions() );
	}

	public function test_settings_command_rejects_unknown_key(): void {
		$commands = new Commands();
		$payload  = $commands->maybe_dispatch( '/settings unknown value' );

		$this->assertIsArray( $payload );
		$this->assertSame( '/settings', $payload['command']['name'] );
		$this->assertSame( true, $payload['command']['error'] );
		$this->assertStringContainsString( 'Unknown setting key', $payload['reply'] );
	}

	public function test_settings_command_parses_boolean_values(): void {
		$commands = new Commands();
		$payload  = $commands->maybe_dispatch( '/settings memory_enabled yes' );

		$this->assertIsArray( $payload );
		$this->assertSame( '/settings', $payload['command']['name'] );
		$this->assertSame( false, $payload['command']['error'] );
		$this->assertTrue( WordPress_Stubs::$options['clawpress_settings']['memory_enabled'] );
	}

	public function test_test_command_requires_saved_provider_and_model(): void {
		$commands  = new Commands();
		$payload_1 = $commands->maybe_dispatch( '/test' );

		$this->assertIsArray( $payload_1 );
		$this->assertSame( '/test', $payload_1['command']['name'] );
		$this->assertSame( true, $payload_1['command']['error'] );
		$this->assertStringContainsString( 'No provider is saved', $payload_1['reply'] );

		$commands->maybe_dispatch( '/settings provider openai' );
		$payload_2 = $commands->maybe_dispatch( '/test' );

		$this->assertIsArray( $payload_2 );
		$this->assertSame( '/test', $payload_2['command']['name'] );
		$this->assertSame( true, $payload_2['command']['error'] );
		$this->assertStringContainsString( 'No model is saved', $payload_2['reply'] );
	}
}

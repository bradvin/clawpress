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
		$this->assertContains( '/onboarding resume', $suggestions );
		$this->assertContains( '/memory list', $suggestions );
		$this->assertContains( '/site info', $suggestions );
		$this->assertContains( '/tools list', $suggestions );
	}
}

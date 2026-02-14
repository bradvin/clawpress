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

	public function test_memory_clear_is_blocked_when_execution_user_missing(): void {
		WordPress_Stubs::$options['clawpress_settings'] = array(
			'memory_enabled'    => true,
			'execution_user_id' => 0,
		);

		$commands = new Commands();
		$payload  = $commands->maybe_dispatch( '/memory clear' );

		$this->assertIsArray( $payload );
		$this->assertSame( true, $payload['command']['error'] );
		$this->assertStringContainsString( 'Setup required', $payload['reply'] );
	}
}

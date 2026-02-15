<?php
/**
 * Tests for chat helper context injection.
 *
 * @package ClawPress\Tests
 */

declare( strict_types=1 );

namespace ClawPress\Tests\Unit;

use ClawPress\Helpers\Chat_Helper;
use ClawPress\Tests\Support\TestCase;

final class ChatHelperTest extends TestCase {
	public function test_generate_ai_reply_injects_context_into_online_generator(): void {
		update_option(
			'clawpress_settings',
			[
				'memory_enabled' => true,
			]
		);
		update_option( 'clawpress_memory_entries', [ 'User prefers concise replies.' ] );
		update_option( 'clawpress_chat_history_1', [
			[
				'id'        => 'msg-1',
				'role'      => 'user',
				'content'   => 'Previous request',
				'createdAt' => 1,
			],
			[
				'id'        => 'msg-2',
				'role'      => 'assistant',
				'content'   => 'Previous response',
				'createdAt' => 2,
			],
		] );

		$calls       = [];
		$chat_helper = Chat_Helper::create_for_testing(
			null,
			static function ( array $context, string $provider, string $model ) use ( &$calls ): string {
				$calls[] = [
					'context'  => $context,
					'provider' => $provider,
					'model'    => $model,
				];

				return 'Online reply from test generator.';
			},
			static fn( array $settings ): array => [
				'provider' => 'openai',
				'model'    => 'gpt-4.1-mini',
			]
		);

		$payload = $chat_helper->generate_ai_reply( 'Current request' );

		$this->assertSame( 'online', $payload['mode'] );
		$this->assertSame( 'Online reply from test generator.', $payload['reply'] );
		$this->assertSame( 'openai', $payload['provider'] );
		$this->assertSame( 'gpt-4.1-mini', $payload['model'] );
		$this->assertCount( 1, $calls );
		$this->assertSame( 'openai', $calls[0]['provider'] );
		$this->assertSame( 'gpt-4.1-mini', $calls[0]['model'] );
		$this->assertSame( 'Current request', $calls[0]['context']['message'] );
		$this->assertStringContainsString( '# ClawPress', $calls[0]['context']['system_prompt'] );
		$this->assertStringContainsString( '# Memory', $calls[0]['context']['system_prompt'] );
		$this->assertCount( 2, $calls[0]['context']['history_messages'] );
	}

	public function test_generate_ai_reply_returns_offline_when_no_provider_resolved(): void {
		$chat_helper = Chat_Helper::create_for_testing(
			null,
			static function (): string {
				return 'should-not-run';
			},
			static fn( array $settings ): array => [
				'provider' => '',
				'model'    => '',
			]
		);

		$payload = $chat_helper->generate_ai_reply( 'hello' );

		$this->assertSame( 'offline', $payload['mode'] );
		$this->assertNull( $payload['provider'] );
		$this->assertNull( $payload['model'] );
		$this->assertStringContainsString( 'Offline mode', $payload['reply'] );
	}
}

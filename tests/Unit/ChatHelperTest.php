<?php
/**
 * Tests for chat helper context injection.
 *
 * @package ClawPress\Tests
 */

declare( strict_types=1 );

namespace ClawPress\Tests\Unit;

use ClawPress\Helpers\Chat_Helper;
use ClawPress\Helpers\Memory_Helper;
use ClawPress\Tests\Support\TestCase;

final class ChatHelperTest extends TestCase {
	public function test_generate_ai_reply_injects_context_into_online_generator(): void {
		update_option(
			'clawpress_settings',
			[
				'memory_enabled' => true,
			]
		);
		Memory_Helper::get_instance()->save_long_term_memory( 'User prefers concise replies.' );
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
		$this->assertSame( 30, $calls[0]['context']['request_timeout'] );
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

	public function test_generate_ai_reply_returns_structured_error_payload_on_exception(): void {
		$chat_helper = Chat_Helper::create_for_testing(
			null,
			static function (): string {
				throw new \RuntimeException( 'cURL error 28: Operation timed out' );
			},
			static fn( array $settings ): array => [
				'provider' => 'openai',
				'model'    => 'gpt-4.1-mini',
			]
		);

		$payload = $chat_helper->generate_ai_reply( 'hello' );

		$this->assertSame( 'error', $payload['mode'] );
		$this->assertSame( 'openai', $payload['provider'] );
		$this->assertSame( 'gpt-4.1-mini', $payload['model'] );
		$this->assertSame( 'timeout', $payload['error']['type'] );
		$this->assertSame( true, $payload['error']['retryable'] );
		$this->assertSame( 'error', $payload['card']['type'] );
		$this->assertStringContainsString( 'AI request failed', $payload['reply'] );
	}

	public function test_generate_ai_reply_includes_online_confirmation_card_payload(): void {
		$chat_helper = Chat_Helper::create_for_testing(
			null,
			static function (): array {
				return [
					'reply' => 'Please confirm this action.',
					'card'  => [
						'type' => 'user_confirmation',
						'data' => [
							'title'   => 'User Confirmation Required',
							'message' => 'Confirm or decline.',
							'actions' => [
								[
									'id'     => 'confirm-action',
									'label'  => 'Confirm Action',
									'type'   => 'send_prompt',
									'prompt' => 'Confirm now.',
								],
							],
						],
					],
				];
			},
			static fn( array $settings ): array => [
				'provider' => 'openai',
				'model'    => 'gpt-4.1-mini',
			]
		);

		$payload = $chat_helper->generate_ai_reply( 'Delete this file' );

		$this->assertSame( 'online', $payload['mode'] );
		$this->assertSame( 'Please confirm this action.', $payload['reply'] );
		$this->assertIsArray( $payload['card'] );
		$this->assertSame( 'user_confirmation', $payload['card']['type'] );
	}

	public function test_generate_ai_reply_uses_card_message_when_online_reply_is_empty(): void {
		$chat_helper = Chat_Helper::create_for_testing(
			null,
			static function (): array {
				return [
					'reply' => '',
					'card'  => [
						'type' => 'user_confirmation',
						'data' => [
							'message' => 'Confirmation is required.',
						],
					],
				];
			},
			static fn( array $settings ): array => [
				'provider' => 'openai',
				'model'    => 'gpt-4.1-mini',
			]
		);

		$payload = $chat_helper->generate_ai_reply( 'Delete this file' );

		$this->assertSame( 'online', $payload['mode'] );
		$this->assertSame( 'Confirmation is required.', $payload['reply'] );
		$this->assertSame( 'user_confirmation', $payload['card']['type'] );
	}
}

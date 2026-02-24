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
use ClawPress\Tests\Support\Agent_Runtime_Wpdb;
use ClawPress\Tests\Support\TestCase;
use ClawPress\Tests\Support\WordPress_Stubs;

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
		$this->assertSame( 45, $calls[0]['context']['request_timeout'] );
		$this->assertSame( 0.2, $calls[0]['context']['generation_settings']['temperature'] );
		$this->assertSame( 0.9, $calls[0]['context']['generation_settings']['top_p'] );
		$this->assertSame( 1200, $calls[0]['context']['generation_settings']['max_output_tokens'] );
		$this->assertSame( 0.2, $calls[0]['context']['generation_settings']['frequency_penalty'] );
		$this->assertSame( 0.0, $calls[0]['context']['generation_settings']['presence_penalty'] );
		$this->assertStringContainsString( '# ClawPress', $calls[0]['context']['system_prompt'] );
		$this->assertStringContainsString( '# Memory', $calls[0]['context']['system_prompt'] );
		$this->assertCount( 2, $calls[0]['context']['history_messages'] );
	}

	public function test_generate_ai_reply_injects_configured_generation_settings_into_online_context(): void {
		update_option(
			'clawpress_settings',
			[
				'request_timeout'   => 63,
				'temperature'       => 0.6,
				'top_p'             => 0.7,
				'max_output_tokens' => 1600,
				'frequency_penalty' => 0.4,
				'presence_penalty'  => 0.3,
			]
		);

		$calls       = [];
		$chat_helper = Chat_Helper::create_for_testing(
			null,
			static function ( array $context ) use ( &$calls ): string {
				$calls[] = $context;
				return 'Configured generation context captured.';
			},
			static fn( array $settings ): array => [
				'provider' => 'openai',
				'model'    => 'gpt-4.1-mini',
			]
		);

		$payload = $chat_helper->generate_ai_reply( 'Use configured generation defaults' );

		$this->assertSame( 'online', $payload['mode'] );
		$this->assertCount( 1, $calls );
		$this->assertSame( 63, $calls[0]['request_timeout'] );
		$this->assertSame( 0.6, $calls[0]['generation_settings']['temperature'] );
		$this->assertSame( 0.7, $calls[0]['generation_settings']['top_p'] );
		$this->assertSame( 1600, $calls[0]['generation_settings']['max_output_tokens'] );
		$this->assertSame( 0.4, $calls[0]['generation_settings']['frequency_penalty'] );
		$this->assertSame( 0.3, $calls[0]['generation_settings']['presence_penalty'] );
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

	public function test_generate_ai_reply_includes_context_usage_payload_when_available(): void {
		$chat_helper = Chat_Helper::create_for_testing(
			null,
			static function (): array {
				return [
					'reply'   => 'Context-aware reply.',
					'context' => [
						'prompt_tokens'         => 1000,
						'completion_tokens'     => 120,
						'total_tokens'          => 1120,
						'used_tokens'           => 1000,
						'context_window_tokens' => 128000,
						'percent_used'          => 1,
						'percent_left'          => 99,
						'window_is_estimated'   => true,
					],
				];
			},
			static fn( array $settings ): array => [
				'provider' => 'openai',
				'model'    => 'gpt-4o',
			]
		);

		$payload = $chat_helper->generate_ai_reply( 'Show context' );

		$this->assertSame( 'online', $payload['mode'] );
		$this->assertSame( 'Context-aware reply.', $payload['reply'] );
		$this->assertIsArray( $payload['context'] );
		$this->assertSame( 1000, $payload['context']['prompt_tokens'] );
		$this->assertSame( 128000, $payload['context']['context_window_tokens'] );
		$this->assertSame( 1, $payload['context']['percent_used'] );
		$this->assertSame( 99, $payload['context']['percent_left'] );
	}

	public function test_generate_ai_reply_includes_tool_call_trace_when_available(): void {
		$chat_helper = Chat_Helper::create_for_testing(
			null,
			static function (): array {
				return [
					'reply'      => 'Completed tool calls.',
					'tool_calls' => [
						[
							'name'                  => 'file_read',
							'ability'               => 'clawpress/file/read',
							'args'                  => [
								'path' => '/tmp/example.txt',
							],
							'status'                => 'success',
							'requires_confirmation' => false,
							'message'               => '',
							'round'                 => 1,
							'sequence'              => 1,
						],
						[
							'name'                  => 'file_delete',
							'ability'               => 'clawpress/file/delete',
							'args'                  => [
								'path' => '/tmp/example.txt',
							],
							'status'                => 'requires_confirmation',
							'requires_confirmation' => true,
							'message'               => 'Explicit confirmation is required.',
							'round'                 => 1,
							'sequence'              => 2,
						],
					],
				];
			},
			static fn( array $settings ): array => [
				'provider' => 'openai',
				'model'    => 'gpt-4.1-mini',
			]
		);

		$payload = $chat_helper->generate_ai_reply( 'Run tools' );

		$this->assertSame( 'online', $payload['mode'] );
		$this->assertSame( 'Completed tool calls.', $payload['reply'] );
		$this->assertIsArray( $payload['tool_calls'] );
		$this->assertCount( 2, $payload['tool_calls'] );
		$this->assertSame( 'file_read', $payload['tool_calls'][0]['name'] );
		$this->assertSame( 'success', $payload['tool_calls'][0]['status'] );
		$this->assertSame( 'file_delete', $payload['tool_calls'][1]['name'] );
		$this->assertSame( 'requires_confirmation', $payload['tool_calls'][1]['status'] );
		$this->assertSame( true, $payload['tool_calls'][1]['requires_confirmation'] );
	}

	public function test_generate_ai_reply_returns_in_progress_with_run_metadata_when_slice_pauses(): void {
		$GLOBALS['wpdb'] = new Agent_Runtime_Wpdb();
		try {
			$chat_helper = Chat_Helper::create_for_testing(
				null,
				static function (): array {
					return [
						'status'        => 'in_progress',
						'next_action'   => 'continue_later',
						'reply'         => 'Working on it...',
						'resume_cursor' => [
							'version' => 1,
							'round'   => 2,
						],
						'tool_calls'    => [
							[
								'name'   => 'file_read',
								'status' => 'success',
							],
						],
					];
				},
				static fn( array $settings ): array => [
					'provider' => 'openai',
					'model'    => 'gpt-4.1-mini',
				]
			);

			$payload = $chat_helper->generate_ai_reply( 'Long running request' );

			$this->assertSame( 'in_progress', $payload['mode'] );
			$this->assertSame( 'in_progress', $payload['status'] );
				$this->assertGreaterThan( 0, (int) $payload['run_id'] );
				$this->assertGreaterThan( 0, (int) $payload['session_id'] );
				$this->assertCount( 1, $GLOBALS['wpdb']->runs );
				$this->assertCount( 1, WordPress_Stubs::$async_actions );
				$this->assertNotEmpty( $GLOBALS['wpdb']->events );
				$events = array_values( $GLOBALS['wpdb']->events );
				$this->assertSame( (int) $payload['run_id'], (int) $events[0]['run_id'] );
				$this->assertSame( (int) $payload['session_id'], (int) $events[0]['session_id'] );
			} finally {
				unset( $GLOBALS['wpdb'] );
			}
		}
	}

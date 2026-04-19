<?php
/**
 * Tests for agent loop helper runtime behavior.
 *
 * @package ClawPress\Tests
 */

declare( strict_types=1 );

namespace ClawPress\Tests\Unit;

use ClawPress\Helpers\Agent_Loop_Helper;
use ClawPress\Transports\Agent_Event_Sink;
use ClawPress\Tests\Support\Agent_Runtime_Wpdb;
use ClawPress\Tests\Support\TestCase;

final class AgentLoopHelperTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['wpdb'] = new Agent_Runtime_Wpdb();
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}

	public function test_run_turn_supports_invokable_generator_object_arity(): void {
		$invokable = new class() {
			/** @var array<string,mixed> */
			public array $captured = [];

			/**
			 * @param array<string,mixed> $context Runtime context.
			 * @return array<string,mixed>
			 */
			public function __invoke( array $context, string $provider, string $model ): array {
				$this->captured = [
					'context'  => $context,
					'provider' => $provider,
					'model'    => $model,
				];

				return [
					'reply' => 'Invokable generator reply.',
				];
			}
		};

		$result = Agent_Loop_Helper::get_instance()->run_turn(
			[
				'message'                 => 'Hello loop',
				'trigger'                 => 'chat',
				'transport_mode'          => 'polling',
				'requesting_user_id'      => 1,
				'execution_user_id'       => 1,
				'provider_model_resolver' => static fn( array $settings ): array => [
					'provider' => 'openai',
					'model'    => 'gpt-4.1-mini',
				],
				'online_reply_generator'  => $invokable,
			]
		);

		$this->assertSame( 'success', $result['status'] );
		$this->assertSame( 'online', $result['mode'] );
		$this->assertSame( 'Invokable generator reply.', $result['assistant_text'] );
		$this->assertSame( 'openai', $invokable->captured['provider'] );
		$this->assertSame( 'gpt-4.1-mini', $invokable->captured['model'] );
		$this->assertSame( 'Hello loop', $invokable->captured['context']['message'] );
	}

	public function test_run_turn_returns_offline_mode_when_provider_is_missing(): void {
		$result = Agent_Loop_Helper::get_instance()->run_turn(
			[
				'message'                 => 'Offline check',
				'provider_model_resolver' => static fn( array $settings ): array => [
					'provider' => '',
					'model'    => '',
				],
			]
		);

		$this->assertSame( 'success', $result['status'] );
		$this->assertSame( 'offline', $result['mode'] );
		$this->assertSame( '', $result['assistant_text'] );
	}

	public function test_run_turn_streaming_mode_uses_polling_transport_events_cursor(): void {
		$result = Agent_Loop_Helper::get_instance()->run_turn(
			[
				'run_id'                  => 55,
				'session_id'              => 77,
				'message'                 => 'Streaming mode',
				'transport_mode'          => 'streaming',
				'provider_model_resolver' => static fn( array $settings ): array => [
					'provider' => 'openai',
					'model'    => 'gpt-4.1-mini',
				],
				'online_reply_generator'  => static function (): array {
					return [
						'status'      => 'success',
						'next_action' => 'stop',
						'reply'       => 'Streaming fallback reply.',
					];
				},
			]
		);

		$this->assertSame( 'success', $result['status'] );
		$this->assertSame( 'Streaming fallback reply.', $result['assistant_text'] );
		$this->assertArrayHasKey( 'events_cursor', $result );
		$this->assertGreaterThan( 0, (int) $result['events_cursor'] );
		$this->assertNotEmpty( $GLOBALS['wpdb']->events );
	}

	public function test_run_turn_streaming_mode_emits_live_delta_without_persisting_delta_events(): void {
		$stream_events = [];

		$result = Agent_Loop_Helper::get_instance()->run_turn(
			[
				'run_id'                  => 155,
				'session_id'              => 177,
				'message'                 => 'Stream live delta',
				'transport_mode'          => 'streaming',
				'stream_event_callback'   => static function ( array $event ) use ( &$stream_events ): void {
					$stream_events[] = $event;
				},
				'provider_model_resolver' => static fn( array $settings ): array => [
					'provider' => 'openai',
					'model'    => 'gpt-4.1-mini',
				],
				'online_reply_generator'  => static function ( array $context, string $provider, string $model, array $turn_request, \ClawPress\Transports\Agent_Event_Sink $event_sink ): array {
					unset( $context, $provider, $model, $turn_request );

					$event_sink->emit(
						[
							'type'    => 'agent.llm.delta',
							'payload' => [
								'text' => 'Hello ',
							],
						]
					);
					$event_sink->emit(
						[
							'type'    => 'agent.tool_call',
							'payload' => [
								'tool_name' => 'file_read',
								'status'    => 'success',
								'round'     => 1,
								'sequence'  => 1,
							],
						]
					);

					return [
						'status'      => 'success',
						'next_action' => 'stop',
						'reply'       => 'Hello world',
					];
				},
			]
		);

		$this->assertSame( 'success', $result['status'] );
		$this->assertSame( 'Hello world', $result['assistant_text'] );
		$this->assertCount( 4, $stream_events );
		$this->assertSame( 'agent.run.started', $stream_events[0]['type'] );
		$this->assertSame( 'agent.llm.delta', $stream_events[1]['type'] );
		$this->assertSame( 'agent.tool_call', $stream_events[2]['type'] );
		$this->assertSame( 'agent.run.finished', $stream_events[3]['type'] );

		$persisted_event_types = array_values(
			array_map(
				static fn( array $event ): string => (string) ( $event['event_type'] ?? '' ),
				$GLOBALS['wpdb']->events
			)
		);
		$this->assertContains( 'agent.tool_call', $persisted_event_types );
		$this->assertNotContains( 'agent.llm.delta', $persisted_event_types );
	}

	public function test_run_turn_fills_terminal_empty_assistant_text_with_friendly_message(): void {
		$result = Agent_Loop_Helper::get_instance()->run_turn(
			[
				'message'                 => 'Summarize files',
				'trigger'                 => 'chat',
				'transport_mode'          => 'polling',
				'requesting_user_id'      => 1,
				'execution_user_id'       => 1,
				'provider_model_resolver' => static fn( array $settings ): array => [
					'provider' => 'openai',
					'model'    => 'gpt-4.1-mini',
				],
				'online_reply_generator'  => static function (): array {
					return [
						'status'      => 'success',
						'next_action' => 'stop',
						'reply'       => '',
						'tool_calls'  => [
							[
								'name'     => 'file_read',
								'status'   => 'success',
								'round'    => 1,
								'sequence' => 1,
							],
						],
					];
				},
			]
		);

		$this->assertSame( 'success', $result['status'] );
		$this->assertSame(
			'I finished the background steps, but I did not receive a final text response. Please tell me to continue and I will pick up from here.',
			$result['assistant_text']
		);
	}

	public function test_emit_transport_stream_delta_ignores_function_call_argument_payloads(): void {
		$stream_events = [];
		$helper        = Agent_Loop_Helper::get_instance();
		$reflection    = new \ReflectionClass( Agent_Loop_Helper::class );
		$method        = $reflection->getMethod( 'emit_transport_stream_delta' );
		$method->setAccessible( true );
		$event_sink = new Agent_Event_Sink(
			static function ( array $event ) use ( &$stream_events ): void {
				$stream_events[] = $event;
			}
		);

		$method->invoke(
			$helper,
			new \WP_AI_Client_SSE_Event(
				'response.function_call_arguments.delta',
				'{"type":"response.function_call_arguments.delta","delta":"{}"}'
			),
			$event_sink
		);
		$method->invoke(
			$helper,
			new \WP_AI_Client_SSE_Event(
				'response.output_text.delta',
				'{"type":"response.output_text.delta","delta":"Files:"}'
			),
			$event_sink
		);

		$this->assertCount( 1, $stream_events );
		$this->assertSame( 'agent.llm.delta', $stream_events[0]['type'] );
		$this->assertSame( 'Files:', $stream_events[0]['payload']['text'] );
	}

	public function test_emit_transport_stream_delta_keeps_legacy_top_level_text_deltas(): void {
		$stream_events = [];
		$helper        = Agent_Loop_Helper::get_instance();
		$reflection    = new \ReflectionClass( Agent_Loop_Helper::class );
		$method        = $reflection->getMethod( 'emit_transport_stream_delta' );
		$method->setAccessible( true );
		$event_sink = new Agent_Event_Sink(
			static function ( array $event ) use ( &$stream_events ): void {
				$stream_events[] = $event;
			}
		);

		$method->invoke(
			$helper,
			new \WP_AI_Client_SSE_Event(
				'',
				'{"delta":"Files:"}'
			),
			$event_sink
		);

		$this->assertCount( 1, $stream_events );
		$this->assertSame( 'Files:', $stream_events[0]['payload']['text'] );
	}

	public function test_run_slice_returns_in_progress_with_resume_cursor(): void {
		$result = Agent_Loop_Helper::get_instance()->run_slice(
			[
				'run_id'                  => 100,
				'session_id'              => 200,
				'message'                 => 'Slice budget test',
				'transport_mode'          => 'polling',
				'slice_budget_ms'         => 1,
				'max_steps_per_slice'     => 1,
				'provider_model_resolver' => static fn( array $settings ): array => [
					'provider' => 'openai',
					'model'    => 'gpt-4.1-mini',
				],
				'online_reply_generator'  => static function (): array {
					return [
						'status'        => 'in_progress',
						'next_action'   => 'continue_later',
						'reply'         => 'Paused slice',
						'resume_cursor' => [
							'version' => 1,
							'round'   => 1,
						],
					];
				},
			]
		);

		$this->assertSame( 'in_progress', $result['status'] );
		$this->assertSame( 'continue_later', $result['next_action'] );
		$this->assertIsArray( $result['resume_cursor'] );
		$this->assertSame( 1, $result['resume_cursor']['version'] );
	}
}

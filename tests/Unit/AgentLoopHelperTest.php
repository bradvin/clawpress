<?php
/**
 * Tests for agent loop helper runtime behavior.
 *
 * @package ClawPress\Tests
 */

declare( strict_types=1 );

namespace ClawPress\Tests\Unit;

use ClawPress\Helpers\Agent_Loop_Helper;
use ClawPress\Tests\Support\TestCase;

final class AgentLoopHelperTest extends TestCase {
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
}


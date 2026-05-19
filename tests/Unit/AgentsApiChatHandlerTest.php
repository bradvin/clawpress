<?php
/**
 * Tests for Agents API chat handler integration.
 *
 * @package ClawPress\Tests
 */

declare( strict_types=1 );

namespace ClawPress\Tests\Unit;

use ClawPress\AgentsAPI\Agents_API;
use ClawPress\AgentsAPI\Chat_Handler;
use ClawPress\Helpers\Chat_Helper;
use ClawPress\Tests\Support\TestCase;

final class AgentsApiChatHandlerTest extends TestCase {
	public function test_resolve_chat_handler_claims_only_clawpress_agent(): void {
		$module   = new Agents_API();
		$existing = static fn(): array => [];

		$this->assertSame(
			$existing,
			$module->resolve_chat_handler(
				$existing,
				[
					'agent'   => Agents_API::AGENT_SLUG,
					'message' => 'hello',
				]
			)
		);
		$this->assertNull(
			$module->resolve_chat_handler(
				null,
				[
					'agent'   => 'other-agent',
					'message' => 'hello',
				]
			)
		);

		$handler = $module->resolve_chat_handler(
			null,
			[
				'agent'   => Agents_API::AGENT_SLUG,
				'message' => 'hello',
			]
		);

		$this->assertIsArray( $handler );
		$this->assertInstanceOf( Chat_Handler::class, $handler[0] );
		$this->assertSame( 'handle', $handler[1] );
	}

	public function test_handle_maps_clawpress_reply_to_canonical_agents_chat_response(): void {
		$handler = new Chat_Handler(
			Chat_Helper::create_for_testing(
				null,
				static fn(): string => 'Assistant reply.',
				static fn( array $settings ): array => [
					'provider' => 'openai',
					'model'    => 'gpt-4.1-mini',
				]
			)
		);

		$response = $handler->handle(
			[
				'agent'          => Agents_API::AGENT_SLUG,
				'message'        => 'Hello',
				'session_id'     => 'channel-session-1',
				'client_context' => [
					'source'      => 'rest',
					'client_name' => 'phpunit',
				],
			]
		);

		$this->assertIsArray( $response );
		$this->assertSame( 'channel-session-1', $response['session_id'] );
		$this->assertSame( 'Assistant reply.', $response['reply'] );
		$this->assertSame(
			[
				[
					'role'    => 'assistant',
					'content' => 'Assistant reply.',
				],
			],
			$response['messages']
		);
		$this->assertTrue( $response['completed'] );
		$this->assertSame( 'clawpress', $response['metadata']['source'] );
		$this->assertSame( 'online', $response['metadata']['mode'] );
		$this->assertSame( 'openai', $response['metadata']['provider'] );
		$this->assertSame( 'gpt-4.1-mini', $response['metadata']['model'] );
		$this->assertSame( 'rest', $response['metadata']['client_context']['source'] );
	}

	public function test_handle_marks_confirmation_card_response_incomplete(): void {
		$handler = new Chat_Handler(
			Chat_Helper::create_for_testing(
				null,
				static fn(): array => [
					'reply' => 'Please confirm this action.',
					'card'  => [
						'type' => 'user_confirmation',
						'data' => [
							'message' => 'Confirm or decline.',
						],
					],
				],
				static fn( array $settings ): array => [
					'provider' => 'openai',
					'model'    => 'gpt-4.1-mini',
				]
			)
		);

		$response = $handler->handle(
			[
				'agent'   => Agents_API::AGENT_SLUG,
				'message' => 'Delete the file',
			]
		);

		$this->assertIsArray( $response );
		$this->assertFalse( $response['completed'] );
		$this->assertSame( 'user_confirmation', $response['metadata']['card']['type'] );
	}

	public function test_handle_rejects_empty_message(): void {
		$handler = new Chat_Handler();
		$result  = $handler->handle(
			[
				'agent'   => Agents_API::AGENT_SLUG,
				'message' => ' ',
			]
		);

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'clawpress_agents_chat_empty_message', $result->get_error_code() );
	}
}

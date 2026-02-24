<?php
/**
 * Tests for context helper.
 *
 * @package ClawPress\Tests
 */

declare( strict_types=1 );

namespace ClawPress\Tests\Unit;

use ClawPress\Helpers\Agent_File_Helper;
use ClawPress\Abilities\Abilities;
use ClawPress\Helpers\Context_Helper;
use ClawPress\Helpers\Memory_Helper;
use ClawPress\Tests\Support\TestCase;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Tools\DTO\FunctionDeclaration;

final class ContextHelperTest extends TestCase {
	public function test_build_messages_includes_system_history_and_current_message(): void {
		update_option(
			'clawpress_settings',
			[
				'memory_enabled' => true,
			]
		);
		Memory_Helper::get_instance()->save_long_term_memory( 'Remember the release deadline.' );
		update_option(
			'clawpress_context_skills',
			[
				[
					'name'    => 'wp-rest-api',
					'summary' => 'Build and debug REST routes.',
				],
			]
		);

		$history = [
			[
				'role'    => 'user',
				'content' => 'Previous user prompt',
			],
			[
				'role'    => 'assistant',
				'content' => 'Previous assistant reply',
			],
		];

		$helper   = Context_Helper::get_instance();
		$messages = $helper->build_messages( $history, 'Current message' );

		$this->assertCount( 4, $messages );
		$this->assertSame( 'system', $messages[0]['role'] );
		$this->assertStringContainsString( '# ClawPress', (string) $messages[0]['content'] );
		$this->assertStringContainsString( '# Memory', (string) $messages[0]['content'] );
		$this->assertStringContainsString( '# Skills', (string) $messages[0]['content'] );
		$this->assertSame( 'user', $messages[1]['role'] );
		$this->assertSame( 'assistant', $messages[2]['role'] );
		$this->assertSame( 'user', $messages[3]['role'] );
		$this->assertSame( 'Current message', $messages[3]['content'] );
	}

	public function test_build_model_context_converts_history_to_model_messages(): void {
		( new Abilities() );
		do_action( 'wp_abilities_api_categories_init' );
		do_action( 'wp_abilities_api_init' );

		update_option( 'clawpress_chat_history_1', [
			[
				'id'        => 'msg-1',
				'role'      => 'user',
				'content'   => 'First question',
				'createdAt' => 1,
			],
			[
				'id'        => 'msg-2',
				'role'      => 'assistant',
				'content'   => 'First answer',
				'createdAt' => 2,
			],
		] );

		$helper  = Context_Helper::get_instance();
		$context = $helper->build_model_context( 'Second question' );

		$this->assertArrayHasKey( 'system_prompt', $context );
		$this->assertArrayHasKey( 'history_messages', $context );
		$this->assertArrayHasKey( 'messages', $context );
		$this->assertArrayHasKey( 'tool_declarations', $context );
		$this->assertSame( 'Second question', $context['message'] );
		$this->assertCount( 2, $context['history_messages'] );
		$this->assertInstanceOf( Message::class, $context['history_messages'][0] );
		$this->assertSame( 'user', $context['history_messages'][0]->getRole()->value );
		$this->assertSame( 'model', $context['history_messages'][1]->getRole()->value );
		$this->assertNotEmpty( $context['tool_declarations'] );
		$this->assertInstanceOf( FunctionDeclaration::class, $context['tool_declarations'][0] );
	}

	public function test_build_model_context_trims_leading_non_user_messages(): void {
		update_option( 'clawpress_chat_history_1', [
			[
				'id'        => 'msg-1',
				'role'      => 'assistant',
				'content'   => 'Interim status',
				'createdAt' => 1,
			],
			[
				'id'        => 'msg-2',
				'role'      => 'user',
				'content'   => 'Real request',
				'createdAt' => 2,
			],
			[
				'id'        => 'msg-3',
				'role'      => 'assistant',
				'content'   => 'Acknowledged',
				'createdAt' => 3,
			],
		] );

		$helper  = Context_Helper::get_instance();
		$context = $helper->build_model_context( 'Continue' );

		$this->assertCount( 2, $context['history_messages'] );
		$this->assertSame( 'user', $context['history_messages'][0]->getRole()->value );
		$this->assertSame( 'model', $context['history_messages'][1]->getRole()->value );
	}

	public function test_build_messages_appends_bootstrap_requirement_before_first_assistant_response(): void {
		update_option(
			'clawpress_settings',
			[
				'setup_completed' => true,
			]
		);
		Agent_File_Helper::get_instance()->upsert_file_by_logical_path(
			'BOOTSTRAP.md',
			'# BOOTSTRAP.md - First Run'
		);

		$helper = Context_Helper::get_instance();

		$command_history = [
			[
				'role'    => 'user',
				'content' => '/status',
			],
			[
				'role'    => 'system',
				'content' => 'Status output',
			],
		];

		$messages = $helper->build_messages( $command_history, 'What now?' );
		$this->assertStringContainsString( '## First-Run Requirement', (string) $messages[0]['content'] );

		$assistant_history = [
			[
				'role'    => 'assistant',
				'content' => 'Setup is complete.',
			],
		];

		$messages = $helper->build_messages( $assistant_history, 'What now?' );
		$this->assertStringNotContainsString( '## First-Run Requirement', (string) $messages[0]['content'] );
	}
}

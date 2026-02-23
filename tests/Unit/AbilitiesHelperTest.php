<?php
/**
 * Tests for abilities helper and registration.
 *
 * @package ClawPress\Tests
 */

declare( strict_types=1 );

namespace ClawPress\Tests\Unit;

use ClawPress\Abilities\Abilities;
use ClawPress\Helpers\Abilities_Helper;
use ClawPress\Tests\Support\TestCase;
use WordPress\AiClient\Tools\DTO\FunctionDeclaration;

/**
 * Minimal wpdb stub for abilities helper agent-event writes.
 */
final class AbilitiesHelperTestWpdb {
	/**
	 * Table prefix.
	 *
	 * @var string
	 */
	public string $prefix = 'wp_';

	/**
	 * Captured insert calls.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	public array $insert_calls = [];

	/**
	 * Capture insert operation.
	 *
	 * @param string              $table Table name.
	 * @param array<string,mixed> $data Insert row.
	 * @param array<int,string>   $format Insert formats.
	 */
	public function insert( string $table, array $data, array $format ) {
		$this->insert_calls[] = [
			'table'  => $table,
			'data'   => $data,
			'format' => $format,
		];

		return 1;
	}
}

final class AbilitiesHelperTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['wpdb'] = new AbilitiesHelperTestWpdb();
		( new Abilities() );
		do_action( 'wp_abilities_api_categories_init' );
		do_action( 'wp_abilities_api_init' );
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}

	public function test_tool_declarations_are_built_from_registered_allowlist(): void {
		$declarations = Abilities_Helper::get_instance()->get_tool_declarations();

		$this->assertCount( 10, $declarations );
		$this->assertInstanceOf( FunctionDeclaration::class, $declarations[0] );
		$this->assertContains( 'file_read', array_map( static fn( FunctionDeclaration $item ): string => $item->getName(), $declarations ) );
		$this->assertContains( 'memory_long_term_delete', array_map( static fn( FunctionDeclaration $item ): string => $item->getName(), $declarations ) );
	}

	public function test_destructive_tool_requires_confirmation_before_execution(): void {
		$result = Abilities_Helper::get_instance()->execute_tool_call(
			'memory_long_term_delete',
			[],
			[
				'requesting_user_id' => 1,
				'execution_user_id'  => 1,
			]
		);

		$this->assertFalse( $result['success'] );
		$this->assertTrue( $result['requires_confirmation'] );
		$this->assertSame( 'clawpress_confirmation_required', $result['error']['code'] );
		$this->assertNotEmpty( $result['error']['token'] );
	}

	public function test_tool_execution_logs_requesting_and_execution_actors(): void {
		$result = Abilities_Helper::get_instance()->execute_tool_call(
			'file_list',
			[],
			[
				'requesting_user_id' => 12,
				'execution_user_id'  => 9,
			]
		);

		$this->assertTrue( $result['success'] );
		$this->assertNotEmpty( $GLOBALS['wpdb']->insert_calls );
		$this->assertSame( 'wp_clawpress_agent_events', $GLOBALS['wpdb']->insert_calls[0]['table'] );
		$this->assertSame( 'tool_call', $GLOBALS['wpdb']->insert_calls[0]['data']['event_type'] );
		$payload = json_decode( (string) $GLOBALS['wpdb']->insert_calls[0]['data']['payload_json'], true );
		$this->assertIsArray( $payload );
		$this->assertSame( 12, $payload['requesting_user_id'] );
		$this->assertSame( 9, $payload['execution_user_id'] );
	}

	public function test_destructive_confirmation_token_must_be_allowlisted_by_execution_context(): void {
		$initial = Abilities_Helper::get_instance()->execute_tool_call(
			'file_delete',
			[
				'path' => 'notes.md',
			],
			[
				'requesting_user_id' => 1,
				'execution_user_id'  => 1,
			]
		);

		$this->assertTrue( $initial['requires_confirmation'] );
		$token = (string) $initial['error']['token'];
		$this->assertNotSame( '', $token );

		$blocked = Abilities_Helper::get_instance()->execute_tool_call(
			'file_delete',
			[
				'path'          => 'notes.md',
				'confirm'       => true,
				'confirm_token' => $token,
			],
			[
				'requesting_user_id'          => 1,
				'execution_user_id'           => 1,
				'allowed_confirmation_tokens' => [ 'different-token' ],
			]
		);

		$this->assertTrue( $blocked['requires_confirmation'] );
		$this->assertSame( 'clawpress_confirmation_required', $blocked['error']['code'] );

		$replacement_token = (string) ( $blocked['error']['token'] ?? '' );
		$this->assertNotSame( '', $replacement_token );

		$allowed = Abilities_Helper::get_instance()->execute_tool_call(
			'file_delete',
			[
				'path'          => 'notes.md',
				'confirm'       => true,
				'confirm_token' => $replacement_token,
			],
			[
				'requesting_user_id'          => 1,
				'execution_user_id'           => 1,
				'allowed_confirmation_tokens' => [ $replacement_token ],
			]
		);

		$this->assertFalse( ! empty( $allowed['requires_confirmation'] ) );
		$this->assertArrayNotHasKey( 'requires_confirmation', $allowed );
	}
}

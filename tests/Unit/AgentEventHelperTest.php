<?php
/**
 * Tests for agent event helper.
 *
 * @package ClawPress\Tests
 */

declare( strict_types=1 );

namespace {
	if ( ! function_exists( 'dbDelta' ) ) {
		/**
		 * Capture dbDelta calls for unit tests.
		 *
		 * @param string $queries SQL statements.
		 * @return array<int,string>
		 */
		function dbDelta( string $queries ): array {
			if ( ! isset( $GLOBALS['clawpress_test_dbdelta_queries'] ) || ! is_array( $GLOBALS['clawpress_test_dbdelta_queries'] ) ) {
				$GLOBALS['clawpress_test_dbdelta_queries'] = [];
			}

			$GLOBALS['clawpress_test_dbdelta_queries'][] = $queries;
			return [];
		}
	}
}

namespace ClawPress\Tests\Unit {

use ClawPress\Helpers\Agent_Event_Helper;
use ClawPress\Plugin;
use ClawPress\Stores\Agent_Event_Store;
use ClawPress\Tests\Support\TestCase;

/**
 * Minimal wpdb stub for agent event helper tests.
 */
final class AgentEventHelperTestWpdb {
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
	 * Captured prepared args.
	 *
	 * @var array<int,mixed>
	 */
	public array $last_prepare_args = [];

	/**
	 * Prepared query result set.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	public array $results = [];

	/**
	 * Insert id stub.
	 */
	public int $insert_id = 0;

	/**
	 * Get charset/collation SQL.
	 */
	public function get_charset_collate(): string {
		return 'DEFAULT CHARSET=utf8mb4';
	}

	/**
	 * Capture insert operation.
	 *
	 * @param string              $table Table name.
	 * @param array<string,mixed> $data Insert row.
	 * @param array<int,string>   $format Insert formats.
	 */
	public function insert( string $table, array $data, array $format ) {
		$this->insert_id++;
		$this->insert_calls[] = [
			'table'  => $table,
			'data'   => $data,
			'format' => $format,
		];

		return 1;
	}

	/**
	 * Capture prepared SQL call.
	 *
	 * @param string                   $query Query string.
	 * @param array<int,mixed>|mixed ...$args Prepare args.
	 */
	public function prepare( string $query, ...$args ): string {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}

		$prepared_query           = $query;
		$non_identifier_arguments = [];

		foreach ( $args as $argument ) {
			if ( false !== strpos( $prepared_query, '%i' ) ) {
				$identifier      = preg_replace( '/[^A-Za-z0-9_]/', '', (string) $argument );
				$prepared_query = preg_replace( '/%i/', (string) $identifier, $prepared_query, 1 ) ?? $prepared_query;
				continue;
			}

			$non_identifier_arguments[] = $argument;
		}

		$this->last_prepare_args = $non_identifier_arguments;
		return $prepared_query;
	}

	/**
	 * Return prepared query rows.
	 *
	 * @param string $query Query string.
	 * @param string $output Output mode.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_results( string $query, string $output ): array {
		unset( $query, $output );
		return $this->results;
	}
}

final class AgentEventHelperTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['clawpress_test_dbdelta_queries'] = [];
		$GLOBALS['wpdb'] = new AgentEventHelperTestWpdb();
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'], $GLOBALS['clawpress_test_dbdelta_queries'] );
		parent::tearDown();
	}

	public function test_store_create_table_registers_clawpress_agent_events_schema(): void {
		$result = Agent_Event_Store::get_instance()->create_table();

		$this->assertTrue( $result );
		$this->assertIsArray( $GLOBALS['clawpress_test_dbdelta_queries'] );
		$this->assertNotEmpty( $GLOBALS['clawpress_test_dbdelta_queries'] );
		$this->assertStringContainsString( 'clawpress_agent_events', (string) $GLOBALS['clawpress_test_dbdelta_queries'][0] );
		$this->assertStringContainsString( 'payload_json', (string) $GLOBALS['clawpress_test_dbdelta_queries'][0] );
	}

	public function test_helper_does_not_expose_schema_methods(): void {
		$this->assertFalse( method_exists( Agent_Event_Helper::class, 'create_table' ) );
		$this->assertFalse( method_exists( Agent_Event_Helper::class, 'get_table_name' ) );
	}

	public function test_plugin_activation_creates_agent_event_table(): void {
		Plugin::activate();

		$this->assertNotEmpty( $GLOBALS['clawpress_test_dbdelta_queries'] );
		$all_queries = implode( "\n", array_map( 'strval', $GLOBALS['clawpress_test_dbdelta_queries'] ) );
		$this->assertStringContainsString( 'clawpress_agent_events', $all_queries );
	}

	public function test_emit_tool_call_persists_row_into_agent_event_table(): void {
		$event_id = Agent_Event_Helper::get_instance()->emit_tool_call(
			'file_list',
			'clawpress/file-list',
			12,
			9,
			'success',
			'abc123',
			[
				'success' => true,
				'result'  => [ 'items' => [ 'README.md' ] ],
			],
			[
				'run_id'     => 77,
				'session_id' => 11,
			]
		);

		$this->assertSame( 1, $event_id );
		$this->assertCount( 1, $GLOBALS['wpdb']->insert_calls );
		$this->assertSame( 'wp_clawpress_agent_events', $GLOBALS['wpdb']->insert_calls[0]['table'] );
		$this->assertSame( 'tool_call', $GLOBALS['wpdb']->insert_calls[0]['data']['event_type'] );
		$this->assertSame( 77, $GLOBALS['wpdb']->insert_calls[0]['data']['run_id'] );
		$this->assertSame( 11, $GLOBALS['wpdb']->insert_calls[0]['data']['session_id'] );

		$payload = json_decode( (string) $GLOBALS['wpdb']->insert_calls[0]['data']['payload_json'], true );
		$this->assertIsArray( $payload );
		$this->assertSame( 'file_list', $payload['tool_name'] );
		$this->assertSame( 'clawpress/file-list', $payload['ability_name'] );
		$this->assertSame( 12, $payload['requesting_user_id'] );
		$this->assertSame( 9, $payload['execution_user_id'] );
	}

	public function test_get_run_events_returns_normalized_incremental_rows(): void {
		$GLOBALS['wpdb']->results = [
			[
				'id'            => '14',
				'run_id'        => '77',
				'session_id'    => '11',
				'event_type'    => 'tool_call',
				'payload_json'  => '{"tool_name":"file_list","success":true}',
				'created_at_gmt' => '2026-02-20 10:00:00',
			],
		];

		$rows = Agent_Event_Helper::get_instance()->get_run_events( 77, 10, 25 );

		$this->assertCount( 1, $rows );
		$this->assertSame( 14, $rows[0]['event_id'] );
		$this->assertSame( 77, $rows[0]['run_id'] );
		$this->assertSame( 11, $rows[0]['session_id'] );
		$this->assertSame( true, $rows[0]['payload']['success'] );
		$this->assertSame( [ 10, 77, 25 ], $GLOBALS['wpdb']->last_prepare_args );
	}
}
}

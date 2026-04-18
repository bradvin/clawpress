<?php
/**
 * Tests for action log helper.
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

use ClawPress\Helpers\Action_Log_Helper;
use ClawPress\Plugin;
use ClawPress\Stores\Action_Log_Store;
use ClawPress\Tests\Support\TestCase;
use ClawPress\Tests\Support\WordPress_Stubs;

/**
 * Minimal wpdb stub for action log helper tests.
 */
final class ActionLogHelperTestWpdb {
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
	 * Prepared query result queue.
	 *
	 * @var array<int,array<int,array<string,mixed>>>
	 */
	public array $results_queue = [];

	/**
	 * Captured raw query calls.
	 *
	 * @var array<int,string>
	 */
	public array $query_calls = [];

	/**
	 * Raw query return value.
	 *
	 * @var int|false
	 */
	public $query_result = 0;

	/**
	 * Get charset/collation SQL.
	 */
	public function get_charset_collate(): string {
		return 'DEFAULT CHARSET=utf8mb4';
	}

	/**
	 * Capture insert operation.
	 *
	 * @param string              $table  Table name.
	 * @param array<string,mixed> $data   Insert row.
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

	/**
	 * Capture prepared SQL call.
	 *
	 * @param string              $query Query string.
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

		if ( [] !== $this->results_queue ) {
			return array_shift( $this->results_queue );
		}

		return $this->results;
	}

	/**
	 * Capture raw SQL query calls.
	 *
	 * @param string $query Query string.
	 * @return int|false
	 */
	public function query( string $query ) {
		$this->query_calls[] = $query;
		return $this->query_result;
	}
}

final class ActionLogHelperTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['clawpress_test_dbdelta_queries'] = [];
		$GLOBALS['wpdb'] = new ActionLogHelperTestWpdb();
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'], $GLOBALS['clawpress_test_dbdelta_queries'] );
		parent::tearDown();
	}

	public function test_store_create_table_registers_clawpress_action_logs_schema(): void {
		$result = Action_Log_Store::get_instance()->create_table();

		$this->assertTrue( $result );
		$this->assertIsArray( $GLOBALS['clawpress_test_dbdelta_queries'] );
		$this->assertNotEmpty( $GLOBALS['clawpress_test_dbdelta_queries'] );
		$this->assertStringContainsString( 'clawpress_action_logs', (string) $GLOBALS['clawpress_test_dbdelta_queries'][0] );
		$this->assertStringContainsString( 'action_name', (string) $GLOBALS['clawpress_test_dbdelta_queries'][0] );
	}

	public function test_helper_does_not_expose_schema_methods(): void {
		$this->assertFalse( method_exists( Action_Log_Helper::class, 'create_table' ) );
		$this->assertFalse( method_exists( Action_Log_Helper::class, 'get_table_name' ) );
	}

	public function test_plugin_activation_creates_action_log_table(): void {
		Plugin::activate();

		$this->assertNotEmpty( $GLOBALS['clawpress_test_dbdelta_queries'] );
		$this->assertStringContainsString( 'clawpress_action_logs', (string) $GLOBALS['clawpress_test_dbdelta_queries'][0] );
	}

	public function test_plugin_activation_initializes_default_panel_state_for_current_user_when_missing(): void {
		WordPress_Stubs::$current_user_id = 27;

		Plugin::activate();

		$this->assertArrayHasKey( 27, WordPress_Stubs::$user_meta );
		$this->assertArrayHasKey( 'clawpress_panel_state', WordPress_Stubs::$user_meta[27] );
		$this->assertSame(
			[
				'open'              => true,
				'width'             => 420,
				'last_history_id'   => '',
				'welcome_card_seen' => false,
			],
			WordPress_Stubs::$user_meta[27]['clawpress_panel_state']
		);
	}

	public function test_plugin_activation_does_not_overwrite_existing_panel_state(): void {
		WordPress_Stubs::$current_user_id = 48;
		WordPress_Stubs::$user_meta[48]   = [
			'clawpress_panel_state' => [
				'open'              => false,
				'width'             => 600,
				'last_history_id'   => 'history-2',
				'welcome_card_seen' => true,
			],
		];

		Plugin::activate();

		$this->assertSame(
			[
				'open'              => false,
				'width'             => 600,
				'last_history_id'   => 'history-2',
				'welcome_card_seen' => true,
			],
			WordPress_Stubs::$user_meta[48]['clawpress_panel_state']
		);
	}

	public function test_plugin_activation_skips_panel_state_when_no_authenticated_user(): void {
		WordPress_Stubs::$current_user_id = 0;

		Plugin::activate();

		$this->assertArrayNotHasKey( 0, WordPress_Stubs::$user_meta );
	}

	public function test_log_event_persists_row_into_action_log_table(): void {
		$helper = Action_Log_Helper::get_instance();
		$result = $helper->log_event(
			'tool.execute',
			[
				'event_type'         => 'tool_call',
				'status'             => 'success',
				'message'            => 'Ability executed.',
				'requesting_user_id' => 12,
				'execution_user_id'  => 9,
				'context'            => [
					'ability' => 'create_workspace',
				],
			]
		);

		$this->assertTrue( $result );
		$this->assertCount( 1, $GLOBALS['wpdb']->insert_calls );
		$this->assertSame( 'wp_clawpress_action_logs', $GLOBALS['wpdb']->insert_calls[0]['table'] );
		$this->assertSame( 'tool.execute', $GLOBALS['wpdb']->insert_calls[0]['data']['action_name'] );
		$this->assertSame( 'tool_call', $GLOBALS['wpdb']->insert_calls[0]['data']['event_type'] );
		$this->assertSame( 'success', $GLOBALS['wpdb']->insert_calls[0]['data']['status'] );
		$this->assertSame( 12, $GLOBALS['wpdb']->insert_calls[0]['data']['requesting_user_id'] );
		$this->assertSame( 9, $GLOBALS['wpdb']->insert_calls[0]['data']['execution_user_id'] );
		$this->assertIsString( $GLOBALS['wpdb']->insert_calls[0]['data']['context'] );
	}

	public function test_get_recent_logs_returns_normalized_rows(): void {
		$GLOBALS['wpdb']->results = [
			[
				'id'                 => '3',
				'event_type'         => 'tool_call',
				'action_name'        => 'tool.execute',
				'status'             => 'success',
				'message'            => 'Done',
				'requesting_user_id' => '12',
				'execution_user_id'  => '9',
				'context'            => '{"ability":"create_workspace"}',
				'created_at'         => '2026-02-15 10:00:00',
			],
		];

		$helper = Action_Log_Helper::get_instance();
		$rows   = $helper->get_recent_logs(
			[
				'event_type' => 'tool_call',
				'limit'      => 10,
			]
		);

		$this->assertCount( 1, $rows );
		$this->assertSame( 3, $rows[0]['id'] );
		$this->assertSame( 'tool_call', $rows[0]['event_type'] );
		$this->assertSame( 12, $rows[0]['requesting_user_id'] );
		$this->assertSame( 9, $rows[0]['execution_user_id'] );
		$this->assertSame( [ 'ability' => 'create_workspace' ], $rows[0]['context'] );
		$this->assertSame( [ 'tool_call', 10, 0 ], $GLOBALS['wpdb']->last_prepare_args );
	}

	public function test_get_log_counts_by_type_returns_normalized_counts(): void {
		$GLOBALS['wpdb']->results = [
			[
				'event_type' => 'command',
				'total'      => '2',
			],
			[
				'event_type' => 'tool_call',
				'total'      => '7',
			],
		];

		$counts = Action_Log_Helper::get_instance()->get_log_counts_by_type();

		$this->assertSame(
			[
				'command'   => 2,
				'tool_call' => 7,
			],
			$counts
		);
	}

	public function test_get_log_count_returns_total_for_optional_event_type(): void {
		$GLOBALS['wpdb']->results = [
			[
				'total' => '4',
			],
		];

		$total = Action_Log_Helper::get_instance()->get_log_count( 'tool_call' );

		$this->assertSame( 4, $total );
		$this->assertSame( [ 'tool_call', 'tool_call' ], $GLOBALS['wpdb']->last_prepare_args );
	}

	public function test_delete_all_logs_runs_delete_query_and_returns_deleted_count(): void {
		$GLOBALS['wpdb']->query_result = 9;

		$deleted = Action_Log_Helper::get_instance()->delete_all_logs();

		$this->assertSame( 9, $deleted );
		$this->assertCount( 1, $GLOBALS['wpdb']->query_calls );
		$this->assertStringContainsString( 'DELETE FROM wp_clawpress_action_logs', $GLOBALS['wpdb']->query_calls[0] );
	}

	public function test_handle_tool_call_logged_persists_generic_summary_with_run_and_session_context(): void {
		Action_Log_Helper::handle_tool_call_logged(
			[
				'tool_name'           => 'file_read',
				'ability_name'        => 'clawpress/file-read',
				'requesting_user_id'  => 12,
					'execution_user_id'   => 9,
					'status'              => 'success',
					'args_hash'           => 'abc123',
					'args'                => [
						'path' => 'README.md',
					],
					'payload'             => [
					'success' => true,
					'result'  => [
						'path'    => 'README.md',
						'content' => 'Hello',
					],
				],
				'event_context'       => [
					'run_id'     => 77,
					'session_id' => 11,
				],
			]
		);

		$this->assertCount( 1, $GLOBALS['wpdb']->insert_calls );
		$this->assertSame( 'wp_clawpress_action_logs', $GLOBALS['wpdb']->insert_calls[0]['table'] );
		$this->assertSame( 'file_read', $GLOBALS['wpdb']->insert_calls[0]['data']['action_name'] );
		$this->assertSame( 'tool_call', $GLOBALS['wpdb']->insert_calls[0]['data']['event_type'] );
		$this->assertSame( 'success', $GLOBALS['wpdb']->insert_calls[0]['data']['status'] );
		$this->assertSame( 12, $GLOBALS['wpdb']->insert_calls[0]['data']['requesting_user_id'] );
		$this->assertSame( 9, $GLOBALS['wpdb']->insert_calls[0]['data']['execution_user_id'] );

			$context = json_decode( (string) $GLOBALS['wpdb']->insert_calls[0]['data']['context'], true );
			$this->assertIsArray( $context );
			$this->assertSame( 'file_read', $context['tool'] );
			$this->assertSame( 'clawpress/file-read', $context['ability'] );
			$this->assertSame( [ 'path' => 'README.md' ], $context['request'] );
			$this->assertSame( true, $context['response']['success'] );
			$this->assertSame( 'README.md', $context['response']['result']['path'] );
			$this->assertSame( 77, $context['run_id'] );
			$this->assertSame( 11, $context['session_id'] );
			$this->assertSame( [ 'path', 'content' ], $context['result_keys'] );
	}
}
}

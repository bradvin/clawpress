<?php
/**
 * Tests for logs REST controller.
 *
 * @package ClawPress\Tests
 */

declare( strict_types=1 );

namespace ClawPress\Tests\Unit;

use ClawPress\RestAPI\Controllers\Logs_Controller;
use ClawPress\Tests\Support\TestCase;

/**
 * Minimal wpdb stub for logs controller tests.
 */
final class LogsControllerTestWpdb {
	/**
	 * Table prefix.
	 *
	 * @var string
	 */
	public string $prefix = 'wp_';

	/**
	 * Prepared args from the last call.
	 *
	 * @var array<int,mixed>
	 */
	public array $last_prepare_args = [];

	/**
	 * Queued result sets for get_results calls.
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
	 * Capture prepared SQL calls.
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
				$identifier       = preg_replace( '/[^A-Za-z0-9_]/', '', (string) $argument );
				$prepared_query = preg_replace( '/%i/', (string) $identifier, $prepared_query, 1 ) ?? $prepared_query;
				continue;
			}

			$non_identifier_arguments[] = $argument;
		}

		$this->last_prepare_args = $non_identifier_arguments;
		return $prepared_query;
	}

	/**
	 * Return the next queued result set.
	 *
	 * @param string $query Query string.
	 * @param string $output Output mode.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_results( string $query, string $output ): array {
		unset( $query, $output );
		return [] !== $this->results_queue
			? array_shift( $this->results_queue )
			: [];
	}

	/**
	 * Capture delete query calls.
	 *
	 * @param string $query Query string.
	 * @return int|false
	 */
	public function query( string $query ) {
		$this->query_calls[] = $query;
		return $this->query_result;
	}
}

final class LogsControllerTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['wpdb'] = new LogsControllerTestWpdb();
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}

	public function test_get_logs_returns_filtered_rows_counts_and_pagination_metadata(): void {
		$GLOBALS['wpdb']->results_queue = [
			[
				[
					'event_type' => 'command',
					'total'      => '1',
				],
				[
					'event_type' => 'tool_call',
					'total'      => '3',
				],
			],
			[
				[
					'id'                 => '7',
					'event_type'         => 'tool_call',
					'action_name'        => 'file.read',
					'status'             => 'success',
					'message'            => 'Read completed.',
					'requesting_user_id' => '12',
					'execution_user_id'  => '9',
					'context'            => '{"path":"README.md"}',
					'created_at'         => '2026-04-12 10:15:00',
				],
				[
					'id'                 => '6',
					'event_type'         => 'tool_call',
					'action_name'        => 'file.list',
					'status'             => 'warning',
					'message'            => 'List truncated.',
					'requesting_user_id' => '12',
					'execution_user_id'  => '9',
					'context'            => '{"path":"."}',
					'created_at'         => '2026-04-12 10:14:00',
				],
			],
		];

		$response = ( new Logs_Controller() )->get_logs(
			new \WP_REST_Request(
				[
					'event_type' => 'tool_call',
					'limit'      => 2,
					'offset'     => 0,
				]
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 3, $data['counts_by_type']['tool_call'] );
		$this->assertSame( 3, $data['total'] );
		$this->assertSame( 2, $data['limit'] );
		$this->assertSame( 0, $data['offset'] );
		$this->assertTrue( $data['has_more'] );
		$this->assertCount( 2, $data['items'] );
		$this->assertSame( 'tool_call', $data['items'][0]['event_type'] );
		$this->assertSame( [ 'tool_call', 2, 0 ], $GLOBALS['wpdb']->last_prepare_args );
	}

	public function test_clear_logs_returns_success_and_deleted_count(): void {
		$GLOBALS['wpdb']->query_result = 5;

		$response = ( new Logs_Controller() )->clear_logs();
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			[
				'success' => true,
				'deleted' => 5,
			],
			$data
		);
		$this->assertCount( 1, $GLOBALS['wpdb']->query_calls );
		$this->assertStringContainsString( 'DELETE FROM wp_clawpress_action_logs', $GLOBALS['wpdb']->query_calls[0] );
	}

	public function test_get_linked_events_returns_run_scoped_events_when_run_id_is_present(): void {
		$GLOBALS['wpdb']->results_queue = [
			[
				[
					'id'             => '14',
					'run_id'         => '77',
					'session_id'     => '11',
					'event_type'     => 'tool_call',
					'payload_json'   => '{"tool_name":"file_read","success":true}',
					'created_at_gmt' => '2026-04-12 10:10:00',
				],
				[
					'id'             => '15',
					'run_id'         => '77',
					'session_id'     => '11',
					'event_type'     => 'agent.runner.slice_completed',
					'payload_json'   => '{"status":"done"}',
					'created_at_gmt' => '2026-04-12 10:11:00',
				],
			],
		];

		$response = ( new Logs_Controller() )->get_linked_events(
			new \WP_REST_Request(
				[
					'run_id'     => 77,
					'session_id' => 11,
					'after'      => 10,
					'limit'      => 2,
				]
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'run', $data['scope'] );
		$this->assertSame( 77, $data['run_id'] );
		$this->assertSame( 11, $data['session_id'] );
		$this->assertSame( 10, $data['after'] );
		$this->assertSame( 15, $data['next_cursor'] );
		$this->assertTrue( $data['has_more'] );
		$this->assertCount( 2, $data['events'] );
		$this->assertSame( [ 10, 2 ], $GLOBALS['wpdb']->last_prepare_args );
	}

	public function test_get_linked_events_requires_run_or_session_identifier(): void {
		$response = ( new Logs_Controller() )->get_linked_events(
			new \WP_REST_Request()
		);

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame(
			[
				'error' => 'A run ID or session ID is required.',
			],
			$response->get_data()
		);
	}
}

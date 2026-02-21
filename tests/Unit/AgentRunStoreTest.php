<?php
/**
 * Tests for DB-backed agent session/run stores.
 *
 * @package ClawPress\Tests
 */

declare( strict_types=1 );

namespace {
	if ( ! function_exists( 'dbDelta' ) ) {
		/**
		 * Capture dbDelta queries in tests.
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

use ClawPress\Helpers\Agent_Run_Store;
use ClawPress\Helpers\Agent_Session_Store;
use ClawPress\Plugin;
use ClawPress\Tests\Support\TestCase;

/**
 * Minimal in-memory wpdb stub for run/session store tests.
 */
final class AgentRunStoreTestWpdb {
	public string $prefix = 'wp_';

	public int $insert_id = 0;

	/** @var array<int,array<string,mixed>> */
	public array $sessions = [];

	/** @var array<int,array<string,mixed>> */
	public array $runs = [];

	/** @var array<int,mixed> */
	public array $last_prepare_args = [];

	public function get_charset_collate(): string {
		return 'DEFAULT CHARSET=utf8mb4';
	}

	/**
	 * @param string              $table Table name.
	 * @param array<string,mixed> $data  Data payload.
	 * @param array<int,string>   $format Format list.
	 */
	public function insert( string $table, array $data, array $format ) {
		unset( $format );

		$this->insert_id++;
		$data['id'] = $this->insert_id;

		if ( false !== strpos( $table, 'agent_sessions' ) ) {
			$this->sessions[ $this->insert_id ] = $data;
			return 1;
		}

		if ( false !== strpos( $table, 'agent_runs' ) ) {
			$this->runs[ $this->insert_id ] = $data;
			return 1;
		}

		return false;
	}

	/**
	 * @param string                    $table Table name.
	 * @param array<string,mixed>       $data Data payload.
	 * @param array<string,int|string>  $where Where payload.
	 * @param array<int,string>         $format Formats.
	 * @param array<int,string>         $where_format Where formats.
	 */
	public function update( string $table, array $data, array $where, ?array $format = null, ?array $where_format = null ) {
		unset( $format, $where_format );

		$target = false !== strpos( $table, 'agent_sessions' ) ? 'sessions' : ( false !== strpos( $table, 'agent_runs' ) ? 'runs' : '' );
		if ( '' === $target ) {
			return false;
		}

		$rows    = $this->{$target};
		$updated = 0;

		foreach ( $rows as $id => $row ) {
			$matches = true;
			foreach ( $where as $key => $value ) {
				$current = $row[ $key ] ?? null;
				if ( (string) $current !== (string) $value ) {
					$matches = false;
					break;
				}
			}

			if ( ! $matches ) {
				continue;
			}

			$this->{$target}[ $id ] = array_merge( $row, $data );
			++$updated;
		}

		return $updated;
	}

	/**
	 * @param string              $query Query string.
	 * @param array<int,mixed>|mixed ...$args Prepare args.
	 */
	public function prepare( string $query, ...$args ): string {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}

		$this->last_prepare_args = $args;
		return $query;
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public function get_row( string $query, string $output ) {
		unset( $output );

		$id = 0;
		if ( preg_match( '/WHERE id =\s*(\d+)/', $query, $matches ) ) {
			$id = (int) $matches[1];
		}
		if ( $id <= 0 ) {
			$id = isset( $this->last_prepare_args[0] ) ? (int) $this->last_prepare_args[0] : 0;
		}

		if ( $id <= 0 ) {
			return null;
		}

		if ( false !== strpos( $query, 'agent_sessions' ) ) {
			return $this->sessions[ $id ] ?? null;
		}

		if ( false !== strpos( $query, 'agent_runs' ) ) {
			return $this->runs[ $id ] ?? null;
		}

		return null;
	}
}

final class AgentRunStoreTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['clawpress_test_dbdelta_queries'] = [];
		$GLOBALS['wpdb'] = new AgentRunStoreTestWpdb();
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'], $GLOBALS['clawpress_test_dbdelta_queries'] );
		parent::tearDown();
	}

	public function test_plugin_activation_registers_session_and_run_tables(): void {
		Plugin::activate();

		$all_queries = implode( "\n", $GLOBALS['clawpress_test_dbdelta_queries'] );
		$this->assertStringContainsString( 'clawpress_agent_sessions', $all_queries );
		$this->assertStringContainsString( 'clawpress_agent_runs', $all_queries );
	}

	public function test_claim_run_success(): void {
		$session_id = Agent_Session_Store::get_instance()->create_session();
		$run_id     = Agent_Run_Store::get_instance()->create_run( $session_id );

		$result = Agent_Run_Store::get_instance()->claim_run( $run_id, 'worker-a', 120 );

		$this->assertTrue( $result['claimed'] );
		$this->assertSame( 'running', $GLOBALS['wpdb']->runs[ $run_id ]['status'] );
		$this->assertSame( 'worker-a', $GLOBALS['wpdb']->runs[ $run_id ]['claimed_by'] );
		$this->assertNotEmpty( $GLOBALS['wpdb']->runs[ $run_id ]['lock_token'] );
	}

	public function test_claim_collision_fails_for_second_worker(): void {
		$session_id = Agent_Session_Store::get_instance()->create_session();
		$run_id     = Agent_Run_Store::get_instance()->create_run( $session_id );

		$first  = Agent_Run_Store::get_instance()->claim_run( $run_id, 'worker-a', 120 );
		$second = Agent_Run_Store::get_instance()->claim_run( $run_id, 'worker-b', 120 );

		$this->assertTrue( $first['claimed'] );
		$this->assertFalse( $second['claimed'] );
		$this->assertSame( 'not_claimable', $second['reason'] );
	}

	public function test_stale_lock_can_be_reclaimed_and_attempt_increments(): void {
		$session_id = Agent_Session_Store::get_instance()->create_session();
		$run_id     = Agent_Run_Store::get_instance()->create_run( $session_id );
		$GLOBALS['wpdb']->runs[ $run_id ]['status'] = 'running';
		$GLOBALS['wpdb']->runs[ $run_id ]['attempt'] = 1;
		$GLOBALS['wpdb']->runs[ $run_id ]['lock_expires_at_gmt'] = '2000-01-01 00:00:00';

		$result = Agent_Run_Store::get_instance()->claim_run( $run_id, 'worker-reclaim', 120 );

		$this->assertTrue( $result['claimed'] );
		$this->assertTrue( $result['reclaimed'] );
		$this->assertSame( 2, $result['attempt'] );
		$this->assertSame( 2, (int) $GLOBALS['wpdb']->runs[ $run_id ]['attempt'] );
	}

	public function test_complete_run_clears_lock_and_updates_session_state(): void {
		$session_id = Agent_Session_Store::get_instance()->create_session();
		$run_id     = Agent_Run_Store::get_instance()->create_run( $session_id );
		$claim      = Agent_Run_Store::get_instance()->claim_run( $run_id, 'worker-a', 120 );

		$completed = Agent_Run_Store::get_instance()->complete_run(
			$run_id,
			(string) $claim['lock_token'],
			'success',
			[
				'meta' => [ 'tools' => 3 ],
			]
		);

		$this->assertTrue( $completed );
		$this->assertSame( 'success', $GLOBALS['wpdb']->runs[ $run_id ]['status'] );
		$this->assertNull( $GLOBALS['wpdb']->runs[ $run_id ]['lock_token'] );
		$this->assertSame( 'success', $GLOBALS['wpdb']->sessions[ $session_id ]['last_run_status'] );
		$this->assertSame( 0, (int) $GLOBALS['wpdb']->sessions[ $session_id ]['consecutive_failures'] );
	}
}
}

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

use ClawPress\Helpers\Agent_Run_Helper;
use ClawPress\Helpers\Agent_Session_Helper;
use ClawPress\Plugin;
use ClawPress\Tests\Support\Agent_Runtime_Wpdb;
use ClawPress\Tests\Support\TestCase;

final class AgentRunHelperTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['clawpress_test_dbdelta_queries'] = [];
		$GLOBALS['wpdb'] = new Agent_Runtime_Wpdb();
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
		$session_id = Agent_Session_Helper::get_instance()->create_session();
		$run_id     = Agent_Run_Helper::get_instance()->create_run( $session_id );

		$result = Agent_Run_Helper::get_instance()->claim_run( $run_id, 'worker-a', 120 );

		$this->assertTrue( $result['claimed'] );
		$this->assertSame( 'running', $GLOBALS['wpdb']->runs[ $run_id ]['status'] );
		$this->assertSame( 'worker-a', $GLOBALS['wpdb']->runs[ $run_id ]['claimed_by'] );
		$this->assertNotEmpty( $GLOBALS['wpdb']->runs[ $run_id ]['lock_token'] );
	}

	public function test_claim_collision_fails_for_second_worker(): void {
		$session_id = Agent_Session_Helper::get_instance()->create_session();
		$run_id     = Agent_Run_Helper::get_instance()->create_run( $session_id );

		$first  = Agent_Run_Helper::get_instance()->claim_run( $run_id, 'worker-a', 120 );
		$second = Agent_Run_Helper::get_instance()->claim_run( $run_id, 'worker-b', 120 );

		$this->assertTrue( $first['claimed'] );
		$this->assertFalse( $second['claimed'] );
		$this->assertSame( 'not_claimable', $second['reason'] );
	}

	public function test_first_claim_keeps_attempt_at_one(): void {
		$session_id = Agent_Session_Helper::get_instance()->create_session();
		$run_id     = Agent_Run_Helper::get_instance()->create_run( $session_id );

		$result = Agent_Run_Helper::get_instance()->claim_run( $run_id, 'worker-a', 120 );

		$this->assertTrue( $result['claimed'] );
		$this->assertSame( 1, $result['attempt'] );
		$this->assertSame( 1, (int) $GLOBALS['wpdb']->runs[ $run_id ]['attempt'] );
	}

	public function test_stale_lock_can_be_reclaimed_and_attempt_increments(): void {
		$session_id = Agent_Session_Helper::get_instance()->create_session();
		$run_id     = Agent_Run_Helper::get_instance()->create_run( $session_id );
		$GLOBALS['wpdb']->runs[ $run_id ]['status'] = 'running';
		$GLOBALS['wpdb']->runs[ $run_id ]['attempt'] = 1;
		$GLOBALS['wpdb']->runs[ $run_id ]['lock_expires_at_gmt'] = '2000-01-01 00:00:00';

		$result = Agent_Run_Helper::get_instance()->claim_run( $run_id, 'worker-reclaim', 120 );

		$this->assertTrue( $result['claimed'] );
		$this->assertTrue( $result['reclaimed'] );
		$this->assertSame( 2, $result['attempt'] );
		$this->assertSame( 2, (int) $GLOBALS['wpdb']->runs[ $run_id ]['attempt'] );
	}

	public function test_claim_session_allows_paused_status(): void {
		$session_id = Agent_Session_Helper::get_instance()->create_session(
			[
				'status' => 'paused',
			]
		);

		$claim = Agent_Session_Helper::get_instance()->claim_session( $session_id, 'worker-paused', 120 );

		$this->assertTrue( $claim['claimed'] );
		$this->assertSame( 'running', $GLOBALS['wpdb']->sessions[ $session_id ]['status'] );
	}

	public function test_pause_run_does_not_increment_session_failures(): void {
		$session_id = Agent_Session_Helper::get_instance()->create_session();
		$run_id     = Agent_Run_Helper::get_instance()->create_run( $session_id );
		$claim      = Agent_Run_Helper::get_instance()->claim_run( $run_id, 'worker-a', 120 );

		$paused = Agent_Run_Helper::get_instance()->pause_run(
			$run_id,
			(string) $claim['lock_token'],
			[
				'status' => 'paused',
			]
		);

		$this->assertTrue( $paused );
		$this->assertSame( 'paused', $GLOBALS['wpdb']->sessions[ $session_id ]['last_run_status'] );
		$this->assertSame( 0, (int) $GLOBALS['wpdb']->sessions[ $session_id ]['consecutive_failures'] );
	}

	public function test_enqueue_run_rejects_non_terminal_statuses(): void {
		$session_id = Agent_Session_Helper::get_instance()->create_session();
		$run_id     = Agent_Run_Helper::get_instance()->create_run( $session_id );
		$claim      = Agent_Run_Helper::get_instance()->claim_run( $run_id, 'worker-a', 120 );

		$this->assertTrue( $claim['claimed'] );
		$this->assertFalse( Agent_Run_Helper::get_instance()->enqueue_run( $run_id ) );
	}

	public function test_requires_confirmation_does_not_increment_session_failures(): void {
		$session_id = Agent_Session_Helper::get_instance()->create_session();
		$this->assertTrue( Agent_Session_Helper::get_instance()->apply_run_completion( $session_id, 'failed', null ) );
		$this->assertSame( 1, (int) $GLOBALS['wpdb']->sessions[ $session_id ]['consecutive_failures'] );

		$run_id = Agent_Run_Helper::get_instance()->create_run( $session_id );
		$claim  = Agent_Run_Helper::get_instance()->claim_run( $run_id, 'worker-a', 120 );
		$this->assertTrue( $claim['claimed'] );

		$completed = Agent_Run_Helper::get_instance()->complete_run(
			$run_id,
			(string) $claim['lock_token'],
			'requires_confirmation'
		);

		$this->assertTrue( $completed );
		$this->assertSame( 0, (int) $GLOBALS['wpdb']->sessions[ $session_id ]['consecutive_failures'] );
	}

	public function test_complete_run_clears_lock_and_updates_session_state(): void {
		$session_id = Agent_Session_Helper::get_instance()->create_session();
		$run_id     = Agent_Run_Helper::get_instance()->create_run( $session_id );
		$claim      = Agent_Run_Helper::get_instance()->claim_run( $run_id, 'worker-a', 120 );

		$completed = Agent_Run_Helper::get_instance()->complete_run(
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

	public function test_complete_run_rolls_back_when_session_update_fails(): void {
		$session_id = Agent_Session_Helper::get_instance()->create_session();
		$run_id     = Agent_Run_Helper::get_instance()->create_run( $session_id );
		$claim      = Agent_Run_Helper::get_instance()->claim_run( $run_id, 'worker-a', 120 );
		$lock_token = (string) $claim['lock_token'];

		$GLOBALS['wpdb']->fail_session_update = true;
		$completed                            = Agent_Run_Helper::get_instance()->complete_run( $run_id, $lock_token, 'success' );

		$this->assertFalse( $completed );
		$this->assertSame( 'running', $GLOBALS['wpdb']->runs[ $run_id ]['status'] );
		$this->assertSame( $lock_token, $GLOBALS['wpdb']->runs[ $run_id ]['lock_token'] );
		$this->assertNull( $GLOBALS['wpdb']->sessions[ $session_id ]['last_run_status'] );
	}

	public function test_apply_run_completion_increments_failures_and_resets_on_success(): void {
		$session_id = Agent_Session_Helper::get_instance()->create_session();

		$this->assertTrue( Agent_Session_Helper::get_instance()->apply_run_completion( $session_id, 'failed', null ) );
		$this->assertSame( 1, (int) $GLOBALS['wpdb']->sessions[ $session_id ]['consecutive_failures'] );

		$this->assertTrue( Agent_Session_Helper::get_instance()->apply_run_completion( $session_id, 'failed', null ) );
		$this->assertSame( 2, (int) $GLOBALS['wpdb']->sessions[ $session_id ]['consecutive_failures'] );

		$this->assertTrue( Agent_Session_Helper::get_instance()->apply_run_completion( $session_id, 'success', null ) );
		$this->assertSame( 0, (int) $GLOBALS['wpdb']->sessions[ $session_id ]['consecutive_failures'] );
	}

	public function test_complete_run_rejects_non_terminal_status(): void {
		$session_id = Agent_Session_Helper::get_instance()->create_session();
		$run_id     = Agent_Run_Helper::get_instance()->create_run( $session_id );
		$claim      = Agent_Run_Helper::get_instance()->claim_run( $run_id, 'worker-a', 120 );
		$lock_token = (string) $claim['lock_token'];

		$completed = Agent_Run_Helper::get_instance()->complete_run( $run_id, $lock_token, 'running' );

		$this->assertFalse( $completed );
		$this->assertSame( 'running', $GLOBALS['wpdb']->runs[ $run_id ]['status'] );
		$this->assertSame( $lock_token, $GLOBALS['wpdb']->runs[ $run_id ]['lock_token'] );
	}

	public function test_helpers_do_not_expose_schema_methods(): void {
		$this->assertFalse( method_exists( Agent_Run_Helper::class, 'create_table' ) );
		$this->assertFalse( method_exists( Agent_Run_Helper::class, 'get_table_name' ) );
		$this->assertFalse( method_exists( Agent_Session_Helper::class, 'create_table' ) );
		$this->assertFalse( method_exists( Agent_Session_Helper::class, 'get_table_name' ) );
	}

	public function test_create_run_recovers_from_duplicate_insert_race_with_idempotency_key(): void {
		$session_id = Agent_Session_Helper::get_instance()->create_session();
		$wpdb       = $GLOBALS['wpdb'];
		$this->assertInstanceOf( Agent_Runtime_Wpdb::class, $wpdb );
		$wpdb->simulate_idempotency_race = true;

		$run_id = Agent_Run_Helper::get_instance()->create_run(
			$session_id,
			[
				'idempotency_key' => 'race-key-123',
				'meta'            => [
					'message' => 'race test',
				],
			]
		);

		$this->assertGreaterThan( 0, $run_id );
		$this->assertCount( 1, $wpdb->runs );
		$this->assertSame( 'race-key-123', (string) $wpdb->runs[ $run_id ]['idempotency_key'] );

		$run_id_2 = Agent_Run_Helper::get_instance()->create_run(
			$session_id,
			[
				'idempotency_key' => 'race-key-123',
			]
		);
		$this->assertSame( $run_id, $run_id_2 );
	}
}
}

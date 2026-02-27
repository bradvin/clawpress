<?php
/**
 * Tests for agent run slice runner.
 *
 * @package ClawPress\Tests
 */

declare( strict_types=1 );

namespace ClawPress\Tests\Unit;

use ClawPress\Helpers\Agent_Run_Helper;
use ClawPress\Helpers\Agent_Session_Helper;
use ClawPress\Runner\Agent_Runner;
use ClawPress\Tests\Support\Agent_Runtime_Wpdb;
use ClawPress\Tests\Support\TestCase;
use ClawPress\Tests\Support\WordPress_Stubs;

final class AgentRunnerTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['wpdb'] = new Agent_Runtime_Wpdb();
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}

	public function test_runner_processes_queued_run_when_session_is_paused(): void {
		$session_id = Agent_Session_Helper::get_instance()->create_session(
			[
				'status'             => 'paused',
				'requesting_user_id' => 1,
				'execution_user_id'  => 1,
			]
		);
		$run_id     = Agent_Run_Helper::get_instance()->create_run(
			$session_id,
			[
				'status' => 'queued',
				'meta'   => [
					'message' => 'Execute queued run.',
				],
			]
		);

		$runner = new Agent_Runner();
		$runner->run_scheduled_tasks();

		$run_row = Agent_Run_Helper::get_instance()->get_run( $run_id );
		$this->assertSame( 'done', $run_row['status'] );
		$this->assertNull( $run_row['lock_token'] );
		$this->assertSame( 'idle', $GLOBALS['wpdb']->sessions[ $session_id ]['status'] );
		$this->assertSame( 'done', $GLOBALS['wpdb']->sessions[ $session_id ]['last_run_status'] );
		$history = get_option( 'clawpress_chat_history_1', [] );
		$this->assertIsArray( $history );
		$this->assertNotEmpty( $history );
		$last_history_item = $history[ count( $history ) - 1 ];
		$this->assertIsArray( $last_history_item );
		$this->assertSame( 'assistant', $last_history_item['role'] ?? '' );
		$this->assertNotSame( '', trim( (string) ( $last_history_item['content'] ?? '' ) ) );
	}

	public function test_enqueue_run_slice_uses_async_and_delayed_single_actions(): void {
		$runner = new Agent_Runner();

		$runner->enqueue_run_slice( 22, 0 );
		$runner->enqueue_run_slice( 23, 15 );

		$this->assertCount( 1, WordPress_Stubs::$async_actions );
		$this->assertSame( Agent_Runner::RUN_SLICE_ACTION_HOOK, WordPress_Stubs::$async_actions[0]['hook'] );
		$this->assertSame( 22, WordPress_Stubs::$async_actions[0]['args']['run_id'] );
		$this->assertCount( 1, WordPress_Stubs::$single_scheduled_actions );
		$this->assertSame( Agent_Runner::RUN_SLICE_ACTION_HOOK, WordPress_Stubs::$single_scheduled_actions[0]['hook'] );
		$this->assertSame( 23, WordPress_Stubs::$single_scheduled_actions[0]['args']['run_id'] );
	}

	public function test_runner_marks_run_error_when_session_is_missing(): void {
		$run_id = Agent_Run_Helper::get_instance()->create_run(
			9999,
			[
				'status' => 'queued',
				'meta'   => [
					'message' => 'Missing session',
				],
			]
		);

		$runner = new Agent_Runner();
		$runner->run_slice_action( $run_id );

		$run = Agent_Run_Helper::get_instance()->get_run( $run_id );
		$this->assertSame( 'error', $run['status'] );
		$this->assertSame( 'session_missing', $run['error_code'] );
	}

	public function test_runner_pauses_run_when_session_cannot_be_claimed(): void {
		$session_id = Agent_Session_Helper::get_instance()->create_session(
			[
				'status' => 'running',
			]
		);
		$GLOBALS['wpdb']->sessions[ $session_id ]['lease_expires_at_gmt'] = gmdate( 'Y-m-d H:i:s', time() + 300 );

		$run_id = Agent_Run_Helper::get_instance()->create_run(
			$session_id,
			[
				'status' => 'queued',
				'meta'   => [
					'message' => 'Busy session',
				],
			]
		);

		$runner = new Agent_Runner();
		$runner->run_slice_action( $run_id );

		$run = Agent_Run_Helper::get_instance()->get_run( $run_id );
		$this->assertSame( 'paused', $run['status'] );
		$this->assertNotNull( $run['next_retry_at_gmt'] ?? null );
		$this->assertSame( 'session_not_claimable', $run['meta']['reason'] ?? null );
		$this->assertSame( 'session_not_claimable', $run['meta']['pause_reason'] ?? null );
		$this->assertCount( 1, WordPress_Stubs::$single_scheduled_actions );
		$this->assertSame( $run_id, WordPress_Stubs::$single_scheduled_actions[0]['args']['run_id'] );
	}

	public function test_runner_reclaims_stale_running_run_from_heartbeat_scan(): void {
		$session_id = Agent_Session_Helper::get_instance()->create_session(
			[
				'status'             => 'paused',
				'requesting_user_id' => 1,
				'execution_user_id'  => 1,
			]
		);
		$run_id     = Agent_Run_Helper::get_instance()->create_run(
			$session_id,
			[
				'status' => 'queued',
				'meta'   => [
					'message' => 'Reclaim stale run',
				],
			]
		);

		$GLOBALS['wpdb']->runs[ $run_id ]['status']              = 'running';
		$GLOBALS['wpdb']->runs[ $run_id ]['attempt']             = 1;
		$GLOBALS['wpdb']->runs[ $run_id ]['lock_token']          = 'stale-lock-token';
		$GLOBALS['wpdb']->runs[ $run_id ]['lock_expires_at_gmt'] = gmdate( 'Y-m-d H:i:s', time() - 300 );

		$runner = new Agent_Runner();
		$runner->run_scheduled_tasks();

		$run_row = Agent_Run_Helper::get_instance()->get_run( $run_id );
		$this->assertSame( 'done', $run_row['status'] );
		$this->assertSame( 2, (int) $run_row['attempt'] );
		$this->assertNull( $run_row['lock_token'] );
	}

	public function test_retry_backoff_calculates_from_retry_count(): void {
		$runner     = new Agent_Runner();
		$reflection = new \ReflectionClass( Agent_Runner::class );
		$method     = $reflection->getMethod( 'calculate_retry_backoff' );
		$method->setAccessible( true );

		$this->assertSame( 15, $method->invoke( $runner, 1 ) );
		$this->assertSame( 30, $method->invoke( $runner, 2 ) );
		$this->assertSame( 60, $method->invoke( $runner, 3 ) );
	}

	public function test_runner_times_out_run_when_max_wall_time_is_exceeded(): void {
		$session_id = Agent_Session_Helper::get_instance()->create_session(
			[
				'status'             => 'paused',
				'requesting_user_id' => 1,
				'execution_user_id'  => 1,
			]
		);
		$run_id     = Agent_Run_Helper::get_instance()->create_run(
			$session_id,
			[
				'status'       => 'queued',
				'trigger_type' => 'heartbeat',
				'meta'         => [
					'message' => 'Long running job',
				],
			]
		);

		$GLOBALS['wpdb']->runs[ $run_id ]['started_at_gmt'] = gmdate( 'Y-m-d H:i:s', time() - 300 );

		$runner = new Agent_Runner();
		$runner->run_scheduled_tasks();

		$run = Agent_Run_Helper::get_instance()->get_run( $run_id );
		$this->assertSame( 'timeout', $run['status'] );
		$this->assertSame( 'wall_time_exceeded', $run['error_code'] );
		$this->assertSame( 'timeout', $GLOBALS['wpdb']->sessions[ $session_id ]['last_run_status'] );
	}
}

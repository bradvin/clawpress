<?php
/**
 * Tests for agent run REST controller.
 *
 * @package ClawPress\Tests
 */

declare( strict_types=1 );

namespace ClawPress\Tests\Unit;

use ClawPress\Helpers\Agent_Event_Helper;
use ClawPress\Helpers\Agent_Run_Helper;
use ClawPress\Helpers\Agent_Session_Helper;
use ClawPress\RestAPI\Controllers\Agent_Run_Controller;
use ClawPress\Runner\Agent_Runner;
use ClawPress\Tests\Support\Agent_Runtime_Wpdb;
use ClawPress\Tests\Support\TestCase;
use ClawPress\Tests\Support\WordPress_Stubs;

final class AgentRunControllerTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['wpdb'] = new Agent_Runtime_Wpdb();
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}

	public function test_create_run_creates_session_and_enqueues_slice(): void {
		$controller = new Agent_Run_Controller();
		$response   = $controller->create_run(
			new \WP_REST_Request(
				[
					'trigger'             => 'chat',
					'message'             => 'Process this run',
					'transport_mode'      => 'polling',
					'max_attempts'        => 6,
					'slice_budget_ms'     => 2000,
					'max_steps_per_slice' => 2,
				]
			)
		);

		$data = $response->get_data();
		$this->assertSame( 201, $response->get_status() );
		$this->assertGreaterThan( 0, (int) $data['session_id'] );
		$this->assertGreaterThan( 0, (int) $data['run_id'] );
		$this->assertSame( 'queued', $data['status'] );
		$this->assertCount( 1, WordPress_Stubs::$async_actions );
		$this->assertSame( Agent_Runner::RUN_SLICE_ACTION_HOOK, WordPress_Stubs::$async_actions[0]['hook'] );
	}

	public function test_enqueue_run_rejects_non_terminal_status(): void {
		$session_id = Agent_Session_Helper::get_instance()->create_session();
		$run_id     = Agent_Run_Helper::get_instance()->create_run( $session_id );
		$claim      = Agent_Run_Helper::get_instance()->claim_run( $run_id, 'worker-running', 120 );
		$this->assertTrue( $claim['claimed'] );

		$controller = new Agent_Run_Controller();
		$response   = $controller->enqueue_run( new \WP_REST_Request( [ 'run_id' => $run_id ] ) );

		$data = $response->get_data();
		$this->assertSame( 409, $response->get_status() );
		$this->assertSame( 'Run is not in a retryable state.', $data['error'] );
		$this->assertCount( 0, WordPress_Stubs::$async_actions );
	}

	public function test_get_run_events_returns_incremental_cursor(): void {
		$session_id = Agent_Session_Helper::get_instance()->create_session();
		$run_id     = Agent_Run_Helper::get_instance()->create_run( $session_id );
		$event_a    = Agent_Event_Helper::get_instance()->emit(
			'agent.test.a',
			[
				'run_id'  => $run_id,
				'payload' => [ 'step' => 1 ],
			]
		);
		$event_b    = Agent_Event_Helper::get_instance()->emit(
			'agent.test.b',
			[
				'run_id'  => $run_id,
				'payload' => [ 'step' => 2 ],
			]
		);

		$controller = new Agent_Run_Controller();
		$response   = $controller->get_run_events(
			new \WP_REST_Request(
				[
					'run_id' => $run_id,
					'after'  => 0,
					'limit'  => 50,
				]
			)
		);

		$data = $response->get_data();
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $run_id, $data['run_id'] );
		$this->assertCount( 2, $data['events'] );
		$this->assertSame( $event_a, $data['events'][0]['event_id'] );
		$this->assertSame( $event_b, $data['next_cursor'] );
	}
}


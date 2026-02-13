<?php
/**
 * Tests for heartbeat module.
 *
 * @package ClawPress\Tests
 */

declare( strict_types=1 );

namespace ClawPress\Tests\Unit;

use ClawPress\Heartbeat\Heartbeat;
use ClawPress\Tests\Support\TestCase;
use ClawPress\Tests\Support\WordPress_Stubs;

final class HeartbeatTest extends TestCase {
	public function test_register_adds_scheduler_and_tick_hooks(): void {
		new Heartbeat();

		$hooks = array_column( WordPress_Stubs::$actions, 'hook' );

		$this->assertCount( 3, WordPress_Stubs::$actions );
		$this->assertContains( 'action_scheduler_init', $hooks );
		$this->assertContains( 'action_scheduler_ensure_recurring_actions', $hooks );
		$this->assertContains( Heartbeat::HEARTBEAT_ACTION_HOOK, $hooks );
	}

	public function test_schedule_recurring_actions_schedules_when_missing(): void {
		WordPress_Stubs::$has_scheduled_action = false;
		$heartbeat                             = new Heartbeat();

		$heartbeat->schedule_recurring_actions();

		$this->assertCount( 1, WordPress_Stubs::$scheduled_actions );
		$this->assertSame(
			Heartbeat::HEARTBEAT_ACTION_HOOK,
			WordPress_Stubs::$scheduled_actions[0]['hook']
		);
		$this->assertSame(
			Heartbeat::ACTION_GROUP,
			WordPress_Stubs::$scheduled_actions[0]['group']
		);
		$this->assertSame(
			Heartbeat::HEARTBEAT_ACTION_INTERVAL,
			WordPress_Stubs::$scheduled_actions[0]['interval']
		);
	}

	public function test_schedule_recurring_actions_bails_when_already_scheduled(): void {
		WordPress_Stubs::$has_scheduled_action = true;
		$heartbeat                             = new Heartbeat();

		$heartbeat->schedule_recurring_actions();

		$this->assertCount( 0, WordPress_Stubs::$scheduled_actions );
	}

	public function test_run_heartbeat_tick_triggers_task_action(): void {
		$heartbeat = new Heartbeat();
		$heartbeat->run_heartbeat_tick();

		$this->assertCount( 1, WordPress_Stubs::$triggered_actions );
		$this->assertSame(
			'clawpress_run_scheduled_tasks',
			WordPress_Stubs::$triggered_actions[0]['hook']
		);
	}
}

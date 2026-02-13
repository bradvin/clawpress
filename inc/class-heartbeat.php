<?php
/**
 * ClawPress heartbeat scheduler and background trigger wiring.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Heartbeat;

defined( 'ABSPATH' ) || exit;

/**
 * Heartbeat module.
 */
final class Heartbeat {
	public const ACTION_GROUP               = 'clawpress';
	public const HEARTBEAT_ACTION_HOOK      = 'clawpress_heartbeat_tick';
	public const HEARTBEAT_ACTION_INTERVAL  = 15 * MINUTE_IN_SECONDS;
	public const HEARTBEAT_ACTION_LEAD_TIME = 1 * MINUTE_IN_SECONDS;

	/**
	 * Register all hooks for heartbeat orchestration.
	 */
	public function __construct() {
		add_action( 'action_scheduler_init', [ $this, 'schedule_recurring_actions' ] );
		add_action( 'action_scheduler_ensure_recurring_actions', [ $this, 'schedule_recurring_actions' ] );
		add_action( self::HEARTBEAT_ACTION_HOOK, [ $this, 'run_heartbeat_tick' ] );
	}

	/**
	 * Ensure required recurring actions exist.
	 */
	public function schedule_recurring_actions(): void {
		if ( ! function_exists( 'as_has_scheduled_action' ) || ! function_exists( 'as_schedule_recurring_action' ) ) {
			return;
		}

		if ( as_has_scheduled_action( self::HEARTBEAT_ACTION_HOOK, [], self::ACTION_GROUP ) ) {
			return;
		}

		as_schedule_recurring_action(
			time() + self::HEARTBEAT_ACTION_LEAD_TIME,
			self::HEARTBEAT_ACTION_INTERVAL,
			self::HEARTBEAT_ACTION_HOOK,
			[],
			self::ACTION_GROUP
		);
	}

	/**
	 * Heartbeat entry point for recurring background work.
	 */
	public function run_heartbeat_tick(): void {
		do_action( 'clawpress_run_scheduled_tasks' );
	}
}

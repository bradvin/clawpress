<?php
/**
 * ClawPress heartbeat scheduler and background trigger wiring.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Heartbeat;

defined( 'ABSPATH' ) || exit;

const ACTION_GROUP               = 'clawpress';
const HEARTBEAT_ACTION_HOOK      = 'clawpress_heartbeat_tick';
const HEARTBEAT_ACTION_INTERVAL  = 15 * MINUTE_IN_SECONDS;
const HEARTBEAT_ACTION_LEAD_TIME = 1 * MINUTE_IN_SECONDS;

add_action( 'action_scheduler_init', __NAMESPACE__ . '\schedule_recurring_actions' );
add_action( 'action_scheduler_ensure_recurring_actions', __NAMESPACE__ . '\schedule_recurring_actions' );
add_action( HEARTBEAT_ACTION_HOOK, __NAMESPACE__ . '\run_heartbeat_tick' );

/**
 * Ensure required recurring actions exist.
 *
 * @return void
 */
function schedule_recurring_actions(): void {
	if ( ! function_exists( 'as_has_scheduled_action' ) || ! function_exists( 'as_schedule_recurring_action' ) ) {
		return;
	}

	if ( as_has_scheduled_action( HEARTBEAT_ACTION_HOOK, array(), ACTION_GROUP ) ) {
		return;
	}

	as_schedule_recurring_action(
		time() + HEARTBEAT_ACTION_LEAD_TIME,
		HEARTBEAT_ACTION_INTERVAL,
		HEARTBEAT_ACTION_HOOK,
		array(),
		ACTION_GROUP
	);
}

/**
 * Heartbeat entry point for recurring background work.
 *
 * @return void
 */
function run_heartbeat_tick(): void {
	do_action( 'clawpress_run_scheduled_tasks' );
}

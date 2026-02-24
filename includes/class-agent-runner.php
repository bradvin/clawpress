<?php
/**
 * Agent run slice runner.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Runner;

use ClawPress\Helpers\Agent_Event_Helper;
use ClawPress\Helpers\Agent_Loop_Helper;
use ClawPress\Helpers\Agent_Run_Helper;
use ClawPress\Helpers\Agent_Session_Helper;
use ClawPress\Helpers\Policy_Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Action Scheduler-backed bounded run executor.
 */
final class Agent_Runner {
	/**
	 * Action Scheduler group.
	 */
	public const ACTION_GROUP = 'clawpress';

	/**
	 * Async action hook for run slices.
	 */
	public const RUN_SLICE_ACTION_HOOK = 'clawpress_agent_run_slice';

	/**
	 * Max runs processed per scheduler tick.
	 */
	private const MAX_RUNS_PER_TICK = 5;

	/**
	 * Run lock lease duration in seconds.
	 */
	private const RUN_LEASE_TTL = 120;

	/**
	 * Session lease duration in seconds.
	 */
	private const SESSION_LEASE_TTL = 120;

	/**
	 * Base retry backoff in seconds.
	 */
	private const RETRY_BACKOFF_BASE = 15;

	/**
	 * Run helper.
	 *
	 * @var Agent_Run_Helper
	 */
	private Agent_Run_Helper $run_helper;

	/**
	 * Session helper.
	 *
	 * @var Agent_Session_Helper
	 */
	private Agent_Session_Helper $session_helper;

	/**
	 * Event helper.
	 *
	 * @var Agent_Event_Helper
	 */
	private Agent_Event_Helper $event_helper;

	/**
	 * Loop helper.
	 *
	 * @var Agent_Loop_Helper
	 */
	private Agent_Loop_Helper $loop_helper;

	/**
	 * Policy helper.
	 *
	 * @var Policy_Helper
	 */
	private Policy_Helper $policy_helper;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->run_helper     = Agent_Run_Helper::get_instance();
		$this->session_helper = Agent_Session_Helper::get_instance();
		$this->event_helper   = Agent_Event_Helper::get_instance();
		$this->loop_helper    = Agent_Loop_Helper::get_instance();
		$this->policy_helper  = Policy_Helper::get_instance();

		add_action( 'clawpress_run_scheduled_tasks', [ $this, 'run_scheduled_tasks' ] );
		add_action( self::RUN_SLICE_ACTION_HOOK, [ $this, 'run_slice_action' ], 10, 1 );
	}

	/**
	 * Process runnable runs on heartbeat tick.
	 */
	public function run_scheduled_tasks(): void {
		$worker_id = $this->build_worker_id( 'heartbeat' );

		for ( $index = 0; $index < self::MAX_RUNS_PER_TICK; ++$index ) {
			$claim = $this->run_helper->claim_next_runnable_run( $worker_id, self::RUN_LEASE_TTL );
			if ( empty( $claim['claimed'] ) ) {
				break;
			}

			$this->process_claimed_run( $claim, $worker_id );
		}
	}

	/**
	 * Process one asynchronously queued run.
	 *
	 * @param int|array<string,mixed>|string $run_id Run id or args payload.
	 */
	public function run_slice_action( $run_id = 0 ): void {
		$resolved_run_id = 0;
		if ( is_array( $run_id ) && isset( $run_id['run_id'] ) ) {
			$resolved_run_id = (int) $run_id['run_id'];
		} else {
			$resolved_run_id = (int) $run_id;
		}

		if ( $resolved_run_id <= 0 ) {
			$this->run_scheduled_tasks();
			return;
		}

		$worker_id = $this->build_worker_id( 'slice' );
		$claim     = $this->run_helper->claim_run( $resolved_run_id, $worker_id, self::RUN_LEASE_TTL );
		if ( empty( $claim['claimed'] ) ) {
			return;
		}

		$this->process_claimed_run( $claim, $worker_id );
	}

	/**
	 * Queue one run slice action.
	 *
	 * @param int $run_id Run identifier.
	 * @param int $delay_seconds Delay in seconds.
	 */
	public function enqueue_run_slice( int $run_id, int $delay_seconds = 0 ): void {
		self::enqueue_run_slice_action( $run_id, $delay_seconds );
	}

	/**
	 * Queue one run slice action through Action Scheduler.
	 *
	 * @param int $run_id Run identifier.
	 * @param int $delay_seconds Delay in seconds.
	 */
	public static function enqueue_run_slice_action( int $run_id, int $delay_seconds = 0 ): void {
		if ( $run_id <= 0 ) {
			return;
		}

		if ( function_exists( 'as_enqueue_async_action' ) && $delay_seconds <= 0 ) {
			as_enqueue_async_action(
				self::RUN_SLICE_ACTION_HOOK,
				[ 'run_id' => $run_id ],
				self::ACTION_GROUP
			);
			return;
		}

		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action(
				time() + max( 0, $delay_seconds ),
				self::RUN_SLICE_ACTION_HOOK,
				[ 'run_id' => $run_id ],
				self::ACTION_GROUP
			);
		}
	}

	/**
	 * Execute one claimed run slice.
	 *
	 * @param array<string,mixed> $claim Claim payload.
	 * @param string              $worker_id Worker id.
	 */
	private function process_claimed_run( array $claim, string $worker_id ): void {
		$run_id     = isset( $claim['run_id'] ) ? (int) $claim['run_id'] : 0;
		$lock_token = isset( $claim['lock_token'] ) ? (string) $claim['lock_token'] : '';
		if ( $run_id <= 0 || '' === $lock_token ) {
			return;
		}

		$run = $this->run_helper->get_run( $run_id );
		if ( [] === $run ) {
			return;
		}

		$session_id = isset( $run['session_id'] ) ? (int) $run['session_id'] : 0;
		$session    = $this->session_helper->get_session( $session_id );
		if ( $session_id <= 0 || [] === $session ) {
			$this->run_helper->complete_run(
				$run_id,
				$lock_token,
				'error',
				[
					'error_code'    => 'session_missing',
					'error_message' => __( 'Run session could not be loaded.', 'clawpress' ),
				]
			);
			return;
		}

		$runtime_policy             = $this->policy_helper->resolve_runtime_policy(
			isset( $run['trigger_type'] ) ? (string) $run['trigger_type'] : 'heartbeat',
			[
				'policy_profile' => isset( $session['policy_profile'] ) ? (string) $session['policy_profile'] : 'default',
			]
		);
		$max_wall_time_seconds      = isset( $runtime_policy['max_wall_time_seconds'] )
			? max( 1, (int) $runtime_policy['max_wall_time_seconds'] )
			: 120;
		$allow_background_followups = isset( $runtime_policy['allow_background_followups'] ) && true === $runtime_policy['allow_background_followups'];

		$session_claim = $this->session_helper->claim_session( $session_id, $worker_id, self::SESSION_LEASE_TTL );
		if ( empty( $session_claim['claimed'] ) ) {
			$delay_seconds = self::RETRY_BACKOFF_BASE;
				$this->run_helper->pause_run(
					$run_id,
					$lock_token,
					[
						'status'            => 'paused',
						'next_retry_at_gmt' => gmdate( 'Y-m-d H:i:s', time() + $delay_seconds ),
						'meta'              => [
							'reason'       => 'session_not_claimable',
							'pause_reason' => 'session_not_claimable',
						],
					]
				);
			$this->enqueue_run_slice( $run_id, $delay_seconds );
			return;
		}

		if ( $this->has_run_exceeded_wall_time( $run, $max_wall_time_seconds ) ) {
			$this->run_helper->complete_run(
				$run_id,
				$lock_token,
				'timeout',
				[
					'error_code'    => 'wall_time_exceeded',
					'error_message' => __( 'Run exceeded maximum wall time.', 'clawpress' ),
					'meta'          => [
						'max_wall_time_seconds' => $max_wall_time_seconds,
					],
				]
			);
			$this->session_helper->release_session( $session_id, (string) $session_claim['lease_token'], 'error' );
			$this->event_helper->emit(
				'agent.runner.run_timed_out',
				[
					'run_id'     => $run_id,
					'session_id' => $session_id,
					'payload'    => [
						'reason'                => 'wall_time_exceeded',
						'max_wall_time_seconds' => $max_wall_time_seconds,
					],
				]
			);
			return;
		}

		$turn_request = [
			'run_id'              => $run_id,
			'session_id'          => $session_id,
			'trigger'             => isset( $run['trigger_type'] ) ? (string) $run['trigger_type'] : 'heartbeat',
			'transport_mode'      => isset( $run['transport_mode'] ) ? (string) $run['transport_mode'] : 'polling',
			'requesting_user_id'  => isset( $session['requesting_user_id'] ) ? (int) $session['requesting_user_id'] : 0,
			'execution_user_id'   => isset( $session['execution_user_id'] ) ? (int) $session['execution_user_id'] : 0,
			'slice_budget_ms'     => isset( $run['meta']['slice_budget_ms'] ) ? (int) $run['meta']['slice_budget_ms'] : 1500,
			'max_steps_per_slice' => isset( $run['meta']['max_steps_per_slice'] ) ? (int) $run['meta']['max_steps_per_slice'] : 1,
			'attempt'             => isset( $run['attempt'] ) ? (int) $run['attempt'] : 1,
			'resume_cursor'       => isset( $run['resume_cursor'] ) ? $run['resume_cursor'] : null,
			'session_metadata'    => [
				'policy_profile' => isset( $session['policy_profile'] ) ? (string) $session['policy_profile'] : 'default',
			],
			'message'             => isset( $run['meta']['message'] ) ? (string) $run['meta']['message'] : '',
		];

		$this->event_helper->emit(
			'agent.runner.slice_started',
			[
				'run_id'     => $run_id,
				'session_id' => $session_id,
				'payload'    => [
					'worker_id' => $worker_id,
					'attempt'   => isset( $run['attempt'] ) ? (int) $run['attempt'] : 1,
				],
			]
		);

		$result = $this->loop_helper->run_slice( $turn_request );

		$status = isset( $result['status'] ) ? (string) $result['status'] : 'error';
		if ( 'in_progress' === $status ) {
			if ( $this->has_run_exceeded_wall_time( $run, $max_wall_time_seconds ) ) {
				$this->run_helper->complete_run(
					$run_id,
					$lock_token,
					'timeout',
					[
						'error_code'    => 'wall_time_exceeded',
						'error_message' => __( 'Run exceeded maximum wall time.', 'clawpress' ),
						'meta'          => [
							'last_result'           => $result,
							'max_wall_time_seconds' => $max_wall_time_seconds,
						],
					]
				);
				$this->session_helper->release_session( $session_id, (string) $session_claim['lease_token'], 'error' );
				return;
			}

			if ( ! $allow_background_followups ) {
				$this->run_helper->complete_run(
					$run_id,
					$lock_token,
					'timeout',
					[
						'error_code'    => 'background_followups_disabled',
						'error_message' => __( 'Background follow-up slices are disabled for this trigger.', 'clawpress' ),
						'meta'          => [
							'last_result' => $result,
						],
					]
				);
				$this->session_helper->release_session( $session_id, (string) $session_claim['lease_token'], 'error' );
				return;
			}

			$delay_seconds = 1;
			$this->run_helper->pause_run(
				$run_id,
				$lock_token,
				[
					'status'            => 'paused',
					'next_retry_at_gmt' => gmdate( 'Y-m-d H:i:s', time() + $delay_seconds ),
					'resume_cursor'     => $result['resume_cursor'] ?? null,
					'meta'              => [
						'last_result'  => $result,
						'pause_reason' => 'slice_budget',
					],
				]
			);
			$this->session_helper->release_session( $session_id, (string) $session_claim['lease_token'], 'paused' );
			$this->enqueue_run_slice( $run_id, $delay_seconds );

			$this->event_helper->emit(
				'agent.runner.slice_paused',
				[
					'run_id'     => $run_id,
					'session_id' => $session_id,
					'payload'    => [
						'next_retry_at_gmt' => gmdate( 'Y-m-d H:i:s', time() + $delay_seconds ),
					],
				]
			);
			return;
		}

		if ( in_array( $status, [ 'success', 'requires_confirmation' ], true ) ) {
			$terminal_status = 'success' === $status ? 'done' : 'requires_confirmation';
			$this->run_helper->complete_run(
				$run_id,
				$lock_token,
				$terminal_status,
				[
					'meta' => [
						'result' => $result,
					],
				]
			);
			$this->session_helper->release_session( $session_id, (string) $session_claim['lease_token'], 'idle' );
			$this->event_helper->emit(
				'agent.runner.slice_completed',
				[
					'run_id'     => $run_id,
					'session_id' => $session_id,
					'payload'    => [
						'status' => $terminal_status,
					],
				]
			);
			return;
		}

			$retry_count  = isset( $run['retry_count'] ) ? max( 0, (int) $run['retry_count'] ) : 0;
			$max_attempts = isset( $run['max_attempts'] ) ? (int) $run['max_attempts'] : 5;
		if ( $retry_count < $max_attempts ) {
			if ( ! $allow_background_followups ) {
				$this->run_helper->complete_run(
					$run_id,
					$lock_token,
					'timeout',
					[
						'error_code'    => 'background_followups_disabled',
						'error_message' => __( 'Background follow-up slices are disabled for this trigger.', 'clawpress' ),
						'meta'          => [
							'last_result' => $result,
						],
					]
				);
				$this->session_helper->release_session( $session_id, (string) $session_claim['lease_token'], 'error' );
				return;
			}

			$next_retry_count = $retry_count + 1;
			$delay_seconds    = $this->calculate_retry_backoff( $next_retry_count );
			$this->run_helper->pause_run(
				$run_id,
				$lock_token,
				[
					'status'            => 'paused',
					'next_retry_at_gmt' => gmdate( 'Y-m-d H:i:s', time() + $delay_seconds ),
					'resume_cursor'     => $result['resume_cursor'] ?? null,
					'retry_count'       => $next_retry_count,
					'meta'              => [
						'last_result'  => $result,
						'retry_count'  => $next_retry_count,
						'pause_reason' => 'retry_backoff',
					],
				]
			);
			$this->session_helper->release_session( $session_id, (string) $session_claim['lease_token'], 'paused' );
			$this->enqueue_run_slice( $run_id, $delay_seconds );
			return;
		}

		$this->run_helper->complete_run(
			$run_id,
			$lock_token,
			'error',
			[
				'error_code'    => isset( $result['error']['type'] ) ? (string) $result['error']['type'] : 'run_failed',
				'error_message' => isset( $result['error']['message'] ) ? (string) $result['error']['message'] : __( 'Agent run failed.', 'clawpress' ),
				'meta'          => [
					'last_result' => $result,
				],
			]
		);
		$this->session_helper->release_session( $session_id, (string) $session_claim['lease_token'], 'error' );
	}

	/**
	 * Build worker ID.
	 *
	 * @param string $suffix Worker suffix.
	 */
	private function build_worker_id( string $suffix ): string {
		return sprintf( 'clawpress-%s-%s', $suffix, substr( md5( uniqid( '', true ) ), 0, 12 ) );
	}

	/**
	 * Calculate exponential retry backoff.
	 *
	 * @param int $retry_count Retry count.
	 */
	private function calculate_retry_backoff( int $retry_count ): int {
		$retry_count = max( 1, $retry_count );
		$delay       = self::RETRY_BACKOFF_BASE * ( 2 ** ( $retry_count - 1 ) );
		return min( $delay, 15 * MINUTE_IN_SECONDS );
	}

	/**
	 * Check if run exceeded max wall time.
	 *
	 * @param array<string,mixed> $run Run row.
	 * @param int                 $max_wall_time_seconds Max wall-time budget in seconds.
	 */
	private function has_run_exceeded_wall_time( array $run, int $max_wall_time_seconds ): bool {
		$started_at = isset( $run['started_at_gmt'] ) ? (string) $run['started_at_gmt'] : '';
		if ( '' === $started_at || $max_wall_time_seconds <= 0 ) {
			return false;
		}

		$started_ts = strtotime( $started_at );
		if ( false === $started_ts ) {
			return false;
		}

		return ( time() - $started_ts ) >= $max_wall_time_seconds;
	}
}

<?php
/**
 * Agent run REST controller.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\RestAPI\Controllers;

use ClawPress\Helpers\Agent_Event_Helper;
use ClawPress\Helpers\Agent_Run_Helper;
use ClawPress\Helpers\Agent_Session_Helper;
use ClawPress\Helpers\Settings_Helper;
use ClawPress\Runner\Agent_Runner;

defined( 'ABSPATH' ) || exit;

/**
 * Agent run endpoints controller.
 */
final class Agent_Run_Controller implements Route_Controller {
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
	 * Settings helper.
	 *
	 * @var Settings_Helper
	 */
	private Settings_Helper $settings_helper;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->run_helper     = Agent_Run_Helper::get_instance();
		$this->session_helper = Agent_Session_Helper::get_instance();
		$this->event_helper   = Agent_Event_Helper::get_instance();
		$this->settings_helper = Settings_Helper::get_instance();
	}

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			'clawpress/v1',
			'/agent/runs',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'create_run' ],
				'permission_callback' => 'clawpress_check_permissions',
				'args'                => [
					'session_id'          => [
						'required'          => false,
						'sanitize_callback' => 'absint',
						'validate_callback' => 'clawpress_validate_int',
					],
					'trigger'             => [
						'required'          => false,
						'sanitize_callback' => 'sanitize_key',
					],
					'message'             => [
						'required'          => false,
						'sanitize_callback' => 'clawpress_sanitize_multiline_text',
					],
					'transport_mode'      => [
						'required'          => false,
						'sanitize_callback' => 'sanitize_key',
					],
					'max_attempts'        => [
						'required'          => false,
						'sanitize_callback' => 'absint',
						'validate_callback' => 'clawpress_validate_int',
					],
					'slice_budget_ms'     => [
						'required'          => false,
						'sanitize_callback' => 'absint',
						'validate_callback' => 'clawpress_validate_int',
					],
					'max_steps_per_slice' => [
						'required'          => false,
						'sanitize_callback' => 'absint',
						'validate_callback' => 'clawpress_validate_int',
					],
					'idempotency_key'     => [
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);

		register_rest_route(
			'clawpress/v1',
			'/agent/spawn',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'spawn_agent' ],
				'permission_callback' => 'clawpress_check_permissions',
				'args'                => [
					'message'             => [
						'required'          => false,
						'sanitize_callback' => 'clawpress_sanitize_multiline_text',
					],
					'transport_mode'      => [
						'required'          => false,
						'sanitize_callback' => 'sanitize_key',
					],
					'max_attempts'        => [
						'required'          => false,
						'sanitize_callback' => 'absint',
						'validate_callback' => 'clawpress_validate_int',
					],
					'slice_budget_ms'     => [
						'required'          => false,
						'sanitize_callback' => 'absint',
						'validate_callback' => 'clawpress_validate_int',
					],
					'max_steps_per_slice' => [
						'required'          => false,
						'sanitize_callback' => 'absint',
						'validate_callback' => 'clawpress_validate_int',
					],
					'idempotency_key'     => [
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);

		register_rest_route(
			'clawpress/v1',
			'/agent/runs/(?P<run_id>\d+)/enqueue',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'enqueue_run' ],
				'permission_callback' => 'clawpress_check_permissions',
				'args'                => [
					'run_id' => [
						'required'          => true,
						'sanitize_callback' => 'absint',
						'validate_callback' => 'clawpress_validate_int',
					],
				],
			]
		);

		register_rest_route(
			'clawpress/v1',
			'/agent/runs/(?P<run_id>\d+)',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_run' ],
				'permission_callback' => 'clawpress_check_permissions',
				'args'                => [
					'run_id' => [
						'required'          => true,
						'sanitize_callback' => 'absint',
						'validate_callback' => 'clawpress_validate_int',
					],
				],
			]
		);

		register_rest_route(
			'clawpress/v1',
			'/agent/runs/(?P<run_id>\d+)/events',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_run_events' ],
				'permission_callback' => 'clawpress_check_permissions',
				'args'                => [
					'run_id' => [
						'required'          => true,
						'sanitize_callback' => 'absint',
						'validate_callback' => 'clawpress_validate_int',
					],
					'after'  => [
						'required'          => false,
						'sanitize_callback' => 'absint',
						'validate_callback' => 'clawpress_validate_int',
					],
					'limit'  => [
						'required'          => false,
						'sanitize_callback' => 'absint',
						'validate_callback' => 'clawpress_validate_int',
					],
				],
			]
		);
	}

	/**
	 * Create an agent run.
	 *
	 * @param \WP_REST_Request $request Request object.
	 */
	public function create_run( \WP_REST_Request $request ): \WP_REST_Response {
		$trigger = sanitize_key( (string) $request->get_param( 'trigger' ) );
		if ( '' === $trigger ) {
			$trigger = 'chat';
		}

		$requesting_user_id = function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0;
		$session_id         = (int) $request->get_param( 'session_id' );
		if ( $session_id > 0 ) {
			$session = $this->session_helper->get_session( $session_id );
			if ( [] === $session ) {
				return new \WP_REST_Response(
					[
						'error' => __( 'Session not found.', 'clawpress' ),
					],
					404
				);
			}
		} else {
			$session_id = $this->session_helper->create_session(
				[
					'trigger_type'       => $trigger,
					'requesting_user_id' => $requesting_user_id,
					'execution_user_id'  => $this->resolve_execution_user_id( $requesting_user_id ),
					'policy_profile'     => 'default',
				]
			);
		}

		if ( $session_id <= 0 ) {
			return new \WP_REST_Response(
				[
					'error' => __( 'Unable to create session for run.', 'clawpress' ),
				],
				500
			);
		}

		$idempotency_key = trim( (string) $request->get_param( 'idempotency_key' ) );
		if ( '' !== $idempotency_key ) {
			$existing_run = $this->run_helper->get_run_by_idempotency_key( $session_id, $idempotency_key );
			if ( [] !== $existing_run && isset( $existing_run['id'] ) ) {
				return new \WP_REST_Response(
					[
						'run_id'       => (int) $existing_run['id'],
						'session_id'   => $session_id,
						'status'       => isset( $existing_run['status'] ) ? (string) $existing_run['status'] : 'queued',
						'deduplicated' => true,
					],
					200
				);
			}
		}

		$run_id = $this->run_helper->create_run(
			$session_id,
			[
				'trigger_type'    => $trigger,
				'transport_mode'  => $this->normalize_transport_mode( (string) $request->get_param( 'transport_mode' ) ),
				'max_attempts'    => max( 1, (int) $request->get_param( 'max_attempts' ) > 0 ? (int) $request->get_param( 'max_attempts' ) : 5 ),
				'idempotency_key' => '' !== $idempotency_key ? $idempotency_key : null,
				'meta'            => [
					'message'             => (string) $request->get_param( 'message' ),
					'slice_budget_ms'     => max( 1, (int) $request->get_param( 'slice_budget_ms' ) > 0 ? (int) $request->get_param( 'slice_budget_ms' ) : 1500 ),
					'max_steps_per_slice' => max( 1, (int) $request->get_param( 'max_steps_per_slice' ) > 0 ? (int) $request->get_param( 'max_steps_per_slice' ) : 1 ),
				],
			]
		);

		if ( $run_id <= 0 ) {
			return new \WP_REST_Response(
				[
					'error' => __( 'Unable to create run.', 'clawpress' ),
				],
				500
			);
		}

		$this->enqueue_run_slice( $run_id );

		return new \WP_REST_Response(
			[
				'run_id'     => $run_id,
				'session_id' => $session_id,
				'status'     => 'queued',
			],
			201
		);
	}

	/**
	 * Spawn a background agent session + first run.
	 *
	 * @param \WP_REST_Request $request Request object.
	 */
	public function spawn_agent( \WP_REST_Request $request ): \WP_REST_Response {
		$requesting_user_id = function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0;
		$session_id         = $this->session_helper->create_session(
			[
				'trigger_type'       => 'spawned_agent',
				'requesting_user_id' => $requesting_user_id,
				'execution_user_id'  => $this->resolve_execution_user_id( $requesting_user_id ),
				'policy_profile'     => 'default',
			]
		);

		if ( $session_id <= 0 ) {
			return new \WP_REST_Response(
				[
					'error' => __( 'Unable to create session for spawned agent.', 'clawpress' ),
				],
				500
			);
		}

		$idempotency_key = trim( (string) $request->get_param( 'idempotency_key' ) );
		$run_id          = $this->run_helper->create_run(
			$session_id,
			[
				'trigger_type'    => 'spawned_agent',
				'transport_mode'  => $this->normalize_transport_mode( (string) $request->get_param( 'transport_mode' ) ),
				'max_attempts'    => max( 1, (int) $request->get_param( 'max_attempts' ) > 0 ? (int) $request->get_param( 'max_attempts' ) : 5 ),
				'idempotency_key' => '' !== $idempotency_key ? $idempotency_key : null,
				'meta'            => [
					'message'             => (string) $request->get_param( 'message' ),
					'slice_budget_ms'     => max( 1, (int) $request->get_param( 'slice_budget_ms' ) > 0 ? (int) $request->get_param( 'slice_budget_ms' ) : 1500 ),
					'max_steps_per_slice' => max( 1, (int) $request->get_param( 'max_steps_per_slice' ) > 0 ? (int) $request->get_param( 'max_steps_per_slice' ) : 1 ),
				],
			]
		);

		if ( $run_id <= 0 ) {
			return new \WP_REST_Response(
				[
					'error' => __( 'Unable to create run for spawned agent.', 'clawpress' ),
				],
				500
			);
		}

		$this->enqueue_run_slice( $run_id );

		return new \WP_REST_Response(
			[
				'session_id' => $session_id,
				'run_id'     => $run_id,
				'trigger'    => 'spawned_agent',
				'status'     => 'queued',
			],
			201
		);
	}

	/**
	 * Re-enqueue an agent run.
	 *
	 * @param \WP_REST_Request $request Request object.
	 */
	public function enqueue_run( \WP_REST_Request $request ): \WP_REST_Response {
		$run_id = (int) $request->get_param( 'run_id' );
		$run    = $this->run_helper->get_run( $run_id );
		if ( [] === $run ) {
			return new \WP_REST_Response(
				[
					'error' => __( 'Run not found.', 'clawpress' ),
				],
				404
			);
		}

		$current_status = isset( $run['status'] ) ? (string) $run['status'] : 'queued';
		if ( ! $this->run_helper->is_enqueueable_status( $current_status ) ) {
			return new \WP_REST_Response(
				[
					'error' => __( 'Run is not in a retryable state.', 'clawpress' ),
				],
				409
			);
		}

		if ( ! $this->run_helper->enqueue_run( $run_id ) ) {
			return new \WP_REST_Response(
				[
					'error' => __( 'Unable to enqueue run.', 'clawpress' ),
				],
				500
			);
		}

		$this->enqueue_run_slice( $run_id );

		return new \WP_REST_Response(
			[
				'run_id' => $run_id,
				'status' => 'queued',
			],
			200
		);
	}

	/**
	 * Get one run status summary.
	 *
	 * @param \WP_REST_Request $request Request object.
	 */
	public function get_run( \WP_REST_Request $request ): \WP_REST_Response {
		$run_id = (int) $request->get_param( 'run_id' );
		$run    = $this->run_helper->get_run_status_summary( $run_id );
		if ( [] === $run ) {
			return new \WP_REST_Response(
				[
					'error' => __( 'Run not found.', 'clawpress' ),
				],
				404
			);
		}

		return new \WP_REST_Response( $run, 200 );
	}

	/**
	 * Get run events after event cursor.
	 *
	 * @param \WP_REST_Request $request Request object.
	 */
	public function get_run_events( \WP_REST_Request $request ): \WP_REST_Response {
		$run_id = (int) $request->get_param( 'run_id' );
		$after  = max( 0, (int) $request->get_param( 'after' ) );
		$limit  = max( 1, min( 200, (int) $request->get_param( 'limit' ) ) );

		$events = $this->event_helper->get_run_events( $run_id, $after, $limit );
		$next   = $after;
		if ( [] !== $events ) {
			$next = (int) $events[ count( $events ) - 1 ]['event_id'];
		}

		return new \WP_REST_Response(
			[
				'run_id'      => $run_id,
				'after'       => $after,
				'next_cursor' => $next,
				'events'      => $events,
			],
			200
		);
	}

	/**
	 * Normalize transport mode.
	 *
	 * @param string $transport_mode Raw transport mode.
	 */
	private function normalize_transport_mode( string $transport_mode ): string {
		$transport_mode = strtolower( trim( $transport_mode ) );
		if ( ! in_array( $transport_mode, [ 'polling', 'streaming' ], true ) ) {
			return 'polling';
		}

		return $transport_mode;
	}

	/**
	 * Queue one run slice action.
	 *
	 * @param int $run_id Run identifier.
	 */
	private function enqueue_run_slice( int $run_id ): void {
		if ( $run_id <= 0 ) {
			return;
		}

		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action(
				Agent_Runner::RUN_SLICE_ACTION_HOOK,
				[ 'run_id' => $run_id ],
				Agent_Runner::ACTION_GROUP
			);
			return;
		}

		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action(
				time(),
				Agent_Runner::RUN_SLICE_ACTION_HOOK,
				[ 'run_id' => $run_id ],
				Agent_Runner::ACTION_GROUP
			);
		}
	}

	/**
	 * Resolve execution user for run/spawn session creation.
	 *
	 * @param int $requesting_user_id Requesting user id.
	 */
	private function resolve_execution_user_id( int $requesting_user_id ): int {
		$execution_user_id = $this->settings_helper->resolve_agent_user_id();
		if ( $execution_user_id > 0 ) {
			return $execution_user_id;
		}

		return $requesting_user_id > 0 ? $requesting_user_id : 0;
	}
}

<?php
/**
 * Logs REST controller.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\RestAPI\Controllers;

use ClawPress\Helpers\Action_Log_Helper;
use ClawPress\Helpers\Agent_Event_Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Logs endpoints controller.
 */
final class Logs_Controller implements Route_Controller {
	/**
	 * Default page size.
	 */
	private const DEFAULT_LIMIT = 50;

	/**
	 * Action log helper.
	 *
	 * @var Action_Log_Helper
	 */
	private Action_Log_Helper $action_log_helper;

	/**
	 * Agent event helper.
	 *
	 * @var Agent_Event_Helper
	 */
	private Agent_Event_Helper $agent_event_helper;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->action_log_helper = Action_Log_Helper::get_instance();
		$this->agent_event_helper = Agent_Event_Helper::get_instance();
	}

	/**
	 * Register logs endpoints.
	 */
	public function register_routes(): void {
		register_rest_route(
			'clawpress/v1',
			'/logs',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_logs' ],
				'permission_callback' => 'clawpress_check_permissions',
				'args'                => [
					'event_type' => [
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					],
					'limit'      => [
						'required'          => false,
						'validate_callback' => 'clawpress_validate_int',
						'sanitize_callback' => 'clawpress_sanitize_int',
					],
					'offset'     => [
						'required'          => false,
						'validate_callback' => 'clawpress_validate_int',
						'sanitize_callback' => 'clawpress_sanitize_int',
					],
				],
			]
		);

		register_rest_route(
			'clawpress/v1',
			'/logs',
			[
				'methods'             => 'DELETE',
				'callback'            => [ $this, 'clear_logs' ],
				'permission_callback' => 'clawpress_check_permissions',
			]
		);

		register_rest_route(
			'clawpress/v1',
			'/logs/linked-events',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_linked_events' ],
				'permission_callback' => 'clawpress_check_permissions',
				'args'                => [
					'run_id'     => [
						'required'          => false,
						'validate_callback' => 'clawpress_validate_int',
						'sanitize_callback' => 'clawpress_sanitize_int',
					],
					'session_id' => [
						'required'          => false,
						'validate_callback' => 'clawpress_validate_int',
						'sanitize_callback' => 'clawpress_sanitize_int',
					],
					'after'      => [
						'required'          => false,
						'validate_callback' => 'clawpress_validate_int',
						'sanitize_callback' => 'clawpress_sanitize_int',
					],
					'limit'      => [
						'required'          => false,
						'validate_callback' => 'clawpress_validate_int',
						'sanitize_callback' => 'clawpress_sanitize_int',
					],
				],
			]
		);
	}

	/**
	 * Return paginated action logs.
	 *
	 * @param \WP_REST_Request $request Request object.
	 */
	public function get_logs( \WP_REST_Request $request ): \WP_REST_Response {
		$event_type = $this->normalize_event_type( $request->get_param( 'event_type' ) );
		$limit      = $this->normalize_limit( $request->get_param( 'limit' ) );
		$offset     = $this->normalize_offset( $request->get_param( 'offset' ) );

		$counts_by_type = $this->action_log_helper->get_log_counts_by_type();
		$total          = '' !== $event_type
			? ( $counts_by_type[ $event_type ] ?? 0 )
			: $this->action_log_helper->get_log_count();
		$items          = 0 === $total
			? []
			: $this->action_log_helper->get_recent_logs(
				[
					'event_type' => $event_type,
					'limit'      => $limit,
					'offset'     => $offset,
				]
			);

		return new \WP_REST_Response(
			[
				'items'          => $items,
				'counts_by_type' => $counts_by_type,
				'total'          => $total,
				'limit'          => $limit,
				'offset'         => $offset,
				'has_more'       => ( $offset + count( $items ) ) < $total,
			],
			200
		);
	}

	/**
	 * Clear all action logs.
	 */
	public function clear_logs(): \WP_REST_Response {
		$deleted = $this->action_log_helper->delete_all_logs();

		return new \WP_REST_Response(
			[
				'success' => true,
				'deleted' => $deleted,
			],
			200
		);
	}

	/**
	 * Return agent events linked to a log row.
	 *
	 * @param \WP_REST_Request $request Request object.
	 */
	public function get_linked_events( \WP_REST_Request $request ): \WP_REST_Response {
		$run_id     = max( 0, (int) $request->get_param( 'run_id' ) );
		$session_id = max( 0, (int) $request->get_param( 'session_id' ) );
		$after      = max( 0, (int) $request->get_param( 'after' ) );
		$limit      = $this->normalize_linked_event_limit( $request->get_param( 'limit' ) );

		if ( $run_id <= 0 && $session_id <= 0 ) {
			return new \WP_REST_Response(
				[
					'error' => __( 'A run ID or session ID is required.', 'clawpress' ),
				],
				400
			);
		}

		$events = $run_id > 0
			? $this->agent_event_helper->get_run_events( $run_id, $after, $limit )
			: $this->agent_event_helper->get_session_events( $session_id, $after, $limit );
		$next   = [] !== $events
			? (int) $events[ count( $events ) - 1 ]['event_id']
			: $after;

		return new \WP_REST_Response(
			[
				'scope'       => $run_id > 0 ? 'run' : 'session',
				'run_id'      => $run_id > 0 ? $run_id : null,
				'session_id'  => $session_id > 0 ? $session_id : null,
				'after'       => $after,
				'next_cursor' => $next,
				'has_more'    => count( $events ) >= $limit,
				'events'      => $events,
			],
			200
		);
	}

	/**
	 * Normalize optional event type filter.
	 *
	 * @param mixed $event_type Raw event type.
	 */
	private function normalize_event_type( $event_type ): string {
		$event_type = is_string( $event_type ) ? trim( $event_type ) : '';
		return '' !== $event_type ? sanitize_text_field( $event_type ) : '';
	}

	/**
	 * Normalize page size.
	 *
	 * @param mixed $limit Raw limit.
	 */
	private function normalize_limit( $limit ): int {
		$limit = is_numeric( $limit ) ? (int) $limit : self::DEFAULT_LIMIT;
		return $limit > 0 ? min( 500, $limit ) : self::DEFAULT_LIMIT;
	}

	/**
	 * Normalize page offset.
	 *
	 * @param mixed $offset Raw offset.
	 */
	private function normalize_offset( $offset ): int {
		$offset = is_numeric( $offset ) ? (int) $offset : 0;
		return max( 0, $offset );
	}

	/**
	 * Normalize linked-event page size.
	 *
	 * @param mixed $limit Raw limit.
	 */
	private function normalize_linked_event_limit( $limit ): int {
		$limit = is_numeric( $limit ) ? (int) $limit : 100;
		return $limit > 0 ? min( 200, $limit ) : 100;
	}
}

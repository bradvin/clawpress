<?php
/**
 * Offline command parser and dispatcher.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Commands;

use ClawPress\Commands\Handlers\Clear_Command_Handler;
use ClawPress\Commands\Handlers\Create_Agent_Files_Command_Handler;
use ClawPress\Commands\Handlers\Create_Agent_User_Command_Handler;
use ClawPress\Commands\Handlers\Create_Workspace_Command_Handler;
use ClawPress\Commands\Handlers\Help_Command_Handler;
use ClawPress\Commands\Handlers\Memory_Command_Handler;
use ClawPress\Commands\Handlers\Setup_Command_Handler;
use ClawPress\Commands\Handlers\Reset_Command_Handler;
use ClawPress\Commands\Handlers\Settings_Command_Handler;
use ClawPress\Commands\Handlers\Site_Command_Handler;
use ClawPress\Commands\Handlers\Status_Command_Handler;
use ClawPress\Commands\Handlers\Test_Command_Handler;
use ClawPress\Commands\Handlers\Tools_Command_Handler;
use ClawPress\Helpers\Chat_History_Helper;
use ClawPress\Helpers\Agent_File_Helper;
use ClawPress\Helpers\Abilities_Helper;
use ClawPress\Helpers\Memory_Helper;
use ClawPress\Helpers\Model_Helper;
use ClawPress\Helpers\Provider_Helper;
use ClawPress\Helpers\Settings_Helper;
use ClawPress\Helpers\Status_Helper;
use ClawPress\Helpers\User_Helper;
use ClawPress\Helpers\Workspace_Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Command module.
 */
final class Commands {
	/**
	 * Command registry.
	 *
	 * @var Command_Registry
	 */
	private Command_Registry $registry;

	/**
	 * Shared settings helper.
	 *
	 * @var Settings_Helper
	 */
	private Settings_Helper $settings_helper;

	/**
	 * Shared abilities helper.
	 *
	 * @var Abilities_Helper
	 */
	private Abilities_Helper $abilities_helper;

	/**
	 * Shared confirmation store.
	 *
	 * @var Command_Confirmation_Store
	 */
	private Command_Confirmation_Store $confirmation_store;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->registry           = new Command_Registry();
		$this->settings_helper    = Settings_Helper::get_instance();
		$this->abilities_helper   = Abilities_Helper::get_instance();
		$this->confirmation_store = new Command_Confirmation_Store();

		$settings_helper    = $this->settings_helper;
		$agent_file_helper  = Agent_File_Helper::get_instance();
		$abilities_helper   = $this->abilities_helper;
		$model_helper       = Model_Helper::get_instance();
		$memory_helper      = Memory_Helper::get_instance();
		$provider_helper    = Provider_Helper::get_instance();
		$status_helper      = Status_Helper::get_instance();
		$history_helper     = Chat_History_Helper::get_instance();
		$user_helper        = User_Helper::get_instance();
		$workspace_helper   = Workspace_Helper::get_instance();
		$confirmation_store = $this->confirmation_store;

		$this->registry->register( new Help_Command_Handler( $this->registry ), false );
		$this->registry->register( new Status_Command_Handler( $status_helper ), false );
		$this->registry->register( new Setup_Command_Handler( $settings_helper, $provider_helper, $model_helper, $user_helper, $workspace_helper, $agent_file_helper ), false );
		$this->registry->register( new Memory_Command_Handler( $settings_helper, $confirmation_store, $memory_helper ), true );
		$this->registry->register( new Clear_Command_Handler( $history_helper ), true );
		$this->registry->register( new Site_Command_Handler(), false );
		$this->registry->register( new Tools_Command_Handler( $status_helper, $abilities_helper ), false );
		$this->registry->register( new Test_Command_Handler( $settings_helper, $provider_helper ), false );
		$this->registry->register( new Create_Agent_User_Command_Handler( $user_helper ), false, false );
		$this->registry->register( new Create_Workspace_Command_Handler( $settings_helper, $workspace_helper ), false, false );
		$this->registry->register( new Create_Agent_Files_Command_Handler( $agent_file_helper ), false, false );
		$this->registry->register( new Reset_Command_Handler(), true, false );
		$this->registry->register( new Settings_Command_Handler( $settings_helper ), false, false );
	}

	/**
	 * Parse and dispatch a command from a raw message.
	 *
	 * @param string $message Incoming chat message.
	 * @return array<string,mixed>|null
	 */
	public function maybe_dispatch( string $message ): ?array {
		$request = Command_Request::from_message( $message );
		if ( null === $request ) {
			return null;
		}

		$response = $this->dispatch_special_command( $request );
		if ( ! $response instanceof Command_Response ) {
			$response = $this->dispatch( $request );
		}
		$reply = clawpress_sanitize_multiline_text( $response->get_text() );

		return [
			'reply'       => $reply,
			'mode'        => 'offline',
			'provider'    => null,
			'model'       => null,
			'suggestions' => $response->get_suggestions(),
			'card'        => $response->get_card(),
			'command'     => [
				'name'                  => $response->get_command(),
				'error'                 => $response->is_error(),
				'destructive'           => $response->is_destructive(),
				'requires_confirmation' => $response->requires_confirmation(),
				'effects'               => $response->get_effects(),
			],
		];
	}

	/**
	 * Dispatch special built-in commands handled outside the registry.
	 *
	 * @param Command_Request $request Parsed request.
	 */
	private function dispatch_special_command( Command_Request $request ): ?Command_Response {
		$command = strtolower( trim( $request->get_command() ) );
		if ( '/confirm' === $command ) {
			return $this->handle_confirm_command( $request );
		}

		if ( '/decline' === $command ) {
			return $this->handle_decline_command( $request );
		}

		return null;
	}

	/**
	 * Handle `/confirm` command for pending tool-confirmation batches.
	 *
	 * @param Command_Request $request Parsed request.
	 */
	private function handle_confirm_command( Command_Request $request ): Command_Response {
		$pending_batch = $this->confirmation_store->get_tool_batch();
		if ( null === $pending_batch ) {
			return Command_Response::error(
				__( 'There is no active confirmation batch to confirm.', 'clawpress' ),
				'/confirm',
				true,
				false,
				[],
				[ '/status', '/help' ]
			);
		}

		$requested_batch_id = $this->extract_requested_batch_id( $request );
		if ( null !== $requested_batch_id && $requested_batch_id !== $pending_batch['batch_id'] ) {
			return Command_Response::error(
				__( 'That batch is no longer active. Confirm the current batch from the latest confirmation card.', 'clawpress' ),
				'/confirm',
				true,
				false,
				[],
				[ '/confirm --batch=' . $pending_batch['batch_id'], '/status', '/help' ]
			);
		}

		$confirmed_batch = $this->confirmation_store->consume_tool_batch( $pending_batch['batch_id'] );
		if ( null === $confirmed_batch ) {
			return Command_Response::error(
				__( 'Unable to confirm this batch. Please request confirmation again.', 'clawpress' ),
				'/confirm',
				true,
				false,
				[],
				[ '/status', '/help' ]
			);
		}

		$requesting_user_id = function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0;
		$execution_user_id  = $this->settings_helper->resolve_agent_user_id();
		if ( $execution_user_id <= 0 ) {
			$execution_user_id = $requesting_user_id;
		}

		$calls         = $confirmed_batch['calls'];
		$total_calls   = count( $calls );
		$success_count = 0;
		$error_count   = 0;
		$lines         = [
			sprintf(
				/* translators: 1: batch ID, 2: number of tool calls */
				__( 'Confirmed batch `%1$s`. Executing %2$d tool call(s):', 'clawpress' ),
				$confirmed_batch['batch_id'],
				$total_calls
			),
		];

		foreach ( $calls as $index => $tool_call ) {
			$tool_name = isset( $tool_call['tool_name'] ) ? (string) $tool_call['tool_name'] : '';
			if ( '' === $tool_name ) {
				continue;
			}

			$args   = isset( $tool_call['args'] ) && is_array( $tool_call['args'] ) ? $tool_call['args'] : [];
			$result = $this->abilities_helper->execute_tool_call(
				$tool_name,
				$args,
				[
					'requesting_user_id' => $requesting_user_id,
					'execution_user_id'  => $execution_user_id,
					'skip_confirmation'  => true,
				]
			);

			$is_success = isset( $result['success'] ) && true === $result['success'];
			if ( $is_success ) {
				++$success_count;
			} else {
				++$error_count;
			}

			$status_label = $is_success
				? __( 'success', 'clawpress' )
				: __( 'error', 'clawpress' );
			$detail       = '';
			if ( isset( $result['error']['message'] ) ) {
				$detail = trim( (string) $result['error']['message'] );
			} elseif ( isset( $result['result']['message'] ) ) {
				$detail = trim( (string) $result['result']['message'] );
			}

			$lines[] = sprintf(
				/* translators: 1: sequence number, 2: tool name, 3: status, 4: optional detail */
				__( '%1$d. `%2$s` -> %3$s%4$s', 'clawpress' ),
				$index + 1,
				$tool_name,
				$status_label,
				'' !== $detail ? ' (' . $detail . ')' : ''
			);
		}

		$lines[] = sprintf(
			/* translators: 1: success count, 2: failure count */
			__( 'Batch result: %1$d succeeded, %2$d failed.', 'clawpress' ),
			$success_count,
			$error_count
		);

		if ( $error_count > 0 ) {
			return Command_Response::error(
				implode( "\n", $lines ),
				'/confirm',
				true,
				false,
				[],
				[ '/status', '/tools list', '/help' ]
			);
		}

		return Command_Response::success(
			implode( "\n", $lines ),
			'/confirm',
			true,
			false,
			[],
			[ '/status', '/tools list', '/help' ]
		);
	}

	/**
	 * Handle `/decline` command for pending tool-confirmation batches.
	 *
	 * @param Command_Request $request Parsed request.
	 */
	private function handle_decline_command( Command_Request $request ): Command_Response {
		$pending_batch = $this->confirmation_store->get_tool_batch();
		if ( null === $pending_batch ) {
			return Command_Response::success(
				__( 'There is no active confirmation batch to decline.', 'clawpress' ),
				'/decline',
				false,
				false,
				[],
				[ '/status', '/help' ]
			);
		}

		$requested_batch_id = $this->extract_requested_batch_id( $request );
		if ( null !== $requested_batch_id && $requested_batch_id !== $pending_batch['batch_id'] ) {
			return Command_Response::error(
				__( 'That batch is no longer active. Decline the current batch from the latest confirmation card.', 'clawpress' ),
				'/decline',
				false,
				false,
				[],
				[ '/decline --batch=' . $pending_batch['batch_id'], '/status', '/help' ]
			);
		}

		$this->confirmation_store->clear_tool_batch();

		return Command_Response::success(
			sprintf(
				/* translators: %s: batch ID */
				__( 'Declined confirmation batch `%s`. No pending destructive tool calls were executed.', 'clawpress' ),
				$pending_batch['batch_id']
			),
			'/decline',
			false,
			false,
			[],
			[ '/status', '/help' ]
		);
	}

	/**
	 * Extract requested batch ID from `/confirm` or `/decline`.
	 *
	 * @param Command_Request $request Parsed request.
	 */
	private function extract_requested_batch_id( Command_Request $request ): ?string {
		$batch_id = $request->get_option_value( 'batch' );
		if ( null === $batch_id || '' === trim( $batch_id ) ) {
			$first_argument = trim( $request->get_argument( 0 ) );
			if ( in_array( strtolower( $first_argument ), [ 'all', '--all' ], true ) ) {
				$first_argument = '';
			}

			if ( '' !== $first_argument && 0 !== strpos( $first_argument, '--' ) ) {
				$batch_id = $first_argument;
			}
		}

		$batch_id = null === $batch_id ? '' : strtolower( trim( $batch_id ) );
		return '' !== $batch_id ? $batch_id : null;
	}

	/**
	 * Dispatch parsed command request.
	 *
	 * @param Command_Request $request Parsed request.
	 */
	private function dispatch( Command_Request $request ): Command_Response {
		$handler = $this->registry->get_handler( $request->get_command() );
		if ( null === $handler ) {
			return Command_Response::error(
				sprintf(
					/* translators: 1: command name, 2: help text */
					__( "Unknown command: `%1\$s`.\n\n%2\$s", 'clawpress' ),
					$request->get_command(),
					$this->get_help_text()
				),
				'/help',
				false,
				false,
				[],
				[ '/help' ]
			);
		}

		$response = $handler->handle( $request );
		if ( ! $response->is_error() || '/help' === $request->get_command() ) {
			return $response;
		}

		return Command_Response::error(
			$response->get_text(),
			$response->get_command(),
			$response->is_destructive(),
			$response->requires_confirmation(),
			$response->get_effects(),
			[] !== $response->get_suggestions()
				? $response->get_suggestions()
				: $this->get_help_suggestions(),
			$response->get_card()
		);
	}

	/**
	 * Build help text fallback.
	 */
	private function get_help_text(): string {
		return __( 'Use `/help` to view available commands.', 'clawpress' );
	}

	/**
	 * Resolve help suggestions fallback.
	 *
	 * @return array<int,string>
	 */
	private function get_help_suggestions(): array {
		$handler = $this->registry->get_handler( '/help' );
		if ( $handler instanceof Help_Command_Handler ) {
			return $handler->get_help_suggestions();
		}

		return [];
	}

	/**
	 * Resolve default offline suggestions from registered handlers.
	 *
	 * @return array<int,string>
	 */
	public function get_default_suggestions(): array {
		$suggestions = [];

		foreach ( $this->registry->get_visible_handlers() as $handler ) {
			$suggestions = array_merge( $suggestions, $handler->get_default_suggestions() );
		}

		$seen               = [];
		$unique_suggestions = [];

		foreach ( $suggestions as $suggestion ) {
			$normalized = trim( (string) $suggestion );
			if ( '' === $normalized || isset( $seen[ $normalized ] ) ) {
				continue;
			}

			$seen[ $normalized ]  = true;
			$unique_suggestions[] = $normalized;
		}

		return array_values( $unique_suggestions );
	}
}

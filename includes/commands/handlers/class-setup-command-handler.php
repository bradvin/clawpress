<?php
/**
 * /setup command handler.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Commands\Handlers;

use ClawPress\Commands\Command_Handler;
use ClawPress\Commands\Command_Request;
use ClawPress\Commands\Command_Response;
use ClawPress\Helpers\Agent_File_Helper;
use ClawPress\Helpers\Model_Helper;
use ClawPress\Helpers\Provider_Helper;
use ClawPress\Helpers\Settings_Helper;
use ClawPress\Helpers\User_Helper;
use ClawPress\Helpers\Workspace_Helper;
use ClawPress\PostTypes\Post_Types;
use Throwable;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;

defined( 'ABSPATH' ) || exit;

/**
 * Setup command.
 */
final class Setup_Command_Handler implements Command_Handler {
	/**
	 * Setup option key.
	 */
	private const SETUP_STATE_OPTION = 'clawpress_setup_state';

	/**
	 * Provider setup admin path.
	 */
	private const PROVIDER_SETUP_PATH = '/wp-admin/options-general.php?page=wp-ai-client';

	/**
	 * ClawPress settings admin path.
	 */
	private const CLAWPRESS_SETTINGS_PATH = '/wp-admin/admin.php?page=clawpress';

	/**
	 * Step order.
	 *
	 * @var array<int,string>
	 */
	private const STEP_ORDER = [
		'provider',
		'model',
		'test_connection',
		'agent_user',
		'workspace',
		'agent_files',
		'ready',
	];

	/**
	 * Settings helper.
	 *
	 * @var Settings_Helper
	 */
	private Settings_Helper $settings_helper;

	/**
	 * Provider helper.
	 *
	 * @var Provider_Helper
	 */
	private Provider_Helper $provider_helper;

	/**
	 * Model helper.
	 *
	 * @var Model_Helper
	 */
	private Model_Helper $model_helper;

	/**
	 * User helper.
	 *
	 * @var User_Helper
	 */
	private User_Helper $user_helper;

	/**
	 * Workspace helper.
	 *
	 * @var Workspace_Helper
	 */
	private Workspace_Helper $workspace_helper;

	/**
	 * Agent file helper.
	 *
	 * @var Agent_File_Helper
	 */
	private Agent_File_Helper $agent_file_helper;

	/**
	 * Constructor.
	 *
	 * @param Settings_Helper   $settings_helper Settings helper.
	 * @param Provider_Helper   $provider_helper Provider helper.
	 * @param Model_Helper      $model_helper Model helper.
	 * @param User_Helper       $user_helper User helper.
	 * @param Workspace_Helper  $workspace_helper Workspace helper.
	 * @param Agent_File_Helper $agent_file_helper Agent file helper.
	 */
	public function __construct(
		Settings_Helper $settings_helper,
		Provider_Helper $provider_helper,
		Model_Helper $model_helper,
		User_Helper $user_helper,
		Workspace_Helper $workspace_helper,
		Agent_File_Helper $agent_file_helper
	) {
		$this->settings_helper   = $settings_helper;
		$this->provider_helper   = $provider_helper;
		$this->model_helper      = $model_helper;
		$this->user_helper       = $user_helper;
		$this->workspace_helper  = $workspace_helper;
		$this->agent_file_helper = $agent_file_helper;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_command(): string {
		return '/setup';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return __( 'Run the setup wizard and manage setup progress.', 'clawpress' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_usage(): string {
		return '/setup <action>';
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_destructive(): bool {
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_default_suggestions(): array {
		return [ '/setup resume' ];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Command_Request $request Command request.
	 * @return Command_Response
	 */
	public function handle( Command_Request $request ): Command_Response {
		$action = strtolower( trim( $request->get_argument( 0 ) ) );
		if ( '' === $action ) {
			$action = 'resume';
		}

		switch ( $action ) {
			case 'start':
				return $this->start_setup();
			case 'resume':
				return $this->resume_setup();
			case 'reset':
				return $this->reset_setup();
			case 'refresh':
				return $this->resume_setup();
			case 'back':
				return $this->go_back_step();
			case 'provider':
				return $this->set_provider( $request );
			case 'model':
				return $this->set_model( $request );
			case 'test':
				return $this->test_connection();
			case 'agent-user':
				return $this->set_agent_user( $request );
			case 'create-agent-user':
				return $this->create_agent_user();
			case 'create-workspace':
				return $this->create_workspace();
			case 'create-agent-files':
				return $this->create_agent_files();
			default:
				return $this->build_error_response(
					sprintf(
						/* translators: %s: expected command usage */
						__( 'Invalid setup action. Expected: `%s`', 'clawpress' ),
						$this->get_usage()
					)
				);
		}
	}

	/**
	 * Start setup flow.
	 */
	private function start_setup(): Command_Response {
		$this->persist_setup_state(
			[
				'connection_tested' => false,
				'step'              => 'provider',
			]
		);
		$this->settings_helper->update_settings( [ 'setup_completed' => false ] );

		return $this->resume_with_notice( __( 'Setup started.', 'clawpress' ) );
	}

	/**
	 * Resume setup flow.
	 */
	private function resume_setup(): Command_Response {
		return $this->resume_with_notice( __( 'Setup resumed.', 'clawpress' ) );
	}

	/**
	 * Reset setup flow.
	 */
	private function reset_setup(): Command_Response {
		$this->persist_setup_state(
			[
				'connection_tested' => false,
				'step'              => 'provider',
			]
		);
		$this->settings_helper->update_settings(
			[
				'provider'        => '',
				'model'           => '',
				'setup_completed' => false,
			]
		);

		return $this->resume_with_notice( __( 'Setup reset.', 'clawpress' ) );
	}

	/**
	 * Move setup to the previous wizard step.
	 */
	private function go_back_step(): Command_Response {
		$settings     = $this->settings_helper->get_settings();
		$current_step = $this->resolve_setup_step( $settings );
		$target_step  = $this->get_previous_step( $current_step );

		if ( $target_step === $current_step ) {
			return $this->resume_with_notice( __( 'Already at the first step.', 'clawpress' ) );
		}

		$this->persist_setup_state( [ 'step' => $target_step ] );
		$this->settings_helper->update_settings( [ 'setup_completed' => false ] );

		return $this->resume_with_notice(
			sprintf(
				/* translators: %s: step key */
				__( 'Moved back to `%s`.', 'clawpress' ),
				$target_step
			)
		);
	}

	/**
	 * Set provider from wizard action.
	 *
	 * @param Command_Request $request Parsed request.
	 */
	private function set_provider( Command_Request $request ): Command_Response {
		$provider = clawpress_sanitize_provider( $request->get_argument( 1 ) );
		$choices  = $this->provider_helper->get_configured_provider_ids();

		if ( '' === $provider || ! in_array( $provider, $choices, true ) ) {
			return $this->build_error_response(
				__( 'Selected provider is not available. Refresh providers and try again.', 'clawpress' )
			);
		}

		$this->settings_helper->update_settings(
			[
				'provider'        => $provider,
				'model'           => '',
				'setup_completed' => false,
			]
		);
		$this->persist_setup_state( [ 'connection_tested' => false ] );
		$this->persist_setup_state( [ 'step' => 'model' ] );

		return $this->resume_with_notice(
			sprintf(
				/* translators: %s: provider slug */
				__( 'Provider selected: `%s`.', 'clawpress' ),
				$provider
			)
		);
	}

	/**
	 * Set model from wizard action.
	 *
	 * @param Command_Request $request Parsed request.
	 */
	private function set_model( Command_Request $request ): Command_Response {
		$settings        = $this->settings_helper->get_settings();
		$provider        = clawpress_sanitize_provider( $settings['provider'] ?? '' );
		$model_arguments = array_slice( $request->get_arguments(), 1 );
		$model           = $this->sanitize_model_id( implode( ' ', $model_arguments ) );

		if ( '' === $provider ) {
			return $this->build_error_response(
				__( 'Select a provider before choosing a model.', 'clawpress' )
			);
		}

		if ( ! $this->is_model_valid_for_provider( $provider, $model ) ) {
			return $this->build_error_response(
				__( 'Selected model is not available for the current provider. Enter a valid model ID and try again.', 'clawpress' )
			);
		}

		$this->settings_helper->update_settings(
			[
				'model'           => $model,
				'setup_completed' => false,
			]
		);
		$this->persist_setup_state( [ 'connection_tested' => false ] );
		$this->persist_setup_state( [ 'step' => 'test_connection' ] );

		return $this->resume_with_notice(
			sprintf(
				/* translators: %s: model identifier */
				__( 'Model selected: `%s`.', 'clawpress' ),
				$model
			)
		);
	}

	/**
	 * Execute connection test step.
	 */
	private function test_connection(): Command_Response {
		$settings        = $this->settings_helper->get_settings();
		$provider        = clawpress_sanitize_provider( $settings['provider'] ?? '' );
		$model           = $this->sanitize_model_id( (string) ( $settings['model'] ?? '' ) );
		$model_is_valid  = $this->is_model_valid_for_provider( $provider, $model );
		$request_timeout = $this->settings_helper->get_request_timeout( $settings );

		if ( '' === $provider || '' === $model || ! $model_is_valid ) {
			return $this->build_error_response(
				__( 'Select valid provider and model values before testing.', 'clawpress' )
			);
		}

		try {
			$request_options = new RequestOptions();
			$request_options->setTimeout( (float) $request_timeout );

			$reply = trim(
				AiClient::prompt( 'Reply with exactly: OK' )
					->usingProvider( $provider )
					->usingModelPreference( [ $provider, $model ] )
					->usingRequestOptions( $request_options )
					->generateText()
			);

			if ( '' === $reply ) {
				return $this->build_error_response(
					__( 'Connection test failed: empty provider response.', 'clawpress' )
				);
			}
		} catch ( Throwable $throwable ) {
			$message = trim( sanitize_text_field( $throwable->getMessage() ) );
			if ( '' === $message ) {
				$message = __( 'Unknown provider error.', 'clawpress' );
			}

			return $this->build_error_response(
				sprintf(
					/* translators: %s: provider error message */
					__( 'Connection test failed: %s', 'clawpress' ),
					$message
				)
			);
		}

		$this->persist_setup_state(
			[
				'connection_tested' => true,
				'step'              => 'agent_user',
			]
		);

		return $this->resume_with_notice( __( 'Connection test passed.', 'clawpress' ) );
	}

	/**
	 * Create and set a dedicated agent user.
	 */
	private function create_agent_user(): Command_Response {
		$result = $this->user_helper->create_agent_user();
		if ( empty( $result['success'] ) ) {
			$error_message = isset( $result['error'] ) && is_string( $result['error'] ) && '' !== trim( $result['error'] )
				? $result['error']
				: __( 'Could not create agent user.', 'clawpress' );

			return $this->build_error_response(
				sprintf(
					/* translators: %s: error message */
					__( 'Agent user creation failed: %s', 'clawpress' ),
					$error_message
				)
			);
		}

		$user_id = isset( $result['user_id'] ) ? (int) $result['user_id'] : 0;
		if ( $user_id <= 0 ) {
			return $this->build_error_response( __( 'Agent user creation returned an invalid user ID.', 'clawpress' ) );
		}

		$this->settings_helper->update_settings(
			[
				'agent_user_id'   => $user_id,
				'setup_completed' => false,
			]
		);
		$this->persist_setup_state( [ 'step' => 'workspace' ] );

		return $this->resume_with_notice(
			sprintf(
				/* translators: %d: user ID */
				__( 'Agent user configured: `%d`.', 'clawpress' ),
				$user_id
			)
		);
	}

	/**
	 * Set agent user by user ID.
	 *
	 * @param Command_Request $request Parsed request.
	 */
	private function set_agent_user( Command_Request $request ): Command_Response {
		$user_id_raw = trim( $request->get_argument( 1 ) );
		if ( '' === $user_id_raw || ! preg_match( '/^\d+$/', $user_id_raw ) ) {
			return $this->build_error_response( __( 'Provide a valid user ID for the agent user step.', 'clawpress' ) );
		}

		$user_id = (int) $user_id_raw;
		if ( $user_id <= 0 ) {
			return $this->build_error_response( __( 'Invalid agent user ID.', 'clawpress' ) );
		}

		$user = $this->user_helper->get_user_by_id( $user_id );
		if ( ! $user instanceof \WP_User ) {
			return $this->build_error_response( __( 'Agent user does not exist.', 'clawpress' ) );
		}

		$this->settings_helper->update_settings(
			[
				'agent_user_id'   => $user_id,
				'setup_completed' => false,
			]
		);
		$this->persist_setup_state( [ 'step' => 'workspace' ] );

		return $this->resume_with_notice(
			sprintf(
				/* translators: 1: user login, 2: user ID */
				__( 'Using agent user `%1$s` (ID `%2$d`).', 'clawpress' ),
				$user->user_login,
				$user_id
			)
		);
	}

	/**
	 * Create workspace for configured agent user.
	 */
	private function create_workspace(): Command_Response {
		$settings      = $this->settings_helper->get_settings();
		$agent_user_id = $this->settings_helper->resolve_agent_user_id( $settings );

		if ( $agent_user_id <= 0 ) {
			return $this->build_error_response( __( 'Set an agent user before creating a workspace.', 'clawpress' ) );
		}

		$result = $this->workspace_helper->create_workspace_for_agent_user( $agent_user_id );
		if ( empty( $result['success'] ) ) {
			$error_message = isset( $result['error'] ) && is_string( $result['error'] ) && '' !== trim( $result['error'] )
				? $result['error']
				: __( 'Could not create workspace.', 'clawpress' );

			return $this->build_error_response(
				sprintf(
					/* translators: %s: error message */
					__( 'Workspace creation failed: %s', 'clawpress' ),
					$error_message
				)
			);
		}
		$this->persist_setup_state( [ 'step' => 'agent_files' ] );

		return $this->resume_with_notice( __( 'Workspace created.', 'clawpress' ) );
	}

	/**
	 * Create agent files from templates.
	 */
	private function create_agent_files(): Command_Response {
		$result      = $this->agent_file_helper->create_default_agent_files_from_templates();
		$error_count = isset( $result['errors'] ) && is_array( $result['errors'] ) ? count( $result['errors'] ) : 0;

		if ( $error_count > 0 ) {
			return $this->build_error_response(
				sprintf(
					/* translators: %d: error count */
					__( 'Agent file bootstrap failed with %d error(s).', 'clawpress' ),
					$error_count
				)
			);
		}
		$this->persist_setup_state( [ 'step' => 'ready' ] );

		return $this->resume_with_notice( __( 'Agent files created.', 'clawpress' ) );
	}

	/**
	 * Resolve current setup step from settings + persisted state.
	 *
	 * @param array<string,mixed> $settings Settings.
	 */
	private function resolve_setup_step( array $settings ): string {
		$state             = $this->get_setup_state();
		$configured        = $this->provider_helper->get_configured_provider_ids();
		$selected_provider = clawpress_sanitize_provider( $settings['provider'] ?? '' );
		$requested_step    = isset( $state['step'] ) ? $this->normalize_step( $state['step'] ) : 'agent_user';

		if ( [] === $configured || '' === $selected_provider || ! in_array( $selected_provider, $configured, true ) ) {
			$this->settings_helper->update_settings( [ 'setup_completed' => false ] );
			return 'provider';
		}

		if ( 'provider' === $requested_step ) {
			$this->settings_helper->update_settings( [ 'setup_completed' => false ] );
			return 'provider';
		}

		$selected_model = trim( (string) ( $settings['model'] ?? '' ) );
		if ( ! $this->is_model_valid_for_provider( $selected_provider, $selected_model ) ) {
			$this->settings_helper->update_settings( [ 'setup_completed' => false ] );
			return 'model';
		}

		if ( 'model' === $requested_step ) {
			$this->settings_helper->update_settings( [ 'setup_completed' => false ] );
			return 'model';
		}

		$connection_tested = isset( $state['connection_tested'] ) && true === $state['connection_tested'];
		if ( ! $connection_tested && ! in_array( $requested_step, [ 'provider', 'model', 'test_connection' ], true ) ) {
			$this->settings_helper->update_settings( [ 'setup_completed' => false ] );
			return 'test_connection';
		}
		if ( 'test_connection' === $requested_step ) {
			$this->settings_helper->update_settings( [ 'setup_completed' => false ] );
			return 'test_connection';
		}

		$agent_user_id = $this->settings_helper->resolve_agent_user_id( $settings );
		if ( $agent_user_id <= 0 ) {
			$this->settings_helper->update_settings( [ 'setup_completed' => false ] );
			return 'agent_user';
		}

		if ( in_array( $requested_step, [ 'workspace', 'agent_files', 'ready' ], true ) && $agent_user_id <= 0 ) {
			$this->settings_helper->update_settings( [ 'setup_completed' => false ] );
			return 'agent_user';
		}

		$workspace_ready = $this->is_workspace_ready( $agent_user_id );
		if ( in_array( $requested_step, [ 'agent_files', 'ready' ], true ) && ! $workspace_ready ) {
			$this->settings_helper->update_settings( [ 'setup_completed' => false ] );
			return 'workspace';
		}

		$agent_files_ready = $this->agent_file_helper->has_default_agent_files_from_templates();
		if ( 'ready' === $requested_step && ! $agent_files_ready ) {
			$this->settings_helper->update_settings( [ 'setup_completed' => false ] );
			return 'agent_files';
		}

		$is_fully_ready = $agent_user_id > 0 && $workspace_ready && $agent_files_ready;
		$this->settings_helper->update_settings( [ 'setup_completed' => $is_fully_ready && 'ready' === $requested_step ] );

		return $requested_step;
	}

	/**
	 * Build success response with refreshed wizard card.
	 *
	 * @param string $message Success message.
	 */
	private function resume_with_notice( string $message ): Command_Response {
		$settings = $this->settings_helper->get_settings();
		$step     = $this->resolve_setup_step( $settings );

		$this->persist_setup_state( [ 'step' => $step ] );

		return Command_Response::success(
			$message,
			$this->get_command(),
			false,
			false,
			[],
			[ '/setup resume', '/status', '/help' ],
			$this->build_setup_card( $step, $settings )
		);
	}

	/**
	 * Build error response with current wizard card.
	 *
	 * @param string $message Error message.
	 */
	private function build_error_response( string $message ): Command_Response {
		$settings = $this->settings_helper->get_settings();
		$step     = $this->resolve_setup_step( $settings );

		return Command_Response::error(
			$message,
			$this->get_command(),
			false,
			false,
			[],
			[ '/setup resume', '/status', '/help' ],
			$this->build_setup_card( $step, $settings, $message )
		);
	}

	/**
	 * Build setup card payload.
	 *
	 * @param string              $step Current step.
	 * @param array<string,mixed> $settings Settings.
	 * @param string              $error Optional error.
	 * @return array<string,mixed>
	 */
	private function build_setup_card( string $step, array $settings, string $error = '' ): array {
		$labels = [
			'provider'        => __( 'Provider', 'clawpress' ),
			'model'           => __( 'Model', 'clawpress' ),
			'test_connection' => __( 'Test Connection', 'clawpress' ),
			'agent_user'      => __( 'Agent User', 'clawpress' ),
			'workspace'       => __( 'Workspace', 'clawpress' ),
			'agent_files'     => __( 'Agent Files', 'clawpress' ),
			'ready'           => __( 'Done', 'clawpress' ),
		];

		$step_index = array_search( $step, self::STEP_ORDER, true );
		if ( false === $step_index ) {
			$step_index = 0;
		}

		$is_completed_state = $step_index === count( self::STEP_ORDER ) - 1;
		$steps              = [];
		foreach ( self::STEP_ORDER as $index => $step_key ) {
			$status = 'pending';
			if ( $is_completed_state ) {
				$status = 'completed';
			} elseif ( $index < $step_index ) {
				$status = 'done';
			} elseif ( $index === $step_index ) {
				$status = 'current';
			}

			$steps[] = [
				'id'     => $step_key,
				'label'  => $labels[ $step_key ],
				'status' => $status,
			];
		}

		$data = [
			'title'        => __( 'Setup Wizard', 'clawpress' ),
			'step'         => self::STEP_ORDER[ $step_index ],
			'step_label'   => $labels[ self::STEP_ORDER[ $step_index ] ],
			'step_index'   => min( $step_index + 1, count( self::STEP_ORDER ) ),
			'step_total'   => count( self::STEP_ORDER ),
			'steps'        => $steps,
			'message'      => '',
			'actions'      => [],
			'settings_url' => self::PROVIDER_SETUP_PATH,
		];

		if ( '' !== $error ) {
			$data['error'] = $error;
		}

		switch ( self::STEP_ORDER[ $step_index ] ) {
			case 'provider':
				$providers = $this->provider_helper->get_configured_provider_ids();
				if ( [] === $providers ) {
					$data['message'] = __( 'No configured providers found. Open provider settings, add credentials, then refresh.', 'clawpress' );
					$data['actions'] = [
						[
							'id'    => 'open-provider-settings',
							'label' => __( 'Open Provider Settings', 'clawpress' ),
							'type'  => 'open_url',
							'url'   => self::PROVIDER_SETUP_PATH,
						],
						[
							'id'     => 'refresh-providers',
							'label'  => __( 'Refresh Providers', 'clawpress' ),
							'type'   => 'send_prompt',
							'prompt' => '/setup refresh',
						],
					];
					break;
				}

				$data['message'] = __( 'Choose a configured provider to continue.', 'clawpress' );
				foreach ( $providers as $provider_id ) {
					$data['actions'][] = [
						'id'     => sprintf( 'provider-%s', $provider_id ),
						/* translators: %s: provider display label */
						'label'  => sprintf( __( 'Use %s', 'clawpress' ), $this->get_provider_label( $provider_id ) ),
						'type'   => 'send_prompt',
						'prompt' => sprintf( '/setup provider %s', $provider_id ),
					];
				}
				$data['actions'][] = [
					'id'    => 'provider-settings',
					'label' => __( 'Provider Settings', 'clawpress' ),
					'type'  => 'open_url',
					'url'   => self::PROVIDER_SETUP_PATH,
				];
				$data['actions'][] = [
					'id'     => 'refresh-providers',
					'label'  => __( 'Refresh Providers', 'clawpress' ),
					'type'   => 'send_prompt',
					'prompt' => '/setup refresh',
				];
				break;

			case 'model':
				$provider               = clawpress_sanitize_provider( $settings['provider'] ?? '' );
				$models                 = $this->get_model_ids_for_provider( $provider );
				$data['selected_model'] = $this->sanitize_model_id( (string) ( $settings['model'] ?? '' ) );
				$data['message']        = sprintf(
					/* translators: %s: provider */
					__( 'Choose a model for `%s`.', 'clawpress' ),
					$provider
				);

				foreach ( $models as $model_id ) {
					$data['actions'][] = [
						'id'     => sprintf( 'model-%s', sanitize_key( $model_id ) ),
						/* translators: %s: model identifier */
						'label'  => sprintf( __( 'Use %s', 'clawpress' ), $model_id ),
						'type'   => 'send_prompt',
						'prompt' => sprintf( '/setup model %s', $model_id ),
					];
				}
				$data['actions'][] = [
					'id'     => 'refresh-model-step',
					'label'  => __( 'Refresh', 'clawpress' ),
					'type'   => 'send_prompt',
					'prompt' => '/setup refresh',
				];
				break;

			case 'test_connection':
				$data['message'] = __( 'Run a live test with the selected provider and model.', 'clawpress' );
				$data['actions'] = [
					[
						'id'     => 'test-connection',
						'label'  => __( 'Test Connection', 'clawpress' ),
						'type'   => 'send_prompt',
						'prompt' => '/setup test',
					],
					[
						'id'     => 'retry-test-step',
						'label'  => __( 'Retry', 'clawpress' ),
						'type'   => 'send_prompt',
						'prompt' => '/setup refresh',
					],
				];
				break;

			case 'agent_user':
				$current_user_id = get_current_user_id();
				$existing_users  = $this->user_helper->get_existing_agent_users();
				$data['message'] = __( 'Select the user account the agent will use. All actions taken by the agent will be performed as this user. A dedicated Contributor user is recommended to start, and more access can be granted later.', 'clawpress' );
				$data['actions'] = [
					[
						'id'     => 'create-agent-user',
						'label'  => __( 'Create Agent User', 'clawpress' ),
						'type'   => 'send_prompt',
						'prompt' => '/setup create-agent-user',
					],
				];

				if ( $current_user_id > 0 ) {
					$data['actions'][] = [
						'id'     => 'use-current-user',
						'label'  => __( 'Use Current User', 'clawpress' ),
						'type'   => 'send_prompt',
						'prompt' => sprintf( '/setup agent-user %d', $current_user_id ),
					];
				}

				foreach ( $existing_users as $existing_user ) {
					if ( ! $existing_user instanceof \WP_User || ! isset( $existing_user->ID ) ) {
						continue;
					}

					$existing_user_id = (int) $existing_user->ID;
					if ( $existing_user_id <= 0 || $existing_user_id === $current_user_id ) {
						continue;
					}

					$data['actions'][] = [
						'id'     => sprintf( 'use-existing-agent-user-%d', $existing_user_id ),
						/* translators: %s: user login */
						'label'  => sprintf( __( 'Use Existing (%s)', 'clawpress' ), $existing_user->user_login ),
						'type'   => 'send_prompt',
						'prompt' => sprintf( '/setup agent-user %d', $existing_user_id ),
					];
				}
				break;

			case 'workspace':
				$agent_user_id   = $this->settings_helper->resolve_agent_user_id( $settings );
				$workspace       = $agent_user_id > 0 ? $this->workspace_helper->ensure_workspace_path_for_agent_user( $agent_user_id ) : '';
				$workspace_short = '' !== $workspace ? $this->get_workspace_display_path( $workspace ) : '';
				$workspace_ready = '' !== $workspace && is_dir( $workspace );
				$data['message'] = __( 'Create a secure workspace for the agent to read/write files.', 'clawpress' );
				if ( '' !== $workspace_short ) {
					$data['workspace_path']   = $workspace_short;
					$data['workspace_exists'] = $workspace_ready
						? __( 'Yes', 'clawpress' )
						: __( 'No', 'clawpress' );
				} else {
					$data['detail'] = __( 'Workspace path is not available until an agent user is selected.', 'clawpress' );
				}
				$data['actions'] = [
					[
						'id'     => 'create-workspace',
						'label'  => $workspace_ready
							? __( 'Use Workspace', 'clawpress' )
							: __( 'Create Workspace', 'clawpress' ),
						'type'   => 'send_prompt',
						'prompt' => '/setup create-workspace',
					],
				];
				break;

			case 'agent_files':
				$data['message'] = implode(
					"\n",
					[
						__( 'Clawpress uses a couple of agent files which you can edit to customize how your ClawPress agent behaves:', 'clawpress' ),
						'',
						__( 'AGENTS.md - core agent operating guide for how your agent behaves.', 'clawpress' ),
						__( 'SOUL.md - core behavior and boundaries for your agent', 'clawpress' ),
						__( 'USER.md - user profile, working relationship, so your agent knows who it is interacting with', 'clawpress' ),
						__( 'HEARTBEAT.md - list of tasks for your agent to perform proactively on a regular basis', 'clawpress' ),
					]
				);
				$data['actions'] = [
					[
						'id'     => 'create-agent-files',
						'label'  => __( 'Create Agent Files', 'clawpress' ),
						'type'   => 'send_prompt',
						'prompt' => '/setup create-agent-files',
					],
				];
				break;

			case 'ready':
			default:
				$data['message'] = __( 'Setup is complete. ClawPress is ready to use.', 'clawpress' );
				$data['actions'] = [
					[
						'id'    => 'view-clawpress-settings',
						'label' => __( 'View Settings', 'clawpress' ),
						'type'  => 'open_url',
						'url'   => self::CLAWPRESS_SETTINGS_PATH,
					],
					[
						'id'    => 'view-agent-files',
						'label' => __( 'View Agent Files', 'clawpress' ),
						'type'  => 'open_url',
						'url'   => sprintf( '/wp-admin/edit.php?post_type=%s', Post_Types::AGENT_FILE_POST_TYPE ),
					],
					[
						'id'     => 'say-hi',
						'label'  => __( 'Say Hi to Your Agent', 'clawpress' ),
						'type'   => 'send_prompt',
						'prompt' => 'Hello',
					],
				];
				break;
		}

		if ( 'provider' !== self::STEP_ORDER[ $step_index ] ) {
			array_unshift(
				$data['actions'],
				[
					'id'     => 'back-step',
					'label'  => __( 'Back', 'clawpress' ),
					'type'   => 'send_prompt',
					'prompt' => '/setup back',
				]
			);
		}

		return [
			'type' => 'setup',
			'data' => $data,
		];
	}

	/**
	 * Get available model IDs for a provider.
	 *
	 * @param string $provider Provider ID.
	 * @return array<int,string>
	 */
	private function get_model_ids_for_provider( string $provider ): array {
		$options = $this->model_helper->get_options_for_provider( $provider );
		$ids     = [];

		foreach ( $options as $option ) {
			if ( ! is_array( $option ) || empty( $option['id'] ) ) {
				continue;
			}

			$model_id = trim( (string) $option['id'] );
			if ( '' === $model_id ) {
				continue;
			}

			$ids[] = $model_id;
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Check whether a model is valid for a provider.
	 *
	 * A model is valid when it is a known option for the provider, or a valid custom model ID.
	 *
	 * @param string $provider Provider ID.
	 * @param string $model Model ID.
	 */
	private function is_model_valid_for_provider( string $provider, string $model ): bool {
		$provider = clawpress_sanitize_provider( $provider );
		$model    = $this->sanitize_model_id( $model );

		if ( '' === $provider || '' === $model ) {
			return false;
		}

		$model_ids = $this->get_model_ids_for_provider( $provider );
		if ( in_array( $model, $model_ids, true ) ) {
			return true;
		}

		return $this->is_valid_custom_model_id( $model );
	}

	/**
	 * Check whether a model ID is valid for the custom model path.
	 *
	 * @param string $model Model ID.
	 */
	private function is_valid_custom_model_id( string $model ): bool {
		return 1 === preg_match( '/^[A-Za-z0-9][A-Za-z0-9._:@\/-]{0,191}$/', $model );
	}

	/**
	 * Normalize and sanitize a model ID.
	 *
	 * @param string $model Raw model ID.
	 */
	private function sanitize_model_id( string $model ): string {
		return trim( sanitize_text_field( $model ) );
	}

	/**
	 * Get a user-facing provider label.
	 *
	 * @param string $provider Provider ID.
	 */
	private function get_provider_label( string $provider ): string {
		$provider = clawpress_sanitize_provider( $provider );

		switch ( $provider ) {
			case 'openai':
				return 'OpenAI';
			case 'anthropic':
				return 'Anthropic';
			case 'google':
				return 'Google';
			default:
				return ucfirst( $provider );
		}
	}

	/**
	 * Check whether workspace is ready.
	 *
	 * @param int $agent_user_id Agent user ID.
	 */
	private function is_workspace_ready( int $agent_user_id ): bool {
		$workspace_path = $this->workspace_helper->get_workspace_path_for_agent_user( $agent_user_id );
		if ( '' === $workspace_path ) {
			return false;
		}

		return is_dir( $workspace_path );
	}

	/**
	 * Get setup state from option.
	 *
	 * @return array<string,mixed>
	 */
	private function get_setup_state(): array {
		$state = get_option( self::SETUP_STATE_OPTION, [] );
		if ( ! is_array( $state ) ) {
			return [];
		}

		return $state;
	}

	/**
	 * Normalize a raw step value to a valid setup step.
	 *
	 * @param mixed $step Raw step value.
	 */
	private function normalize_step( $step ): string {
		$step = is_string( $step ) ? trim( strtolower( $step ) ) : '';
		if ( ! in_array( $step, self::STEP_ORDER, true ) ) {
			return 'agent_user';
		}

		return $step;
	}

	/**
	 * Resolve previous step key for a current step.
	 *
	 * @param string $current_step Current step.
	 */
	private function get_previous_step( string $current_step ): string {
		$current_index = array_search( $current_step, self::STEP_ORDER, true );
		if ( false === $current_index || $current_index <= 0 ) {
			return 'provider';
		}

		return self::STEP_ORDER[ $current_index - 1 ];
	}

	/**
	 * Convert absolute workspace path to a display path rooted at /wp-content.
	 *
	 * @param string $workspace_path Absolute workspace path.
	 */
	private function get_workspace_display_path( string $workspace_path ): string {
		$normalized_path = str_replace( '\\', '/', trim( $workspace_path ) );
		if ( '' === $normalized_path ) {
			return '';
		}

		$wp_content_dir = defined( 'WP_CONTENT_DIR' ) ? str_replace( '\\', '/', wp_normalize_path( (string) WP_CONTENT_DIR ) ) : '';
		if ( '' !== $wp_content_dir && 0 === strpos( $normalized_path, $wp_content_dir ) ) {
			$suffix = substr( $normalized_path, strlen( $wp_content_dir ) );
			if ( false === $suffix ) {
				$suffix = '';
			}

			return '/wp-content' . $suffix;
		}

		$position = strpos( $normalized_path, '/wp-content/' );
		if ( false === $position ) {
			$position = strpos( $normalized_path, '/wp-content' );
		}

		if ( false !== $position ) {
			return substr( $normalized_path, $position );
		}

		return $normalized_path;
	}

	/**
	 * Persist setup state updates.
	 *
	 * @param array<string,mixed> $updates State updates.
	 */
	private function persist_setup_state( array $updates ): void {
		$state = $this->get_setup_state();

		foreach ( $updates as $key => $value ) {
			$state[ $key ] = $value;
		}

		$state['updated_at'] = time();

		update_option( self::SETUP_STATE_OPTION, $state );
	}
}

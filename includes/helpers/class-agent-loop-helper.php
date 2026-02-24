<?php
/**
 * Agent loop runtime helper.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Helpers;

use ClawPress\Commands\Command_Confirmation_Store;
use ClawPress\Transports\Agent_Transport;
use ClawPress\Transports\Null_Transport;
use ClawPress\Transports\Polling_Transport;
use Throwable;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Builders\MessageBuilder;
use WordPress\AiClient\Builders\PromptBuilder;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\Models\DTO\ModelConfig;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AiClient\Tools\DTO\FunctionCall;
use WordPress\AiClient\Tools\DTO\FunctionDeclaration;
use WordPress\AiClient\Tools\DTO\FunctionResponse;

defined( 'ABSPATH' ) || exit;

/**
 * Reusable transport-agnostic agent loop runtime.
 */
final class Agent_Loop_Helper {
	/**
	 * Known context windows for common model families.
	 *
	 * @var array<string,array<string,int>>
	 */
	private const MODEL_CONTEXT_WINDOWS = [
		'openai'    => [
			'gpt-4.1-mini' => 1048576,
			'gpt-4.1'      => 1048576,
			'gpt-4o-mini'  => 128000,
			'gpt-4o'       => 128000,
			'gpt-5'        => 400000,
			'o3'           => 200000,
			'o1'           => 200000,
		],
		'anthropic' => [
			'claude-' => 200000,
		],
		'google'    => [
			'gemini-2.5' => 1048576,
			'gemini-2.0' => 1048576,
			'gemini-1.5' => 1048576,
		],
	];

	/**
	 * Singleton instance.
	 *
	 * @var ?self
	 */
	private static ?self $instance = null;

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
	 * Context helper.
	 *
	 * @var Context_Helper
	 */
	private Context_Helper $context_helper;

	/**
	 * Abilities helper.
	 *
	 * @var Abilities_Helper
	 */
	private Abilities_Helper $abilities_helper;

	/**
	 * Confirmation store.
	 *
	 * @var Command_Confirmation_Store
	 */
	private Command_Confirmation_Store $confirmation_store;

	/**
	 * Policy helper.
	 *
	 * @var Policy_Helper
	 */
	private Policy_Helper $policy_helper;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->settings_helper    = Settings_Helper::get_instance();
		$this->provider_helper    = Provider_Helper::get_instance();
		$this->context_helper     = Context_Helper::get_instance();
		$this->abilities_helper   = Abilities_Helper::get_instance();
		$this->confirmation_store = new Command_Confirmation_Store();
		$this->policy_helper      = Policy_Helper::get_instance();
	}

	/**
	 * Get singleton instance.
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Execute a full turn.
	 *
	 * @param array<string,mixed> $turn_request Turn request payload.
	 * @return array<string,mixed>
	 */
	public function run_turn( array $turn_request ): array {
		return $this->run_internal( $turn_request, false );
	}

	/**
	 * Execute a bounded runtime slice.
	 *
	 * @param array<string,mixed> $turn_request Turn request payload.
	 * @return array<string,mixed>
	 */
	public function run_slice( array $turn_request ): array {
		return $this->run_internal( $turn_request, true );
	}

	/**
	 * Execute turn/slice internals.
	 *
	 * @param array<string,mixed> $turn_request Turn request payload.
	 * @param bool                $is_slice Whether slice execution mode is enabled.
	 */
	private function run_internal( array $turn_request, bool $is_slice ): array {
		$settings                = $this->settings_helper->get_settings();
		$provider_model_resolver = isset( $turn_request['provider_model_resolver'] ) && is_callable( $turn_request['provider_model_resolver'] )
			? $turn_request['provider_model_resolver']
			: [ $this->provider_helper, 'resolve_provider_and_model' ];
		$resolved                = call_user_func( $provider_model_resolver, $settings );
		$provider                = isset( $resolved['provider'] ) ? trim( (string) $resolved['provider'] ) : '';
		$model                   = isset( $resolved['model'] ) ? trim( (string) $resolved['model'] ) : '';
		$run_id                  = isset( $turn_request['run_id'] ) ? (int) $turn_request['run_id'] : 0;
		$session_id              = isset( $turn_request['session_id'] ) ? (int) $turn_request['session_id'] : 0;
		$trigger                 = isset( $turn_request['trigger'] ) ? (string) $turn_request['trigger'] : 'chat';
		$transport_mode          = isset( $turn_request['transport_mode'] ) ? (string) $turn_request['transport_mode'] : 'polling';

		if ( '' === $provider ) {
			return [
				'status'         => 'success',
				'next_action'    => 'stop',
				'mode'           => 'offline',
				'provider'       => null,
				'model'          => null,
				'assistant_text' => '',
				'tool_calls'     => [],
				'card'           => null,
				'context'        => null,
			];
		}

		$transport              = $this->create_transport( $transport_mode, $run_id, $session_id );
		$online_reply_generator = isset( $turn_request['online_reply_generator'] ) && is_callable( $turn_request['online_reply_generator'] )
			? $turn_request['online_reply_generator']
			: [ $this, 'generate_online_reply' ];

		try {
			$requesting_user_id = isset( $turn_request['requesting_user_id'] )
				? (int) $turn_request['requesting_user_id']
				: ( function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0 );

			$context                        = $this->context_helper->build_model_context(
				isset( $turn_request['message'] ) ? (string) $turn_request['message'] : '',
				$requesting_user_id > 0 ? $requesting_user_id : null
			);
			$context['request_timeout']     = $this->settings_helper->get_request_timeout( $settings );
			$context['generation_settings'] = $this->settings_helper->get_generation_settings( $settings );

			$transport->emit(
				[
					'type'    => 'agent.run.started',
					'payload' => [
						'run_id'     => $run_id > 0 ? $run_id : null,
						'session_id' => $session_id > 0 ? $session_id : null,
						'trigger'    => $trigger,
						'attempt'    => isset( $turn_request['attempt'] ) ? (int) $turn_request['attempt'] : 1,
						'transport'  => $transport_mode,
						'slice_mode' => $is_slice,
					],
				]
			);

			$online_reply_payload = $this->normalize_online_reply_payload(
				$this->invoke_online_reply_generator(
					$online_reply_generator,
					$context,
					$provider,
					$model,
					$turn_request,
					$transport,
					$is_slice
				)
			);

			$result = [
				'status'         => isset( $online_reply_payload['status'] ) ? (string) $online_reply_payload['status'] : 'success',
				'next_action'    => isset( $online_reply_payload['next_action'] ) ? (string) $online_reply_payload['next_action'] : 'stop',
				'mode'           => 'online',
				'provider'       => $provider,
				'model'          => '' !== $model ? $model : null,
				'assistant_text' => (string) $online_reply_payload['reply'],
				'card'           => $online_reply_payload['card'],
				'context'        => $online_reply_payload['context'],
				'tool_calls'     => $online_reply_payload['tool_calls'],
				'resume_cursor'  => isset( $online_reply_payload['resume_cursor'] ) ? $online_reply_payload['resume_cursor'] : null,
			];

			if ( isset( $online_reply_payload['events_cursor'] ) ) {
				$result['events_cursor'] = (int) $online_reply_payload['events_cursor'];
			}

			if ( isset( $online_reply_payload['error'] ) && is_array( $online_reply_payload['error'] ) ) {
				$result['error'] = $online_reply_payload['error'];
				$result['mode']  = 'error';
			}

			$transport->emit(
				[
					'type'    => 'agent.run.finished',
					'payload' => [
						'status'      => $result['status'],
						'next_action' => $result['next_action'],
					],
				]
			);

			if ( $transport instanceof Polling_Transport ) {
				$result['events_cursor'] = $transport->get_last_event_id();
			}

			return $result;
		} catch ( Throwable $throwable ) {
			$error_message = trim( sanitize_text_field( $throwable->getMessage() ) );
			if ( '' === $error_message ) {
				$error_message = __( 'Unknown provider error.', 'clawpress' );
			}

			$error_type = $this->classify_provider_error_type( $throwable, $error_message );
			$transport->emit(
				[
					'type'    => 'agent.run.error',
					'payload' => [
						'error_type' => $error_type,
						'message'    => $error_message,
					],
				]
			);

			$result = [
				'status'         => 'timeout' === $error_type ? 'timeout' : 'error',
				'next_action'    => 'stop',
				'mode'           => 'error',
				'provider'       => $provider,
				'model'          => '' !== $model ? $model : null,
				'assistant_text' => '',
				'card'           => null,
				'context'        => null,
				'tool_calls'     => [],
				'error'          => [
					'type'      => $error_type,
					'message'   => $error_message,
					'code'      => is_int( $throwable->getCode() ) || is_string( $throwable->getCode() ) ? $throwable->getCode() : 0,
					'retryable' => 'timeout' === $error_type,
				],
			];

			if ( $transport instanceof Polling_Transport ) {
				$result['events_cursor'] = $transport->get_last_event_id();
			}

			return $result;
		} finally {
			$transport->close();
		}
	}

	/**
	 * Generate online reply using model/tool execution loop.
	 *
	 * @param array<string,mixed>  $context Model context payload.
	 * @param string               $provider Provider identifier.
	 * @param string               $model Model identifier.
	 * @param array<string,mixed>  $turn_request Turn request metadata.
	 * @param Agent_Transport|null $transport Transport implementation.
	 * @param bool                 $is_slice Whether slice execution mode is enabled.
	 * @return array<string,mixed>
	 */
	private function generate_online_reply( array $context, string $provider, string $model, array $turn_request = [], ?Agent_Transport $transport = null, bool $is_slice = false ): array {
		$current_message     = isset( $context['message'] ) ? trim( (string) $context['message'] ) : '';
		$system_prompt       = isset( $context['system_prompt'] ) ? trim( (string) $context['system_prompt'] ) : '';
		$request_timeout     = isset( $context['request_timeout'] ) ? (int) $context['request_timeout'] : 45;
		$generation_settings = isset( $context['generation_settings'] ) && is_array( $context['generation_settings'] )
			? $this->normalize_generation_settings( $context['generation_settings'] )
			: $this->settings_helper->get_generation_settings();
		$history_messages    = $this->normalize_history_messages( $context );
		$tool_declarations   = $this->normalize_tool_declarations( $context );
		$requesting_user_id  = isset( $turn_request['requesting_user_id'] ) ? (int) $turn_request['requesting_user_id'] : ( isset( $context['requesting_user_id'] ) ? (int) $context['requesting_user_id'] : 0 );
		$execution_user_id   = isset( $turn_request['execution_user_id'] ) ? (int) $turn_request['execution_user_id'] : ( isset( $context['execution_user_id'] ) ? (int) $context['execution_user_id'] : 0 );
		$trigger_type        = isset( $turn_request['trigger'] ) ? (string) $turn_request['trigger'] : ( isset( $context['trigger_type'] ) ? (string) $context['trigger_type'] : 'chat' );
		$runtime_policy      = $this->policy_helper->resolve_runtime_policy(
			$trigger_type,
			isset( $turn_request['session_metadata'] ) && is_array( $turn_request['session_metadata'] )
				? $turn_request['session_metadata']
				: [],
			isset( $turn_request['policy_overrides'] ) && is_array( $turn_request['policy_overrides'] )
				? $turn_request['policy_overrides']
				: []
		);
		$run_id              = isset( $turn_request['run_id'] ) ? (int) $turn_request['run_id'] : 0;
		$session_id          = isset( $turn_request['session_id'] ) ? (int) $turn_request['session_id'] : 0;
		$slice_budget_ms     = $is_slice ? max( 1, (int) ( $turn_request['slice_budget_ms'] ?? 1500 ) ) : 0;
		$max_steps_per_slice = $is_slice ? max( 1, (int) ( $turn_request['max_steps_per_slice'] ?? 1 ) ) : PHP_INT_MAX;
		$resume_state        = $this->normalize_resume_cursor( $turn_request['resume_cursor'] ?? null );
		$transport           = $transport ?? new Null_Transport();

		$this->confirmation_store->clear_tool_batch( $requesting_user_id > 0 ? $requesting_user_id : null );

		$conversation = [];
		if ( isset( $resume_state['conversation'] ) && is_array( $resume_state['conversation'] ) ) {
			$conversation = $this->restore_conversation( $resume_state['conversation'] );
		}

		if ( [] === $conversation ) {
			$conversation = $history_messages;
			if ( '' !== $current_message ) {
				$conversation[] = ( new MessageBuilder( $current_message ) )
					->usingUserRole()
					->get();
			}
		}

		$latest_assistant_text = isset( $resume_state['assistant_text'] ) ? (string) $resume_state['assistant_text'] : '';
		$latest_context_usage  = isset( $resume_state['context'] ) && is_array( $resume_state['context'] )
			? $this->normalize_context_usage_payload( $resume_state['context'] )
			: null;
		$tool_call_trace       = isset( $resume_state['tool_calls'] ) && is_array( $resume_state['tool_calls'] )
			? $this->normalize_tool_call_trace_payload( $resume_state['tool_calls'] )
			: [];
		$round_start           = isset( $resume_state['round'] ) ? max( 0, (int) $resume_state['round'] ) : 0;
		$steps_completed       = $is_slice
			? 0
			: ( isset( $resume_state['steps_completed'] ) ? max( 0, (int) $resume_state['steps_completed'] ) : 0 );

		if ( $is_slice && $round_start > 0 ) {
			$transport->emit(
				[
					'type'    => 'agent.slice.resumed',
					'payload' => [
						'round' => $round_start,
					],
				]
			);
		}

		$started_at_ms = $this->now_ms();

		for ( $round = $round_start; $round < (int) $runtime_policy['max_tool_rounds']; ++$round ) {
			if ( $is_slice && $steps_completed > 0 && $this->should_pause_slice( $steps_completed, $max_steps_per_slice, $started_at_ms, $slice_budget_ms ) ) {
				$resume_cursor = $this->build_resume_cursor(
					$conversation,
					$round,
					$steps_completed,
					$latest_assistant_text,
					$tool_call_trace,
					$latest_context_usage
				);

				$transport->emit(
					[
						'type'    => 'agent.slice.paused',
						'payload' => [
							'round'           => $round,
							'steps_completed' => $steps_completed,
						],
					]
				);

				return [
					'status'        => 'in_progress',
					'next_action'   => 'continue_later',
					'reply'         => $latest_assistant_text,
					'card'          => null,
					'context'       => $latest_context_usage,
					'tool_calls'    => $tool_call_trace,
					'resume_cursor' => $resume_cursor,
				];
			}

			$builder = AiClient::prompt( $conversation )->usingProvider( $provider );
			if ( '' !== $system_prompt ) {
				$builder = $builder->usingSystemInstruction( $system_prompt );
			}

			if ( '' !== $model ) {
				$builder = $builder->usingModelPreference( [ $provider, $model ] );
			}

			$builder = $builder->usingRequestOptions( $this->build_request_options( $request_timeout ) );
			$builder = $this->apply_generation_settings_to_prompt_builder( $builder, $generation_settings, $provider, $model );
			if ( [] !== $tool_declarations ) {
				$builder = $builder->usingFunctionDeclarations( ...$tool_declarations );
			}

			$result                = $builder->generateResult();
			$assistant_message     = $result->toMessage();
			$conversation[]        = $assistant_message;
			$latest_assistant_text = $this->extract_text_from_message( $assistant_message );
			$latest_context_usage  = $this->extract_context_usage_from_result( $result, $provider, $model );
			++$steps_completed;

			$function_calls = $this->extract_function_calls( $assistant_message );
			$transport->emit(
				[
					'type'    => 'agent.llm.response',
					'payload' => [
						'round'             => $round + 1,
						'tool_call_count'   => count( $function_calls ),
						'assistant_excerpt' => '' !== $latest_assistant_text ? mb_substr( $latest_assistant_text, 0, 300 ) : '',
					],
				]
			);

			if ( [] === $function_calls ) {
				return [
					'status'      => 'success',
					'next_action' => 'stop',
					'reply'       => $latest_assistant_text,
					'card'        => null,
					'context'     => $latest_context_usage,
					'tool_calls'  => $tool_call_trace,
				];
			}

			$function_responses = [];
			$confirmation_batch = [];

			$max_tool_calls_per_round = max( 1, (int) $runtime_policy['max_tool_calls_per_round'] );
			foreach ( $function_calls as $index => $function_call ) {
				$tool_name = trim( (string) $function_call->getName() );
				if ( '' === $tool_name ) {
					$tool_name = 'unknown_tool';
				}

				$function_response_id = trim( (string) $function_call->getId() );
				if ( '' === $function_response_id ) {
					$function_response_id = sprintf( 'tool-call-%d-%d', $round + 1, $index + 1 );
				}

				if ( $index >= $max_tool_calls_per_round ) {
					$deferred_count = count( $function_calls ) - $index;
					$this->append_deferred_tool_call_responses(
						$function_calls,
						$index,
						$round + 1,
						$max_tool_calls_per_round,
						$function_responses
					);

					$transport->emit(
						[
							'type'    => 'agent.tool_calls.deferred',
							'payload' => [
								'round'          => $round + 1,
								'deferred_count' => max( 1, $deferred_count ),
								'executed_count' => $max_tool_calls_per_round,
							],
						]
					);

					break;
				}

				$tool_result       = $this->abilities_helper->execute_tool_call(
					$tool_name,
					$function_call->getArgs(),
					[
						'run_id'             => $run_id,
						'session_id'         => $session_id,
						'requesting_user_id' => $requesting_user_id,
						'execution_user_id'  => $execution_user_id,
						'confirmation_scope' => 'batch',
						'trigger_type'       => $trigger_type,
						'runtime_policy'     => $runtime_policy,
					]
				);
				$tool_call_trace[] = $this->build_tool_call_trace_entry(
					$tool_name,
					$function_call->getArgs(),
					$tool_result,
					$round + 1,
					$index + 1
				);
				$latest_tool_trace = $tool_call_trace[ count( $tool_call_trace ) - 1 ];
				$tool_call_status  = isset( $latest_tool_trace['status'] ) ? (string) $latest_tool_trace['status'] : 'success';
				$tool_call_payload = [
					'round'     => $round + 1,
					'sequence'  => $index + 1,
					'tool_name' => strtolower( trim( $tool_name ) ),
					'status'    => $tool_call_status,
				];
				if ( isset( $latest_tool_trace['message'] ) && is_string( $latest_tool_trace['message'] ) && '' !== $latest_tool_trace['message'] ) {
					$tool_call_payload['message'] = $latest_tool_trace['message'];
				}

				$transport->emit(
					[
						'type'    => 'agent.tool_call',
						'payload' => $tool_call_payload,
					]
				);

				$pending_confirmation = $this->normalize_pending_confirmation_tool_call(
					$tool_result,
					$tool_name,
					$function_call->getArgs()
				);
				if ( is_array( $pending_confirmation ) ) {
					$confirmation_batch[] = $pending_confirmation;
				}

				$function_responses[] = new FunctionResponse(
					$function_response_id,
					$tool_name,
					$tool_result
				);
			}

			if ( [] === $function_responses ) {
				return [
					'status'      => 'success',
					'next_action' => 'stop',
					'reply'       => $latest_assistant_text,
					'card'        => null,
					'context'     => $latest_context_usage,
					'tool_calls'  => $tool_call_trace,
				];
			}

			if ( [] !== $confirmation_batch ) {
				$issued_batch = $this->confirmation_store->issue_tool_batch(
					$confirmation_batch,
					$requesting_user_id > 0 ? $requesting_user_id : null
				);

				$transport->emit(
					[
						'type'    => 'agent.confirmation.required',
						'payload' => [
							'round'      => $round + 1,
							'tool_count' => count( $confirmation_batch ),
						],
					]
				);

				return [
					'status'      => 'requires_confirmation',
					'next_action' => 'stop',
					'reply'       => $latest_assistant_text,
					'card'        => $this->build_tool_confirmation_card( $issued_batch ),
					'context'     => $latest_context_usage,
					'tool_calls'  => $tool_call_trace,
				];
			}

			foreach ( $function_responses as $function_response ) {
				$tool_response_message_builder = new MessageBuilder();
				$tool_response_message_builder->usingUserRole();
				$tool_response_message_builder->withFunctionResponse( $function_response );
				$conversation[] = $tool_response_message_builder->get();
			}

			if ( $is_slice && $this->should_pause_slice( $steps_completed, $max_steps_per_slice, $started_at_ms, $slice_budget_ms ) ) {
				$resume_cursor = $this->build_resume_cursor(
					$conversation,
					$round + 1,
					$steps_completed,
					$latest_assistant_text,
					$tool_call_trace,
					$latest_context_usage
				);

				$transport->emit(
					[
						'type'    => 'agent.slice.paused',
						'payload' => [
							'round'           => $round + 1,
							'steps_completed' => $steps_completed,
						],
					]
				);

				return [
					'status'        => 'in_progress',
					'next_action'   => 'continue_later',
					'reply'         => $latest_assistant_text,
					'card'          => null,
					'context'       => $latest_context_usage,
					'tool_calls'    => $tool_call_trace,
					'resume_cursor' => $resume_cursor,
				];
			}
		}

		return [
			'status'      => 'success',
			'next_action' => 'stop',
			'reply'       => $latest_assistant_text,
			'card'        => null,
			'context'     => $latest_context_usage,
			'tool_calls'  => $tool_call_trace,
		];
	}

	/**
	 * Normalize online generator output.
	 *
	 * @param mixed $raw_output Raw callback output.
	 * @return array<string,mixed>
	 */
	private function normalize_online_reply_payload( $raw_output ): array {
		if ( is_array( $raw_output ) ) {
			$reply      = isset( $raw_output['reply'] ) ? (string) $raw_output['reply'] : '';
			$card       = isset( $raw_output['card'] ) && is_array( $raw_output['card'] )
				? $this->normalize_card_payload( $raw_output['card'] )
				: null;
			$context    = isset( $raw_output['context'] ) && is_array( $raw_output['context'] )
				? $this->normalize_context_usage_payload( $raw_output['context'] )
				: null;
			$tool_calls = isset( $raw_output['tool_calls'] ) && is_array( $raw_output['tool_calls'] )
				? $this->normalize_tool_call_trace_payload( $raw_output['tool_calls'] )
				: [];

			$payload = [
				'reply'       => $reply,
				'card'        => $card,
				'context'     => $context,
				'tool_calls'  => $tool_calls,
				'status'      => isset( $raw_output['status'] ) ? (string) $raw_output['status'] : 'success',
				'next_action' => isset( $raw_output['next_action'] ) ? (string) $raw_output['next_action'] : 'stop',
			];

			if ( isset( $raw_output['resume_cursor'] ) ) {
				$payload['resume_cursor'] = $raw_output['resume_cursor'];
			}

			if ( isset( $raw_output['error'] ) && is_array( $raw_output['error'] ) ) {
				$payload['error'] = $raw_output['error'];
			}

			if ( isset( $raw_output['events_cursor'] ) ) {
				$payload['events_cursor'] = (int) $raw_output['events_cursor'];
			}

			return $payload;
		}

		return [
			'reply'       => (string) $raw_output,
			'card'        => null,
			'context'     => null,
			'tool_calls'  => [],
			'status'      => 'success',
			'next_action' => 'stop',
		];
	}

	/**
	 * Create transport from transport mode.
	 *
	 * @param string $transport_mode Transport mode.
	 * @param int    $run_id Run identifier.
	 * @param int    $session_id Session identifier.
	 */
	private function create_transport( string $transport_mode, int $run_id, int $session_id ): Agent_Transport {
		$transport_mode = strtolower( trim( $transport_mode ) );
		if ( in_array( $transport_mode, [ 'polling', 'streaming' ], true ) && ( $run_id > 0 || $session_id > 0 ) ) {
			return new Polling_Transport( $run_id, $session_id );
		}

		return new Null_Transport();
	}

	/**
	 * Determine whether current slice should pause.
	 *
	 * @param int $steps_completed Completed steps in current run.
	 * @param int $max_steps_per_slice Max steps allowed per slice.
	 * @param int $started_at_ms Slice start timestamp in milliseconds.
	 * @param int $slice_budget_ms Wall-time budget for this slice.
	 */
	private function should_pause_slice( int $steps_completed, int $max_steps_per_slice, int $started_at_ms, int $slice_budget_ms ): bool {
		if ( $steps_completed >= $max_steps_per_slice ) {
			return true;
		}

		if ( $slice_budget_ms <= 0 ) {
			return false;
		}

		return ( $this->now_ms() - $started_at_ms ) >= $slice_budget_ms;
	}

	/**
	 * Create resume cursor payload.
	 *
	 * @param array<int,Message>             $conversation Conversation state.
	 * @param int                            $round Next round.
	 * @param int                            $steps_completed Completed steps.
	 * @param string                         $assistant_text Latest assistant text.
	 * @param array<int,array<string,mixed>> $tool_calls Tool trace.
	 * @param array<string,mixed>|null       $context_usage Context usage payload.
	 * @return array<string,mixed>
	 */
	private function build_resume_cursor(
		array $conversation,
		int $round,
		int $steps_completed,
		string $assistant_text,
		array $tool_calls,
		?array $context_usage
	): array {
		return [
			'version'         => 1,
			'round'           => max( 0, $round ),
			'steps_completed' => max( 0, $steps_completed ),
			'assistant_text'  => $assistant_text,
			'tool_calls'      => $tool_calls,
			'context'         => $context_usage,
			'conversation'    => array_values(
				array_map(
					static fn ( Message $message ): array => $message->toArray(),
					$conversation
				)
			),
		];
	}

	/**
	 * Normalize resume cursor payload.
	 *
	 * @param mixed $resume_cursor Resume cursor payload.
	 * @return array<string,mixed>
	 */
	private function normalize_resume_cursor( $resume_cursor ): array {
		if ( is_string( $resume_cursor ) && '' !== trim( $resume_cursor ) ) {
			$decoded = json_decode( $resume_cursor, true );
			if ( is_array( $decoded ) ) {
				$resume_cursor = $decoded;
			}
		}

		if ( ! is_array( $resume_cursor ) ) {
			return [];
		}

		if ( ! isset( $resume_cursor['conversation'] ) || ! is_array( $resume_cursor['conversation'] ) ) {
			$resume_cursor['conversation'] = [];
		}

		return $resume_cursor;
	}

	/**
	 * Restore conversation from serialized resume payload.
	 *
	 * @param array<int,mixed> $serialized_conversation Serialized conversation array.
	 * @return array<int,Message>
	 */
	private function restore_conversation( array $serialized_conversation ): array {
		$conversation = [];

		foreach ( $serialized_conversation as $message ) {
			if ( ! is_array( $message ) ) {
				continue;
			}

			try {
				$converted = Message::fromArray( $message );
				if ( $converted instanceof Message ) {
					$conversation[] = $converted;
				}
			} catch ( Throwable $throwable ) {
				unset( $throwable );
				continue;
			}
		}

		return $conversation;
	}

	/**
	 * Build a compact trace row for a single tool call.
	 *
	 * @param string              $tool_name Tool name.
	 * @param mixed               $raw_args Tool arguments.
	 * @param array<string,mixed> $tool_result Tool result payload.
	 * @param int                 $round Tool round number.
	 * @param int                 $sequence Tool sequence in round.
	 * @return array<string,mixed>
	 */
	private function build_tool_call_trace_entry(
		string $tool_name,
		$raw_args,
		array $tool_result,
		int $round,
		int $sequence
	): array {
		$normalized_tool_name  = strtolower( trim( $tool_name ) );
		$ability_name          = isset( $tool_result['ability'] ) ? trim( (string) $tool_result['ability'] ) : '';
		$requires_confirmation = isset( $tool_result['requires_confirmation'] ) && true === $tool_result['requires_confirmation'];
		$success               = isset( $tool_result['success'] ) && true === $tool_result['success'];
		$status                = $success ? 'success' : ( $requires_confirmation ? 'requires_confirmation' : 'error' );
		$message               = '';

		if ( isset( $tool_result['error']['message'] ) ) {
			$message = trim( sanitize_text_field( (string) $tool_result['error']['message'] ) );
		} elseif ( isset( $tool_result['result']['message'] ) ) {
			$message = trim( sanitize_text_field( (string) $tool_result['result']['message'] ) );
		}

		if ( '' !== $message && strlen( $message ) > 200 ) {
			$message = substr( $message, 0, 197 ) . '...';
		}

		return [
			'name'                  => $normalized_tool_name,
			'ability'               => '' !== $ability_name ? $ability_name : null,
			'args'                  => $this->normalize_tool_args_for_prompt( $raw_args ),
			'status'                => $status,
			'requires_confirmation' => $requires_confirmation,
			'message'               => '' !== $message ? $message : null,
			'round'                 => max( 1, $round ),
			'sequence'              => max( 1, $sequence ),
		];
	}

	/**
	 * Build a synthetic tool result when the per-round execution cap is exceeded.
	 *
	 * @param int $max_tool_calls_per_round Per-round execution cap.
	 * @return array<string,mixed>
	 */
	private function build_tool_call_limit_tool_result( int $max_tool_calls_per_round ): array {
		return [
			'success'               => false,
			'requires_confirmation' => false,
			'ability'               => '',
			'result'                => [],
			'error'                 => [
				'code'    => 'tool_call_limit_exceeded',
				/* translators: %d: maximum number of tool calls executed per round. */
				'message' => sprintf( __( 'Tool call deferred because the per-round limit of %d was reached. Retry this call in the next round.', 'clawpress' ), max( 1, $max_tool_calls_per_round ) ),
			],
		];
	}

	/**
	 * Append synthetic responses for deferred tool calls once round cap is reached.
	 *
	 * @param array<int,FunctionCall>     $function_calls Full round function calls.
	 * @param int                         $start_index First deferred call index.
	 * @param int                         $round Tool round number.
	 * @param int                         $max_tool_calls_per_round Per-round execution cap.
	 * @param array<int,FunctionResponse> $function_responses Accumulator.
	 */
	private function append_deferred_tool_call_responses(
		array $function_calls,
		int $start_index,
		int $round,
		int $max_tool_calls_per_round,
		array &$function_responses
	): void {
		$total_calls = count( $function_calls );
		for ( $index = $start_index; $index < $total_calls; ++$index ) {
			$function_call = $function_calls[ $index ];
			if ( ! $function_call instanceof FunctionCall ) {
				continue;
			}

			$tool_name = trim( (string) $function_call->getName() );
			if ( '' === $tool_name ) {
				$tool_name = 'unknown_tool';
			}

			$function_response_id = trim( (string) $function_call->getId() );
			if ( '' === $function_response_id ) {
				$function_response_id = sprintf( 'tool-call-%d-%d', $round, $index + 1 );
			}

			$function_responses[] = new FunctionResponse(
				$function_response_id,
				$tool_name,
				$this->build_tool_call_limit_tool_result( $max_tool_calls_per_round )
			);
		}
	}

	/**
	 * Normalize tool call trace payload.
	 *
	 * @param array<int,mixed> $tool_calls Raw trace payload.
	 * @return array<int,array<string,mixed>>
	 */
	private function normalize_tool_call_trace_payload( array $tool_calls ): array {
		$normalized = [];

		foreach ( $tool_calls as $index => $tool_call ) {
			if ( ! is_array( $tool_call ) ) {
				continue;
			}

			$name = isset( $tool_call['name'] ) ? strtolower( trim( sanitize_text_field( (string) $tool_call['name'] ) ) ) : '';
			if ( '' === $name ) {
				continue;
			}

			$ability  = isset( $tool_call['ability'] ) ? trim( sanitize_text_field( (string) $tool_call['ability'] ) ) : '';
			$args     = isset( $tool_call['args'] ) ? $this->normalize_tool_args_for_prompt( $tool_call['args'] ) : [];
			$status   = isset( $tool_call['status'] ) ? strtolower( trim( (string) $tool_call['status'] ) ) : 'success';
			$status   = in_array( $status, [ 'success', 'error', 'requires_confirmation' ], true ) ? $status : 'success';
			$message  = isset( $tool_call['message'] ) ? trim( sanitize_text_field( (string) $tool_call['message'] ) ) : '';
			$round    = isset( $tool_call['round'] ) ? (int) $tool_call['round'] : 1;
			$sequence = isset( $tool_call['sequence'] ) ? (int) $tool_call['sequence'] : ( $index + 1 );

			$normalized[] = [
				'name'                  => $name,
				'ability'               => '' !== $ability ? $ability : null,
				'args'                  => $args,
				'status'                => $status,
				'requires_confirmation' => isset( $tool_call['requires_confirmation'] ) ? (bool) $tool_call['requires_confirmation'] : ( 'requires_confirmation' === $status ),
				'message'               => '' !== $message ? $message : null,
				'round'                 => max( 1, $round ),
				'sequence'              => max( 1, $sequence ),
			];
		}

		return $normalized;
	}

	/**
	 * Build normalized context usage metadata from a provider result.
	 *
	 * @param GenerativeAiResult $result Result payload.
	 * @param string             $provider Provider identifier.
	 * @param string             $model Model identifier.
	 * @return array<string,mixed>|null
	 */
	private function extract_context_usage_from_result( GenerativeAiResult $result, string $provider, string $model ): ?array {
		$token_usage        = $result->getTokenUsage();
		$prompt_tokens      = max( 0, (int) $token_usage->getPromptTokens() );
		$completion_tokens  = max( 0, (int) $token_usage->getCompletionTokens() );
		$total_tokens       = max( 0, (int) $token_usage->getTotalTokens() );
		$context_used       = $prompt_tokens > 0 ? $prompt_tokens : $total_tokens;
		$context_window     = $this->resolve_context_window_tokens( $provider, $model );
		$percent_used       = null;
		$percent_left       = null;
		$window_is_estimate = null;

		if ( $context_window > 0 ) {
			$percent_used       = (int) round( min( 100, ( $context_used / $context_window ) * 100 ) );
			$percent_left       = max( 0, 100 - $percent_used );
			$window_is_estimate = true;
		}

		if ( 0 === $prompt_tokens && 0 === $completion_tokens && 0 === $total_tokens && null === $percent_used ) {
			return null;
		}

		return [
			'prompt_tokens'         => $prompt_tokens,
			'completion_tokens'     => $completion_tokens,
			'total_tokens'          => $total_tokens,
			'used_tokens'           => $context_used,
			'context_window_tokens' => $context_window > 0 ? $context_window : null,
			'percent_used'          => $percent_used,
			'percent_left'          => $percent_left,
			'window_is_estimated'   => $window_is_estimate,
		];
	}

	/**
	 * Normalize context usage payload.
	 *
	 * @param array<string,mixed> $context Raw context payload.
	 * @return array<string,mixed>|null
	 */
	private function normalize_context_usage_payload( array $context ): ?array {
		$prompt_tokens      = isset( $context['prompt_tokens'] ) ? (int) $context['prompt_tokens'] : 0;
		$completion_tokens  = isset( $context['completion_tokens'] ) ? (int) $context['completion_tokens'] : 0;
		$total_tokens       = isset( $context['total_tokens'] ) ? (int) $context['total_tokens'] : 0;
		$used_tokens        = isset( $context['used_tokens'] ) ? (int) $context['used_tokens'] : max( 0, $prompt_tokens );
		$context_window     = isset( $context['context_window_tokens'] ) ? (int) $context['context_window_tokens'] : 0;
		$percent_used       = isset( $context['percent_used'] ) ? (int) $context['percent_used'] : null;
		$percent_left       = isset( $context['percent_left'] ) ? (int) $context['percent_left'] : null;
		$window_is_estimate = isset( $context['window_is_estimated'] ) ? (bool) $context['window_is_estimated'] : null;

		$prompt_tokens     = max( 0, $prompt_tokens );
		$completion_tokens = max( 0, $completion_tokens );
		$total_tokens      = max( 0, $total_tokens );
		$used_tokens       = max( 0, $used_tokens );
		$context_window    = max( 0, $context_window );

		if ( $context_window > 0 ) {
			if ( null === $percent_used ) {
				$percent_used = (int) round( min( 100, ( $used_tokens / $context_window ) * 100 ) );
			}

			$percent_used = max( 0, min( 100, $percent_used ) );
			$percent_left = null !== $percent_left
				? max( 0, min( 100, $percent_left ) )
				: max( 0, 100 - $percent_used );
		} else {
			$percent_used = null;
			$percent_left = null;
		}

		if ( 0 === $prompt_tokens && 0 === $completion_tokens && 0 === $total_tokens && 0 === $used_tokens && 0 === $context_window ) {
			return null;
		}

		return [
			'prompt_tokens'         => $prompt_tokens,
			'completion_tokens'     => $completion_tokens,
			'total_tokens'          => $total_tokens,
			'used_tokens'           => $used_tokens,
			'context_window_tokens' => $context_window > 0 ? $context_window : null,
			'percent_used'          => $percent_used,
			'percent_left'          => $percent_left,
			'window_is_estimated'   => $window_is_estimate,
		];
	}

	/**
	 * Resolve best-known context window for a provider/model pair.
	 *
	 * @param string $provider Provider identifier.
	 * @param string $model Model identifier.
	 */
	private function resolve_context_window_tokens( string $provider, string $model ): int {
		$provider = clawpress_sanitize_provider( $provider );
		$model    = strtolower( trim( $model ) );
		if ( '' === $provider || '' === $model || ! isset( self::MODEL_CONTEXT_WINDOWS[ $provider ] ) ) {
			return 0;
		}

		foreach ( self::MODEL_CONTEXT_WINDOWS[ $provider ] as $prefix => $window ) {
			if ( 0 !== strpos( $model, strtolower( (string) $prefix ) ) ) {
				continue;
			}

			return max( 0, (int) $window );
		}

		return 0;
	}

	/**
	 * Normalize card payload.
	 *
	 * @param array<string,mixed> $card Raw card payload.
	 * @return array<string,mixed>|null
	 */
	private function normalize_card_payload( array $card ): ?array {
		$type = isset( $card['type'] ) ? strtolower( sanitize_text_field( (string) $card['type'] ) ) : '';
		$type = (string) preg_replace( '/[^a-z0-9_\-]/', '', $type );
		if ( '' === $type ) {
			return null;
		}

		return [
			'type' => $type,
			'data' => isset( $card['data'] ) && is_array( $card['data'] )
				? $card['data']
				: [],
		];
	}

	/**
	 * Build one pending confirmation row for a destructive tool call.
	 *
	 * @param array<string,mixed> $tool_result Tool execution result.
	 * @param string              $tool_name Tool name.
	 * @param mixed               $raw_args Raw tool-call args.
	 * @return array<string,mixed>|null
	 */
	private function normalize_pending_confirmation_tool_call( array $tool_result, string $tool_name, $raw_args ): ?array {
		if ( empty( $tool_result['requires_confirmation'] ) ) {
			return null;
		}

		$normalized_tool_name = strtolower( trim( $tool_name ) );
		if ( '' === $normalized_tool_name ) {
			return null;
		}

		return [
			'tool_name'    => $normalized_tool_name,
			'ability_name' => isset( $tool_result['ability'] ) ? (string) $tool_result['ability'] : '',
			'args'         => $this->normalize_tool_args_for_prompt( $raw_args ),
		];
	}

	/**
	 * Build a user-confirmation card payload.
	 *
	 * @param array<string,mixed>|null $confirmation_batch Pending confirmation batch.
	 * @return array<string,mixed>|null
	 */
	private function build_tool_confirmation_card( ?array $confirmation_batch ): ?array {
		if ( ! is_array( $confirmation_batch ) ) {
			return null;
		}

		$batch_id   = isset( $confirmation_batch['batch_id'] ) ? strtolower( trim( (string) $confirmation_batch['batch_id'] ) ) : '';
		$expires_at = isset( $confirmation_batch['expires_at'] ) ? (int) $confirmation_batch['expires_at'] : 0;
		$calls      = isset( $confirmation_batch['calls'] ) && is_array( $confirmation_batch['calls'] )
			? array_values( $confirmation_batch['calls'] )
			: [];

		if ( '' === $batch_id || [] === $calls ) {
			return null;
		}

		$tool_names    = array_values(
			array_unique(
				array_filter(
					array_map(
						static fn( $call ): string => isset( $call['tool_name'] )
							? strtolower( trim( (string) $call['tool_name'] ) )
							: '',
						$calls
					)
				)
			)
		);
		$total_calls   = count( $calls );
		$expires_at    = $expires_at > 0 ? $expires_at : time();
		$expires_label = function_exists( 'wp_date' )
			? wp_date( 'Y-m-d H:i:s', $expires_at )
			: gmdate( 'Y-m-d H:i:s', $expires_at );

		if ( 1 === $total_calls ) {
			$message = sprintf(
				/* translators: 1: tool name, 2: batch ID, 3: expiry time */
				__( 'Confirm batch `%2$s` to run `%1$s`. This batch expires at %3$s.', 'clawpress' ),
				$tool_names[0] ?? 'tool',
				$batch_id,
				$expires_label
			);
		} else {
			$message = sprintf(
				/* translators: 1: total destructive calls, 2: batch ID, 3: comma-separated tool names, 4: expiry time */
				__( 'This reply queued %1$d destructive tool calls in batch `%2$s` (%3$s). Use Confirm All to execute the entire batch. This batch expires at %4$s.', 'clawpress' ),
				$total_calls,
				$batch_id,
				[] !== $tool_names ? implode( ', ', $tool_names ) : __( 'tools', 'clawpress' ),
				$expires_label
			);
		}

		return [
			'type' => 'user_confirmation',
			'data' => [
				'title'    => __( 'User Confirmation Required', 'clawpress' ),
				'subtitle' => __( 'Destructive batch pending', 'clawpress' ),
				'message'  => $message,
				'actions'  => [
					[
						'id'     => 'confirm-batch-' . md5( $batch_id ),
						'label'  => $total_calls > 1
							? __( 'Confirm All', 'clawpress' )
							: __( 'Confirm Action', 'clawpress' ),
						'type'   => 'send_prompt',
						'prompt' => '/confirm --batch=' . $batch_id,
					],
					[
						'id'     => 'decline-batch-' . md5( $batch_id ),
						'label'  => __( 'Decline', 'clawpress' ),
						'type'   => 'send_prompt',
						'prompt' => '/decline --batch=' . $batch_id,
					],
				],
			],
		];
	}

	/**
	 * Normalize tool-call args.
	 *
	 * @param mixed $raw_args Raw args payload.
	 * @return array<string,mixed>
	 */
	private function normalize_tool_args_for_prompt( $raw_args ): array {
		if ( is_array( $raw_args ) ) {
			return $raw_args;
		}

		if ( is_object( $raw_args ) ) {
			return (array) $raw_args;
		}

		if ( ! is_string( $raw_args ) || '' === trim( $raw_args ) ) {
			return [];
		}

		$decoded = json_decode( $raw_args, true );
		return is_array( $decoded ) ? $decoded : [];
	}

	/**
	 * Normalize history message list.
	 *
	 * @param array<string,mixed> $context Model context payload.
	 * @return array<int,Message>
	 */
	private function normalize_history_messages( array $context ): array {
		$history_messages = [];
		if ( ! isset( $context['history_messages'] ) || ! is_array( $context['history_messages'] ) ) {
			return $history_messages;
		}

		foreach ( $context['history_messages'] as $history_message ) {
			if ( $history_message instanceof Message ) {
				$history_messages[] = $history_message;
			}
		}

		return $history_messages;
	}

	/**
	 * Normalize function declarations from context payload.
	 *
	 * @param array<string,mixed> $context Model context payload.
	 * @return array<int,FunctionDeclaration>
	 */
	private function normalize_tool_declarations( array $context ): array {
		$declarations = [];
		if ( ! isset( $context['tool_declarations'] ) || ! is_array( $context['tool_declarations'] ) ) {
			return $declarations;
		}

		foreach ( $context['tool_declarations'] as $declaration ) {
			if ( $declaration instanceof FunctionDeclaration ) {
				$declarations[] = $declaration;
			}
		}

		return $declarations;
	}

	/**
	 * Build request options object.
	 *
	 * @param int $request_timeout Request timeout in seconds.
	 */
	private function build_request_options( int $request_timeout ): RequestOptions {
		$request_options = new RequestOptions();
		$request_options->setTimeout( (float) max( 1, $request_timeout ) );
		return $request_options;
	}

	/**
	 * Normalize generation settings payload.
	 *
	 * @param array<string,mixed> $generation_settings Raw generation settings.
	 * @return array{temperature:float,top_p:float,max_output_tokens:int,frequency_penalty:float,presence_penalty:float}
	 */
	private function normalize_generation_settings( array $generation_settings ): array {
		return [
			'temperature'       => clawpress_sanitize_temperature( $generation_settings['temperature'] ?? 0.2 ),
			'top_p'             => clawpress_sanitize_top_p( $generation_settings['top_p'] ?? 0.9 ),
			'max_output_tokens' => clawpress_sanitize_max_output_tokens( $generation_settings['max_output_tokens'] ?? 1200 ),
			'frequency_penalty' => clawpress_sanitize_frequency_penalty( $generation_settings['frequency_penalty'] ?? 0.2 ),
			'presence_penalty'  => clawpress_sanitize_presence_penalty( $generation_settings['presence_penalty'] ?? 0.0 ),
		];
	}

	/**
	 * Apply generation settings to prompt builder.
	 *
	 * @param PromptBuilder       $builder Prompt builder.
	 * @param array<string,mixed> $generation_settings Generation settings.
	 * @param string              $provider Provider identifier.
	 * @param string              $model Model identifier.
	 */
	private function apply_generation_settings_to_prompt_builder( PromptBuilder $builder, array $generation_settings, string $provider, string $model ): PromptBuilder {
		$option_setters = [
			static fn ( PromptBuilder $current ): PromptBuilder => $current->usingTemperature( (float) $generation_settings['temperature'] ),
			static fn ( PromptBuilder $current ): PromptBuilder => $current->usingTopP( (float) $generation_settings['top_p'] ),
			fn ( PromptBuilder $current ): PromptBuilder => $this->apply_max_output_tokens_to_prompt_builder(
				$current,
				(int) $generation_settings['max_output_tokens'],
				$provider,
				$model
			),
			static fn ( PromptBuilder $current ): PromptBuilder => $current->usingFrequencyPenalty( (float) $generation_settings['frequency_penalty'] ),
			static fn ( PromptBuilder $current ): PromptBuilder => $current->usingPresencePenalty( (float) $generation_settings['presence_penalty'] ),
		];

		foreach ( $option_setters as $setter ) {
			try {
				$builder = $setter( $builder );
			} catch ( Throwable $throwable ) {
				unset( $throwable );
				continue;
			}
		}

		return $builder;
	}

	/**
	 * Apply max output token setting to prompt builder.
	 *
	 * @param PromptBuilder $builder Prompt builder.
	 * @param int           $max_output_tokens Max output token count.
	 * @param string        $provider Provider identifier.
	 * @param string        $model Model identifier.
	 */
	private function apply_max_output_tokens_to_prompt_builder( PromptBuilder $builder, int $max_output_tokens, string $provider, string $model ): PromptBuilder {
		if ( $this->provider_helper->should_use_max_completion_tokens( $provider, $model ) ) {
			$model_config = ModelConfig::fromArray(
				[
					ModelConfig::KEY_CUSTOM_OPTIONS => [
						'max_completion_tokens' => $max_output_tokens,
					],
				]
			);

			return $builder->usingModelConfig( $model_config );
		}

		return $builder->usingMaxTokens( $max_output_tokens );
	}

	/**
	 * Extract function calls from a message.
	 *
	 * @param Message $message Model message.
	 * @return array<int,FunctionCall>
	 */
	private function extract_function_calls( Message $message ): array {
		$function_calls = [];

		foreach ( $message->getParts() as $part ) {
			$function_call = $part->getFunctionCall();
			if ( $function_call instanceof FunctionCall ) {
				$function_calls[] = $function_call;
			}
		}

		return $function_calls;
	}

	/**
	 * Extract text content from a message.
	 *
	 * @param Message $message Model message.
	 */
	private function extract_text_from_message( Message $message ): string {
		$chunks = [];

		foreach ( $message->getParts() as $part ) {
			$text = $part->getText();
			if ( null === $text || '' === trim( $text ) ) {
				continue;
			}

			$chunks[] = trim( $text );
		}

		return trim( implode( "\n", $chunks ) );
	}

	/**
	 * Classify known provider error patterns.
	 *
	 * @param Throwable $throwable Thrown exception.
	 * @param string    $error_message Sanitized error message.
	 */
	public function classify_provider_error_type( Throwable $throwable, string $error_message ): string {
		$message  = strtolower( $error_message . ' ' . $throwable->getMessage() );
		$patterns = [
			'timed out',
			'timeout',
			'curl error 28',
			'deadline exceeded',
			'operation timed out',
		];

		foreach ( $patterns as $pattern ) {
			if ( false !== strpos( $message, $pattern ) ) {
				return 'timeout';
			}
		}

		return 'provider';
	}

	/**
	 * Get current timestamp in milliseconds.
	 */
	private function now_ms(): int {
		return (int) round( microtime( true ) * 1000 );
	}

	/**
	 * Invoke the online reply generator with arity-safe argument slicing.
	 *
	 * @param callable            $online_reply_generator Online reply generator callback.
	 * @param array<string,mixed> $context Model context.
	 * @param string              $provider Provider ID.
	 * @param string              $model Model ID.
	 * @param array<string,mixed> $turn_request Turn request payload.
	 * @param Agent_Transport     $transport Runtime transport.
	 * @param bool                $is_slice Whether this is a slice execution.
	 * @return mixed
	 */
	private function invoke_online_reply_generator(
		callable $online_reply_generator,
		array $context,
		string $provider,
		string $model,
		array $turn_request,
		Agent_Transport $transport,
		bool $is_slice
	) {
		$args  = [ $context, $provider, $model, $turn_request, $transport, $is_slice ];
		$arity = $this->resolve_callable_arity( $online_reply_generator );
		if ( $arity > 0 && $arity < count( $args ) ) {
			$args = array_slice( $args, 0, $arity );
		}

		return call_user_func_array( $online_reply_generator, $args );
	}

	/**
	 * Resolve callable arity for safe callback invocation.
	 *
	 * @param callable $target_callable Callable to inspect.
	 */
	private function resolve_callable_arity( callable $target_callable ): int {
		try {
			if ( is_array( $target_callable ) ) {
				$reflection = new \ReflectionMethod( $target_callable[0], (string) $target_callable[1] );
				return $reflection->isVariadic() ? 0 : $reflection->getNumberOfParameters();
			}

			if ( $target_callable instanceof \Closure || is_string( $target_callable ) ) {
				$reflection = new \ReflectionFunction( $target_callable );
				return $reflection->isVariadic() ? 0 : $reflection->getNumberOfParameters();
			}

			if ( is_object( $target_callable ) && method_exists( $target_callable, '__invoke' ) ) {
				$reflection = new \ReflectionMethod( $target_callable, '__invoke' );
				return $reflection->isVariadic() ? 0 : $reflection->getNumberOfParameters();
			}

			$reflection = new \ReflectionFunction( $target_callable );
			return $reflection->isVariadic() ? 0 : $reflection->getNumberOfParameters();
		} catch ( \ReflectionException | \TypeError $exception ) {
			unset( $exception );
			return 0;
		}
	}
}

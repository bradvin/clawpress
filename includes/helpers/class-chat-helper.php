<?php
/**
 * Chat helper.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Helpers;

use ClawPress\Commands\Commands;
use Throwable;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Builders\MessageBuilder;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AiClient\Tools\DTO\FunctionCall;
use WordPress\AiClient\Tools\DTO\FunctionDeclaration;
use WordPress\AiClient\Tools\DTO\FunctionResponse;

defined( 'ABSPATH' ) || exit;

/**
 * Reply generation helper.
 */
final class Chat_Helper {
	/**
	 * Maximum tool-calling rounds per user message.
	 */
	private const MAX_TOOL_ROUNDS = 4;

	/**
	 * Maximum tool calls executed in one assistant turn.
	 */
	private const MAX_TOOL_CALLS_PER_ROUND = 6;

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
	 * LLM reply generator.
	 *
	 * @var callable|null
	 */
	private $online_reply_generator;

	/**
	 * Provider/model resolver.
	 *
	 * @var callable|null
	 */
	private $provider_model_resolver;

	/**
	 * Constructor.
	 *
	 * @param Context_Helper|null $context_helper Optional context helper.
	 * @param callable|null       $online_reply_generator Optional online reply generator.
	 * @param callable|null       $provider_model_resolver Optional provider/model resolver.
	 */
	private function __construct(
		?Context_Helper $context_helper = null,
		?callable $online_reply_generator = null,
		?callable $provider_model_resolver = null
	) {
		$this->settings_helper         = Settings_Helper::get_instance();
		$this->provider_helper         = Provider_Helper::get_instance();
		$this->context_helper          = $context_helper ?? Context_Helper::get_instance();
		$this->abilities_helper        = Abilities_Helper::get_instance();
		$this->online_reply_generator  = $online_reply_generator ?? [ $this, 'generate_online_reply' ];
		$this->provider_model_resolver = $provider_model_resolver ?? [ $this, 'resolve_provider_and_model' ];
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
	 * Create a test-scoped helper with optional dependency overrides.
	 *
	 * @param Context_Helper|null $context_helper Optional context helper.
	 * @param callable|null       $online_reply_generator Optional reply generator.
	 * @param callable|null       $provider_model_resolver Optional provider/model resolver.
	 */
	public static function create_for_testing(
		?Context_Helper $context_helper = null,
		?callable $online_reply_generator = null,
		?callable $provider_model_resolver = null
	): self {
		return new self( $context_helper, $online_reply_generator, $provider_model_resolver );
	}

	/**
	 * Generate a model reply payload.
	 *
	 * @param string $message User message.
	 * @return array<string,mixed>
	 */
	public function generate_ai_reply( string $message ): array {
		$settings = $this->settings_helper->get_settings();
		$resolved = call_user_func( $this->provider_model_resolver, $settings );
		$provider = isset( $resolved['provider'] ) ? trim( (string) $resolved['provider'] ) : '';
		$model    = isset( $resolved['model'] ) ? trim( (string) $resolved['model'] ) : '';

		if ( '' === $provider ) {
			return [
				'reply'       => $this->build_offline_reply( $message ),
				'mode'        => 'offline',
				'provider'    => null,
				'model'       => null,
				'suggestions' => $this->get_default_offline_suggestions(),
			];
		}

		try {
			$context                    = $this->context_helper->build_model_context( $message );
			$context['request_timeout'] = $this->settings_helper->get_request_timeout( $settings );
			$online_reply_payload       = $this->normalize_online_reply_payload(
				call_user_func(
					$this->online_reply_generator,
					$context,
					$provider,
					$model
				)
			);
			$reply                      = trim( (string) $online_reply_payload['reply'] );
			$card                       = $online_reply_payload['card'];
			$context_usage              = $online_reply_payload['context'];
			$tool_calls                 = $online_reply_payload['tool_calls'];

			if ( '' === $reply && null === $card ) {
				return [
					'reply'       => $this->build_offline_reply( $message ),
					'mode'        => 'offline',
					'provider'    => $provider,
					'model'       => '' !== $model ? $model : null,
					'suggestions' => $this->get_default_offline_suggestions(),
				];
			}

			if ( '' === $reply && null !== $card ) {
				$reply = $this->build_card_fallback_reply( $card );
			}

			$payload = [
				'reply'       => $reply,
				'mode'        => 'online',
				'provider'    => $provider,
				'model'       => '' !== $model ? $model : null,
				'suggestions' => $this->get_online_suggestions( $reply, $provider, $model ),
			];

			if ( null !== $card ) {
				$payload['card'] = $card;
			}

			if ( null !== $context_usage ) {
				$payload['context'] = $context_usage;
			}

			if ( [] !== $tool_calls ) {
				$payload['tool_calls'] = $tool_calls;
			}

			return $payload;
		} catch ( Throwable $throwable ) {
			return $this->build_error_reply_payload( $throwable, $provider, $model );
		}
	}

	/**
	 * Default online reply generator using php-ai-client.
	 *
	 * @param array<string,mixed> $context Model context payload.
	 * @param string              $provider Provider identifier.
	 * @param string              $model Model identifier.
	 * @return array{
	 *     reply:string,
	 *     card:array<string,mixed>|null,
	 *     context:array<string,mixed>|null,
	 *     tool_calls:array<int,array<string,mixed>>
	 * }
	 */
	private function generate_online_reply( array $context, string $provider, string $model ): array {
		$current_message          = isset( $context['message'] ) ? trim( (string) $context['message'] ) : '';
		$system_prompt            = isset( $context['system_prompt'] ) ? trim( (string) $context['system_prompt'] ) : '';
		$request_timeout          = isset( $context['request_timeout'] ) ? (int) $context['request_timeout'] : 30;
		$history_messages         = $this->normalize_history_messages( $context );
		$tool_declarations        = $this->normalize_tool_declarations( $context );
		$requesting_user_id       = isset( $context['requesting_user_id'] ) ? (int) $context['requesting_user_id'] : 0;
		$execution_user_id        = isset( $context['execution_user_id'] ) ? (int) $context['execution_user_id'] : 0;
		$user_confirmation_tokens = $this->extract_confirmation_tokens_from_message( $current_message );

		$conversation = $history_messages;
		if ( '' !== $current_message ) {
			$conversation[] = ( new MessageBuilder( $current_message ) )
				->usingUserRole()
				->get();
		}

		$latest_assistant_text = '';
		$confirmation_option   = null;
		$latest_context_usage  = null;
		$tool_call_trace       = [];

		for ( $round = 0; $round < self::MAX_TOOL_ROUNDS; ++$round ) {
			$builder = AiClient::prompt( $conversation )->usingProvider( $provider );
			if ( '' !== $system_prompt ) {
				$builder = $builder->usingSystemInstruction( $system_prompt );
			}

			if ( '' !== $model ) {
				$builder = $builder->usingModelPreference( [ $provider, $model ] );
			}

			$builder = $builder->usingRequestOptions( $this->build_request_options( $request_timeout ) );
			if ( [] !== $tool_declarations ) {
				$builder = $builder->usingFunctionDeclarations( ...$tool_declarations );
			}

			$result                = $builder->generateResult();
			$assistant_message     = $result->toMessage();
			$conversation[]        = $assistant_message;
			$latest_assistant_text = $this->extract_text_from_message( $assistant_message );
			$latest_context_usage  = $this->extract_context_usage_from_result( $result, $provider, $model );

			$function_calls = $this->extract_function_calls( $assistant_message );
			if ( [] === $function_calls ) {
				break;
			}

			$function_responses = [];
			foreach ( array_slice( $function_calls, 0, self::MAX_TOOL_CALLS_PER_ROUND ) as $index => $function_call ) {
				$tool_name = trim( (string) $function_call->getName() );
				if ( '' === $tool_name ) {
					continue;
				}

				$function_response_id = trim( (string) $function_call->getId() );
				if ( '' === $function_response_id ) {
					$function_response_id = sprintf( 'tool-call-%d-%d', $round + 1, $index + 1 );
				}

				$tool_result       = $this->abilities_helper->execute_tool_call(
					$tool_name,
					$function_call->getArgs(),
					[
						'requesting_user_id'          => $requesting_user_id,
						'execution_user_id'           => $execution_user_id,
						'allowed_confirmation_tokens' => $user_confirmation_tokens,
					]
				);
				$tool_call_trace[] = $this->build_tool_call_trace_entry(
					$tool_name,
					$function_call->getArgs(),
					$tool_result,
					$round + 1,
					$index + 1
				);

				$detected_confirmation = $this->extract_tool_confirmation_option(
					$tool_result,
					$tool_name,
					$function_call->getArgs()
				);
				if ( is_array( $detected_confirmation ) ) {
					$confirmation_option = $detected_confirmation;
				}

				$function_responses[] = new FunctionResponse(
					$function_response_id,
					$tool_name,
					$tool_result
				);
			}

			if ( [] === $function_responses ) {
				break;
			}

			if ( is_array( $confirmation_option ) ) {
				return [
					'reply'      => $latest_assistant_text,
					'card'       => $this->build_tool_confirmation_card( $confirmation_option ),
					'context'    => $latest_context_usage,
					'tool_calls' => $tool_call_trace,
				];
			}

			foreach ( $function_responses as $function_response ) {
				$tool_response_message_builder = new MessageBuilder();
				$tool_response_message_builder->usingUserRole();
				$tool_response_message_builder->withFunctionResponse( $function_response );
				$conversation[] = $tool_response_message_builder->get();
			}
		}

		return [
			'reply'      => $latest_assistant_text,
			'card'       => $this->build_tool_confirmation_card( $confirmation_option ),
			'context'    => $latest_context_usage,
			'tool_calls' => $tool_call_trace,
		];
	}

	/**
	 * Extract confirmation tokens from the current user message.
	 *
	 * @param string $message User message.
	 * @return array<int,string>
	 */
	private function extract_confirmation_tokens_from_message( string $message ): array {
		$message = trim( $message );
		if ( '' === $message ) {
			return [];
		}

		$tokens = [];

		if ( preg_match_all( '/--confirm=([a-f0-9]{10,64})/i', $message, $matches ) ) {
			$tokens = array_merge( $tokens, $matches[1] );
		}

		if ( preg_match_all( '/confirm_token[^a-f0-9]+([a-f0-9]{10,64})/i', $message, $matches ) ) {
			$tokens = array_merge( $tokens, $matches[1] );
		}

		if ( preg_match_all( '/\b([a-f0-9]{10,64})\b/i', $message, $matches ) ) {
			$tokens = array_merge( $tokens, $matches[1] );
		}

		$normalized = [];
		foreach ( $tokens as $token ) {
			$candidate = strtolower( trim( (string) $token ) );
			if ( '' === $candidate ) {
				continue;
			}

			$normalized[] = $candidate;
		}

		return array_values( array_unique( $normalized ) );
	}

	/**
	 * Normalize online generator output into reply + optional card payload.
	 *
	 * @param mixed $raw_output Raw callback output.
	 * @return array{
	 *     reply:string,
	 *     card:array<string,mixed>|null,
	 *     context:array<string,mixed>|null,
	 *     tool_calls:array<int,array<string,mixed>>
	 * }
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

			return [
				'reply'      => $reply,
				'card'       => $card,
				'context'    => $context,
				'tool_calls' => $tool_calls,
			];
		}

		return [
			'reply'      => (string) $raw_output,
			'card'       => null,
			'context'    => null,
			'tool_calls' => [],
		];
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
	 * Normalize card payload for chat responses.
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
	 * Build text fallback for card-only responses.
	 *
	 * @param array<string,mixed> $card Card payload.
	 */
	private function build_card_fallback_reply( array $card ): string {
		$message = isset( $card['data']['message'] ) ? trim( (string) $card['data']['message'] ) : '';
		if ( '' !== $message ) {
			return $message;
		}

		if ( 'user_confirmation' === (string) ( $card['type'] ?? '' ) ) {
			return __( 'A destructive action is waiting for your confirmation.', 'clawpress' );
		}

		return __( 'Action required.', 'clawpress' );
	}

	/**
	 * Extract one pending confirmation option from tool result payload.
	 *
	 * @param mixed  $tool_result Tool result payload.
	 * @param string $tool_name   Tool name.
	 * @param mixed  $raw_args    Tool call arguments.
	 * @return array<string,mixed>|null
	 */
	private function extract_tool_confirmation_option( $tool_result, string $tool_name, $raw_args ): ?array {
		if ( ! is_array( $tool_result ) || empty( $tool_result['requires_confirmation'] ) ) {
			return null;
		}

		$error = isset( $tool_result['error'] ) && is_array( $tool_result['error'] )
			? $tool_result['error']
			: [];
		$token = isset( $error['token'] ) ? trim( (string) $error['token'] ) : '';
		if ( '' === $token ) {
			return null;
		}

		$normalized_tool_name = strtolower( trim( $tool_name ) );
		if ( '' === $normalized_tool_name ) {
			$normalized_tool_name = 'tool';
		}

		$expires_at = isset( $error['expires_at'] ) ? (int) $error['expires_at'] : 0;
		$args       = $this->normalize_tool_args_for_prompt( $raw_args );

		return [
			'tool_name'      => $normalized_tool_name,
			'ability_name'   => isset( $tool_result['ability'] ) ? (string) $tool_result['ability'] : '',
			'error_message'  => isset( $error['message'] ) ? (string) $error['message'] : '',
			'token'          => $token,
			'expires_at'     => $expires_at,
			'args'           => $args,
			'confirm_prompt' => $this->build_confirmation_prompt( $normalized_tool_name, $token, $args ),
			'decline_prompt' => $this->build_decline_prompt( $normalized_tool_name ),
		];
	}

	/**
	 * Build a user-confirmation card payload from a pending option.
	 *
	 * @param array<string,mixed>|null $confirmation_option Pending confirmation option.
	 * @return array<string,mixed>|null
	 */
	private function build_tool_confirmation_card( ?array $confirmation_option ): ?array {
		if ( ! is_array( $confirmation_option ) ) {
			return null;
		}

		$tool_name      = isset( $confirmation_option['tool_name'] ) ? (string) $confirmation_option['tool_name'] : 'tool';
		$token          = isset( $confirmation_option['token'] ) ? (string) $confirmation_option['token'] : '';
		$confirm_prompt = isset( $confirmation_option['confirm_prompt'] ) ? (string) $confirmation_option['confirm_prompt'] : '';
		$decline_prompt = isset( $confirmation_option['decline_prompt'] ) ? (string) $confirmation_option['decline_prompt'] : '';
		$expires_at     = isset( $confirmation_option['expires_at'] ) ? (int) $confirmation_option['expires_at'] : 0;
		$error_message  = isset( $confirmation_option['error_message'] ) ? trim( (string) $confirmation_option['error_message'] ) : '';

		if ( '' === $token || '' === $confirm_prompt ) {
			return null;
		}

		$expires_label = $expires_at > 0
			? ( function_exists( 'wp_date' ) ? wp_date( 'Y-m-d H:i:s', $expires_at ) : gmdate( 'Y-m-d H:i:s', $expires_at ) )
			: __( 'soon', 'clawpress' );

		$message = sprintf(
			/* translators: 1: tool name, 2: confirmation token expiry time */
			__( 'Confirm `%1$s` to continue this destructive action. Confirmation token expires at %2$s.', 'clawpress' ),
			$tool_name,
			$expires_label
		);

		if ( '' !== $error_message ) {
			$message = $error_message . "\n\n" . $message;
		}

		return [
			'type' => 'user_confirmation',
			'data' => [
				'title'    => __( 'User Confirmation Required', 'clawpress' ),
				'subtitle' => __( 'Destructive action pending', 'clawpress' ),
				'message'  => $message,
				'actions'  => [
					[
						'id'     => 'confirm-' . md5( $token ),
						'label'  => __( 'Confirm Action', 'clawpress' ),
						'type'   => 'send_prompt',
						'prompt' => $confirm_prompt,
					],
					[
						'id'     => 'decline-' . md5( $token ),
						'label'  => __( 'Decline', 'clawpress' ),
						'type'   => 'send_prompt',
						'prompt' => '' !== $decline_prompt
							? $decline_prompt
							: __( 'Do not run the pending destructive action.', 'clawpress' ),
					],
				],
			],
		];
	}

	/**
	 * Build a confirmation prompt for the next user turn.
	 *
	 * @param string              $tool_name Tool name.
	 * @param string              $token Confirmation token.
	 * @param array<string,mixed> $args Original tool arguments.
	 */
	private function build_confirmation_prompt( string $tool_name, string $token, array $args ): string {
		$args_json = wp_json_encode( $args );
		if ( false === $args_json || '' === trim( (string) $args_json ) ) {
			$args_json = '{}';
		}

		return sprintf(
			/* translators: 1: tool name, 2: confirmation token, 3: serialized JSON arguments */
			__( 'Confirm and run the pending `%1$s` tool call now. Re-run `%1$s` with arguments %3$s, and include `confirm=true` plus `confirm_token="%2$s"`.', 'clawpress' ),
			$tool_name,
			$token,
			(string) $args_json
		);
	}

	/**
	 * Build a decline/cancel prompt for the next user turn.
	 *
	 * @param string $tool_name Tool name.
	 */
	private function build_decline_prompt( string $tool_name ): string {
		return sprintf(
			/* translators: %s: tool name */
			__( 'Cancel the pending destructive `%s` tool call and do not run it.', 'clawpress' ),
			$tool_name
		);
	}

	/**
	 * Normalize tool-call args for prompt interpolation.
	 *
	 * @param mixed $raw_args Raw tool-call args payload.
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
	 * Extract function calls from an assistant/model message.
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
	 * Extract text content from an assistant/model message.
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
	 * Resolve provider + model with default runtime behavior.
	 *
	 * @param array<string,mixed> $settings Current settings.
	 * @return array{provider:string,model:string}
	 */
	private function resolve_provider_and_model( array $settings ): array {
		return [
			'provider' => $this->provider_helper->resolve_provider_with_fallback( $settings ),
			'model'    => $this->provider_helper->resolve_model( $settings ),
		];
	}

	/**
	 * Build deterministic offline fallback response.
	 *
	 * @param string $message User message.
	 */
	public function build_offline_reply( string $message ): string {
		return sprintf(
			/* translators: %s: the original user message */
			__( 'Offline mode: no configured AI provider was available. You said: "%s"', 'clawpress' ),
			$message
		);
	}

	/**
	 * Get default offline command suggestions.
	 *
	 * @return array<int,string>
	 */
	private function get_default_offline_suggestions(): array {
		return ( new Commands() )->get_default_suggestions();
	}

	/**
	 * Resolve online suggestions from provider output via filter hook.
	 *
	 * @param string $reply Generated reply text.
	 * @param string $provider Provider identifier.
	 * @param string $model Model identifier.
	 * @return array<int,string>
	 */
	private function get_online_suggestions( string $reply, string $provider, string $model ): array {
		$suggestions = apply_filters(
			'clawpress_ai_suggestions',
			[],
			[
				'reply'    => $reply,
				'provider' => $provider,
				'model'    => $model,
			]
		);

		if ( ! is_array( $suggestions ) ) {
			return [];
		}

		$normalized = array_values(
			array_filter(
				array_map(
					static fn ( $suggestion ): string => trim( (string) $suggestion ),
					$suggestions
				),
				static fn ( string $suggestion ): bool => '' !== $suggestion
			)
		);

		return array_slice( $normalized, 0, 8 );
	}

	/**
	 * Build error payload for model/transport failures.
	 *
	 * @param Throwable $throwable Thrown exception.
	 * @param string    $provider Provider identifier.
	 * @param string    $model Model identifier.
	 * @return array<string,mixed>
	 */
	private function build_error_reply_payload( Throwable $throwable, string $provider, string $model ): array {
		$error_message = trim( sanitize_text_field( $throwable->getMessage() ) );
		if ( '' === $error_message ) {
			$error_message = __( 'Unknown provider error.', 'clawpress' );
		}

		$error_type = $this->classify_error_type( $throwable, $error_message );
		$reply      = sprintf(
			/* translators: %s: provider/transport error message */
			__( 'AI request failed: %s', 'clawpress' ),
			$error_message
		);

		$card_subtitle = 'timeout' === $error_type
			? __( 'Request timed out', 'clawpress' )
			: __( 'Provider error', 'clawpress' );

		$error_code = $throwable->getCode();
		if ( ! is_int( $error_code ) && ! is_string( $error_code ) ) {
			$error_code = 0;
		}

		return [
			'reply'       => $reply,
			'mode'        => 'error',
			'provider'    => '' !== $provider ? $provider : null,
			'model'       => '' !== $model ? $model : null,
			'suggestions' => $this->get_default_offline_suggestions(),
			'error'       => [
				'type'      => $error_type,
				'message'   => $error_message,
				'code'      => $error_code,
				'retryable' => 'timeout' === $error_type,
			],
			'card'        => [
				'type' => 'error',
				'data' => [
					'title'    => __( 'Request Error', 'clawpress' ),
					'subtitle' => $card_subtitle,
					'message'  => $error_message,
				],
			],
		];
	}

	/**
	 * Classify known provider error patterns.
	 *
	 * @param Throwable $throwable Thrown exception.
	 * @param string    $error_message Sanitized error message.
	 */
	private function classify_error_type( Throwable $throwable, string $error_message ): string {
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
}

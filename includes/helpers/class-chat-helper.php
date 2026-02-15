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
	 * @var callable(array<string,mixed>,string,string):string
	 */
	private $online_reply_generator;

	/**
	 * Provider/model resolver.
	 *
	 * @var callable(array<string,mixed>):array{provider:string,model:string}
	 */
	private $provider_model_resolver;

	/**
	 * Constructor.
	 *
	 * @param Context_Helper|null                                                    $context_helper Optional context helper.
	 * @param callable(array<string,mixed>,string,string):string|null                $online_reply_generator Optional online reply generator.
	 * @param callable(array<string,mixed>):array{provider:string,model:string}|null $provider_model_resolver Optional provider/model resolver.
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
	 * @param Context_Helper|null                                                    $context_helper Optional context helper.
	 * @param callable(array<string,mixed>,string,string):string|null                $online_reply_generator Optional reply generator.
	 * @param callable(array<string,mixed>):array{provider:string,model:string}|null $provider_model_resolver Optional provider/model resolver.
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
			$reply                      = trim(
				(string) call_user_func(
					$this->online_reply_generator,
					$context,
					$provider,
					$model
				)
			);

			if ( '' === $reply ) {
				return [
					'reply'       => $this->build_offline_reply( $message ),
					'mode'        => 'offline',
					'provider'    => $provider,
					'model'       => '' !== $model ? $model : null,
					'suggestions' => $this->get_default_offline_suggestions(),
				];
			}

			return [
				'reply'       => $reply,
				'mode'        => 'online',
				'provider'    => $provider,
				'model'       => '' !== $model ? $model : null,
				'suggestions' => $this->get_online_suggestions( $reply, $provider, $model ),
			];
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
	 */
	private function generate_online_reply( array $context, string $provider, string $model ): string {
		$current_message    = isset( $context['message'] ) ? trim( (string) $context['message'] ) : '';
		$system_prompt      = isset( $context['system_prompt'] ) ? trim( (string) $context['system_prompt'] ) : '';
		$request_timeout    = isset( $context['request_timeout'] ) ? (int) $context['request_timeout'] : 30;
		$history_messages   = $this->normalize_history_messages( $context );
		$tool_declarations  = $this->normalize_tool_declarations( $context );
		$requesting_user_id = isset( $context['requesting_user_id'] ) ? (int) $context['requesting_user_id'] : 0;
		$execution_user_id  = isset( $context['execution_user_id'] ) ? (int) $context['execution_user_id'] : 0;

		$conversation = $history_messages;
		if ( '' !== $current_message ) {
			$conversation[] = ( new MessageBuilder( $current_message ) )
				->usingUserRole()
				->get();
		}

		$latest_assistant_text = '';

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

				$tool_result = $this->abilities_helper->execute_tool_call(
					$tool_name,
					$function_call->getArgs(),
					[
						'requesting_user_id' => $requesting_user_id,
						'execution_user_id'  => $execution_user_id,
					]
				);

				$function_responses[] = new FunctionResponse(
					$function_response_id,
					$tool_name,
					$tool_result
				);
			}

			if ( [] === $function_responses ) {
				break;
			}

			foreach ( $function_responses as $function_response ) {
				$tool_response_message_builder = new MessageBuilder();
				$tool_response_message_builder->usingUserRole();
				$tool_response_message_builder->withFunctionResponse( $function_response );
				$conversation[] = $tool_response_message_builder->get();
			}
		}

		return $latest_assistant_text;
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

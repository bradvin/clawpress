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

defined( 'ABSPATH' ) || exit;

/**
 * Reply generation helper.
 */
final class Chat_Helper {
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
	 * Agent loop runtime helper.
	 *
	 * @var Agent_Loop_Helper
	 */
	private Agent_Loop_Helper $agent_loop_helper;

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
	 * @param Context_Helper|null $context_helper Optional context helper (reserved for compatibility).
	 * @param callable|null       $online_reply_generator Optional online reply generator.
	 * @param callable|null       $provider_model_resolver Optional provider/model resolver.
	 */
	private function __construct(
		?Context_Helper $context_helper = null,
		?callable $online_reply_generator = null,
		?callable $provider_model_resolver = null
	) {
		unset( $context_helper );

		$this->settings_helper         = Settings_Helper::get_instance();
		$this->provider_helper         = Provider_Helper::get_instance();
		$this->agent_loop_helper       = Agent_Loop_Helper::get_instance();
		$this->online_reply_generator  = $online_reply_generator;
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
			$turn_request = [
				'message'                 => $message,
				'trigger'                 => 'chat',
				'transport_mode'          => 'polling',
				'requesting_user_id'      => function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0,
				'execution_user_id'       => $this->settings_helper->resolve_agent_user_id( $settings ),
				'provider_model_resolver' => $this->provider_model_resolver,
			];
			if ( null !== $this->online_reply_generator ) {
				$turn_request['online_reply_generator'] = $this->online_reply_generator;
			}

			$runtime_result = $this->agent_loop_helper->run_turn( $turn_request );

			if ( isset( $runtime_result['error'] ) && is_array( $runtime_result['error'] ) ) {
				return $this->build_runtime_error_reply_payload( $runtime_result['error'], $provider, $model );
			}

			$reply         = isset( $runtime_result['assistant_text'] ) ? trim( (string) $runtime_result['assistant_text'] ) : '';
			$card          = isset( $runtime_result['card'] ) && is_array( $runtime_result['card'] ) ? $runtime_result['card'] : null;
			$context_usage = isset( $runtime_result['context'] ) && is_array( $runtime_result['context'] ) ? $runtime_result['context'] : null;
			$tool_calls    = isset( $runtime_result['tool_calls'] ) && is_array( $runtime_result['tool_calls'] ) ? $runtime_result['tool_calls'] : [];

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
	 * Build error payload from runtime error data.
	 *
	 * @param array<string,mixed> $error Runtime error payload.
	 * @param string              $provider Provider identifier.
	 * @param string              $model Model identifier.
	 * @return array<string,mixed>
	 */
	private function build_runtime_error_reply_payload( array $error, string $provider, string $model ): array {
		$error_message = isset( $error['message'] ) ? trim( sanitize_text_field( (string) $error['message'] ) ) : '';
		if ( '' === $error_message ) {
			$error_message = __( 'Unknown provider error.', 'clawpress' );
		}

		$error_type = isset( $error['type'] ) ? strtolower( trim( (string) $error['type'] ) ) : 'provider';
		if ( ! in_array( $error_type, [ 'timeout', 'provider' ], true ) ) {
			$error_type = 'provider';
		}

		$error_code = $error['code'] ?? 0;
		if ( ! is_int( $error_code ) && ! is_string( $error_code ) ) {
			$error_code = 0;
		}

		return [
			'reply'       => sprintf(
				/* translators: %s: provider/transport error message */
				__( 'AI request failed: %s', 'clawpress' ),
				$error_message
			),
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
					'subtitle' => 'timeout' === $error_type
						? __( 'Request timed out', 'clawpress' )
						: __( 'Provider error', 'clawpress' ),
					'message'  => $error_message,
				],
			],
		];
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
		$error_code = $throwable->getCode();
		if ( ! is_int( $error_code ) && ! is_string( $error_code ) ) {
			$error_code = 0;
		}

		return [
			'reply'       => sprintf(
				/* translators: %s: provider/transport error message */
				__( 'AI request failed: %s', 'clawpress' ),
				$error_message
			),
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
					'subtitle' => 'timeout' === $error_type
						? __( 'Request timed out', 'clawpress' )
						: __( 'Provider error', 'clawpress' ),
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

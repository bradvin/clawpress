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
use WordPress\AiClient\Messages\DTO\Message;

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
	 * Context helper.
	 *
	 * @var Context_Helper
	 */
	private Context_Helper $context_helper;

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
			$context = $this->context_helper->build_model_context( $message );
			$reply   = trim(
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
			unset( $throwable );

			return [
				'reply'       => $this->build_offline_reply( $message ),
				'mode'        => 'offline',
				'provider'    => $provider,
				'model'       => '' !== $model ? $model : null,
				'suggestions' => $this->get_default_offline_suggestions(),
			];
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
		$current_message = isset( $context['message'] ) ? trim( (string) $context['message'] ) : '';
		$builder         = AiClient::prompt( $current_message )->usingProvider( $provider );

		$system_prompt = isset( $context['system_prompt'] ) ? trim( (string) $context['system_prompt'] ) : '';
		if ( '' !== $system_prompt ) {
			$builder = $builder->usingSystemInstruction( $system_prompt );
		}

		$history_messages = [];
		if ( isset( $context['history_messages'] ) && is_array( $context['history_messages'] ) ) {
			foreach ( $context['history_messages'] as $history_message ) {
				if ( $history_message instanceof Message ) {
					$history_messages[] = $history_message;
				}
			}
		}

		if ( [] !== $history_messages ) {
			$builder = $builder->withHistory( ...$history_messages );
		}

		if ( '' !== $model ) {
			$builder = $builder->usingModelPreference( [ $provider, $model ] );
		}

		return (string) $builder->generateText();
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
}

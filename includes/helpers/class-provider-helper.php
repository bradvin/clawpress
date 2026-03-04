<?php
/**
 * Provider helper.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Helpers;

use ClawPress\Helpers\ProviderRules\Provider_Message_Rules;
use ClawPress\Helpers\ProviderRules\Provider_Message_Rules_Resolver;
use Throwable;
use WordPress\AiClient\AiClient;

defined( 'ABSPATH' ) || exit;

/**
 * Provider resolution and credential helper.
 */
final class Provider_Helper {
	/**
	 * Provider credential map.
	 *
	 * @var array<string,string>
	 */
	private const PROVIDER_CREDENTIALS = [
		'openai'    => 'OPENAI_API_KEY',
		'anthropic' => 'ANTHROPIC_API_KEY',
		'google'    => 'GOOGLE_API_KEY',
	];

	/**
	 * Singleton instance.
	 *
	 * @var ?self
	 */
	private static ?self $instance = null;

	/**
	 * In-request provider configuration cache.
	 *
	 * @var array<string,bool>
	 */
	private array $provider_configuration_cache = [];

	/**
	 * Provider message rules resolver.
	 *
	 * @var Provider_Message_Rules_Resolver
	 */
	private Provider_Message_Rules_Resolver $provider_message_rules_resolver;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->provider_message_rules_resolver = new Provider_Message_Rules_Resolver();
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
	 * Get provider to credential map.
	 *
	 * @return array<string,string>
	 */
	public function get_provider_credentials_map(): array {
		return self::PROVIDER_CREDENTIALS;
	}

	/**
	 * Resolve provider from settings only.
	 *
	 * @param array<string,mixed> $settings Settings array.
	 */
	public function resolve_provider_from_settings( array $settings ): string {
		if ( ! isset( $settings['provider'] ) || ! is_string( $settings['provider'] ) ) {
			return '';
		}

		$provider = clawpress_sanitize_provider( $settings['provider'] );
		if ( '' === $provider ) {
			return '';
		}

		return $this->is_provider_configured( $provider ) ? $provider : '';
	}

	/**
	 * Resolve provider using settings first and available credentials second.
	 *
	 * @param array<string,mixed> $settings Settings array.
	 */
	public function resolve_provider_with_fallback( array $settings ): string {
		$provider = $this->resolve_provider_from_settings( $settings );
		if ( '' !== $provider ) {
			return $provider;
		}

		foreach ( $this->get_registered_provider_ids() as $candidate_provider ) {
			if ( $this->is_provider_configured( $candidate_provider ) ) {
				return $candidate_provider;
			}
		}

		return '';
	}

	/**
	 * Get configured provider IDs with valid credentials.
	 *
	 * @return array<int,string>
	 */
	public function get_configured_provider_ids(): array {
		$configured = [];

		foreach ( $this->get_registered_provider_ids() as $provider_id ) {
			if ( ! $this->has_provider_credentials( $provider_id ) && ! $this->is_provider_configured( $provider_id ) ) {
				continue;
			}

			$configured[] = $provider_id;
		}

		return array_values( array_unique( $configured ) );
	}

	/**
	 * Resolve model from settings.
	 *
	 * @param array<string,mixed> $settings Settings array.
	 */
	public function resolve_model( array $settings ): string {
		if ( ! isset( $settings['model'] ) || ! is_string( $settings['model'] ) ) {
			return '';
		}

		return trim( $settings['model'] );
	}

	/**
	 * Resolve provider and model with fallback behavior.
	 *
	 * @param array<string,mixed> $settings Settings array.
	 * @return array{provider:string,model:string}
	 */
	public function resolve_provider_and_model( array $settings ): array {
		return [
			'provider' => $this->resolve_provider_with_fallback( $settings ),
			'model'    => $this->resolve_model( $settings ),
		];
	}

	/**
	 * Whether a provider/model combination should apply temperature sampling.
	 *
	 * @param string $provider Provider identifier.
	 * @param string $model Model identifier.
	 */
	public function should_use_temperature( string $provider, string $model ): bool {
		return $this->resolve_provider_message_rules( $provider )->should_use_temperature( $model );
	}

	/**
	 * Whether a provider/model combination should use `max_output_tokens`.
	 *
	 * Some OpenAI model families reject legacy `max_tokens` and require
	 * `max_output_tokens` when using the Responses API.
	 *
	 * @param string $provider Provider identifier.
	 * @param string $model Model identifier.
	 */
	public function should_use_max_output_tokens( string $provider, string $model ): bool {
		return $this->resolve_provider_message_rules( $provider )->should_use_max_output_tokens( $model );
	}

	/**
	 * Backward-compatible alias for earlier naming.
	 *
	 * @param string $provider Provider identifier.
	 * @param string $model Model identifier.
	 */
	public function should_use_max_completion_tokens( string $provider, string $model ): bool {
		return $this->should_use_max_output_tokens( $provider, $model );
	}

	/**
	 * Whether a provider/model combination should apply top-p sampling.
	 *
	 * Anthropic rejects requests that send both temperature and top_p together,
	 * so we skip top_p there and keep temperature as the single sampling control.
	 *
	 * @param string $provider Provider identifier.
	 * @param string $model Model identifier.
	 */
	public function should_use_top_p( string $provider, string $model ): bool {
		return $this->resolve_provider_message_rules( $provider )->should_use_top_p( $model );
	}

	/**
	 * Whether a provider/model combination supports frequency penalty settings.
	 *
	 * @param string $provider Provider identifier.
	 * @param string $model Model identifier.
	 */
	public function should_use_frequency_penalty( string $provider, string $model ): bool {
		return $this->resolve_provider_message_rules( $provider )->should_use_frequency_penalty( $model );
	}

	/**
	 * Whether a provider/model combination supports presence penalty settings.
	 *
	 * @param string $provider Provider identifier.
	 * @param string $model Model identifier.
	 */
	public function should_use_presence_penalty( string $provider, string $model ): bool {
		return $this->resolve_provider_message_rules( $provider )->should_use_presence_penalty( $model );
	}

	/**
	 * Resolve provider message rules implementation for a provider.
	 *
	 * @param string $provider Provider identifier.
	 */
	private function resolve_provider_message_rules( string $provider ): Provider_Message_Rules {
		return $this->provider_message_rules_resolver->resolve_for_provider( $provider );
	}

	/**
	 * Check whether credentials exist for a provider.
	 *
	 * @param string $provider Provider ID.
	 */
	public function has_provider_credentials( string $provider ): bool {
		$provider = clawpress_sanitize_provider( $provider );

		if ( ! isset( self::PROVIDER_CREDENTIALS[ $provider ] ) ) {
			return false;
		}

		return $this->has_credential( self::PROVIDER_CREDENTIALS[ $provider ] );
	}

	/**
	 * Get registered provider IDs from AiClient.
	 *
	 * @return array<int,string>
	 */
	private function get_registered_provider_ids(): array {
		try {
			$provider_ids = AiClient::defaultRegistry()->getRegisteredProviderIds();
			if ( ! is_array( $provider_ids ) ) {
				return [];
			}

			$normalized_provider_ids = [];

			foreach ( $provider_ids as $provider_id ) {
				$normalized_provider_id = clawpress_sanitize_provider( $provider_id );
				if ( '' === $normalized_provider_id ) {
					continue;
				}

				$normalized_provider_ids[] = $normalized_provider_id;
			}

			return [] !== $normalized_provider_ids
				? array_values( array_unique( $normalized_provider_ids ) )
				: [];
		} catch ( Throwable $throwable ) {
			unset( $throwable );
			return [];
		}
	}

	/**
	 * Check whether a provider is configured through AiClient.
	 *
	 * @param string $provider Provider ID.
	 */
	private function is_provider_configured( string $provider ): bool {
		$provider = clawpress_sanitize_provider( $provider );
		if ( '' === $provider ) {
			return false;
		}

		if ( isset( $this->provider_configuration_cache[ $provider ] ) ) {
			return $this->provider_configuration_cache[ $provider ];
		}

		$is_configured = false;

		try {
			$is_configured = AiClient::isConfigured( $provider );
		} catch ( Throwable $throwable ) {
			unset( $throwable );
			$is_configured = false;
		}

		$this->provider_configuration_cache[ $provider ] = (bool) $is_configured;
		return $this->provider_configuration_cache[ $provider ];
	}

	/**
	 * Check whether an environment variable or constant has a non-empty value.
	 *
	 * @param string $key Credential key.
	 */
	private function has_credential( string $key ): bool {
		$env_value = getenv( $key );
		if ( false !== $env_value && '' !== trim( (string) $env_value ) ) {
			return true;
		}

		if ( ! defined( $key ) ) {
			return false;
		}

		$constant_value = constant( $key );
		if ( ! is_scalar( $constant_value ) ) {
			return false;
		}

		return '' !== trim( (string) $constant_value );
	}
}

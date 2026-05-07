<?php
/**
 * Provider helper.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Helpers;

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
	 * Constructor.
	 */
	private function __construct() {}

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
	 * Get provider options from the AI client registry.
	 *
	 * @return array<int,array{value:string,label:string}>
	 */
	public function get_provider_options(): array {
		$options = [];

		foreach ( $this->get_registered_provider_ids() as $provider_id ) {
			$label = $this->get_provider_label( $provider_id );
			if ( '' === $label ) {
				$label = $provider_id;
			}

			$options[] = [
				'value' => $provider_id,
				'label' => $label,
			];
		}

		return $options;
	}

	/**
	 * Determine whether a provider ID is supported.
	 *
	 * Falls back to the local credential map when the AI client registry is not
	 * available so existing saved settings remain valid in tests and bootstrap.
	 *
	 * @param string $provider Provider ID.
	 */
	public function supports_provider_id( string $provider ): bool {
		$provider = $this->normalize_provider_id( $provider );
		if ( '' === $provider ) {
			return false;
		}

		$registered_provider_ids = $this->get_registered_provider_ids();
		if ( [] !== $registered_provider_ids ) {
			return in_array( $provider, $registered_provider_ids, true );
		}

		return isset( self::PROVIDER_CREDENTIALS[ $provider ] );
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
				$normalized_provider_id = $this->normalize_provider_id( (string) $provider_id );
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
	 * Resolve a user-facing label for a provider.
	 *
	 * @param string $provider Provider ID.
	 */
	private function get_provider_label( string $provider ): string {
		$provider = $this->normalize_provider_id( $provider );
		if ( '' === $provider ) {
			return '';
		}

		try {
			$registry = AiClient::defaultRegistry();
			if ( ! $registry->hasProvider( $provider ) ) {
				return $this->format_provider_label( $provider );
			}

			$provider_class_name = $registry->getProviderClassName( $provider );
			if ( ! is_string( $provider_class_name ) || '' === trim( $provider_class_name ) || ! class_exists( $provider_class_name ) ) {
				return $this->format_provider_label( $provider );
			}

			if ( ! method_exists( $provider_class_name, 'metadata' ) ) {
				return $this->format_provider_label( $provider );
			}

			$provider_metadata = $provider_class_name::metadata();
			if ( ! is_object( $provider_metadata ) || ! method_exists( $provider_metadata, 'getName' ) ) {
				return $this->format_provider_label( $provider );
			}

			$provider_label = trim( (string) $provider_metadata->getName() );
			return '' !== $provider_label ? $provider_label : $this->format_provider_label( $provider );
		} catch ( Throwable $throwable ) {
			unset( $throwable );
			return $this->format_provider_label( $provider );
		}
	}

	/**
	 * Format a provider identifier into a readable label.
	 *
	 * @param string $provider Provider ID.
	 */
	private function format_provider_label( string $provider ): string {
		$provider = trim( str_replace( [ '-', '_' ], ' ', $provider ) );
		if ( '' === $provider ) {
			return '';
		}

		return ucwords( $provider );
	}

	/**
	 * Normalize a provider identifier without validating it.
	 *
	 * @param string $provider Raw provider ID.
	 */
	private function normalize_provider_id( string $provider ): string {
		return strtolower( sanitize_key( sanitize_text_field( $provider ) ) );
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

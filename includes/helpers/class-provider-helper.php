<?php
/**
 * Provider helper.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Helpers;

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
	 * Resolve provider from settings only.
	 *
	 * @param array<string,mixed> $settings Settings array.
	 */
	public function resolve_provider_from_settings( array $settings ): string {
		if ( ! isset( $settings['provider'] ) || ! is_string( $settings['provider'] ) ) {
			return '';
		}

		$provider = clawpress_sanitize_provider( $settings['provider'] );
		return isset( self::PROVIDER_CREDENTIALS[ $provider ] ) ? $provider : '';
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

		foreach ( self::PROVIDER_CREDENTIALS as $candidate_provider => $credential_key ) {
			if ( $this->has_credential( $credential_key ) ) {
				return $candidate_provider;
			}
		}

		return '';
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
	 * Check whether credentials exist for a provider.
	 *
	 * @param string $provider Provider ID.
	 */
	public function has_provider_credentials( string $provider ): bool {
		if ( ! isset( self::PROVIDER_CREDENTIALS[ $provider ] ) ) {
			return false;
		}

		return $this->has_credential( self::PROVIDER_CREDENTIALS[ $provider ] );
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

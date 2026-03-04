<?php
/**
 * Provider message rules resolver.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Helpers\ProviderRules;

defined( 'ABSPATH' ) || exit;

/**
 * Central resolver for provider-specific message generation rules.
 */
final class Provider_Message_Rules_Resolver {
	/**
	 * Fallback key used for unknown providers.
	 */
	private const FALLBACK_PROVIDER_KEY = '*';

	/**
	 * Rules indexed by provider id.
	 *
	 * @var array<string,Provider_Message_Rules>
	 */
	private array $rules_by_provider = [];

	/**
	 * Constructor.
	 *
	 * @param array<int,Provider_Message_Rules>|null $rules Optional rules.
	 */
	public function __construct( ?array $rules = null ) {
		$registered_rules = is_array( $rules ) && [] !== $rules
			? $rules
			: $this->get_default_rules();

		foreach ( $registered_rules as $rule ) {
			if ( ! $rule instanceof Provider_Message_Rules ) {
				continue;
			}

			$provider_id = trim( $rule->get_provider_id() );
			if ( self::FALLBACK_PROVIDER_KEY === $provider_id ) {
				$this->rules_by_provider[ self::FALLBACK_PROVIDER_KEY ] = $rule;
				continue;
			}

			$normalized_provider_id = clawpress_sanitize_provider( $provider_id );
			if ( '' === $normalized_provider_id ) {
				continue;
			}

			$this->rules_by_provider[ $normalized_provider_id ] = $rule;
		}

		if ( ! isset( $this->rules_by_provider[ self::FALLBACK_PROVIDER_KEY ] ) ) {
			$this->rules_by_provider[ self::FALLBACK_PROVIDER_KEY ] = new Generic_Provider_Message_Rules(
				self::FALLBACK_PROVIDER_KEY
			);
		}
	}

	/**
	 * Resolve rules for a provider.
	 *
	 * @param string $provider Provider identifier.
	 */
	public function resolve_for_provider( string $provider ): Provider_Message_Rules {
		$provider = clawpress_sanitize_provider( $provider );
		if ( '' !== $provider && isset( $this->rules_by_provider[ $provider ] ) ) {
			return $this->rules_by_provider[ $provider ];
		}

		return $this->rules_by_provider[ self::FALLBACK_PROVIDER_KEY ];
	}

	/**
	 * Get the default rule implementations.
	 *
	 * @return array<int,Provider_Message_Rules>
	 */
	private function get_default_rules(): array {
		return [
			new OpenAI_Provider_Message_Rules(),
			new Anthropic_Provider_Message_Rules(),
			new Google_Provider_Message_Rules(),
			new Generic_Provider_Message_Rules( self::FALLBACK_PROVIDER_KEY ),
		];
	}
}


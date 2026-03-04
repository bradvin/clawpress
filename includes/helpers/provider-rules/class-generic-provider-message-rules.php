<?php
/**
 * Generic provider message rules.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Helpers\ProviderRules;

defined( 'ABSPATH' ) || exit;

/**
 * Default rule behavior for providers without specific overrides.
 *
 * This fallback is intentionally conservative: it opts out of optional
 * provider-specific generation knobs to reduce request compatibility risks.
 */
final class Generic_Provider_Message_Rules implements Provider_Message_Rules {
	/**
	 * Provider identifier this ruleset is registered under.
	 *
	 * @var string
	 */
	private string $provider_id;

	/**
	 * Constructor.
	 *
	 * @param string $provider_id Provider identifier.
	 */
	public function __construct( string $provider_id = '*' ) {
		$this->provider_id = '' !== trim( $provider_id ) ? trim( $provider_id ) : '*';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_provider_id(): string {
		return $this->provider_id;
	}

	/**
	 * {@inheritDoc}
	 */
	public function should_use_temperature( string $model ): bool {
		unset( $model );
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function should_use_max_output_tokens( string $model ): bool {
		unset( $model );
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function should_use_top_p( string $model ): bool {
		unset( $model );
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function should_use_frequency_penalty( string $model ): bool {
		unset( $model );
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function should_use_presence_penalty( string $model ): bool {
		unset( $model );
		return false;
	}
}

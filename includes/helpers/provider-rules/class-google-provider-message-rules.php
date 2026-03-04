<?php
/**
 * Google provider message rules.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Helpers\ProviderRules;

defined( 'ABSPATH' ) || exit;

/**
 * Google-specific generation option rules.
 */
final class Google_Provider_Message_Rules implements Provider_Message_Rules {
	/**
	 * {@inheritDoc}
	 */
	public function get_provider_id(): string {
		return 'google';
	}

	/**
	 * {@inheritDoc}
	 */
	public function should_use_temperature( string $model ): bool {
		unset( $model );
		return true;
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
		return true;
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

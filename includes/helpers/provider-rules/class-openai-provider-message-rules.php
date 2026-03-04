<?php
/**
 * OpenAI provider message rules.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Helpers\ProviderRules;

defined( 'ABSPATH' ) || exit;

/**
 * OpenAI-specific generation option rules.
 */
final class OpenAI_Provider_Message_Rules implements Provider_Message_Rules {
	/**
	 * Models that support temperature sampling.
	 *
	 * @var array<int,string>
	 */
	private const TEMPERATURE_SUPPORTED_MODELS = [
		'gpt-5.3-codex',
		'gpt-5.2',
		'gpt-5.2-2025-12-11',
	];

	/**
	 * Models that reject top_p sampling fields.
	 *
	 * @var array<int,string>
	 */
	private const TOP_P_UNSUPPORTED_MODELS = [
		'gpt-5.3-chat-latest',
	];

	/**
	 * {@inheritDoc}
	 */
	public function get_provider_id(): string {
		return 'openai';
	}

	/**
	 * {@inheritDoc}
	 */
	public function should_use_temperature( string $model ): bool {
		$normalized_model = strtolower( trim( $model ) );
		return in_array( $normalized_model, self::TEMPERATURE_SUPPORTED_MODELS, true );
	}

	/**
	 * {@inheritDoc}
	 */
	public function should_use_max_output_tokens( string $model ): bool {
		$normalized_model = strtolower( trim( $model ) );
		if ( '' === $normalized_model ) {
			return false;
		}

		return str_starts_with( $normalized_model, 'o1' )
			|| str_starts_with( $normalized_model, 'o3' )
			|| str_starts_with( $normalized_model, 'o4' )
			|| str_starts_with( $normalized_model, 'gpt-5' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function should_use_top_p( string $model ): bool {
		$normalized_model = strtolower( trim( $model ) );
		return ! in_array( $normalized_model, self::TOP_P_UNSUPPORTED_MODELS, true );
	}

	/**
	 * {@inheritDoc}
	 */
	public function should_use_frequency_penalty( string $model ): bool {
		unset( $model );
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function should_use_presence_penalty( string $model ): bool {
		unset( $model );
		return true;
	}
}

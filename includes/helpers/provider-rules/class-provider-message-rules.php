<?php
/**
 * Provider message rules contract.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Helpers\ProviderRules;

defined( 'ABSPATH' ) || exit;

/**
 * Provider-specific rules for message generation options.
 */
interface Provider_Message_Rules {
	/**
	 * Get provider identifier this ruleset handles.
	 */
	public function get_provider_id(): string;

	/**
	 * Whether to include `temperature` for this model.
	 *
	 * @param string $model Model identifier.
	 */
	public function should_use_temperature( string $model ): bool;

	/**
	 * Whether to use `max_output_tokens` for this model.
	 *
	 * @param string $model Model identifier.
	 */
	public function should_use_max_output_tokens( string $model ): bool;

	/**
	 * Whether to include `top_p` for this model.
	 *
	 * @param string $model Model identifier.
	 */
	public function should_use_top_p( string $model ): bool;

	/**
	 * Whether to include `frequency_penalty` for this model.
	 *
	 * @param string $model Model identifier.
	 */
	public function should_use_frequency_penalty( string $model ): bool;

	/**
	 * Whether to include `presence_penalty` for this model.
	 *
	 * @param string $model Model identifier.
	 */
	public function should_use_presence_penalty( string $model ): bool;
}

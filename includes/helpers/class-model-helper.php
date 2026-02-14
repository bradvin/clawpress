<?php
/**
 * Model helper.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Shared model options helper.
 */
final class Model_Helper {
	/**
	 * Hardcoded model options grouped by provider.
	 *
	 * @var array<string,array<int,array{id:string,label:string}>>
	 */
	private const MODEL_OPTIONS = [
		'openai' => [
			[
				'id'    => 'gpt-4.1-mini',
				'label' => 'GPT-4.1 Mini',
			],
			[
				'id'    => 'gpt-4.1',
				'label' => 'GPT-4.1',
			],
			[
				'id'    => 'gpt-4o-mini',
				'label' => 'GPT-4o Mini',
			],
			[
				'id'    => 'gpt-4o',
				'label' => 'GPT-4o',
			],
		],
		'anthropic' => [
			[
				'id'    => 'claude-3-5-haiku-latest',
				'label' => 'Claude 3.5 Haiku',
			],
			[
				'id'    => 'claude-3-5-sonnet-latest',
				'label' => 'Claude 3.5 Sonnet',
			],
			[
				'id'    => 'claude-3-7-sonnet-latest',
				'label' => 'Claude 3.7 Sonnet',
			],
		],
		'google' => [
			[
				'id'    => 'gemini-2.0-flash',
				'label' => 'Gemini 2.0 Flash',
			],
			[
				'id'    => 'gemini-2.5-flash',
				'label' => 'Gemini 2.5 Flash',
			],
			[
				'id'    => 'gemini-2.5-pro',
				'label' => 'Gemini 2.5 Pro',
			],
		],
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
	 * Get model options for a provider.
	 *
	 * @param string $provider Provider ID.
	 * @return array<int,array{id:string,label:string}>
	 */
	public function get_options_for_provider( string $provider ): array {
		$provider = clawpress_sanitize_provider( $provider );
		if ( '' === $provider || ! isset( self::MODEL_OPTIONS[ $provider ] ) ) {
			return [];
		}

		return self::MODEL_OPTIONS[ $provider ];
	}

	/**
	 * Get model options for all supported providers.
	 *
	 * @return array<string,array<int,array{id:string,label:string}>>
	 */
	public function get_all_options(): array {
		return self::MODEL_OPTIONS;
	}

	/**
	 * Get the default model ID for a provider.
	 *
	 * @param string $provider Provider ID.
	 */
	public function get_default_model_for_provider( string $provider ): string {
		$options = $this->get_options_for_provider( $provider );
		if ( [] === $options ) {
			return '';
		}

		return $options[0]['id'];
	}
}

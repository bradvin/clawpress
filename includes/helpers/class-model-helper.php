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
		'openai'    => [
			[
				'id'      => 'gpt-5.2-codex',
				'label'   => 'GPT-5.2 Codex',
				'context' => '400K',
				'cost'    => '$1.75/M input tokens | $14/M output tokens',
			],
			[
				'id'      => 'gpt-5.2',
				'label'   => 'GPT-5.2',
				'context' => '400K',
				'cost'    => '$1.75/M input tokens | $14/M output tokens',
			],
			[
				'id'      => 'gpt-5.2-chat',
				'label'   => 'GPT-5.2 Chat',
				'context' => '128K',
				'cost'    => '$1.75/M input tokens | $14/M output tokens',
			],
			[
				'id'      => 'gpt-5.2-pro',
				'label'   => 'GPT-5.2 Pro',
				'context' => '400K',
				'cost'    => '$21/M input tokens | $168/M output tokens',
			],
		],
		'anthropic' => [
			[
				'id'      => 'claude-opus-4.6',
				'label'   => 'Claude Opus 4.6',
				'context' => '1M',
				'cost'    => '$5/M input tokens | $25/M output tokens',
			],
			[
				'id'      => 'claude-sonnet-4.5',
				'label'   => 'Claude Sonnet 4.5',
				'context' => '200K',
				'cost'    => '$1/M input tokens | $5/M output tokens',
			],
			[
				'id'      => 'claude-haiku-4.5',
				'label'   => 'Claude Haiku 4.5',
				'context' => '1M',
				'cost'    => '$5/M input tokens | $25/M output tokens',
			],

		],
		'google'    => [
			[
				'id'      => 'gemini-3-pro-preview',
				'label'   => 'Gemini 3 Pro',
				'context' => '1.05M',
				'cost'    => '$2/M input tokens | $12/M output tokens',
			],
			[
				'id'      => 'gemini-3-flash-preview',
				'label'   => 'Gemini 3 Flash',
				'context' => '1.05M',
				'cost'    => '$0.50/M input tokens | $3/M output tokens',
			],
			[
				'id'      => 'gemini-2.5-pro',
				'label'   => 'Gemini 2.5 Pro',
				'context' => '1.05M',
				'cost'    => '$1.25/M input tokens | $10/M output tokens',
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

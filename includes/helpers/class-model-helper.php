<?php
/**
 * Model helper.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Helpers;

use Throwable;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;

defined( 'ABSPATH' ) || exit;

/**
 * Shared model options helper.
 */
final class Model_Helper {
	/**
	 * Curated model catalog grouped by provider.
	 *
	 * @var array<string,array<int,array{id:string,label:string,context:string,cost:string}>>
	 */
	private const MODEL_CATALOG = [
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
	 * Model option helper.
	 *
	 * @var Model_Option_Helper
	 */
	private Model_Option_Helper $model_option_helper;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->model_option_helper = Model_Option_Helper::get_instance();
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
	 * Get model options for a provider.
	 *
	 * @param string $provider Provider ID.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_options_for_provider( string $provider ): array {
		$provider = clawpress_sanitize_provider( $provider );
		if ( '' === $provider ) {
			return [];
		}

		$provider_registered = $this->is_registry_provider( $provider );
		if ( $provider_registered ) {
			return $this->get_registry_options_for_provider( $provider );
		}

		if ( ! isset( self::MODEL_CATALOG[ $provider ] ) ) {
			return [];
		}

		return array_map(
			function ( array $option ) use ( $provider ): array {
				$model_id = (string) $option['id'];

				return [
					'id'    => $model_id,
					'label' => (string) $option['label'],
				] + $this->model_option_helper->get_generation_option_summary( $provider, $model_id );
			},
			self::MODEL_CATALOG[ $provider ]
		);
	}

	/**
	 * Get model options for all supported providers.
	 *
	 * @return array<string,array<int,array<string,mixed>>>
	 */
	public function get_all_options(): array {
		$options = [];

		foreach ( $this->get_supported_provider_ids() as $provider ) {
			$options[ $provider ] = $this->get_options_for_provider( $provider );
		}

		return $options;
	}

	/**
	 * Get discovered model options from registered providers only.
	 *
	 * @return array<string,array<int,array<string,mixed>>>
	 */
	public function get_all_discovered_options(): array {
		$options = [];

		try {
			$provider_ids = AiClient::defaultRegistry()->getRegisteredProviderIds();
		} catch ( Throwable $throwable ) {
			unset( $throwable );
			return $options;
		}

		if ( ! is_array( $provider_ids ) ) {
			return $options;
		}

		foreach ( $provider_ids as $provider_id ) {
			$provider = clawpress_sanitize_provider( $provider_id );
			if ( '' === $provider ) {
				continue;
			}

			$options[ $provider ] = $this->get_registry_options_for_provider( $provider );
		}

		return $options;
	}

	/**
	 * Get curated model catalog metadata.
	 *
	 * @return array<string,array<int,array{id:string,label:string,context:string,cost:string}>>
	 */
	public function get_model_catalog(): array {
		return self::MODEL_CATALOG;
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

	/**
	 * Resolve text-generation models registered for a provider via the AI client registry.
	 *
	 * @param string $provider Provider ID.
	 * @return array<int,array<string,mixed>>
	 */
	private function get_registry_options_for_provider( string $provider ): array {
		try {
			$registry = AiClient::defaultRegistry();
			if ( ! $registry->hasProvider( $provider ) ) {
				return [];
			}

			$provider_class_name = $registry->getProviderClassName( $provider );
			if ( ! is_string( $provider_class_name ) || '' === trim( $provider_class_name ) || ! class_exists( $provider_class_name ) ) {
				return [];
			}

			$provider_availability = $provider_class_name::availability();
			if ( $provider_availability instanceof ProviderAvailabilityInterface && ! $provider_availability->isConfigured() ) {
				return [];
			}

			$model_metadata_directory = $provider_class_name::modelMetadataDirectory();
			if ( ! $model_metadata_directory instanceof ModelMetadataDirectoryInterface ) {
				return [];
			}

			$models = $model_metadata_directory->listModelMetadata();
			if ( ! is_array( $models ) ) {
				return [];
			}

			$options = [];
			foreach ( $models as $model ) {
				if ( ! $model instanceof ModelMetadata ) {
					continue;
				}

				$model_id = trim( (string) $model->getId() );
				if ( '' === $model_id ) {
					continue;
				}

				$model_label = trim( (string) $model->getName() );
				if ( '' === $model_label ) {
					$model_label = $model_id;
				}

				$options[ $model_id ] = [
					'id'    => $model_id,
					'label' => $model_label,
				] + $this->model_option_helper->get_generation_option_summary_from_metadata(
					$provider,
					$model_id,
					$model
				);
			}

			return array_values( $options );
		} catch ( Throwable $throwable ) {
			unset( $throwable );
			return [];
		}
	}

	/**
	 * Check whether a provider is registered in the AI client registry.
	 *
	 * @param string $provider Provider ID.
	 */
	private function is_registry_provider( string $provider ): bool {
		try {
			return AiClient::defaultRegistry()->hasProvider( $provider );
		} catch ( Throwable $throwable ) {
			unset( $throwable );
			return false;
		}
	}

	/**
	 * Get all known provider IDs (registry + local fallback list).
	 *
	 * @return array<int,string>
	 */
	private function get_supported_provider_ids(): array {
		$providers = array_keys( self::MODEL_CATALOG );

		try {
			$registry_provider_ids = AiClient::defaultRegistry()->getRegisteredProviderIds();
			if ( is_array( $registry_provider_ids ) ) {
				foreach ( $registry_provider_ids as $provider_id ) {
					$normalized_provider = clawpress_sanitize_provider( $provider_id );
					if ( '' !== $normalized_provider ) {
						$providers[] = $normalized_provider;
					}
				}
			}
		} catch ( Throwable $throwable ) {
			unset( $throwable );
		}

		return array_values( array_unique( $providers ) );
	}
}

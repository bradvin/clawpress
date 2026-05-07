<?php
/**
 * Model option helper.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Helpers;

use Throwable;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Models\DTO\ModelConfig;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves generation option support from model metadata and learned provider errors.
 */
final class Model_Option_Helper {
	/**
	 * Option key for learned unsupported model options.
	 */
	private const UNSUPPORTED_OPTIONS_OPTION = 'clawpress_unsupported_model_options';

	/**
	 * Generation setting keys mapped to AI client metadata option names.
	 *
	 * @var array<string,string>
	 */
	private const GENERATION_OPTION_NAMES = [
		'temperature'       => 'temperature',
		'top_p'             => 'topP',
		'max_output_tokens' => 'maxTokens',
		'frequency_penalty' => 'frequencyPenalty',
		'presence_penalty'  => 'presencePenalty',
	];

	/**
	 * Provider parameter names mapped to ClawPress generation setting keys.
	 *
	 * @var array<string,string>
	 */
	private const PARAMETER_ALIASES = [
		'temperature'           => 'temperature',
		'top_p'                 => 'top_p',
		'topp'                  => 'top_p',
		'topP'                  => 'top_p',
		'max_tokens'            => 'max_output_tokens',
		'maxtokens'             => 'max_output_tokens',
		'maxTokens'             => 'max_output_tokens',
		'max_output_tokens'     => 'max_output_tokens',
		'maxoutputtokens'       => 'max_output_tokens',
		'max_completion_tokens' => 'max_output_tokens',
		'maxcompletiontokens'   => 'max_output_tokens',
		'frequency_penalty'     => 'frequency_penalty',
		'frequencypenalty'      => 'frequency_penalty',
		'frequencyPenalty'      => 'frequency_penalty',
		'presence_penalty'      => 'presence_penalty',
		'presencepenalty'       => 'presence_penalty',
		'presencePenalty'       => 'presence_penalty',
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
	 * Check whether a generation option should be sent for a provider/model pair.
	 *
	 * Unknown metadata is treated as supported so providers without metadata keep
	 * working, while learned provider errors still disable known-bad options.
	 *
	 * @param string $provider Provider identifier.
	 * @param string $model Model identifier.
	 * @param string $option_key Generation setting key.
	 * @param mixed  $value Setting value.
	 */
	public function supports_generation_option( string $provider, string $model, string $option_key, $value ): bool {
		$option_key = $this->normalize_generation_option_key( $option_key );
		if ( '' === $option_key ) {
			return false;
		}

		if ( $this->has_learned_unsupported_generation_option( $provider, $model, $option_key ) ) {
			return false;
		}

		$metadata_support = $this->metadata_supports_option(
			$provider,
			$model,
			self::GENERATION_OPTION_NAMES[ $option_key ],
			$value
		);

		return false !== $metadata_support;
	}

	/**
	 * Check option support from model metadata.
	 *
	 * Returns null when metadata cannot be resolved.
	 *
	 * @param string $provider Provider identifier.
	 * @param string $model Model identifier.
	 * @param string $option_name AI client metadata option name.
	 * @param mixed  $value Optional value to validate.
	 */
	public function metadata_supports_option( string $provider, string $model, string $option_name, $value = null ): ?bool {
		$provider = clawpress_sanitize_provider( $provider );
		$model    = trim( sanitize_text_field( $model ) );
		if ( '' === $provider || '' === $model || '' === trim( $option_name ) ) {
			return null;
		}

		$metadata = $this->resolve_model_metadata( $provider, $model );
		if ( null === $metadata || ! method_exists( $metadata, 'getSupportedOptions' ) ) {
			return null;
		}

		return $this->metadata_object_supports_option( $metadata, $option_name, $value );
	}

	/**
	 * Get metadata option names supported by a model.
	 *
	 * @param string $provider Provider identifier.
	 * @param string $model Model identifier.
	 * @return array<int,string>
	 */
	public function get_supported_option_names( string $provider, string $model ): array {
		$metadata = $this->resolve_model_metadata(
			clawpress_sanitize_provider( $provider ),
			trim( sanitize_text_field( $model ) )
		);

		return null !== $metadata ? $this->get_supported_option_names_from_metadata( $metadata ) : [];
	}

	/**
	 * Get unsupported generation option metadata for a model.
	 *
	 * @param string $provider Provider identifier.
	 * @param string $model Model identifier.
	 * @return array{supported_options:array<int,string>,unsupported_generation_options:array<int,string>,unsupported_generation_option_labels:array<int,string>,learned_unsupported_options:array<int,string>}
	 */
	public function get_generation_option_summary( string $provider, string $model ): array {
		$metadata = $this->resolve_model_metadata(
			clawpress_sanitize_provider( $provider ),
			trim( sanitize_text_field( $model ) )
		);

		return $this->get_generation_option_summary_from_metadata( $provider, $model, $metadata );
	}

	/**
	 * Get unsupported generation option metadata from an existing metadata object.
	 *
	 * @param string  $provider Provider identifier.
	 * @param string  $model Model identifier.
	 * @param ?object $metadata Model metadata.
	 * @return array{supported_options:array<int,string>,unsupported_generation_options:array<int,string>,unsupported_generation_option_labels:array<int,string>,learned_unsupported_options:array<int,string>}
	 */
	public function get_generation_option_summary_from_metadata( string $provider, string $model, ?object $metadata ): array {
		$unsupported = [];
		$learned     = [];

		foreach ( array_keys( self::GENERATION_OPTION_NAMES ) as $option_key ) {
			if ( $this->has_learned_unsupported_generation_option( $provider, $model, $option_key ) ) {
				$unsupported[] = $option_key;
				$learned[]     = $option_key;
				continue;
			}

			$metadata_support = null !== $metadata
				? $this->metadata_object_supports_option( $metadata, self::GENERATION_OPTION_NAMES[ $option_key ] )
				: null;
			if ( false === $metadata_support ) {
				$unsupported[] = $option_key;
			}
		}

		$unsupported = array_values( array_unique( $unsupported ) );

		return [
			'supported_options'                    => null !== $metadata ? $this->get_supported_option_names_from_metadata( $metadata ) : [],
			'unsupported_generation_options'      => $unsupported,
			'unsupported_generation_option_labels' => array_map(
				[ $this, 'get_generation_option_label' ],
				$unsupported
			),
			'learned_unsupported_options'         => array_values( array_unique( $learned ) ),
		];
	}

	/**
	 * Record an unsupported provider parameter if an error identifies one.
	 *
	 * @param string           $provider Provider identifier.
	 * @param string           $model Model identifier.
	 * @param Throwable|string $error Provider error.
	 */
	public function record_unsupported_parameter_from_error( string $provider, string $model, $error ): ?string {
		$message    = $error instanceof Throwable ? $error->getMessage() : (string) $error;
		$option_key = $this->extract_unsupported_generation_option( $message );
		if ( null === $option_key ) {
			return null;
		}

		$this->mark_generation_option_unsupported( $provider, $model, $option_key );
		return $option_key;
	}

	/**
	 * Extract a known generation setting key from an unsupported parameter error.
	 *
	 * @param string $message Error message.
	 */
	public function extract_unsupported_generation_option( string $message ): ?string {
		$message = trim( $message );
		if ( '' === $message ) {
			return null;
		}

		$patterns = [
			'/unsupported\s+parameter\s*:?\s*[\'"]?([a-zA-Z0-9_.-]+)[\'"]?/i',
			'/unknown\s+parameter\s*:?\s*[\'"]?([a-zA-Z0-9_.-]+)[\'"]?/i',
			'/unrecognized\s+(?:request\s+)?(?:argument|parameter).*?[\'"]([a-zA-Z0-9_.-]+)[\'"]/i',
			'/[\'"]([a-zA-Z0-9_.-]+)[\'"]\s+is\s+not\s+supported/i',
		];

		foreach ( $patterns as $pattern ) {
			if ( 1 !== preg_match( $pattern, $message, $matches ) ) {
				continue;
			}

			$option_key = $this->normalize_generation_parameter_name( (string) ( $matches[1] ?? '' ) );
			if ( '' !== $option_key ) {
				return $option_key;
			}
		}

		return null;
	}

	/**
	 * Check whether an option was learned unsupported from an earlier provider error.
	 *
	 * @param string $provider Provider identifier.
	 * @param string $model Model identifier.
	 * @param string $option_key Generation setting key.
	 */
	public function has_learned_unsupported_generation_option( string $provider, string $model, string $option_key ): bool {
		$provider   = clawpress_sanitize_provider( $provider );
		$model      = trim( sanitize_text_field( $model ) );
		$option_key = $this->normalize_generation_option_key( $option_key );
		if ( '' === $provider || '' === $model || '' === $option_key ) {
			return false;
		}

		$options = $this->get_learned_unsupported_options();
		return isset( $options[ $provider ][ $model ][ $option_key ] );
	}

	/**
	 * Mark a generation option unsupported for a provider/model pair.
	 *
	 * @param string $provider Provider identifier.
	 * @param string $model Model identifier.
	 * @param string $option_key Generation setting key.
	 */
	public function mark_generation_option_unsupported( string $provider, string $model, string $option_key ): void {
		$provider   = clawpress_sanitize_provider( $provider );
		$model      = trim( sanitize_text_field( $model ) );
		$option_key = $this->normalize_generation_option_key( $option_key );
		if ( '' === $provider || '' === $model || '' === $option_key ) {
			return;
		}

		$options = $this->get_learned_unsupported_options();
		if ( ! isset( $options[ $provider ] ) || ! is_array( $options[ $provider ] ) ) {
			$options[ $provider ] = [];
		}
		if ( ! isset( $options[ $provider ][ $model ] ) || ! is_array( $options[ $provider ][ $model ] ) ) {
			$options[ $provider ][ $model ] = [];
		}

		$options[ $provider ][ $model ][ $option_key ] = time();
		update_option( self::UNSUPPORTED_OPTIONS_OPTION, $options, false );
	}

	/**
	 * Get a user-facing generation option label.
	 *
	 * @param string $option_key Generation setting key.
	 */
	public function get_generation_option_label( string $option_key ): string {
		switch ( $this->normalize_generation_option_key( $option_key ) ) {
			case 'temperature':
				return __( 'Temperature', 'clawpress' );
			case 'top_p':
				return __( 'Top P', 'clawpress' );
			case 'max_output_tokens':
				return __( 'Max Output Tokens', 'clawpress' );
			case 'frequency_penalty':
				return __( 'Frequency Penalty', 'clawpress' );
			case 'presence_penalty':
				return __( 'Presence Penalty', 'clawpress' );
		}

		return $option_key;
	}

	/**
	 * Resolve model metadata from the AI client registry.
	 *
	 * @param string $provider Provider identifier.
	 * @param string $model Model identifier.
	 */
	private function resolve_model_metadata( string $provider, string $model ): ?object {
		if ( '' === $provider || '' === $model ) {
			return null;
		}

		try {
			$selected_model = AiClient::defaultRegistry()->getProviderModel(
				$provider,
				$model,
				ModelConfig::fromArray( [] )
			);
		} catch ( Throwable $throwable ) {
			unset( $throwable );
			return null;
		}

		if ( ! is_object( $selected_model ) || ! method_exists( $selected_model, 'metadata' ) ) {
			return null;
		}

		$metadata = $selected_model->metadata();
		return is_object( $metadata ) ? $metadata : null;
	}

	/**
	 * Normalize a metadata option name for comparison.
	 *
	 * @param mixed $option_name Metadata option enum/string.
	 */
	private function normalize_metadata_option_name( $option_name ): string {
		return strtolower( preg_replace( '/[^a-z0-9]/i', '', $this->get_metadata_option_value( $option_name ) ) ?? '' );
	}

	/**
	 * Read a metadata option enum/string value.
	 *
	 * @param mixed $option_name Metadata option enum/string.
	 */
	private function get_metadata_option_value( $option_name ): string {
		if ( is_string( $option_name ) ) {
			return trim( $option_name );
		}

		if ( is_object( $option_name ) ) {
			try {
				if ( isset( $option_name->value ) && is_string( $option_name->value ) ) {
					return trim( $option_name->value );
				}
			} catch ( Throwable $throwable ) {
				unset( $throwable );
			}
		}

		return '';
	}

	/**
	 * Normalize a provider parameter name into a generation setting key.
	 *
	 * @param string $parameter_name Provider parameter name.
	 */
	private function normalize_generation_parameter_name( string $parameter_name ): string {
		$parameter_name = trim( $parameter_name );
		if ( '' === $parameter_name ) {
			return '';
		}

		if ( isset( self::PARAMETER_ALIASES[ $parameter_name ] ) ) {
			return self::PARAMETER_ALIASES[ $parameter_name ];
		}

		$normalized = strtolower( preg_replace( '/[^a-z0-9]/i', '', $parameter_name ) ?? '' );
		return self::PARAMETER_ALIASES[ $normalized ] ?? '';
	}

	/**
	 * Normalize a generation setting key.
	 *
	 * @param string $option_key Generation setting key.
	 */
	private function normalize_generation_option_key( string $option_key ): string {
		$option_key = strtolower( sanitize_key( sanitize_text_field( $option_key ) ) );
		return isset( self::GENERATION_OPTION_NAMES[ $option_key ] ) ? $option_key : '';
	}

	/**
	 * Get learned unsupported options from storage.
	 *
	 * @return array<string,array<string,array<string,int>>>
	 */
	private function get_learned_unsupported_options(): array {
		$options = get_option( self::UNSUPPORTED_OPTIONS_OPTION, [] );
		return is_array( $options ) ? $options : [];
	}

	/**
	 * Check whether a metadata object supports an option.
	 *
	 * @param object $metadata Model metadata.
	 * @param string $option_name AI client metadata option name.
	 * @param mixed  $value Optional value to validate.
	 */
	private function metadata_object_supports_option( object $metadata, string $option_name, $value = null ): ?bool {
		if ( ! method_exists( $metadata, 'getSupportedOptions' ) ) {
			return null;
		}

		$supported_options = $metadata->getSupportedOptions();
		if ( ! is_array( $supported_options ) ) {
			return null;
		}

		$normalized_target = $this->normalize_metadata_option_name( $option_name );
		foreach ( $supported_options as $supported_option ) {
			if ( ! is_object( $supported_option ) || ! method_exists( $supported_option, 'getName' ) ) {
				continue;
			}

			$supported_name = $this->normalize_metadata_option_name( $supported_option->getName() );
			if ( $normalized_target !== $supported_name ) {
				continue;
			}

			if ( null !== $value && method_exists( $supported_option, 'isSupportedValue' ) ) {
				return (bool) $supported_option->isSupportedValue( $value );
			}

			return true;
		}

		return false;
	}

	/**
	 * Get supported option names from a metadata object.
	 *
	 * @param object $metadata Model metadata.
	 * @return array<int,string>
	 */
	private function get_supported_option_names_from_metadata( object $metadata ): array {
		if ( ! method_exists( $metadata, 'getSupportedOptions' ) ) {
			return [];
		}

		$supported_options = $metadata->getSupportedOptions();
		if ( ! is_array( $supported_options ) ) {
			return [];
		}

		$option_names = [];
		foreach ( $supported_options as $supported_option ) {
			if ( ! is_object( $supported_option ) || ! method_exists( $supported_option, 'getName' ) ) {
				continue;
			}

			$option_name = $this->get_metadata_option_value( $supported_option->getName() );
			if ( '' !== $option_name ) {
				$option_names[] = $option_name;
			}
		}

		return array_values( array_unique( $option_names ) );
	}
}

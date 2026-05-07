<?php
/**
 * /test command handler.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Commands\Handlers;

use ClawPress\Commands\Command_Handler;
use ClawPress\Commands\Command_Request;
use ClawPress\Commands\Command_Response;
use ClawPress\Helpers\Model_Option_Helper;
use ClawPress\Helpers\Provider_Helper;
use ClawPress\Helpers\Settings_Helper;
use Throwable;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\Models\DTO\ModelConfig;

defined( 'ABSPATH' ) || exit;

/**
 * Connection test command.
 */
final class Test_Command_Handler implements Command_Handler {
	/**
	 * Settings helper.
	 *
	 * @var Settings_Helper
	 */
	private Settings_Helper $settings_helper;

	/**
	 * Provider helper.
	 *
	 * @var Provider_Helper
	 */
	private Provider_Helper $provider_helper;

	/**
	 * Model option helper.
	 *
	 * @var Model_Option_Helper
	 */
	private Model_Option_Helper $model_option_helper;

	/**
	 * Constructor.
	 *
	 * @param Settings_Helper $settings_helper Settings helper.
	 * @param Provider_Helper $provider_helper Provider helper.
	 */
	public function __construct( Settings_Helper $settings_helper, Provider_Helper $provider_helper ) {
		$this->settings_helper     = $settings_helper;
		$this->provider_helper     = $provider_helper;
		$this->model_option_helper = Model_Option_Helper::get_instance();
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_command(): string {
		return '/test';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return __( 'Test the saved provider and model connection.', 'clawpress' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_usage(): string {
		return '/test';
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_destructive(): bool {
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_default_suggestions(): array {
		return [ '/test' ];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Command_Request $request Parsed request.
	 */
	public function handle( Command_Request $request ): Command_Response {
		if ( '' !== $request->get_argument( 0 ) ) {
			return Command_Response::error(
				__( 'Invalid usage. No arguments expected.', 'clawpress' ),
				$this->get_command(),
				false,
				false,
				[],
				[ '/test', '/status', '/help' ]
			);
		}

		$settings            = $this->settings_helper->get_settings();
		$saved_provider      = isset( $settings['provider'] ) ? clawpress_sanitize_provider( $settings['provider'] ) : '';
		$saved_model         = $this->provider_helper->resolve_model( $settings );
		$configured_provider = $this->provider_helper->resolve_provider_from_settings( $settings );
		$request_timeout     = $this->settings_helper->get_request_timeout( $settings );
		$generation_settings = $this->settings_helper->get_generation_settings( $settings );

		if ( '' === $saved_provider ) {
			return Command_Response::error(
				__( 'No provider is saved. Set one with `/settings provider <provider>`.', 'clawpress' ),
				$this->get_command(),
				false,
				false,
				[],
				[ '/status', '/help' ]
			);
		}

		if ( '' === $saved_model ) {
			return Command_Response::error(
				__( 'No model is saved. Set one with `/settings model <model>`.', 'clawpress' ),
				$this->get_command(),
				false,
				false,
				[],
				[ '/status', '/help' ]
			);
		}

		if ( '' === $configured_provider ) {
			return Command_Response::error(
				sprintf(
					/* translators: %s: provider slug */
					__( 'Saved provider `%s` is not configured with valid credentials.', 'clawpress' ),
					$saved_provider
				),
				$this->get_command(),
				false,
				false,
				[],
				[ '/status', '/help' ]
			);
		}

		try {
			$request_options = new RequestOptions();
			$request_options->setTimeout( (float) $request_timeout );

			$reply = trim(
				$this->generate_text_with_explicit_model_fallback(
					'Reply with exactly: OK',
					$configured_provider,
					$saved_model,
					$request_options,
					$generation_settings
				)
			);

			if ( '' === $reply ) {
				return Command_Response::error(
					__( 'Connection test failed: empty response from provider.', 'clawpress' ),
					$this->get_command(),
					false,
					false,
					[],
					[ '/status', '/help' ]
				);
			}

			return Command_Response::success(
				implode(
					"\n",
					[
						__( 'Connection test succeeded.', 'clawpress' ),
						sprintf(
							/* translators: %s: provider ID or slug */
							__( '- Provider: %s', 'clawpress' ),
							$configured_provider
						),
						sprintf(
							/* translators: %s: model identifier */
							__( '- Model: %s', 'clawpress' ),
							$saved_model
						),
						sprintf(
							/* translators: %s: short provider reply */
							__( '- Reply: %s', 'clawpress' ),
							sanitize_text_field( $reply )
						),
						$this->get_tool_call_support_line( $configured_provider, $saved_model ),
					]
				),
				$this->get_command(),
				false,
				false,
				[],
				[ '/status', '/help' ]
			);
		} catch ( Throwable $throwable ) {
			$message = trim( sanitize_text_field( $throwable->getMessage() ) );
			if ( '' === $message ) {
				$message = __( 'Unknown provider error.', 'clawpress' );
			}

			return Command_Response::error(
				sprintf(
					/* translators: %s: provider error message */
					__( 'Connection test failed: %s', 'clawpress' ),
					$message
				),
				$this->get_command(),
				false,
				false,
				[],
				[ '/status', '/help' ]
			);
		}
	}

	/**
	 * Generate text and retry once with explicit model binding when metadata matching fails.
	 *
	 * @param string              $prompt Prompt text.
	 * @param string              $provider Provider identifier.
	 * @param string              $model Model identifier.
	 * @param RequestOptions      $request_options Request options.
	 * @param array<string,mixed> $generation_settings Generation settings.
	 */
	private function generate_text_with_explicit_model_fallback(
		string $prompt,
		string $provider,
		string $model,
		RequestOptions $request_options,
		array $generation_settings
	): string {
		$use_explicit_model          = false;
		$retried_unsupported_options = [];
		$last_throwable              = null;

		for ( $attempt = 0; $attempt < 8; $attempt++ ) {
			$builder = $this->build_prompt_builder(
				$prompt,
				$provider,
				$model,
				$request_options,
				$generation_settings,
				$use_explicit_model
			);

			try {
				return $builder->generateText();
			} catch ( Throwable $throwable ) {
				$last_throwable      = $throwable;
				$unsupported_option = $this->model_option_helper->record_unsupported_parameter_from_error(
					$provider,
					$model,
					$throwable
				);

				if ( null !== $unsupported_option && ! in_array( $unsupported_option, $retried_unsupported_options, true ) ) {
					$retried_unsupported_options[] = $unsupported_option;
					continue;
				}

				if ( ! $use_explicit_model && $this->should_retry_with_explicit_model( $throwable, $provider, $model ) ) {
					$use_explicit_model = true;
					continue;
				}

				throw $throwable;
			}
		}

		if ( $last_throwable instanceof Throwable ) {
			throw $last_throwable;
		}

		throw new \RuntimeException( __( 'AI generation failed.', 'clawpress' ) );
	}

	/**
	 * Build prompt builder for test connection checks.
	 *
	 * @param string              $prompt Prompt text.
	 * @param string              $provider Provider identifier.
	 * @param string              $model Model identifier.
	 * @param RequestOptions      $request_options Request options.
	 * @param array<string,mixed> $generation_settings Generation settings.
	 * @param bool                $use_explicit_model Whether to bind model explicitly.
	 * @return object
	 */
	private function build_prompt_builder(
		string $prompt,
		string $provider,
		string $model,
		RequestOptions $request_options,
		array $generation_settings,
		bool $use_explicit_model
	): object {
		$builder = AiClient::prompt( $prompt )
			->usingProvider( $provider )
			->usingRequestOptions( $request_options );

		if ( '' !== $model ) {
			if ( $use_explicit_model ) {
				$selected_model = AiClient::defaultRegistry()->getProviderModel(
					$provider,
					$model,
					ModelConfig::fromArray( [] )
				);
				$builder        = $builder->usingModel( $selected_model );
			} else {
				$builder = $builder->usingModelPreference( [ $provider, $model ] );
			}
		}

		return $this->apply_generation_settings( $builder, $generation_settings, $provider, $model );
	}

	/**
	 * Determine whether test command should retry with explicit model binding.
	 *
	 * @param Throwable $throwable Generation failure.
	 * @param string    $provider Provider identifier.
	 * @param string    $model Model identifier.
	 */
	private function should_retry_with_explicit_model( Throwable $throwable, string $provider, string $model ): bool {
		if ( '' === $provider || '' === $model ) {
			return false;
		}

		$error_message = strtolower( trim( sanitize_text_field( $throwable->getMessage() ) ) );
		if ( '' === $error_message ) {
			return false;
		}

		$provider_token = strtolower( sprintf( 'provider "%s"', $provider ) );

		return false !== strpos( $error_message, 'no models found' )
			&& false !== strpos( $error_message, $provider_token );
	}

	/**
	 * Build output line describing tool-call support for the selected model.
	 *
	 * @param string $provider Provider identifier.
	 * @param string $model Model identifier.
	 */
	private function get_tool_call_support_line( string $provider, string $model ): string {
		$supports_tool_calls = $this->detect_tool_call_support( $provider, $model );

		if ( true === $supports_tool_calls ) {
			return __( '- Tool calls: Supported', 'clawpress' );
		}

		if ( false === $supports_tool_calls ) {
			return __( '- Tool calls: Not supported', 'clawpress' );
		}

		return __( '- Tool calls: Unknown (model metadata unavailable)', 'clawpress' );
	}

	/**
	 * Detect whether a provider model supports function/tool calls.
	 *
	 * Returns null when metadata cannot be resolved safely.
	 *
	 * @param string $provider Provider identifier.
	 * @param string $model Model identifier.
	 */
	private function detect_tool_call_support( string $provider, string $model ): ?bool {
		return $this->model_option_helper->metadata_supports_option(
			$provider,
			$model,
			'functionDeclarations',
			true
		);
	}

	/**
	 * Apply generation settings to prompt builder, ignoring unsupported options.
	 *
	 * @param object              $builder Prompt builder instance.
	 * @param array<string,mixed> $generation_settings Settings.
	 * @param string              $provider Provider identifier.
	 * @param string              $model Model identifier.
	 * @return object
	 */
	private function apply_generation_settings( object $builder, array $generation_settings, string $provider, string $model ): object {
		$setters = [
			fn ( object $current ): object => $this->apply_temperature( $current, (float) $generation_settings['temperature'], $provider, $model ),
			fn ( object $current ): object => $this->apply_top_p( $current, (float) $generation_settings['top_p'], $provider, $model ),
			fn ( object $current ): object => $this->apply_max_output_tokens( $current, (int) $generation_settings['max_output_tokens'], $provider, $model ),
			fn ( object $current ): object => $this->apply_frequency_penalty( $current, (float) $generation_settings['frequency_penalty'], $provider, $model ),
			fn ( object $current ): object => $this->apply_presence_penalty( $current, (float) $generation_settings['presence_penalty'], $provider, $model ),
		];

		foreach ( $setters as $setter ) {
			try {
				$builder = $setter( $builder );
			} catch ( Throwable $throwable ) {
				unset( $throwable );
				continue;
			}
		}

		return $builder;
	}

	/**
	 * Apply max output token setting to prompt builder.
	 *
	 * @param object $builder Prompt builder instance.
	 * @param int    $max_output_tokens Max output tokens.
	 * @param string $provider Provider identifier.
	 * @param string $model Model identifier.
	 * @return object
	 */
	private function apply_max_output_tokens( object $builder, int $max_output_tokens, string $provider, string $model ): object {
		if ( ! $this->model_option_helper->supports_generation_option( $provider, $model, 'max_output_tokens', $max_output_tokens ) ) {
			return $builder;
		}

		return $builder->usingMaxTokens( $max_output_tokens );
	}

	/**
	 * Apply temperature when supported by provider/model.
	 *
	 * @param object $builder Prompt builder instance.
	 * @param float  $temperature Temperature value.
	 * @param string $provider Provider identifier.
	 * @param string $model Model identifier.
	 * @return object
	 */
	private function apply_temperature( object $builder, float $temperature, string $provider, string $model ): object {
		if ( ! $this->model_option_helper->supports_generation_option( $provider, $model, 'temperature', $temperature ) ) {
			return $builder;
		}

		return $builder->usingTemperature( $temperature );
	}

	/**
	 * Apply top-p sampling when supported by provider/model.
	 *
	 * @param object $builder Prompt builder instance.
	 * @param float  $top_p Top-p value.
	 * @param string $provider Provider identifier.
	 * @param string $model Model identifier.
	 * @return object
	 */
	private function apply_top_p( object $builder, float $top_p, string $provider, string $model ): object {
		if ( ! $this->model_option_helper->supports_generation_option( $provider, $model, 'top_p', $top_p ) ) {
			return $builder;
		}

		return $builder->usingTopP( $top_p );
	}

	/**
	 * Apply frequency penalty when supported by provider/model.
	 *
	 * @param object $builder Prompt builder instance.
	 * @param float  $frequency_penalty Frequency penalty.
	 * @param string $provider Provider identifier.
	 * @param string $model Model identifier.
	 * @return object
	 */
	private function apply_frequency_penalty( object $builder, float $frequency_penalty, string $provider, string $model ): object {
		if ( ! $this->model_option_helper->supports_generation_option( $provider, $model, 'frequency_penalty', $frequency_penalty ) ) {
			return $builder;
		}

		return $builder->usingFrequencyPenalty( $frequency_penalty );
	}

	/**
	 * Apply presence penalty when supported by provider/model.
	 *
	 * @param object $builder Prompt builder instance.
	 * @param float  $presence_penalty Presence penalty.
	 * @param string $provider Provider identifier.
	 * @param string $model Model identifier.
	 * @return object
	 */
	private function apply_presence_penalty( object $builder, float $presence_penalty, string $provider, string $model ): object {
		if ( ! $this->model_option_helper->supports_generation_option( $provider, $model, 'presence_penalty', $presence_penalty ) ) {
			return $builder;
		}

		return $builder->usingPresencePenalty( $presence_penalty );
	}
}

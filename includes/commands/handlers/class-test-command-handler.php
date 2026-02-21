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
	 * Constructor.
	 *
	 * @param Settings_Helper $settings_helper Settings helper.
	 * @param Provider_Helper $provider_helper Provider helper.
	 */
	public function __construct( Settings_Helper $settings_helper, Provider_Helper $provider_helper ) {
		$this->settings_helper = $settings_helper;
		$this->provider_helper = $provider_helper;
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

			$builder = AiClient::prompt( 'Reply with exactly: OK' )
				->usingProvider( $configured_provider )
				->usingModelPreference( [ $configured_provider, $saved_model ] )
				->usingRequestOptions( $request_options );
			$builder = $this->apply_generation_settings( $builder, $generation_settings, $configured_provider, $saved_model );

			$reply = trim( $builder->generateText() );

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
							/* translators: %s: provider slug */
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
			static fn ( object $current ): object => $current->usingTemperature( (float) $generation_settings['temperature'] ),
			static fn ( object $current ): object => $current->usingTopP( (float) $generation_settings['top_p'] ),
			fn ( object $current ): object => $this->apply_max_output_tokens( $current, (int) $generation_settings['max_output_tokens'], $provider, $model ),
			static fn ( object $current ): object => $current->usingFrequencyPenalty( (float) $generation_settings['frequency_penalty'] ),
			static fn ( object $current ): object => $current->usingPresencePenalty( (float) $generation_settings['presence_penalty'] ),
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
	 * Uses `max_completion_tokens` for OpenAI model families that reject
	 * legacy `max_tokens`.
	 *
	 * @param object $builder Prompt builder instance.
	 * @param int    $max_output_tokens Max output tokens.
	 * @param string $provider Provider identifier.
	 * @param string $model Model identifier.
	 * @return object
	 */
	private function apply_max_output_tokens( object $builder, int $max_output_tokens, string $provider, string $model ): object {
		if ( $this->provider_helper->should_use_max_completion_tokens( $provider, $model ) && method_exists( $builder, 'usingModelConfig' ) ) {
			$model_config = ModelConfig::fromArray(
				[
					ModelConfig::KEY_CUSTOM_OPTIONS => [
						'max_completion_tokens' => $max_output_tokens,
					],
				]
			);

			return $builder->usingModelConfig( $model_config );
		}

		return $builder->usingMaxTokens( $max_output_tokens );
	}
}

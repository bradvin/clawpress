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

		$settings              = $this->settings_helper->get_settings();
		$saved_provider        = isset( $settings['provider'] ) ? clawpress_sanitize_provider( $settings['provider'] ) : '';
		$saved_model           = $this->provider_helper->resolve_model( $settings );
		$configured_provider   = $this->provider_helper->resolve_provider_from_settings( $settings );

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
			$reply = trim(
				AiClient::prompt( 'Reply with exactly: OK' )
					->usingProvider( $configured_provider )
					->usingModelPreference( [ $configured_provider, $saved_model ] )
					->generateText()
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
}

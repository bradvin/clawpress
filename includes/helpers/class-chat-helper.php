<?php
/**
 * Chat helper.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Helpers;

use Throwable;
use WordPress\AiClient\AiClient;

defined( 'ABSPATH' ) || exit;

/**
 * Reply generation helper.
 */
final class Chat_Helper {
	/**
	 * Singleton instance.
	 *
	 * @var ?self
	 */
	private static ?self $instance = null;

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
	 */
	private function __construct() {
		$this->settings_helper = Settings_Helper::get_instance();
		$this->provider_helper = Provider_Helper::get_instance();
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
	 * Generate a model reply payload.
	 *
	 * @param string $message User message.
	 * @return array<string,mixed>
	 */
	public function generate_ai_reply( string $message ): array {
		$settings = $this->settings_helper->get_settings();
		$provider = $this->provider_helper->resolve_provider_with_fallback( $settings );
		$model    = $this->provider_helper->resolve_model( $settings );

		if ( '' === $provider ) {
			return [
				'reply'    => $this->build_offline_reply( $message ),
				'mode'     => 'offline',
				'provider' => null,
				'model'    => null,
			];
		}

		try {
			$builder = AiClient::prompt( $message )->usingProvider( $provider );
			if ( '' !== $model ) {
				$builder = $builder->usingModelPreference( [ $provider, $model ] );
			}

			$reply = trim( $builder->generateText() );
			if ( '' === $reply ) {
				return [
					'reply'    => $this->build_offline_reply( $message ),
					'mode'     => 'offline',
					'provider' => $provider,
					'model'    => '' !== $model ? $model : null,
				];
			}

			return [
				'reply'    => $reply,
				'mode'     => 'online',
				'provider' => $provider,
				'model'    => '' !== $model ? $model : null,
			];
		} catch ( Throwable $throwable ) {
			unset( $throwable );

			return [
				'reply'    => $this->build_offline_reply( $message ),
				'mode'     => 'offline',
				'provider' => $provider,
				'model'    => '' !== $model ? $model : null,
			];
		}
	}

	/**
	 * Build deterministic offline fallback response.
	 *
	 * @param string $message User message.
	 */
	public function build_offline_reply( string $message ): string {
		return sprintf(
			'Offline mode: no configured AI provider was available. You said: "%s"',
			$message
		);
	}
}

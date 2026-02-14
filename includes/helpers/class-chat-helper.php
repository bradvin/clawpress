<?php
/**
 * Chat helper.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Helpers;

use ClawPress\Commands\Commands;
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
				'reply'       => $this->build_offline_reply( $message ),
				'mode'        => 'offline',
				'provider'    => null,
				'model'       => null,
				'suggestions' => $this->get_default_offline_suggestions(),
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
					'reply'       => $this->build_offline_reply( $message ),
					'mode'        => 'offline',
					'provider'    => $provider,
					'model'       => '' !== $model ? $model : null,
					'suggestions' => $this->get_default_offline_suggestions(),
				];
			}

			return [
				'reply'       => $reply,
				'mode'        => 'online',
				'provider'    => $provider,
				'model'       => '' !== $model ? $model : null,
				'suggestions' => $this->get_online_suggestions( $reply, $provider, $model ),
			];
		} catch ( Throwable $throwable ) {
			unset( $throwable );

			return [
				'reply'       => $this->build_offline_reply( $message ),
				'mode'        => 'offline',
				'provider'    => $provider,
				'model'       => '' !== $model ? $model : null,
				'suggestions' => $this->get_default_offline_suggestions(),
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

	/**
	 * Get default offline command suggestions.
	 *
	 * @return array<int,string>
	 */
	private function get_default_offline_suggestions(): array {
		return ( new Commands() )->get_default_suggestions();
	}

	/**
	 * Resolve online suggestions from provider output via filter hook.
	 *
	 * @param string $reply Generated reply text.
	 * @param string $provider Provider identifier.
	 * @param string $model Model identifier.
	 * @return array<int,string>
	 */
	private function get_online_suggestions( string $reply, string $provider, string $model ): array {
		$suggestions = apply_filters(
			'clawpress_ai_suggestions',
			[],
			[
				'reply'    => $reply,
				'provider' => $provider,
				'model'    => $model,
			]
		);

		if ( ! is_array( $suggestions ) ) {
			return [];
		}

		$normalized = array_values(
			array_filter(
				array_map(
					static fn ( $suggestion ): string => trim( (string) $suggestion ),
					$suggestions
				),
				static fn ( string $suggestion ): bool => '' !== $suggestion
			)
		);

		return array_slice( $normalized, 0, 8 );
	}
}

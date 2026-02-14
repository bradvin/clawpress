<?php
/**
 * Status helper.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Shared status payload helper.
 */
final class Status_Helper {
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
	 * Onboarding helper.
	 *
	 * @var Onboarding_Helper
	 */
	private Onboarding_Helper $onboarding_helper;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->settings_helper   = Settings_Helper::get_instance();
		$this->provider_helper   = Provider_Helper::get_instance();
		$this->onboarding_helper = Onboarding_Helper::get_instance();
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
	 * Build status endpoint payload.
	 *
	 * @return array<string,mixed>
	 */
	public function build_status_payload(): array {
		$settings            = $this->settings_helper->get_settings();
		$provider_id         = $this->provider_helper->resolve_provider_from_settings( $settings );
		$model_id            = $this->provider_helper->resolve_model( $settings );
		$provider_configured = '' !== $provider_id && $this->provider_helper->has_provider_credentials( $provider_id );
		$execution_user_id   = $this->settings_helper->resolve_execution_user_id( $settings );

		return [
			'mode'           => $provider_configured ? 'online' : 'offline',
			'provider'       => [
				'id'         => '' !== $provider_id ? $provider_id : null,
				'configured' => $provider_configured,
			],
			'model'          => [
				'id'         => '' !== $model_id ? $model_id : null,
				'configured' => '' !== $model_id,
			],
			'onboarding'     => [
				'completed' => $this->onboarding_helper->is_onboarding_complete(),
			],
			'memory'         => [
				'enabled' => $this->settings_helper->get_memory_enabled(),
			],
			'execution_user' => [
				'id'         => $execution_user_id > 0 ? $execution_user_id : null,
				'configured' => $execution_user_id > 0,
			],
		];
	}

	/**
	 * Build status-derived settings payload.
	 *
	 * @param array<string,mixed> $settings Current settings.
	 * @return array<string,mixed>
	 */
	public function build_status_settings_payload( array $settings ): array {
		$execution_user_id = isset( $settings['execution_user_id'] )
			? clawpress_sanitize_int( $settings['execution_user_id'] )
			: $this->settings_helper->get_execution_user_id_option();
		$provider          = $this->provider_helper->resolve_provider_from_settings( $settings );
		$model             = $this->provider_helper->resolve_model( $settings );

		return [
			'provider'             => $provider,
			'model'                => $model,
			'execution_user_id'    => $execution_user_id > 0 ? $execution_user_id : 0,
			'memory_enabled'       => $this->settings_helper->get_memory_enabled(),
			'onboarding_completed' => $this->onboarding_helper->is_onboarding_complete(),
		];
	}
}

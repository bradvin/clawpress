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
	 * Returns current status.
	 *
	 * @return array<string,mixed>
	 */
	public function get_current_status(): array {
		$settings    = $this->settings_helper->get_settings();
		$provider_id = $this->provider_helper->resolve_provider_from_settings( $settings );
		$model_id    = $this->provider_helper->resolve_model( $settings );
		$mode        = ( '' !== $provider_id && '' !== $model_id ) ? 'online' : 'offline';
		$execution   = $this->settings_helper->resolve_execution_user_id( $settings );

		return [
			'mode' => $mode,
			'provider' => [
				'id'         => '' !== $provider_id ? $provider_id : null,
				'configured' => '' !== $provider_id,
			],
			'model' => [
				'id'         => '' !== $model_id ? $model_id : null,
				'configured' => '' !== $model_id,
			],
			'memory' => [
				'enabled' => $this->settings_helper->get_memory_enabled( $settings ),
			],
			'onboarding' => [
				'completed' => $this->settings_helper->get_onboarding_completed( $settings ),
			],
			'execution_user' => [
				'id'         => $execution > 0 ? $execution : null,
				'configured' => $execution > 0,
			],
			'permissions' => [
				'can_manage_options' => current_user_can( 'manage_options' ),
			],
			'plugin' => [
				'version' => defined( 'CLAWPRESS_VERSION' ) ? (string) CLAWPRESS_VERSION : 'unknown',
			],
		];
	}
}

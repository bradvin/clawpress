<?php
/**
 * Settings helper.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Shared settings helper.
 */
final class Settings_Helper {
	/**
	 * Settings option key.
	 */
	public const SETTINGS_OPTION = 'clawpress_settings';

	/**
	 * Memory enabled option key.
	 */
	public const MEMORY_ENABLED_OPTION = 'clawpress_memory_enabled';

	/**
	 * Execution user option key.
	 */
	public const EXECUTION_USER_OPTION = 'clawpress_execution_user_id';

	/**
	 * Legacy onboarding option name.
	 */
	private const ONBOARDING_OPTION = 'clawpress_onboarding_completed';

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
	 * Get typed settings array.
	 *
	 * @return array<string,mixed>
	 */
	public function get_settings(): array {
		$settings = get_option( self::SETTINGS_OPTION, [] );
		return is_array( $settings ) ? $settings : [];
	}

	/**
	 * Get raw settings option.
	 *
	 * @return mixed
	 */
	public function get_raw_settings_option() {
		return get_option( self::SETTINGS_OPTION, '' );
	}

	/**
	 * Persist settings option.
	 *
	 * @param array<string,mixed> $settings Settings array.
	 */
	public function update_settings( array $settings ): void {
		update_option( self::SETTINGS_OPTION, $settings );
	}

	/**
	 * Get memory enabled flag.
	 */
	public function get_memory_enabled(): bool {
		return (bool) get_option( self::MEMORY_ENABLED_OPTION, false );
	}

	/**
	 * Persist memory enabled flag.
	 *
	 * @param bool $enabled Whether memory is enabled.
	 */
	public function set_memory_enabled( bool $enabled ): void {
		update_option( self::MEMORY_ENABLED_OPTION, $enabled );
	}

	/**
	 * Get execution user from fallback option.
	 */
	public function get_execution_user_id_option(): int {
		$user_id = (int) get_option( self::EXECUTION_USER_OPTION, 0 );
		return $user_id > 0 ? $user_id : 0;
	}

	/**
	 * Persist execution user fallback option.
	 *
	 * @param int $user_id Execution user ID.
	 */
	public function set_execution_user_id_option( int $user_id ): void {
		update_option( self::EXECUTION_USER_OPTION, clawpress_sanitize_int( $user_id ) );
	}

	/**
	 * Resolve execution user from settings first and option fallback second.
	 *
	 * @param array<string,mixed>|null $settings Optional settings array.
	 */
	public function resolve_execution_user_id( ?array $settings = null ): int {
		$settings         = is_array( $settings ) ? $settings : $this->get_settings();
		$settings_user_id = isset( $settings['execution_user_id'] )
			? clawpress_sanitize_int( $settings['execution_user_id'] )
			: 0;
		if ( $settings_user_id > 0 ) {
			return $settings_user_id;
		}

		return $this->get_execution_user_id_option();
	}

	/**
	 * Update a legacy named setting.
	 *
	 * @param string $option_name Setting option name.
	 * @param mixed  $option_value Setting value.
	 * @return array<string,mixed>
	 */
	public function update_named_setting( string $option_name, $option_value ): array {
		if ( ! $this->is_valid_named_setting( $option_name ) ) {
			return [
				'error' => 'Invalid option name',
			];
		}

		if ( self::ONBOARDING_OPTION === $option_name ) {
			$normalized = clawpress_sanitize_boolean( $option_value );
			Onboarding_Helper::get_instance()->set_onboarding_complete( $normalized );

			return [
				'success' => true,
				'option'  => $option_name,
				'value'   => $normalized,
			];
		}

		if ( self::EXECUTION_USER_OPTION === $option_name ) {
			$normalized = clawpress_sanitize_int( $option_value );
			$this->set_execution_user_id_option( $normalized );

			$settings                      = $this->get_settings();
			$settings['execution_user_id'] = $normalized;
			$this->update_settings( $settings );

			return [
				'success' => true,
				'option'  => $option_name,
				'value'   => $normalized,
			];
		}

		if ( self::MEMORY_ENABLED_OPTION === $option_name ) {
			$normalized = clawpress_sanitize_boolean( $option_value );
			$this->set_memory_enabled( $normalized );

			return [
				'success' => true,
				'option'  => $option_name,
				'value'   => $normalized,
			];
		}

		$normalized = $this->normalize_settings_option_value( $option_value );
		update_option( self::SETTINGS_OPTION, $normalized );

		return [
			'success' => true,
			'option'  => $option_name,
			'value'   => $normalized,
		];
	}

	/**
	 * Whether a named setting can be updated by the legacy endpoint path.
	 *
	 * @param string $option_name Option name.
	 */
	private function is_valid_named_setting( string $option_name ): bool {
		$allowed_options = [
			self::SETTINGS_OPTION,
			self::MEMORY_ENABLED_OPTION,
			self::EXECUTION_USER_OPTION,
			self::ONBOARDING_OPTION,
		];

		return in_array( $option_name, $allowed_options, true );
	}

	/**
	 * Normalize a value for the main settings option.
	 *
	 * @param mixed $value Raw value.
	 * @return mixed
	 */
	private function normalize_settings_option_value( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		if ( isset( $value['provider'] ) ) {
			$value['provider'] = clawpress_sanitize_provider( $value['provider'] );
		}

		if ( isset( $value['model'] ) ) {
			$value['model'] = sanitize_text_field( (string) $value['model'] );
		}

		if ( isset( $value['execution_user_id'] ) ) {
			$value['execution_user_id'] = clawpress_sanitize_int( $value['execution_user_id'] );
			$this->set_execution_user_id_option( $value['execution_user_id'] );
		}

		return $value;
	}
}

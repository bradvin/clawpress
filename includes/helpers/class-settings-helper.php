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
	 * Registered settings schema.
	 *
	 * @var array<string,array<string,mixed>>
	 */
	private const SETTINGS = [
		'provider'            => [
			'default'  => '',
			'sanitize' => 'clawpress_sanitize_provider',
		],
		'model'               => [
			'default'  => '',
			'sanitize' => 'sanitize_text_field',
		],
		'temperature'         => [
			'default'  => 0.2,
			'sanitize' => 'clawpress_sanitize_temperature',
		],
		'top_p'               => [
			'default'  => 0.9,
			'sanitize' => 'clawpress_sanitize_top_p',
		],
		'max_output_tokens'   => [
			'default'  => 1200,
			'sanitize' => 'clawpress_sanitize_max_output_tokens',
		],
		'frequency_penalty'   => [
			'default'  => 0.2,
			'sanitize' => 'clawpress_sanitize_frequency_penalty',
		],
		'presence_penalty'    => [
			'default'  => 0.0,
			'sanitize' => 'clawpress_sanitize_presence_penalty',
		],
		'request_timeout'     => [
			'default'  => 45,
			'sanitize' => 'clawpress_sanitize_request_timeout',
		],
		'agent_user_id'   => [
			'default'  => 0,
			'sanitize' => 'clawpress_sanitize_int',
		],
		'memory_enabled'  => [
			'default'  => false,
			'sanitize' => 'clawpress_sanitize_boolean',
		],
		'setup_completed' => [
			'default'  => false,
			'sanitize' => 'clawpress_sanitize_boolean',
		],
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
	 * Get typed settings array.
	 *
	 * @return array<string,mixed>
	 */
	public function get_settings(): array {
		$settings = get_option( self::SETTINGS_OPTION, [] );
		if ( ! is_array( $settings ) ) {
			return $this->get_default_settings();
		}

		return $this->normalize_settings_array( $settings );
	}

	/**
	 * Get supported settings keys.
	 *
	 * @return array<int,string>
	 */
	public function get_supported_setting_keys(): array {
		return array_keys( self::SETTINGS );
	}

	/**
	 * Update settings by applying a partial or full update payload.
	 *
	 * @param array<string,mixed> $updates Update payload.
	 * @return array<string,mixed>
	 */
	public function update_settings( array $updates ): array {
		$settings   = $this->get_settings();
		$has_update = false;

		foreach ( self::SETTINGS as $setting_key => $schema ) {
			if ( ! array_key_exists( $setting_key, $updates ) || null === $updates[ $setting_key ] ) {
				continue;
			}

			$settings[ $setting_key ] = $this->sanitize_setting_value( $setting_key, $updates[ $setting_key ] );
			$has_update               = true;
		}

		if ( ! $has_update ) {
			return [
				'error' => __( 'No settings provided', 'clawpress' ),
			];
		}

		$normalized = $this->normalize_settings_array( $settings );
		update_option( self::SETTINGS_OPTION, $normalized );

		return [
			'success'  => true,
			'settings' => $normalized,
		];
	}

	/**
	 * Resolve configured agent user ID.
	 *
	 * @param array<string,mixed>|null $settings Optional settings array.
	 */
	public function resolve_agent_user_id( ?array $settings = null ): int {
		$settings = is_array( $settings ) ? $this->normalize_settings_array( $settings ) : $this->get_settings();
		return (int) $settings['agent_user_id'];
	}

	/**
	 * Get memory enabled flag.
	 *
	 * @param array<string,mixed>|null $settings Optional settings array.
	 */
	public function get_memory_enabled( ?array $settings = null ): bool {
		$settings = is_array( $settings ) ? $this->normalize_settings_array( $settings ) : $this->get_settings();
		return (bool) $settings['memory_enabled'];
	}

	/**
	 * Get setup completed flag.
	 *
	 * @param array<string,mixed>|null $settings Optional settings array.
	 */
	public function get_setup_completed( ?array $settings = null ): bool {
		$settings = is_array( $settings ) ? $this->normalize_settings_array( $settings ) : $this->get_settings();
		return (bool) $settings['setup_completed'];
	}

	/**
	 * Get request timeout in seconds.
	 *
	 * @param array<string,mixed>|null $settings Optional settings array.
	 */
	public function get_request_timeout( ?array $settings = null ): int {
		$settings = is_array( $settings ) ? $this->normalize_settings_array( $settings ) : $this->get_settings();
		return (int) $settings['request_timeout'];
	}

	/**
	 * Get model-generation settings.
	 *
	 * @param array<string,mixed>|null $settings Optional settings array.
	 * @return array{temperature:float,top_p:float,max_output_tokens:int,frequency_penalty:float,presence_penalty:float}
	 */
	public function get_generation_settings( ?array $settings = null ): array {
		$settings = is_array( $settings ) ? $this->normalize_settings_array( $settings ) : $this->get_settings();

		return [
			'temperature'       => (float) $settings['temperature'],
			'top_p'             => (float) $settings['top_p'],
			'max_output_tokens' => (int) $settings['max_output_tokens'],
			'frequency_penalty' => (float) $settings['frequency_penalty'],
			'presence_penalty'  => (float) $settings['presence_penalty'],
		];
	}

	/**
	 * Normalize a settings array.
	 *
	 * @param array<string,mixed> $settings Raw settings.
	 * @return array<string,mixed>
	 */
	private function normalize_settings_array( array $settings ): array {
		$normalized = $this->get_default_settings();

		foreach ( self::SETTINGS as $setting_key => $schema ) {
			if ( ! array_key_exists( $setting_key, $settings ) ) {
				continue;
			}

			$normalized[ $setting_key ] = $this->sanitize_setting_value( $setting_key, $settings[ $setting_key ] );
		}

		return $normalized;
	}

	/**
	 * Get default settings values.
	 *
	 * @return array<string,mixed>
	 */
	private function get_default_settings(): array {
		$defaults = [];

		foreach ( self::SETTINGS as $setting_key => $schema ) {
			$defaults[ $setting_key ] = $schema['default'];
		}

		return $defaults;
	}

	/**
	 * Sanitize a setting value using the configured schema callback.
	 *
	 * @param string $setting_key Setting key.
	 * @param mixed  $value Raw value.
	 * @return mixed
	 */
	private function sanitize_setting_value( string $setting_key, $value ) {
		$schema = self::SETTINGS[ $setting_key ];
		return call_user_func( $schema['sanitize'], $value );
	}
}

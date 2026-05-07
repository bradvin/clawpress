<?php
/**
 * Shared helper functions.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'clawpress_sanitize_boolean' ) ) {
	/**
	 * Sanitize a boolean value.
	 *
	 * @param mixed $value Raw value.
	 */
	function clawpress_sanitize_boolean( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		return in_array( strtolower( (string) $value ), [ '1', 'true', 'yes', 'on' ], true );
	}
}

if ( ! function_exists( 'clawpress_get_wp_version' ) ) {
	/**
	 * Get the current WordPress version string.
	 */
	function clawpress_get_wp_version(): string {
		$version = wp_get_wp_version();
		if ( is_string( $version ) ) {
			return $version;
		}

		return (string) get_bloginfo( 'version' );
	}
}

if ( ! function_exists( 'clawpress_is_supported_wp_version' ) ) {
	/**
	 * Check whether the running WordPress version is supported.
	 */
	function clawpress_is_supported_wp_version(): bool {
		$version = clawpress_get_wp_version();
		return '' !== $version && version_compare( $version, '7.0-alpha', '>=' );
	}
}

if ( ! function_exists( 'clawpress_render_minimum_wp_version_notice' ) ) {
	/**
	 * Render an admin notice when WordPress is below the minimum supported version.
	 */
	function clawpress_render_minimum_wp_version_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		/* translators: 1: minimum supported WordPress version, 2: current WordPress version. */
		$message_template = __( 'ClawPress requires WordPress %1$s or newer. You are currently running WordPress %2$s.', 'clawpress' );
		$message          = sprintf(
			$message_template,
			'7.0',
			clawpress_get_wp_version() ?: __( 'an unknown version', 'clawpress' )
		);

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html( $message )
		);
	}
}

if ( ! function_exists( 'clawpress_sanitize_int' ) ) {
	/**
	 * Sanitize an integer value.
	 *
	 * @param mixed $value Raw value.
	 */
	function clawpress_sanitize_int( $value ): int {
		$int_value = (int) $value;
		return $int_value > 0 ? $int_value : 0;
	}
}

if ( ! function_exists( 'clawpress_validate_int' ) ) {
	/**
	 * Validate an integer value.
	 *
	 * @param mixed $value Raw value.
	 */
	function clawpress_validate_int( $value ): bool {
		return is_numeric( $value ) && (int) $value >= 0;
	}
}

if ( ! function_exists( 'clawpress_sanitize_generation_float' ) ) {
	/**
	 * Sanitize a model-generation float setting.
	 *
	 * @param mixed $value Raw value.
	 * @param float $default_value Default value.
	 * @param float $min Minimum allowed value.
	 * @param float $max Maximum allowed value.
	 */
	function clawpress_sanitize_generation_float( $value, float $default_value, float $min, float $max ): float {
		if ( ! is_numeric( $value ) ) {
			return $default_value;
		}

		$normalized = (float) $value;
		if ( $normalized < $min || $normalized > $max ) {
			return $default_value;
		}

		return $normalized;
	}
}

if ( ! function_exists( 'clawpress_validate_generation_float' ) ) {
	/**
	 * Validate a model-generation float setting.
	 *
	 * @param mixed $value Raw value.
	 * @param float $min Minimum allowed value.
	 * @param float $max Maximum allowed value.
	 */
	function clawpress_validate_generation_float( $value, float $min, float $max ): bool {
		if ( ! is_numeric( $value ) ) {
			return false;
		}

		$normalized = (float) $value;
		return $normalized >= $min && $normalized <= $max;
	}
}

if ( ! function_exists( 'clawpress_sanitize_temperature' ) ) {
	/**
	 * Sanitize temperature.
	 *
	 * @param mixed $value Raw value.
	 */
	function clawpress_sanitize_temperature( $value ): float {
		return clawpress_sanitize_generation_float( $value, 0.2, 0.0, 2.0 );
	}
}

if ( ! function_exists( 'clawpress_validate_temperature' ) ) {
	/**
	 * Validate temperature.
	 *
	 * @param mixed $value Raw value.
	 */
	function clawpress_validate_temperature( $value ): bool {
		return clawpress_validate_generation_float( $value, 0.0, 2.0 );
	}
}

if ( ! function_exists( 'clawpress_sanitize_top_p' ) ) {
	/**
	 * Sanitize top-p.
	 *
	 * @param mixed $value Raw value.
	 */
	function clawpress_sanitize_top_p( $value ): float {
		return clawpress_sanitize_generation_float( $value, 0.9, 0.0, 1.0 );
	}
}

if ( ! function_exists( 'clawpress_validate_top_p' ) ) {
	/**
	 * Validate top-p.
	 *
	 * @param mixed $value Raw value.
	 */
	function clawpress_validate_top_p( $value ): bool {
		return clawpress_validate_generation_float( $value, 0.0, 1.0 );
	}
}

if ( ! function_exists( 'clawpress_sanitize_penalty' ) ) {
	/**
	 * Sanitize frequency/presence penalties.
	 *
	 * @param mixed $value Raw value.
	 * @param float $default_value Default value.
	 */
	function clawpress_sanitize_penalty( $value, float $default_value ): float {
		return clawpress_sanitize_generation_float( $value, $default_value, -2.0, 2.0 );
	}
}

if ( ! function_exists( 'clawpress_validate_penalty' ) ) {
	/**
	 * Validate frequency/presence penalties.
	 *
	 * @param mixed $value Raw value.
	 */
	function clawpress_validate_penalty( $value ): bool {
		return clawpress_validate_generation_float( $value, -2.0, 2.0 );
	}
}

if ( ! function_exists( 'clawpress_sanitize_frequency_penalty' ) ) {
	/**
	 * Sanitize frequency penalty.
	 *
	 * @param mixed $value Raw value.
	 */
	function clawpress_sanitize_frequency_penalty( $value ): float {
		return clawpress_sanitize_penalty( $value, 0.2 );
	}
}

if ( ! function_exists( 'clawpress_validate_frequency_penalty' ) ) {
	/**
	 * Validate frequency penalty.
	 *
	 * @param mixed $value Raw value.
	 */
	function clawpress_validate_frequency_penalty( $value ): bool {
		return clawpress_validate_penalty( $value );
	}
}

if ( ! function_exists( 'clawpress_sanitize_presence_penalty' ) ) {
	/**
	 * Sanitize presence penalty.
	 *
	 * @param mixed $value Raw value.
	 */
	function clawpress_sanitize_presence_penalty( $value ): float {
		return clawpress_sanitize_penalty( $value, 0.0 );
	}
}

if ( ! function_exists( 'clawpress_validate_presence_penalty' ) ) {
	/**
	 * Validate presence penalty.
	 *
	 * @param mixed $value Raw value.
	 */
	function clawpress_validate_presence_penalty( $value ): bool {
		return clawpress_validate_penalty( $value );
	}
}

if ( ! function_exists( 'clawpress_sanitize_max_output_tokens' ) ) {
	/**
	 * Sanitize max output tokens.
	 *
	 * @param mixed $value Raw value.
	 */
	function clawpress_sanitize_max_output_tokens( $value ): int {
		$tokens = (int) $value;

		return $tokens > 0 ? $tokens : 1200;
	}
}

if ( ! function_exists( 'clawpress_validate_max_output_tokens' ) ) {
	/**
	 * Validate max output tokens.
	 *
	 * @param mixed $value Raw value.
	 */
	function clawpress_validate_max_output_tokens( $value ): bool {
		return is_numeric( $value ) && (int) $value > 0;
	}
}

if ( ! function_exists( 'clawpress_sanitize_request_timeout' ) ) {
	/**
	 * Sanitize LLM request timeout in seconds.
	 *
	 * @param mixed $value Raw value.
	 */
	function clawpress_sanitize_request_timeout( $value ): int {
		$timeout = (int) $value;

		return $timeout > 0 ? $timeout : 45;
	}
}

if ( ! function_exists( 'clawpress_validate_request_timeout' ) ) {
	/**
	 * Validate LLM request timeout.
	 *
	 * @param mixed $value Raw value.
	 */
	function clawpress_validate_request_timeout( $value ): bool {
		return is_numeric( $value ) && (int) $value > 0;
	}
}

if ( ! function_exists( 'clawpress_sanitize_provider' ) ) {
	/**
	 * Sanitize a provider identifier.
	 *
	 * @param mixed $value Raw value.
	 */
	function clawpress_sanitize_provider( $value ): string {
		$provider = strtolower( sanitize_key( sanitize_text_field( (string) $value ) ) );
		return '' !== $provider ? $provider : '';
	}
}

if ( ! function_exists( 'clawpress_check_permissions_for_user' ) ) {
	/**
	 * Check permissions for a specific user.
	 *
	 * Filter hook: `clawpress_permissions_capability`.
	 *
	 * @param int $user_id User ID.
	 */
	function clawpress_check_permissions_for_user( int $user_id ): bool {
		$capability = apply_filters( 'clawpress_permissions_capability', 'manage_options' );

		if ( empty( $capability ) ) {
			$capability = 'manage_options';
		}

		if ( $user_id > 0 ) {
			return user_can( $user_id, $capability );
		}

		return current_user_can( $capability );
	}
}

if ( ! function_exists( 'clawpress_check_permissions' ) ) {
	/**
	 * Check permissions for the current user.
	 *
	 * Filter hook: `clawpress_permissions_capability`.
	 */
	function clawpress_check_permissions(): bool {
		return clawpress_check_permissions_for_user( (int) get_current_user_id() );
	}
}

if ( ! function_exists( 'clawpress_sanitize_multiline_text' ) ) {
	/**
	 * Sanitize multiline plain text for deterministic command responses.
	 *
	 * @param mixed $value Raw text.
	 */
	function clawpress_sanitize_multiline_text( $value ): string {
		$text = (string) $value;
		$text = str_replace( [ "\r\n", "\r" ], "\n", $text );

		$lines = array_map(
			static function ( string $line ): string {
				return sanitize_text_field( $line );
			},
			explode( "\n", $text )
		);

		return trim( implode( "\n", $lines ) );
	}
}

<?php
/**
 * Shared helper functions.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

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

if ( ! function_exists( 'clawpress_sanitize_provider' ) ) {
	/**
	 * Sanitize a provider identifier.
	 *
	 * @param mixed $value Raw value.
	 */
	function clawpress_sanitize_provider( $value ): string {
		$provider = strtolower( sanitize_text_field( (string) $value ) );
		$allowed  = [ 'openai', 'anthropic', 'google' ];

		return in_array( $provider, $allowed, true ) ? $provider : '';
	}
}

if ( ! function_exists( 'clawpress_check_permissions' ) ) {
	/**
	 * Check permissions for ClawPress routes.
	 *
	 * Filter hook: `clawpress_permissions_capaability`.
	 */
	function clawpress_check_permissions(): bool {
		$capability = 'manage_options';
		if ( function_exists( 'apply_filters' ) ) {
			$capability = (string) apply_filters( 'clawpress_permissions_capaability', $capability );
		}

		if ( '' === $capability ) {
			$capability = 'manage_options';
		}

		return current_user_can( $capability );
	}
}

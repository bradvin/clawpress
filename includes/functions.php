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

if ( ! function_exists( 'clawpress_sanitize_request_timeout' ) ) {
	/**
	 * Sanitize LLM request timeout in seconds.
	 *
	 * @param mixed $value Raw value.
	 */
	function clawpress_sanitize_request_timeout( $value ): int {
		$timeout = (int) $value;

		return $timeout > 0 ? $timeout : 30;
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
		$provider = strtolower( sanitize_text_field( (string) $value ) );
		$allowed  = [ 'openai', 'anthropic', 'google' ];

		return in_array( $provider, $allowed, true ) ? $provider : '';
	}
}

if ( ! function_exists( 'clawpress_check_permissions' ) ) {
	/**
	 * Check permissions for ClawPress routes.
	 *
	 * Filter hook: `clawpress_permissions_capability`.
	 */
	function clawpress_check_permissions(): bool {
		$capability = apply_filters( 'clawpress_permissions_capability', 'manage_options' );

		if ( empty( $capability ) ) {
			$capability = 'manage_options';
		}

		return current_user_can( $capability );
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

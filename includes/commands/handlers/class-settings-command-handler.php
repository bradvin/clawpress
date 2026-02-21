<?php
/**
 * /settings command handler.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Commands\Handlers;

use ClawPress\Commands\Command_Handler;
use ClawPress\Commands\Command_Request;
use ClawPress\Commands\Command_Response;
use ClawPress\Helpers\Settings_Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Hidden settings update command.
 */
final class Settings_Command_Handler implements Command_Handler {
	/**
	 * Settings helper.
	 *
	 * @var Settings_Helper
	 */
	private Settings_Helper $settings_helper;

	/**
	 * Constructor.
	 *
	 * @param Settings_Helper $settings_helper Settings helper.
	 */
	public function __construct( Settings_Helper $settings_helper ) {
		$this->settings_helper = $settings_helper;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_command(): string {
		return '/settings';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return __( 'Update a ClawPress setting value.', 'clawpress' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_usage(): string {
		return '/settings <key> <value>';
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
		return [];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Command_Request $request Command request.
	 */
	public function handle( Command_Request $request ): Command_Response {
		$key = trim( strtolower( $request->get_argument( 0 ) ) );
		if ( '' === $key ) {
			return Command_Response::error(
				sprintf(
					/* translators: %s: expected command usage */
					__( 'Invalid usage. Expected: `%s`', 'clawpress' ),
					$this->get_usage()
				),
				$this->get_command(),
				false,
				false,
				[],
				[ '/status', '/help' ]
			);
		}

		$supported_keys = $this->settings_helper->get_supported_setting_keys();
		if ( ! in_array( $key, $supported_keys, true ) ) {
			return Command_Response::error(
				sprintf(
					/* translators: 1: unknown key, 2: comma-separated supported keys */
					__( 'Unknown setting key `%1$s`. Supported keys: %2$s', 'clawpress' ),
					$key,
					implode( ', ', $supported_keys )
				),
				$this->get_command(),
				false,
				false,
				[],
				[ '/status', '/help' ]
			);
		}

		$arguments = $request->get_arguments();
		$value_raw = trim( implode( ' ', array_slice( $arguments, 1 ) ) );
		if ( '' === $value_raw ) {
			return Command_Response::error(
				sprintf(
					/* translators: 1: setting key, 2: expected command usage */
					__( 'Missing value for `%1$s`. Expected: `%2$s`', 'clawpress' ),
					$key,
					$this->get_usage()
				),
				$this->get_command(),
				false,
				false,
				[],
				[ '/status', '/help' ]
			);
		}

		$settings      = $this->settings_helper->get_settings();
		$parsed_result = $this->parse_value_for_key( $key, $value_raw, $settings );

		if ( isset( $parsed_result['error'] ) ) {
			return Command_Response::error(
				(string) $parsed_result['error'],
				$this->get_command(),
				false,
				false,
				[],
				[ '/status', '/help' ]
			);
		}

		$update_result = $this->settings_helper->update_settings(
			[
				$key => $parsed_result['value'],
			]
		);

		if ( isset( $update_result['error'] ) ) {
			return Command_Response::error(
				(string) $update_result['error'],
				$this->get_command(),
				false,
				false,
				[],
				[ '/status', '/help' ]
			);
		}

		$updated_settings = isset( $update_result['settings'] ) && is_array( $update_result['settings'] )
			? $update_result['settings']
			: $this->settings_helper->get_settings();
		$updated_value    = array_key_exists( $key, $updated_settings ) ? $updated_settings[ $key ] : $parsed_result['value'];

		return Command_Response::success(
			sprintf(
				/* translators: 1: setting key, 2: updated setting value */
				__( 'Updated `%1$s` to `%2$s`.', 'clawpress' ),
				$key,
				$this->stringify_value( $updated_value )
			),
			$this->get_command(),
			false,
			false,
			[],
			[ '/status', '/help' ]
		);
	}

	/**
	 * Parse incoming setting value based on existing setting type.
	 *
	 * @param string              $key Setting key.
	 * @param string              $value_raw Raw value string.
	 * @param array<string,mixed> $settings Current settings.
	 * @return array<string,mixed>
	 */
	private function parse_value_for_key( string $key, string $value_raw, array $settings ): array {
		$current_value = $settings[ $key ] ?? '';

		if ( is_bool( $current_value ) ) {
			$parsed_boolean = $this->parse_boolean_value( $value_raw );
			if ( null === $parsed_boolean ) {
				return [
					'error' => sprintf(
						/* translators: %s: setting key */
						__( 'Invalid boolean value for `%s`. Use true|false|1|0|yes|no|on|off.', 'clawpress' ),
						$key
					),
				];
			}

			return [ 'value' => $parsed_boolean ];
		}

		if ( is_int( $current_value ) ) {
			if ( ! preg_match( '/^-?\d+$/', $value_raw ) ) {
				return [
					'error' => sprintf(
						/* translators: %s: setting key */
						__( 'Invalid integer value for `%s`.', 'clawpress' ),
						$key
					),
				];
			}

			return [ 'value' => (int) $value_raw ];
		}

		return [ 'value' => $value_raw ];
	}

	/**
	 * Parse supported boolean strings.
	 *
	 * @param string $value Raw value.
	 */
	private function parse_boolean_value( string $value ): ?bool {
		$normalized = strtolower( trim( $value ) );

		if ( in_array( $normalized, [ '1', 'true', 'yes', 'on' ], true ) ) {
			return true;
		}

		if ( in_array( $normalized, [ '0', 'false', 'no', 'off' ], true ) ) {
			return false;
		}

		return null;
	}

	/**
	 * Convert setting value to printable string.
	 *
	 * @param mixed $value Setting value.
	 */
	private function stringify_value( $value ): string {
		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}

		if ( is_scalar( $value ) ) {
			return (string) $value;
		}

		$encoded_value = wp_json_encode( $value );
		return false === $encoded_value ? '' : $encoded_value;
	}
}

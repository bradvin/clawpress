<?php
/**
 * Server-side command confirmation storage.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Commands;

defined( 'ABSPATH' ) || exit;

/**
 * Per-user confirmation token store for destructive command flows.
 */
final class Command_Confirmation_Store {
	/**
	 * Option key prefix.
	 */
	private const OPTION_PREFIX = 'clawpress_command_confirmation_';

	/**
	 * Confirmation token TTL in seconds.
	 */
	private const TOKEN_TTL = 300;

	/**
	 * Issue and persist a confirmation token.
	 *
	 * @param string   $action Action identifier.
	 * @param int|null $user_id User ID.
	 * @return array{token:string,expires_at:int}
	 */
	public function issue_confirmation( string $action, ?int $user_id = null ): array {
		$record = [
			'action'     => $action,
			'token'      => $this->generate_token(),
			'expires_at' => time() + self::TOKEN_TTL,
		];

		update_option( $this->get_option_key( $user_id ), $record );

		return [
			'token'      => $record['token'],
			'expires_at' => $record['expires_at'],
		];
	}

	/**
	 * Consume and validate a confirmation token.
	 *
	 * @param string      $action Action identifier.
	 * @param string|null $token Token value.
	 * @param int|null    $user_id User ID.
	 */
	public function consume_confirmation( string $action, ?string $token, ?int $user_id = null ): bool {
		$record = get_option( $this->get_option_key( $user_id ), [] );
		$this->clear_confirmation( $user_id );

		if ( ! is_array( $record ) ) {
			return false;
		}

		if ( ! isset( $record['action'], $record['token'], $record['expires_at'] ) ) {
			return false;
		}

		if ( ! is_string( $record['action'] ) || ! is_string( $record['token'] ) || ! is_numeric( $record['expires_at'] ) ) {
			return false;
		}

		if ( $action !== $record['action'] ) {
			return false;
		}

		if ( (int) $record['expires_at'] < time() ) {
			return false;
		}

		if ( null === $token || '' === trim( $token ) ) {
			return false;
		}

		return hash_equals( $record['token'], trim( $token ) );
	}

	/**
	 * Clear pending confirmation record.
	 *
	 * @param int|null $user_id User ID.
	 */
	private function clear_confirmation( ?int $user_id = null ): void {
		update_option( $this->get_option_key( $user_id ), [] );
	}

	/**
	 * Resolve option key for user.
	 *
	 * @param int|null $user_id User ID.
	 */
	private function get_option_key( ?int $user_id = null ): string {
		$resolved_user_id = null === $user_id ? get_current_user_id() : $user_id;
		return self::OPTION_PREFIX . (int) $resolved_user_id;
	}

	/**
	 * Build an opaque confirmation token.
	 */
	private function generate_token(): string {
		try {
			return bin2hex( random_bytes( 5 ) );
		} catch ( \Throwable $throwable ) {
			unset( $throwable );
			return substr( md5( uniqid( (string) mt_rand(), true ) ), 0, 10 );
		}
	}
}

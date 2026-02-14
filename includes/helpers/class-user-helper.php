<?php
/**
 * User helper.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Shared user helper.
 *
 * Central place for ClawPress user-related operations.
 */
final class User_Helper {
	/**
	 * Agent user role.
	 */
	private const AGENT_ROLE = 'contributor';

	/**
	 * Default base login.
	 */
	private const DEFAULT_AGENT_LOGIN = 'clawpress-agent';

	/**
	 * Default display name.
	 */
	private const DEFAULT_AGENT_DISPLAY_NAME = 'ClawPress Agent';

	/**
	 * Fallback email domain.
	 */
	private const FALLBACK_EMAIL_DOMAIN = 'example.test';

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
	 * Create a dedicated contributor agent user.
	 *
	 * @param array<string,mixed> $args Optional args:
	 *                                  - user_login: preferred base login.
	 *                                  - user_email: preferred base email.
	 *                                  - display_name: preferred display name.
	 * @return array<string,mixed> Either user payload or error payload.
	 */
	public function create_agent_user( array $args = [] ): array {
		$base_login   = isset( $args['user_login'] ) ? sanitize_user( (string) $args['user_login'], true ) : self::DEFAULT_AGENT_LOGIN;
		$display_name = isset( $args['display_name'] ) ? sanitize_text_field( (string) $args['display_name'] ) : self::DEFAULT_AGENT_DISPLAY_NAME;
		$base_email   = isset( $args['user_email'] ) ? sanitize_email( (string) $args['user_email'] ) : $this->build_default_agent_email();

		if ( '' === $base_login ) {
			$base_login = self::DEFAULT_AGENT_LOGIN;
		}

		if ( '' === $display_name ) {
			$display_name = self::DEFAULT_AGENT_DISPLAY_NAME;
		}

		if ( '' === $base_email || ! is_email( $base_email ) ) {
			$base_email = $this->build_default_agent_email();
		}

		$user_login = $this->build_unique_login( $base_login );
		$user_email = $this->build_unique_email( $base_email );

		$user_id = wp_insert_user(
			[
				'user_login'   => $user_login,
				'user_pass'    => wp_generate_password( 32, true, true ),
				'user_email'   => $user_email,
				'display_name' => $display_name,
				'role'         => self::AGENT_ROLE,
			]
		);

		if ( is_wp_error( $user_id ) ) {
			return [
				'error'   => $user_id->get_error_message(),
				'code'    => $user_id->get_error_code(),
				'success' => false,
			];
		}

		return [
			'success'      => true,
			'user_id'      => (int) $user_id,
			'user_login'   => $user_login,
			'user_email'   => $user_email,
			'display_name' => $display_name,
			'role'         => self::AGENT_ROLE,
		];
	}

	/**
	 * Get a user by ID.
	 *
	 * @param int $user_id User ID.
	 * @return \WP_User|null
	 */
	public function get_user_by_id( int $user_id ): ?\WP_User {
		if ( $user_id <= 0 ) {
			return null;
		}

		$user = get_user_by( 'id', $user_id );
		return $user instanceof \WP_User ? $user : null;
	}

	/**
	 * Determine whether a user ID belongs to an existing contributor.
	 *
	 * @param int $user_id User ID.
	 */
	public function is_valid_agent_user( int $user_id ): bool {
		$user = $this->get_user_by_id( $user_id );
		if ( ! $user instanceof \WP_User ) {
			return false;
		}

		return in_array( self::AGENT_ROLE, (array) $user->roles, true );
	}

	/**
	 * Build unique user login from base value.
	 *
	 * @param string $base_login Base login.
	 */
	private function build_unique_login( string $base_login ): string {
		$base_login = sanitize_user( $base_login, true );
		if ( '' === $base_login ) {
			$base_login = self::DEFAULT_AGENT_LOGIN;
		}

		if ( ! username_exists( $base_login ) ) {
			return $base_login;
		}

		for ( $index = 2; $index < 1000; ++$index ) {
			$candidate_login = $base_login . '-' . $index;
			if ( ! username_exists( $candidate_login ) ) {
				return $candidate_login;
			}
		}

		return $base_login . '-' . wp_generate_password( 6, false, false );
	}

	/**
	 * Build unique email from base value.
	 *
	 * @param string $base_email Base email.
	 */
	private function build_unique_email( string $base_email ): string {
		$base_email = sanitize_email( $base_email );
		if ( '' === $base_email || ! is_email( $base_email ) ) {
			$base_email = $this->build_default_agent_email();
		}

		if ( ! email_exists( $base_email ) ) {
			return $base_email;
		}

		$parts = explode( '@', $base_email, 2 );
		$local = $parts[0] ?? self::DEFAULT_AGENT_LOGIN;
		$host  = $parts[1] ?? self::FALLBACK_EMAIL_DOMAIN;

		for ( $index = 2; $index < 1000; ++$index ) {
			$candidate_email = sanitize_email( $local . '+' . $index . '@' . $host );
			if ( '' !== $candidate_email && is_email( $candidate_email ) && ! email_exists( $candidate_email ) ) {
				return $candidate_email;
			}
		}

		$fallback_email = sanitize_email( self::DEFAULT_AGENT_LOGIN . '+' . wp_generate_password( 6, false, false ) . '@' . $host );
		return '' !== $fallback_email && is_email( $fallback_email ) ? $fallback_email : $this->build_default_agent_email();
	}

	/**
	 * Build default agent email based on site host.
	 */
	private function build_default_agent_email(): string {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( ! is_string( $host ) || '' === trim( $host ) ) {
			$host = self::FALLBACK_EMAIL_DOMAIN;
		}

		$candidate_email = sanitize_email( self::DEFAULT_AGENT_LOGIN . '@' . strtolower( $host ) );
		if ( '' !== $candidate_email && is_email( $candidate_email ) ) {
			return $candidate_email;
		}

		return self::DEFAULT_AGENT_LOGIN . '@' . self::FALLBACK_EMAIL_DOMAIN;
	}
}

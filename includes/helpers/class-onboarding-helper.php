<?php
/**
 * Onboarding helper.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * User onboarding state helper.
 */
final class Onboarding_Helper {
	/**
	 * User meta key for onboarding completion.
	 */
	public const USER_META_KEY = 'clawpress_onboarding_completed';

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
	 * Check onboarding completion for a user.
	 *
	 * @param int|null $user_id User ID.
	 */
	public function is_onboarding_complete( ?int $user_id = null ): bool {
		$user_id = $this->resolve_user_id( $user_id );
		$value   = get_user_meta( $user_id, self::USER_META_KEY, true );

		if ( is_bool( $value ) ) {
			return $value;
		}

		return clawpress_sanitize_boolean( $value );
	}

	/**
	 * Persist onboarding completion for a user.
	 *
	 * @param bool     $is_complete Whether onboarding is complete.
	 * @param int|null $user_id User ID.
	 */
	public function set_onboarding_complete( bool $is_complete, ?int $user_id = null ): void {
		$user_id = $this->resolve_user_id( $user_id );
		update_user_meta( $user_id, self::USER_META_KEY, $is_complete ? '1' : '0' );
	}

	/**
	 * Resolve user ID, defaulting to current user.
	 *
	 * @param int|null $user_id User ID.
	 */
	private function resolve_user_id( ?int $user_id = null ): int {
		$resolved_user_id = null === $user_id ? get_current_user_id() : $user_id;
		return $resolved_user_id > 0 ? $resolved_user_id : 0;
	}
}

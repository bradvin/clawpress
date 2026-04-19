<?php
/**
 * Security policy checks and confirmation gates.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Security;

use ClawPress\Commands\Command_Confirmation_Store;

defined( 'ABSPATH' ) || exit;

/**
 * Central policy helper for abilities and mutating actions.
 */
final class Security {
	/**
	 * Singleton instance.
	 *
	 * @var ?self
	 */
	private static ?self $instance = null;

	/**
	 * Confirmation store.
	 *
	 * @var Command_Confirmation_Store
	 */
	private Command_Confirmation_Store $confirmation_store;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->confirmation_store = new Command_Confirmation_Store();
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
	 * Determine whether a safety class requires explicit confirmation.
	 *
	 * @param string $safety_class Safety class (`read|write|destructive`).
	 */
	public function requires_confirmation_for_safety_class( string $safety_class ): bool {
		return 'destructive' === strtolower( trim( $safety_class ) );
	}

	/**
	 * Validate requesting-user access to ClawPress routes.
	 *
	 * @param int|null $requesting_user_id Optional requesting user ID.
	 * @return true|\WP_Error
	 */
	public function assert_requesting_user_allowed( ?int $requesting_user_id = null ) {
		$resolved_user_id = null === $requesting_user_id ? 0 : (int) $requesting_user_id;
		$has_access       = $resolved_user_id > 0
			? clawpress_check_permissions_for_user( $resolved_user_id )
			: clawpress_check_permissions();

		if ( ! $has_access ) {
			return new \WP_Error(
				'clawpress_requesting_user_forbidden',
				__( 'The requesting user is not allowed to use ClawPress.', 'clawpress' )
			);
		}

		return true;
	}

	/**
	 * Issue a destructive-action confirmation token.
	 *
	 * @param string   $ability_name Ability ID.
	 * @param int|null $requesting_user_id Requesting user ID.
	 * @return array{token:string,expires_at:int}
	 */
	public function issue_destructive_confirmation( string $ability_name, ?int $requesting_user_id = null ): array {
		return $this->confirmation_store->issue_confirmation(
			$this->build_confirmation_action( $ability_name ),
			$requesting_user_id
		);
	}

	/**
	 * Consume a destructive-action confirmation token.
	 *
	 * @param string      $ability_name Ability ID.
	 * @param string|null $token Confirmation token.
	 * @param int|null    $requesting_user_id Requesting user ID.
	 */
	public function consume_destructive_confirmation( string $ability_name, ?string $token, ?int $requesting_user_id = null ): bool {
		return $this->confirmation_store->consume_confirmation(
			$this->build_confirmation_action( $ability_name ),
			$token,
			$requesting_user_id
		);
	}

	/**
	 * Build confirmation action key.
	 *
	 * @param string $ability_name Ability ID.
	 */
	private function build_confirmation_action( string $ability_name ): string {
		return 'ability:' . strtolower( trim( $ability_name ) );
	}
}

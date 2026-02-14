<?php
/**
 * Panel helper.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Panel state helper.
 */
final class Panel_Helper {
	/**
	 * User meta key for panel state.
	 */
	private const USER_META_KEY = 'clawpress_panel_state';

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
	 * Get panel state for a user.
	 *
	 * @param int|null $user_id User ID.
	 * @return array<string,mixed>
	 */
	public function get_panel_state( ?int $user_id = null ): array {
		$resolved_user_id = $this->resolve_user_id( $user_id );
		$raw_state        = get_user_meta( $resolved_user_id, self::USER_META_KEY, true );

		return $this->normalize_panel_state( $raw_state );
	}

	/**
	 * Update panel state fields for a user.
	 *
	 * @param array<string,mixed> $state_updates State updates.
	 * @param int|null            $user_id User ID.
	 * @return array<string,mixed>
	 */
	public function update_panel_state( array $state_updates, ?int $user_id = null ): array {
		$resolved_user_id = $this->resolve_user_id( $user_id );
		$state            = $this->get_panel_state( $resolved_user_id );

		if ( array_key_exists( 'open', $state_updates ) ) {
			$state['open'] = clawpress_sanitize_boolean( $state_updates['open'] );
		}

		if ( array_key_exists( 'width', $state_updates ) ) {
			$state['width'] = $this->sanitize_width( $state_updates['width'] );
		}

		if ( array_key_exists( 'last_history_id', $state_updates ) ) {
			$state['last_history_id'] = sanitize_text_field( (string) $state_updates['last_history_id'] );
		}

		if ( array_key_exists( 'welcome_card_seen', $state_updates ) ) {
			$state['welcome_card_seen'] = clawpress_sanitize_boolean( $state_updates['welcome_card_seen'] );
		}

		update_user_meta( $resolved_user_id, self::USER_META_KEY, $state );

		return $state;
	}

	/**
	 * Validate width input before sanitization.
	 *
	 * @param mixed $value Width value.
	 */
	public function validate_width( $value ): bool {
		if ( ! is_numeric( $value ) ) {
			return false;
		}

		$width = (int) $value;
		return $width >= 280 && $width <= 1200;
	}

	/**
	 * Sanitize and clamp width.
	 *
	 * @param mixed $value Width value.
	 */
	public function sanitize_width( $value ): int {
		$width = (int) $value;
		if ( $width < 320 ) {
			return 320;
		}
		if ( $width > 960 ) {
			return 960;
		}

		return $width;
	}

	/**
	 * Normalize persisted state with defaults.
	 *
	 * @param mixed $state Raw state value.
	 * @return array<string,mixed>
	 */
	public function normalize_panel_state( $state ): array {
		$defaults = [
			'open'              => false,
			'width'             => 420,
			'last_history_id'   => '',
			'welcome_card_seen' => false,
		];

		if ( ! is_array( $state ) ) {
			return $defaults;
		}

		return [
			'open'              => isset( $state['open'] ) ? clawpress_sanitize_boolean( $state['open'] ) : $defaults['open'],
			'width'             => isset( $state['width'] ) ? $this->sanitize_width( $state['width'] ) : $defaults['width'],
			'last_history_id'   => isset( $state['last_history_id'] ) ? sanitize_text_field( (string) $state['last_history_id'] ) : '',
			'welcome_card_seen' => isset( $state['welcome_card_seen'] ) ? clawpress_sanitize_boolean( $state['welcome_card_seen'] ) : false,
		];
	}

	/**
	 * Resolve user ID, defaulting to current user.
	 *
	 * @param int|null $user_id User ID.
	 */
	private function resolve_user_id( ?int $user_id = null ): int {
		return null === $user_id ? get_current_user_id() : $user_id;
	}
}

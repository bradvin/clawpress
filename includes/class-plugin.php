<?php
/**
 * Core plugin bootstrap class.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress;

use ClawPress\Abilities\Abilities;
use ClawPress\AdminPage\Admin_Page;
use ClawPress\Heartbeat\Heartbeat;
use ClawPress\Helpers\Action_Log_Helper;
use ClawPress\Helpers\Panel_Helper;
use ClawPress\Panel\Panel;
use ClawPress\PostTypes\Post_Types;
use ClawPress\RestAPI\Rest_API;

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin module registry.
 */
final class Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var ?self
	 */
	private static ?self $instance = null;

	/**
	 * WordPress APIs required to initialize plugin modules.
	 *
	 * @var array<int,string>
	 */
	private const BOOT_REQUIRED_FUNCTIONS = [
		'add_action',
	];

	/**
	 * WordPress APIs required by activation path.
	 *
	 * @var array<int,string>
	 */
	private const ACTIVATION_REQUIRED_FUNCTIONS = [
		'get_current_user_id',
		'metadata_exists',
	];

	/**
	 * Initialize plugin modules.
	 */
	private function __construct() {
		self::assert_boot_contract();

		new Post_Types();
		new Abilities();
		new Rest_API();
		new Admin_Page();
		new Panel();
		new Heartbeat();

		// Initialize AI client. Goto Settings -> AI Credentials to set up.
		add_action( 'init', [ 'WordPress\AI_Client\AI_Client', 'init' ] );
	}

	/**
	 * Ensure required WordPress APIs are loaded for plugin boot.
	 *
	 * @param ?callable $has_function Function existence checker.
	 *
	 * @throws \RuntimeException When required WordPress APIs are unavailable.
	 */
	public static function assert_boot_contract( ?callable $has_function = null ): void {
		self::assert_required_functions(
			self::BOOT_REQUIRED_FUNCTIONS,
			$has_function,
			'plugin boot'
		);
	}

	/**
	 * Ensure required WordPress APIs are loaded for plugin activation.
	 *
	 * @param ?callable $has_function Function existence checker.
	 *
	 * @throws \RuntimeException When required WordPress APIs are unavailable.
	 */
	public static function assert_activation_contract( ?callable $has_function = null ): void {
		self::assert_required_functions(
			self::ACTIVATION_REQUIRED_FUNCTIONS,
			$has_function,
			'plugin activation'
		);
	}

	/**
	 * Assert required WordPress functions exist.
	 *
	 * @param array<int,string> $required_functions Required function names.
	 * @param ?callable         $has_function       Function existence checker.
	 * @param string            $context            Boot context label.
	 *
	 * @throws \RuntimeException When required WordPress APIs are unavailable.
	 */
	private static function assert_required_functions( array $required_functions, ?callable $has_function, string $context ): void {
		$function_exists = $has_function ?? 'function_exists';
		$missing         = array_values(
			array_filter(
				$required_functions,
				static fn( string $function_name ): bool => ! call_user_func( $function_exists, $function_name )
			)
		);

		if ( [] === $missing ) {
			return;
		}

		// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing diagnostic message.
		throw new \RuntimeException(
			sprintf(
				'ClawPress %1$s requires WordPress APIs that are unavailable: %2$s. Ensure WordPress is loaded before bootstrapping the plugin, and include tests/Support/WordPressStubs.php when running isolated tests.',
				$context,
				implode( ', ', $missing )
			)
		);
		// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
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
	 * Plugin activation callback.
	 */
	public static function activate(): void {
		self::assert_activation_contract();

		Action_Log_Helper::get_instance()->create_table();

		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return;
		}

		if ( ! metadata_exists( 'user', $user_id, 'clawpress_panel_state' ) ) {
			Panel_Helper::get_instance()->update_panel_state(
				[
					'open' => true,
				],
				$user_id
			);
		}
	}
}

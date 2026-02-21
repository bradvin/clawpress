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
use ClawPress\Helpers\Agent_Run_Store;
use ClawPress\Helpers\Agent_Session_Store;
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
	 * Initialize plugin modules.
	 */
	private function __construct() {
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
		Action_Log_Helper::get_instance()->create_table();
		Agent_Session_Store::get_instance()->create_table();
		Agent_Run_Store::get_instance()->create_table();

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

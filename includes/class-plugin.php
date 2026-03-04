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
use ClawPress\Helpers\Panel_Helper;
use ClawPress\Panel\Panel;
use ClawPress\PostTypes\Post_Types;
use ClawPress\RestAPI\Rest_API;
use ClawPress\Runner\Agent_Runner;
use ClawPress\Stores\Action_Log_Store;
use ClawPress\Stores\Agent_Event_Store;
use ClawPress\Stores\Agent_Run_Store;
use ClawPress\Stores\Agent_Session_Store;

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
		new Agent_Runner();

		// Initialize AI client bridge when available. Core AI integrations can work without it.
		if ( $this->can_initialize_ai_client_bridge() ) {
			add_action( 'init', [ 'WordPress\AI_Client\AI_Client', 'init' ] );
		}
	}

	/**
	 * Determine whether the AI client bridge can be safely initialized.
	 *
	 * @return bool
	 */
	private function can_initialize_ai_client_bridge(): bool {
		if ( ! class_exists( '\WordPress\AI_Client\AI_Client' ) ) {
			return false;
		}

		$prompt_capability_callback = [ '\WordPress\AI_Client\Capabilities\Capabilities_Manager', 'grant_prompt_ai_to_administrators' ];
		$list_capability_callback   = [ '\WordPress\AI_Client\Capabilities\Capabilities_Manager', 'grant_list_ai_providers_models_to_administrators' ];

		if ( ! is_callable( $prompt_capability_callback ) || ! is_callable( $list_capability_callback ) ) {
			return false;
		}

		/*
		 * WordPress 7+ provides native AI client infrastructure, so the bridge can initialize
		 * without relying on plugin-shipped SDK wiring methods.
		 */
		if ( function_exists( 'wp_has_ai_client' ) && wp_has_ai_client() ) {
			return true;
		}

		if ( ! class_exists( '\WordPress\AiClient\AiClient' ) ) {
			return false;
		}

		return method_exists( '\WordPress\AiClient\AiClient', 'setEventDispatcher' )
			&& method_exists( '\WordPress\AiClient\AiClient', 'setCache' );
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
		Action_Log_Store::get_instance()->create_table();
		Agent_Session_Store::get_instance()->create_table();
		Agent_Run_Store::get_instance()->create_table();
		Agent_Event_Store::get_instance()->create_table();

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

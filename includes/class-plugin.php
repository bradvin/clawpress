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
use ClawPress\Panel\Panel;
use ClawPress\PostTypes\Post_Types;
use ClawPress\RestAPI\Rest_API;
use WordPress\AI_Client\AI_Client;

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
		
		add_action( 'init', [ $this, 'init' ] );
	}

	/**
	 * Initialize AI client integration for available package versions.
	 */
	public function init(): void {
		// Ensure the modern php-ai-client is available.
		if ( class_exists( AI_Client::class ) ) {
			AI_Client::init();
		}
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
	}
}

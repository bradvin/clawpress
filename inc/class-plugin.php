<?php
/**
 * Core plugin bootstrap class.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress;

use ClawPress\AdminPage\Admin_Page;
use ClawPress\Heartbeat\Heartbeat;
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
	 * Prevent direct instantiation.
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
	 * Register all plugin module hooks.
	 */
	public function init(): void {
		Post_Types::init();
		Rest_API::init();
		Admin_Page::init();
		Panel::init();
		Heartbeat::init();
	}
}

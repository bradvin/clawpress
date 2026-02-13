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
	 * Register all plugin module hooks.
	 */
	public static function register(): void {
		Post_Types::register();
		Rest_API::register();
		Admin_Page::register();
		Panel::register();
		Heartbeat::register();
	}
}

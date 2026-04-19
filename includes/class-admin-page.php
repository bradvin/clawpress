<?php
/**
 * WordPress admin page registration.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\AdminPage;

use ClawPress\PostTypes\Post_Types;

defined( 'ABSPATH' ) || exit;

/**
 * Admin page module.
 */
final class Admin_Page {
	/**
	 * Main admin screen hook suffix.
	 */
	private const MAIN_HOOK_SUFFIX = 'toplevel_page_clawpress';

	/**
	 * Logs admin screen hook suffix.
	 */
	private const LOGS_HOOK_SUFFIX = 'clawpress_page_clawpress-logs';

	/**
	 * Register all hooks for the admin page.
	 */
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
		add_action( 'admin_menu', [ $this, 'ensure_agent_post_type_submenus' ], 110 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		add_action( 'admin_head', [ $this, 'render_menu_icon_styles' ] );
	}

	/**
	 * Register the ClawPress admin page.
	 */
	public function register_admin_page(): void {
		add_menu_page(
			__( 'ClawPress', 'clawpress' ),
			__( 'ClawPress', 'clawpress' ),
			'manage_options',
			'clawpress',
			[ $this, 'render_admin_page' ],
			'none',
			58
		);

		// Keep the plugin landing page available when other submenus (for example CPT screens) are added.
		remove_submenu_page( 'clawpress', 'clawpress' );
		add_submenu_page(
			'clawpress',
			__( 'ClawPress', 'clawpress' ),
			__( 'ClawPress', 'clawpress' ),
			'manage_options',
			'clawpress',
			[ $this, 'render_admin_page' ],
			0
		);

		add_submenu_page(
			'clawpress',
			__( 'Logs', 'clawpress' ),
			__( 'Logs', 'clawpress' ),
			'manage_options',
			'clawpress-logs',
			[ $this, 'render_logs_page' ],
			10
		);
	}

	/**
	 * Ensure agent post type submenus are available under ClawPress.
	 */
	public function ensure_agent_post_type_submenus(): void {
		$this->ensure_submenu_page(
			__( 'Agent Files', 'clawpress' ),
			'edit.php?post_type=' . Post_Types::AGENT_FILE_POST_TYPE
		);
		$this->ensure_submenu_page(
			__( 'Agent Memories', 'clawpress' ),
			'edit.php?post_type=' . Post_Types::AGENT_MEMORY_POST_TYPE
		);
	}

	/**
	 * Add a submenu only when an existing item with the same slug is not present.
	 *
	 * @param string $menu_title Submenu title and label.
	 * @param string $menu_slug  Submenu slug.
	 */
	private function ensure_submenu_page( string $menu_title, string $menu_slug ): void {
		global $submenu;

		$existing_submenus = $submenu['clawpress'] ?? [];
		foreach ( $existing_submenus as $submenu_item ) {
			if ( isset( $submenu_item[2] ) && $menu_slug === $submenu_item[2] ) {
				return;
			}
		}

		add_submenu_page(
			'clawpress',
			$menu_title,
			$menu_title,
			'manage_options',
			$menu_slug
		);
	}

	/**
	 * Render the admin page container.
	 */
	public function render_admin_page(): void {
		$this->render_screen(
			__( 'ClawPress', 'clawpress' )
		);
	}

	/**
	 * Render the logs admin page container.
	 */
	public function render_logs_page(): void {
		$this->render_screen(
			__( 'Logs', 'clawpress' )
		);
	}

	/**
	 * Render a React admin screen container.
	 *
	 * @param string $title Screen title.
	 */
	private function render_screen( string $title ): void {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( $title ); ?></h1>
			<div id="clawpress-admin-root"></div>
		</div>
		<?php
	}

	/**
	 * Render custom CSS for the ClawPress top-level menu icon.
	 */
	public function render_menu_icon_styles(): void {
		?>
		<style id="clawpress-menu-icon">
			#toplevel_page_clawpress .wp-menu-image:before {
				content: "🦞";
				font-family: "Apple Color Emoji", "Segoe UI Emoji", "Noto Color Emoji", sans-serif;
				font-size: 17px;
				line-height: 20px;
			}
		</style>
		<?php
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, [ self::MAIN_HOOK_SUFFIX, self::LOGS_HOOK_SUFFIX ], true ) ) {
			return;
		}

		$asset_file = CLAWPRESS_DIR . 'build/scripts/admin.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		// Enqueue the main script.
		wp_enqueue_script(
			'clawpress',
			CLAWPRESS_URL . 'build/scripts/admin.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_set_script_translations( 'clawpress', 'clawpress', CLAWPRESS_DIR . 'languages' );

		wp_localize_script(
			'clawpress',
			'CLAWPRESS_ADMIN',
			[
				'restBase' => esc_url_raw( rest_url( 'clawpress/v1' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'screen'   => self::LOGS_HOOK_SUFFIX === $hook_suffix ? 'logs' : 'main',
			]
		);

		// Enqueue styles.
		wp_enqueue_style(
			'clawpress',
			CLAWPRESS_URL . 'build/scripts/style-admin.css',
			[ 'wp-components' ],
			$asset['version']
		);
	}
}

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
	 * Register all hooks for the admin page.
	 */
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
		add_action( 'admin_menu', [ $this, 'ensure_agent_post_type_submenus' ], 110 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
	}

	/**
	 * Register the ClawPress admin page.
	 */
	public function register_admin_page(): void {
		$menu_icon = 'data:image/svg+xml;utf8,' . rawurlencode(
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><text x="50%" y="30%" text-anchor="middle" dominant-baseline="middle" font-size="8">🦞</text></svg>'
		);

		add_menu_page(
			__( 'ClawPress', 'clawpress' ),
			__( 'ClawPress', 'clawpress' ),
			'manage_options',
			'clawpress',
			[ $this, 'render_admin_page' ],
			$menu_icon,
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
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'ClawPress', 'clawpress' ); ?></h1>
			<div id="clawpress-admin-root"></div>
		</div>
		<?php
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		if ( 'toplevel_page_clawpress' !== $hook_suffix ) {
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

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'clawpress', 'clawpress', CLAWPRESS_DIR . 'languages' );
		}

		wp_localize_script(
			'clawpress',
			'CLAWPRESS_ADMIN',
			[
				'restBase' => esc_url_raw( rest_url( 'clawpress/v1' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
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

<?php
/**
 * WordPress admin page registration.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\AdminPage;

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

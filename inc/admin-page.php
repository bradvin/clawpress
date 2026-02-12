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
 * Register the ClawPress admin page.
 */
function register_admin_page(): void {
	add_menu_page(
		__( 'ClawPress', 'clawpress' ),
		__( 'ClawPress', 'clawpress' ),
		'manage_options',
		'clawpress',
		__NAMESPACE__ . '\render_admin_page',
		'dashicons-admin-generic',
		58
	);
}
add_action( 'admin_menu', __NAMESPACE__ . '\register_admin_page' );

/**
 * Render the admin page container.
 */
function render_admin_page(): void {
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
function enqueue_admin_assets( string $hook_suffix ): void {
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

	// Enqueue styles.
	wp_enqueue_style(
		'clawpress',
		CLAWPRESS_URL . 'build/scripts/style-admin.css',
		[ 'wp-components' ],
		$asset['version']
	);

	/*
	 * Optional: Localize script with custom data.
	 * The demo uses @wordpress/core-data which handles auth automatically.
	 * Uncomment if you need to pass custom PHP data to JavaScript.
	 *
	 * wp_localize_script(
	 *     'clawpress',
	 *     'clawpress',
	 *     array(
	 *         'customSetting' => get_option( 'clawpress_settings', '' ),
	 *     )
	 * );
	 */
}
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\enqueue_admin_assets' );

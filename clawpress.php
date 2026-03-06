<?php
/**
 * Plugin Name: ClawPress
 * Description: AI assistant tools for WordPress admin workflows.
 * Version: 0.0.3
 * Requires PHP: 8.1
 * Requires at least: 7.0
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: clawpress
 * Domain Path: /languages
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress;

defined( 'ABSPATH' ) || exit;

define( 'CLAWPRESS_VERSION', '0.0.3' );
define( 'CLAWPRESS_FILE', __FILE__ );
define( 'CLAWPRESS_DIR', plugin_dir_path( __FILE__ ) );
define( 'CLAWPRESS_URL', plugin_dir_url( __FILE__ ) );

require_once CLAWPRESS_DIR . 'includes/functions.php';

if ( ! clawpress_is_supported_wp_version() ) {
	add_action( 'admin_notices', 'clawpress_render_minimum_wp_version_notice' );
	return;
}

// Load Action Scheduler library bundled via Composer.
if ( file_exists( CLAWPRESS_DIR . 'vendor/woocommerce/action-scheduler/action-scheduler.php' ) ) {
	require_once CLAWPRESS_DIR . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
}

if ( file_exists( CLAWPRESS_DIR . 'vendor/autoload.php' ) ) {
	require_once CLAWPRESS_DIR . 'vendor/autoload.php';
}

if ( function_exists( 'register_activation_hook' ) ) {
	register_activation_hook( CLAWPRESS_FILE, [ Plugin::class, 'activate' ] );
}

Plugin::get_instance();

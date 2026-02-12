<?php
/**
 * Plugin Name: ClawPress
 * Version: 0.0.1
 * Requires PHP: 8.1
 * Requires at least: 6.9
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress;

defined( 'ABSPATH' ) || exit;

define( 'CLAWPRESS_VERSION', '0.0.1' );
define( 'CLAWPRESS_FILE', __FILE__ );
define( 'CLAWPRESS_DIR', plugin_dir_path( __FILE__ ) );
define( 'CLAWPRESS_URL', plugin_dir_url( __FILE__ ) );

// Jetpack autoloader for Composer packages (preferred over default Composer loader).
if ( file_exists( CLAWPRESS_DIR . 'vendor/autoload_packages.php' ) ) {
	require_once CLAWPRESS_DIR . 'vendor/autoload_packages.php';
} elseif ( file_exists( CLAWPRESS_DIR . 'vendor/autoload.php' ) ) {
	// Fallback for environments where autoload_packages.php is unavailable.
	require_once CLAWPRESS_DIR . 'vendor/autoload.php';
}

// Load Action Scheduler library bundled via Composer.
if ( file_exists( CLAWPRESS_DIR . 'vendor/woocommerce/action-scheduler/action-scheduler.php' ) ) {
	require_once CLAWPRESS_DIR . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
}

// Feature modules.
require_once CLAWPRESS_DIR . 'inc/post-types.php';
require_once CLAWPRESS_DIR . 'inc/rest-api.php';
require_once CLAWPRESS_DIR . 'inc/admin-page.php';
require_once CLAWPRESS_DIR . 'inc/blocks.php';
require_once CLAWPRESS_DIR . 'inc/heartbeat.php';

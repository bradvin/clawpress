<?php
/**
 * Plugin Name: ClawPress
 * Description: AI assistant tools for WordPress admin workflows.
 * Version: 0.0.2
 * Requires PHP: 8.1
 * Requires at least: 6.9
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

define( 'CLAWPRESS_VERSION', '0.0.2' );
define( 'CLAWPRESS_FILE', __FILE__ );
define( 'CLAWPRESS_DIR', plugin_dir_path( __FILE__ ) );
define( 'CLAWPRESS_URL', plugin_dir_url( __FILE__ ) );

require_once CLAWPRESS_DIR . 'includes/functions.php';

/*
 * Prefer Core's AI client classes when available to avoid class collisions with vendored copies.
 * We use Composer's loader in that case so we can remove AI namespace mappings from this plugin.
 */
$core_ai_client_available = false;
if ( class_exists( '\WordPress\AiClient\AiClient' ) ) {
	try {
		$core_ai_client_file      = ( new \ReflectionClass( '\WordPress\AiClient\AiClient' ) )->getFileName();
		$core_ai_client_available = is_string( $core_ai_client_file ) && str_contains( $core_ai_client_file, '/wp-includes/php-ai-client/' );
	} catch ( \ReflectionException $e ) {
		$core_ai_client_available = false;
	}
}

$composer_loader = null;
if ( $core_ai_client_available ) {
	if ( file_exists( CLAWPRESS_DIR . 'vendor/autoload.php' ) ) {
		$composer_loader = require CLAWPRESS_DIR . 'vendor/autoload.php';
	} elseif ( file_exists( CLAWPRESS_DIR . 'vendor/autoload_packages.php' ) ) {
		require_once CLAWPRESS_DIR . 'vendor/autoload_packages.php';
	}
} else {
	// Jetpack autoloader for Composer packages (preferred over default Composer loader).
	if ( file_exists( CLAWPRESS_DIR . 'vendor/autoload_packages.php' ) ) {
		require_once CLAWPRESS_DIR . 'vendor/autoload_packages.php';
	} elseif ( file_exists( CLAWPRESS_DIR . 'vendor/autoload.php' ) ) {
		// Fallback for environments where autoload_packages.php is unavailable.
		$composer_loader = require CLAWPRESS_DIR . 'vendor/autoload.php';
	}
}

if ( $core_ai_client_available && is_object( $composer_loader ) && method_exists( $composer_loader, 'setPsr4' ) ) {
	$composer_loader->setPsr4( 'WordPress\\AiClient\\', [] );
	$composer_loader->setPsr4( 'WordPress\\AI_Client\\', [] );
}

// Load Action Scheduler library bundled via Composer.
if ( file_exists( CLAWPRESS_DIR . 'vendor/woocommerce/action-scheduler/action-scheduler.php' ) ) {
	require_once CLAWPRESS_DIR . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
}

if ( function_exists( 'register_activation_hook' ) ) {
	register_activation_hook( CLAWPRESS_FILE, [ Plugin::class, 'activate' ] );
}

Plugin::get_instance();

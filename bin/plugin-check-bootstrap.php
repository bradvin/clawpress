<?php
/**
 * Standalone Plugin Check command bootstrap for WP-CLI.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

$engine_dir = getenv( 'WP_PLUGIN_CHECK_ENGINE_DIR' );
if ( ! is_string( $engine_dir ) || '' === $engine_dir ) {
	\WP_CLI::error( 'Missing WP_PLUGIN_CHECK_ENGINE_DIR environment variable.' );
}

$engine_dir = rtrim( $engine_dir, "/\\" );
$autoload   = $engine_dir . '/vendor/autoload.php';
$main_file  = $engine_dir . '/plugin.php';
$dir_path   = rtrim( $engine_dir, '/\\' ) . '/';

if ( ! defined( 'WP_PLUGIN_CHECK_VERSION' ) ) {
	define( 'WP_PLUGIN_CHECK_VERSION', '1.8.0' );
}

if ( ! defined( 'WP_PLUGIN_CHECK_MINIMUM_PHP' ) ) {
	define( 'WP_PLUGIN_CHECK_MINIMUM_PHP', '7.4' );
}

if ( ! defined( 'WP_PLUGIN_CHECK_MAIN_FILE' ) ) {
	define( 'WP_PLUGIN_CHECK_MAIN_FILE', $main_file );
}

if ( ! defined( 'WP_PLUGIN_CHECK_PLUGIN_DIR_PATH' ) ) {
	define( 'WP_PLUGIN_CHECK_PLUGIN_DIR_PATH', $dir_path );
}

if ( ! defined( 'WP_PLUGIN_CHECK_PLUGIN_DIR_URL' ) ) {
	define( 'WP_PLUGIN_CHECK_PLUGIN_DIR_URL', '' );
}

if ( ! file_exists( $autoload ) ) {
	\WP_CLI::error( 'Plugin Check engine autoloader not found: ' . $autoload );
}

if ( ! function_exists( 'clawpress_plugin_check_disable_engine_ai_client_autoload' ) ) {
	/**
	 * Prevent Plugin Check's bundled AI client from shadowing WordPress core's scoped AI client.
	 *
	 * @param mixed $loader Composer loader returned by the standalone engine.
	 */
	function clawpress_plugin_check_disable_engine_ai_client_autoload( $loader ): void {
		if ( ! is_object( $loader ) || ! method_exists( $loader, 'setPsr4' ) ) {
			return;
		}

		$loader->setPsr4( 'WordPress\\AiClient\\', array() );
		$loader->setPsr4( 'WordPress\\AI_Client\\', array() );
	}
}

$engine_loader = require_once $autoload;
clawpress_plugin_check_disable_engine_ai_client_autoload( $engine_loader );

if ( ! class_exists( '\WordPress\Plugin_Check\CLI\Plugin_Check_Command' ) ) {
	\WP_CLI::error( 'Plugin Check command class could not be loaded from standalone engine.' );
}

$context = new \WordPress\Plugin_Check\Plugin_Context( $main_file );
\WP_CLI::add_command(
	'plugin',
	new \WordPress\Plugin_Check\CLI\Plugin_Check_Command( $context ),
	array(
		'after_invoke' => static function (): void {
			if ( ! defined( 'WP_CONTENT_DIR' ) ) {
				define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
			}

			$dropin_path = WP_CONTENT_DIR . '/object-cache.php';
			if ( ! file_exists( $dropin_path ) ) {
				return;
			}

			$contents = file_get_contents( $dropin_path );
			if ( false !== $contents && false !== strpos( $contents, 'WP_PLUGIN_CHECK_STANDALONE_DROPIN_VERSION' ) ) {
				unlink( $dropin_path );
			}
		},
	)
);

\WP_CLI::add_hook(
	'after_wp_config_load',
	static function (): void {
		if ( ! defined( 'WP_CONTENT_DIR' ) ) {
			define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
		}

		if ( ! \WordPress\Plugin_Check\Checker\CLI_Runner::is_plugin_check() ) {
			return;
		}

		$dropin_path = WP_CONTENT_DIR . '/object-cache.php';
		if ( file_exists( $dropin_path ) ) {
			return;
		}

		$dropin = <<<'DROPIN'
<?php

define( 'WP_PLUGIN_CHECK_STANDALONE_DROPIN_VERSION', 1 );
define( 'WP_PLUGIN_CHECK_OBJECT_CACHE_DROPIN_VERSION', 1 );

function plugin_check_initialize_runner(): void {
	$engine_dir = getenv( 'WP_PLUGIN_CHECK_ENGINE_DIR' );
	if ( ! is_string( $engine_dir ) || '' === $engine_dir ) {
		return;
	}

	$engine_dir = rtrim( $engine_dir, '/\\' );
	$autoload   = $engine_dir . '/vendor/autoload.php';
	if ( ! file_exists( $autoload ) ) {
		return;
	}

	if ( ! function_exists( 'clawpress_plugin_check_disable_engine_ai_client_autoload' ) ) {
		/**
		 * Prevent Plugin Check's bundled AI client from shadowing WordPress core's scoped AI client.
		 *
		 * @param mixed $loader Composer loader returned by the standalone engine.
		 */
		function clawpress_plugin_check_disable_engine_ai_client_autoload( $loader ): void {
			if ( ! is_object( $loader ) || ! method_exists( $loader, 'setPsr4' ) ) {
				return;
			}

			$loader->setPsr4( 'WordPress\\AiClient\\', array() );
			$loader->setPsr4( 'WordPress\\AI_Client\\', array() );
		}
	}

	$engine_loader = require_once $autoload;
	clawpress_plugin_check_disable_engine_ai_client_autoload( $engine_loader );

	if ( class_exists( '\WordPress\Plugin_Check\Utilities\Plugin_Request_Utility' ) ) {
		\WordPress\Plugin_Check\Utilities\Plugin_Request_Utility::initialize_runner();
	}
}

plugin_check_initialize_runner();
DROPIN;

		if ( false === file_put_contents( $dropin_path, $dropin ) ) {
			\WP_CLI::error( 'Unable to create temporary Plugin Check object-cache.php drop-in.' );
		}
	},
	5
);

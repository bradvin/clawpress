<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package ClawPress\Tests
 */

declare( strict_types=1 );

$plugin_root = dirname( __DIR__ );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $plugin_root . '/' );
}

if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}

$fixture_root = sys_get_temp_dir() . '/clawpress-phpunit-fixtures';

if ( ! is_dir( $fixture_root . '/build/scripts' ) ) {
	mkdir( $fixture_root . '/build/scripts', 0777, true );
}

if ( ! is_dir( $fixture_root . '/build/panel' ) ) {
	mkdir( $fixture_root . '/build/panel', 0777, true );
}

$fixture_files = array(
	$fixture_root . '/build/scripts/admin.asset.php' => "<?php return array( 'dependencies' => array( 'wp-element' ), 'version' => 'test-version' );\n",
	$fixture_root . '/build/panel/panel.asset.php'   => "<?php return array( 'dependencies' => array( 'wp-element' ), 'version' => 'test-version' );\n",
	$fixture_root . '/build/panel/panel.css'         => "/* panel */\n",
	$fixture_root . '/build/panel/panel-rtl.css'     => "/* panel rtl */\n",
	$fixture_root . '/build/panel/style-panel.css'   => "/* fallback panel */\n",
);

foreach ( $fixture_files as $file => $contents ) {
	file_put_contents( $file, $contents );
}

if ( ! defined( 'CLAWPRESS_DIR' ) ) {
	define( 'CLAWPRESS_DIR', rtrim( $fixture_root, '/' ) . '/' );
}

if ( ! defined( 'CLAWPRESS_URL' ) ) {
	define( 'CLAWPRESS_URL', 'https://example.test/wp-content/plugins/clawpress/' );
}

require_once __DIR__ . '/Support/WordPressStubs.php';
require_once __DIR__ . '/Support/TestCase.php';

require_once $plugin_root . '/inc/class-post-types.php';
require_once $plugin_root . '/inc/class-rest-api.php';
require_once $plugin_root . '/inc/class-admin-page.php';
require_once $plugin_root . '/inc/class-panel.php';
require_once $plugin_root . '/inc/class-heartbeat.php';
require_once $plugin_root . '/inc/class-plugin.php';

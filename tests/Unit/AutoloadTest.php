<?php
/**
 * Tests for the plugin autoload fallback.
 *
 * @package ClawPress\Tests
 */

declare( strict_types=1 );

namespace ClawPress\Tests\Unit;

use ClawPress\RestAPI\Controllers\Logs_Controller;
use ClawPress\Tests\Support\TestCase;

final class AutoloadTest extends TestCase {
	/**
	 * The fallback autoloader must cover fresh classes when Composer's classmap is stale.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_fallback_autoloader_loads_rest_controller_without_composer_classmap(): void {
		foreach ( spl_autoload_functions() ?: [] as $loader ) {
			if ( is_array( $loader ) && is_object( $loader[0] ) && $loader[0] instanceof \Composer\Autoload\ClassLoader ) {
				spl_autoload_unregister( $loader );
			}
		}

		require_once dirname( __DIR__, 2 ) . '/includes/autoload.php';

		$this->assertTrue( class_exists( Logs_Controller::class ) );
	}
}

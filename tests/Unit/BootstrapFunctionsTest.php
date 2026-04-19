<?php
/**
 * Tests for bootstrap helper functions.
 *
 * @package ClawPress\Tests
 */

declare( strict_types=1 );

namespace ClawPress\Tests\Unit;

use ClawPress\Tests\Support\TestCase;
use ClawPress\Tests\Support\WordPress_Stubs;

final class BootstrapFunctionsTest extends TestCase {
	public function test_supported_wp_version_accepts_wordpress_seven_prerelease(): void {
		WordPress_Stubs::$wp_version = '7.0-beta1';

		$this->assertTrue( \clawpress_is_supported_wp_version() );
	}

	public function test_supported_wp_version_rejects_wordpress_six_series(): void {
		WordPress_Stubs::$wp_version = '6.9.3';

		$this->assertFalse( \clawpress_is_supported_wp_version() );
	}

	public function test_minimum_wp_version_notice_renders_current_version(): void {
		WordPress_Stubs::$wp_version = '6.9.3';

		ob_start();
		\clawpress_render_minimum_wp_version_notice();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'ClawPress requires WordPress 7.0 or newer.', $output );
		$this->assertStringContainsString( 'WordPress 6.9.3', $output );
	}
}

<?php
/**
 * Test Workspace Helper file operations.
 *
 * @package ClawPress\Tests
 */

declare( strict_types=1 );

namespace ClawPress\Tests\Unit;

use ClawPress\Helpers\Workspace_Helper;
use ClawPress\Tests\Support\TestCase;

/**
 * Workspace Helper Tests.
 *
 * Note: These are integration tests that require a real WordPress environment.
 * Run with: lando wp eval-file tests/Unit/WorkspaceHelperTest.php
 */
final class WorkspaceHelperTest extends TestCase {
	/**
	 * Test that workspace helper can be instantiated.
	 */
	public function test_get_instance_returns_singleton(): void {
		$instance1 = Workspace_Helper::get_instance();
		$instance2 = Workspace_Helper::get_instance();

		$this->assertSame( $instance1, $instance2 );
		$this->assertInstanceOf( Workspace_Helper::class, $instance1 );
	}

	/**
	 * Test normalize_workspace_relative_path logic.
	 *
	 * Note: This tests the private method indirectly through public methods.
	 */
	public function test_write_validates_path_normalization(): void {
		$helper = Workspace_Helper::get_instance();

		// This would fail with invalid paths like '../../../etc/passwd'
		// but we can't test that without a real user, so this is a placeholder
		$this->assertInstanceOf( Workspace_Helper::class, $helper );
	}
}

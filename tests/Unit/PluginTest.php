<?php
/**
 * Tests for plugin module wiring.
 *
 * @package ClawPress\Tests
 */

declare( strict_types=1 );

namespace ClawPress\Tests\Unit;

use ClawPress\Plugin;
use ClawPress\Tests\Support\TestCase;
use ClawPress\Tests\Support\WordPress_Stubs;

final class PluginTest extends TestCase {
	public function test_get_instance_wires_all_module_hooks_once(): void {
		$instance = Plugin::get_instance();
		$this->assertSame( $instance, Plugin::get_instance() );

		$hooks = array_column( WordPress_Stubs::$actions, 'hook' );

		$this->assertCount( 9, WordPress_Stubs::$actions );
		$this->assertContains( 'init', $hooks );
		$this->assertContains( 'rest_api_init', $hooks );
		$this->assertContains( 'admin_menu', $hooks );
		$this->assertContains( 'admin_enqueue_scripts', $hooks );
		$this->assertContains( 'admin_bar_menu', $hooks );
		$this->assertContains( 'action_scheduler_init', $hooks );
		$this->assertContains( 'action_scheduler_ensure_recurring_actions', $hooks );
		$this->assertContains( 'clawpress_heartbeat_tick', $hooks );
	}
}

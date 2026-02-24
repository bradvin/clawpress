<?php
/**
 * Tests for plugin module wiring.
 *
 * @package ClawPress\Tests
 */

declare( strict_types=1 );

namespace ClawPress\Tests\Unit;

use ClawPress\Plugin;
use ClawPress\PostTypes\Post_Types;
use ClawPress\Tests\Support\TestCase;
use ClawPress\Tests\Support\WordPress_Stubs;

final class PluginTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		$instance_property = new \ReflectionProperty( Plugin::class, 'instance' );
		$instance_property->setAccessible( true );
		$instance_property->setValue( null, null );
	}

	public function test_get_instance_wires_all_module_hooks_once(): void {
		$instance = Plugin::get_instance();
		$this->assertSame( $instance, Plugin::get_instance() );

		$hooks = array_column( WordPress_Stubs::$actions, 'hook' );

		$this->assertCount( 18, WordPress_Stubs::$actions );
		$this->assertContains( 'init', $hooks );
		$this->assertContains( 'use_block_editor_for_post_type', $hooks );
		$this->assertContains( 'rest_api_init', $hooks );
		$this->assertContains( 'admin_menu', $hooks );
		$this->assertContains( 'admin_enqueue_scripts', $hooks );
		$this->assertContains( 'admin_head', $hooks );
		$this->assertContains( 'admin_bar_menu', $hooks );
		$this->assertContains( 'wp_abilities_api_categories_init', $hooks );
		$this->assertContains( 'wp_abilities_api_init', $hooks );
		$this->assertContains( 'action_scheduler_init', $hooks );
		$this->assertContains( 'action_scheduler_ensure_recurring_actions', $hooks );
		$this->assertContains( 'clawpress_heartbeat_tick', $hooks );
		$this->assertContains( 'clawpress_run_scheduled_tasks', $hooks );
		$this->assertContains( 'clawpress_agent_run_slice', $hooks );
	}

	public function test_plugin_boot_admin_menu_hook_registers_expected_menu_items(): void {
		Plugin::get_instance();

		do_action( 'admin_menu' );

		$this->assertCount( 1, WordPress_Stubs::$menu_pages );
		$this->assertSame( 'clawpress', WordPress_Stubs::$menu_pages[0]['menu_slug'] );
		$this->assertCount( 1, WordPress_Stubs::$removed_submenu_pages );
		$this->assertCount( 3, WordPress_Stubs::$submenu_pages );
		$this->assertSame( 'clawpress', WordPress_Stubs::$submenu_pages[0]['menu_slug'] );
		$this->assertSame( 'edit.php?post_type=' . Post_Types::AGENT_FILE_POST_TYPE, WordPress_Stubs::$submenu_pages[1]['menu_slug'] );
		$this->assertSame( 'edit.php?post_type=' . Post_Types::AGENT_MEMORY_POST_TYPE, WordPress_Stubs::$submenu_pages[2]['menu_slug'] );
	}

	public function test_plugin_boot_admin_enqueue_scripts_hook_enqueues_admin_and_panel_assets(): void {
		Plugin::get_instance();

		do_action( 'admin_enqueue_scripts', 'toplevel_page_clawpress' );

		$enqueued_script_handles = array_column( WordPress_Stubs::$enqueued_scripts, 'handle' );
		$enqueued_style_handles  = array_column( WordPress_Stubs::$enqueued_styles, 'handle' );
		$localized_script_names  = array_column( WordPress_Stubs::$localized_scripts, 'object_name' );

		$this->assertCount( 2, WordPress_Stubs::$enqueued_scripts );
		$this->assertCount( 2, WordPress_Stubs::$enqueued_styles );
		$this->assertCount( 2, WordPress_Stubs::$localized_scripts );
		$this->assertContains( 'clawpress', $enqueued_script_handles );
		$this->assertContains( 'clawpress-panel', $enqueued_script_handles );
		$this->assertContains( 'clawpress', $enqueued_style_handles );
		$this->assertContains( 'clawpress-panel', $enqueued_style_handles );
		$this->assertContains( 'CLAWPRESS_ADMIN', $localized_script_names );
		$this->assertContains( 'CLAWPRESS_PANEL', $localized_script_names );
	}
}

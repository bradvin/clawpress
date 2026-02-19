<?php
/**
 * Tests for admin page module.
 *
 * @package ClawPress\Tests
 */

declare( strict_types=1 );

namespace ClawPress\Tests\Unit;

use ClawPress\AdminPage\Admin_Page;
use ClawPress\Tests\Support\TestCase;
use ClawPress\Tests\Support\WordPress_Stubs;

final class AdminPageTest extends TestCase {
	public function test_register_adds_expected_hooks(): void {
		new Admin_Page();

		$hooks = array_column( WordPress_Stubs::$actions, 'hook' );

		$this->assertCount( 4, WordPress_Stubs::$actions );
		$this->assertContains( 'admin_menu', $hooks );
		$this->assertContains( 'admin_enqueue_scripts', $hooks );
		$this->assertContains( 'admin_head', $hooks );
	}

	public function test_register_admin_page_registers_menu_item(): void {
		$admin_page = new Admin_Page();
		$admin_page->register_admin_page();

		$this->assertCount( 1, WordPress_Stubs::$menu_pages );
		$this->assertSame( 'clawpress', WordPress_Stubs::$menu_pages[0]['menu_slug'] );
	}

	public function test_render_admin_page_outputs_mount_node(): void {
		$admin_page = new Admin_Page();
		ob_start();
		$admin_page->render_admin_page();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'id="clawpress-admin-root"', $output );
	}

	public function test_enqueue_admin_assets_bails_for_unrelated_screen(): void {
		$admin_page = new Admin_Page();
		$admin_page->enqueue_admin_assets( 'dashboard_page' );

		$this->assertCount( 0, WordPress_Stubs::$enqueued_scripts );
		$this->assertCount( 0, WordPress_Stubs::$enqueued_styles );
	}

	public function test_enqueue_admin_assets_enqueues_script_and_style(): void {
		$admin_page = new Admin_Page();
		$admin_page->enqueue_admin_assets( 'toplevel_page_clawpress' );

		$this->assertCount( 1, WordPress_Stubs::$enqueued_scripts );
		$this->assertCount( 1, WordPress_Stubs::$enqueued_styles );
		$this->assertCount( 1, WordPress_Stubs::$localized_scripts );
		$this->assertSame( 'clawpress', WordPress_Stubs::$enqueued_scripts[0]['handle'] );
		$this->assertSame( 'clawpress', WordPress_Stubs::$enqueued_styles[0]['handle'] );
		$this->assertSame( 'CLAWPRESS_ADMIN', WordPress_Stubs::$localized_scripts[0]['object_name'] );
		$this->assertArrayHasKey( 'restBase', WordPress_Stubs::$localized_scripts[0]['data'] );
		$this->assertArrayHasKey( 'nonce', WordPress_Stubs::$localized_scripts[0]['data'] );
	}
}

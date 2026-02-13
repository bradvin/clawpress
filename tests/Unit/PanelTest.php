<?php
/**
 * Tests for panel module.
 *
 * @package ClawPress\Tests
 */

declare( strict_types=1 );

namespace ClawPress\Tests\Unit;

use ClawPress\Panel\Panel;
use ClawPress\Tests\Support\TestCase;
use ClawPress\Tests\Support\WordPress_Stubs;

final class PanelTest extends TestCase {
	public function test_register_adds_expected_hooks(): void {
		Panel::register();

		$this->assertCount( 2, WordPress_Stubs::$actions );
		$this->assertSame( 'admin_enqueue_scripts', WordPress_Stubs::$actions[0]['hook'] );
		$this->assertSame( 'admin_bar_menu', WordPress_Stubs::$actions[1]['hook'] );
		$this->assertSame( 100, WordPress_Stubs::$actions[1]['priority'] );
	}

	public function test_enqueue_assets_bails_when_user_lacks_capability(): void {
		WordPress_Stubs::$can_manage_options = false;

		Panel::enqueue_assets( 'plugins.php' );

		$this->assertCount( 0, WordPress_Stubs::$enqueued_styles );
		$this->assertCount( 0, WordPress_Stubs::$enqueued_scripts );
	}

	public function test_enqueue_assets_enqueues_panel_assets_and_config(): void {
		WordPress_Stubs::$can_manage_options = true;
		WordPress_Stubs::$is_rtl             = true;
		WordPress_Stubs::$current_user_id    = 52;

		Panel::enqueue_assets( 'plugins.php' );

		$this->assertCount( 2, WordPress_Stubs::$enqueued_styles );
		$this->assertCount( 1, WordPress_Stubs::$enqueued_scripts );
		$this->assertCount( 1, WordPress_Stubs::$localized_scripts );
		$this->assertSame( 'clawpress-panel', WordPress_Stubs::$enqueued_scripts[0]['handle'] );
		$this->assertSame( 'CLAWPRESS_PANEL', WordPress_Stubs::$localized_scripts[0]['object_name'] );
		$this->assertSame( 52, WordPress_Stubs::$localized_scripts[0]['data']['userId'] );
	}

	public function test_register_admin_bar_toggle_adds_button_for_authorized_users(): void {
		$admin_bar = new \WP_Admin_Bar();

		Panel::register_admin_bar_toggle( $admin_bar );

		$this->assertCount( 1, $admin_bar->nodes );
		$this->assertSame( 'clawpress-toggle', $admin_bar->nodes[0]['id'] );
	}

	public function test_register_admin_bar_toggle_bails_for_unauthorized_users(): void {
		WordPress_Stubs::$can_manage_options = false;
		$admin_bar                           = new \WP_Admin_Bar();

		Panel::register_admin_bar_toggle( $admin_bar );

		$this->assertCount( 0, $admin_bar->nodes );
	}
}

<?php
/**
 * Contract tests for plugin/admin WordPress boot APIs.
 *
 * @package ClawPress\Tests
 */

declare( strict_types=1 );

namespace ClawPress\Tests\Unit;

use ClawPress\AdminPage\Admin_Page;
use ClawPress\Plugin;
use ClawPress\Tests\Support\TestCase;

final class BootContractsTest extends TestCase {
	public function test_plugin_boot_contract_is_satisfied_by_test_stubs(): void {
		Plugin::assert_boot_contract();
		$this->addToAssertionCount( 1 );
	}

	public function test_plugin_activation_contract_is_satisfied_by_test_stubs(): void {
		Plugin::assert_activation_contract();
		$this->addToAssertionCount( 1 );
	}

	public function test_admin_boot_contract_is_satisfied_by_test_stubs(): void {
		Admin_Page::assert_boot_contract();
		$this->addToAssertionCount( 1 );
	}

	public function test_plugin_boot_contract_failure_is_explicit_and_actionable(): void {
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'plugin boot requires WordPress APIs that are unavailable: add_action' );
		$this->expectExceptionMessage( 'tests/Support/WordPressStubs.php' );

		Plugin::assert_boot_contract(
			static fn( string $function_name ): bool => 'add_action' !== $function_name
		);
	}

	public function test_admin_boot_contract_failure_is_explicit_and_actionable(): void {
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'admin boot requires WordPress APIs that are unavailable: wp_localize_script' );
		$this->expectExceptionMessage( 'tests/Support/WordPressStubs.php' );

		Admin_Page::assert_boot_contract(
			static fn( string $function_name ): bool => 'wp_localize_script' !== $function_name
		);
	}
}

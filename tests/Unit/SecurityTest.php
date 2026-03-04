<?php
/**
 * Tests for security permission checks.
 *
 * @package ClawPress\Tests
 */

declare( strict_types=1 );

namespace ClawPress\Tests\Unit;

use ClawPress\Security\Security;
use ClawPress\Tests\Support\TestCase;
use ClawPress\Tests\Support\WordPress_Stubs;

final class SecurityTest extends TestCase {
	public function test_assert_requesting_user_allowed_uses_explicit_user_id(): void {
		WordPress_Stubs::$current_user_id                        = 0;
		WordPress_Stubs::$can_manage_options                     = false;
		WordPress_Stubs::$user_capabilities[1]['manage_options'] = true;

		$result = Security::get_instance()->assert_requesting_user_allowed( 1 );

		$this->assertTrue( true === $result );
	}

	public function test_assert_requesting_user_allowed_rejects_explicit_user_without_capability(): void {
		WordPress_Stubs::$current_user_id                        = 2;
		WordPress_Stubs::$can_manage_options                     = true;
		WordPress_Stubs::$user_capabilities[1]['manage_options'] = false;

		$result = Security::get_instance()->assert_requesting_user_allowed( 1 );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'clawpress_requesting_user_forbidden', $result->get_error_code() );
	}
}


<?php
/**
 * Tests for runtime policy helper.
 *
 * @package ClawPress\Tests
 */

declare( strict_types=1 );

namespace ClawPress\Tests\Unit;

use ClawPress\Helpers\Policy_Helper;
use ClawPress\Tests\Support\TestCase;

final class PolicyHelperTest extends TestCase {
	public function test_chat_trigger_uses_default_policy_contract(): void {
		$policy = Policy_Helper::get_instance()->resolve_runtime_policy( 'chat' );

		$this->assertSame( 'chat', $policy['trigger_type'] );
		$this->assertTrue( $policy['allow_tools'] );
		$this->assertTrue( $policy['allow_destructive_tools'] );
		$this->assertTrue( $policy['require_confirmation_for_destructive'] );
		$this->assertSame( 6, $policy['max_tool_rounds'] );
		$this->assertSame( 8, $policy['max_tool_calls_per_round'] );
	}

	public function test_heartbeat_trigger_resolves_stricter_defaults(): void {
		$policy = Policy_Helper::get_instance()->resolve_runtime_policy( 'heartbeat' );

		$this->assertSame( 'heartbeat', $policy['trigger_type'] );
		$this->assertTrue( $policy['allow_tools'] );
		$this->assertFalse( $policy['allow_destructive_tools'] );
		$this->assertFalse( $policy['allow_file_delete'] );
		$this->assertSame( 2, $policy['max_tool_rounds'] );
		$this->assertSame( 3, $policy['max_tool_calls_per_round'] );
	}

	public function test_profile_overrides_are_applied_deterministically(): void {
		$first = Policy_Helper::get_instance()->resolve_runtime_policy(
			'spawned_agent',
			[ 'policy_profile' => 'trusted-runner' ],
			[
				'allow_destructive_tools' => true,
				'allow_file_delete'       => true,
			]
		);
		$second = Policy_Helper::get_instance()->resolve_runtime_policy(
			'spawned_agent',
			[ 'policy_profile' => 'trusted-runner' ],
			[
				'allow_destructive_tools' => true,
				'allow_file_delete'       => true,
			]
		);

		$this->assertSame( $first, $second );
		$this->assertSame( 'trusted-runner', $first['policy_profile'] );
		$this->assertTrue( $first['allow_destructive_tools'] );
		$this->assertTrue( $first['allow_file_delete'] );
	}
}

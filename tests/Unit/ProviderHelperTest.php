<?php
/**
 * Tests for provider helper resolution.
 *
 * @package ClawPress\Tests
 */

declare( strict_types=1 );

namespace ClawPress\Tests\Unit;

use ClawPress\Helpers\Provider_Helper;
use ClawPress\Tests\Support\TestCase;
use ClawPress\Tests\Support\WordPress_Stubs;

final class ProviderHelperTest extends TestCase {
	public function test_supports_provider_id_uses_registered_provider_ids(): void {
		WordPress_Stubs::$ai_registered_provider_ids = [ 'openai', 'custom-provider' ];

		$provider_helper = Provider_Helper::get_instance();

		$this->assertTrue( $provider_helper->supports_provider_id( 'openai' ) );
		$this->assertTrue( $provider_helper->supports_provider_id( 'custom-provider' ) );
		$this->assertFalse( $provider_helper->supports_provider_id( 'anthropic' ) );
	}

	public function test_supports_provider_id_falls_back_to_local_provider_map(): void {
		$provider_helper = Provider_Helper::get_instance();

		$this->assertTrue( $provider_helper->supports_provider_id( 'openai' ) );
		$this->assertTrue( $provider_helper->supports_provider_id( 'anthropic' ) );
		$this->assertFalse( $provider_helper->supports_provider_id( 'custom-provider' ) );
	}

	public function test_get_provider_options_formats_registered_provider_labels(): void {
		WordPress_Stubs::$ai_registered_provider_ids = [ 'custom-provider' ];

		$provider_helper = Provider_Helper::get_instance();
		$options         = $provider_helper->get_provider_options();

		$this->assertSame(
			[
				[
					'value' => 'custom-provider',
					'label' => 'Custom Provider',
				],
			],
			$options
		);
	}
}

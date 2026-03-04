<?php
/**
 * Tests for provider helper message rules.
 *
 * @package ClawPress\Tests
 */

declare( strict_types=1 );

namespace ClawPress\Tests\Unit;

use ClawPress\Helpers\Provider_Helper;
use ClawPress\Tests\Support\TestCase;

final class ProviderHelperTest extends TestCase {
	public function test_openai_rules_enable_openai_specific_generation_options(): void {
		$provider_helper = Provider_Helper::get_instance();

		$this->assertTrue( $provider_helper->should_use_max_output_tokens( 'openai', 'gpt-5.2' ) );
		$this->assertFalse( $provider_helper->should_use_max_output_tokens( 'openai', 'gpt-4o' ) );
		$this->assertTrue( $provider_helper->should_use_max_completion_tokens( 'openai', 'gpt-5.2' ) );
		$this->assertTrue( $provider_helper->should_use_temperature( 'openai', 'gpt-5.2' ) );
		$this->assertTrue( $provider_helper->should_use_top_p( 'openai', 'gpt-5.2' ) );
		$this->assertTrue( $provider_helper->should_use_frequency_penalty( 'openai', 'gpt-5.2' ) );
		$this->assertTrue( $provider_helper->should_use_presence_penalty( 'openai', 'gpt-5.2' ) );
	}

	public function test_anthropic_rules_disable_conflicting_sampling_and_penalty_options(): void {
		$provider_helper = Provider_Helper::get_instance();

		$this->assertFalse( $provider_helper->should_use_max_output_tokens( 'anthropic', 'claude-sonnet-4-5' ) );
		$this->assertFalse( $provider_helper->should_use_temperature( 'anthropic', 'claude-sonnet-4-5' ) );
		$this->assertFalse( $provider_helper->should_use_top_p( 'anthropic', 'claude-sonnet-4-5' ) );
		$this->assertFalse( $provider_helper->should_use_frequency_penalty( 'anthropic', 'claude-sonnet-4-5' ) );
		$this->assertFalse( $provider_helper->should_use_presence_penalty( 'anthropic', 'claude-sonnet-4-5' ) );
	}

	public function test_google_rules_use_generic_non_openai_penalty_behavior(): void {
		$provider_helper = Provider_Helper::get_instance();

		$this->assertFalse( $provider_helper->should_use_max_output_tokens( 'google', 'gemini-2.5-pro' ) );
		$this->assertTrue( $provider_helper->should_use_temperature( 'google', 'gemini-2.5-pro' ) );
		$this->assertTrue( $provider_helper->should_use_top_p( 'google', 'gemini-2.5-pro' ) );
		$this->assertFalse( $provider_helper->should_use_frequency_penalty( 'google', 'gemini-2.5-pro' ) );
		$this->assertFalse( $provider_helper->should_use_presence_penalty( 'google', 'gemini-2.5-pro' ) );
	}

	public function test_unknown_provider_falls_back_to_generic_rules(): void {
		$provider_helper = Provider_Helper::get_instance();

		$this->assertFalse( $provider_helper->should_use_max_output_tokens( 'custom-provider', 'custom-model' ) );
		$this->assertFalse( $provider_helper->should_use_temperature( 'custom-provider', 'custom-model' ) );
		$this->assertFalse( $provider_helper->should_use_top_p( 'custom-provider', 'custom-model' ) );
		$this->assertFalse( $provider_helper->should_use_frequency_penalty( 'custom-provider', 'custom-model' ) );
		$this->assertFalse( $provider_helper->should_use_presence_penalty( 'custom-provider', 'custom-model' ) );
	}
}

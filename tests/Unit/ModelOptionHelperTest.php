<?php
/**
 * Tests for model option helper.
 *
 * @package ClawPress\Tests
 */

declare( strict_types=1 );

namespace ClawPress\Tests\Unit;

use ClawPress\Helpers\Model_Option_Helper;
use ClawPress\Tests\Support\TestCase;
use ClawPress\Tests\Support\WordPress_Stubs;
use RuntimeException;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;

final class ModelOptionHelperTest extends TestCase {
	public function test_supports_generation_option_when_metadata_contains_option(): void {
		WordPress_Stubs::$ai_provider_models = [
			'openai' => [
				'gpt-test' => new ModelMetadata(
					'gpt-test',
					'GPT Test',
					[],
					[
						new SupportedOption( 'temperature' ),
						new SupportedOption( 'topP' ),
						new SupportedOption( 'maxTokens' ),
					]
				),
			],
		];

		$helper = Model_Option_Helper::get_instance();

		$this->assertTrue( $helper->supports_generation_option( 'openai', 'gpt-test', 'temperature', 0.2 ) );
		$this->assertTrue( $helper->supports_generation_option( 'openai', 'gpt-test', 'top_p', 0.9 ) );
		$this->assertTrue( $helper->supports_generation_option( 'openai', 'gpt-test', 'max_output_tokens', 1200 ) );
	}

	public function test_skips_generation_option_when_metadata_omits_option(): void {
		WordPress_Stubs::$ai_provider_models = [
			'openai' => [
				'gpt-test' => new ModelMetadata(
					'gpt-test',
					'GPT Test',
					[],
					[
						new SupportedOption( 'temperature' ),
					]
				),
			],
		];

		$helper = Model_Option_Helper::get_instance();

		$this->assertTrue( $helper->supports_generation_option( 'openai', 'gpt-test', 'temperature', 0.2 ) );
		$this->assertFalse( $helper->supports_generation_option( 'openai', 'gpt-test', 'top_p', 0.9 ) );
	}

	public function test_treats_unavailable_metadata_as_supported_until_provider_reports_error(): void {
		$helper = Model_Option_Helper::get_instance();

		$this->assertTrue( $helper->supports_generation_option( 'custom', 'model-a', 'top_p', 0.9 ) );
	}

	public function test_honors_supported_option_value_constraints(): void {
		WordPress_Stubs::$ai_provider_models = [
			'openai' => [
				'gpt-test' => new ModelMetadata(
					'gpt-test',
					'GPT Test',
					[],
					[
						new SupportedOption( 'topP', [ 0.5 ] ),
					]
				),
			],
		];

		$helper = Model_Option_Helper::get_instance();

		$this->assertTrue( $helper->supports_generation_option( 'openai', 'gpt-test', 'top_p', 0.5 ) );
		$this->assertFalse( $helper->supports_generation_option( 'openai', 'gpt-test', 'top_p', 0.9 ) );
	}

	public function test_records_unsupported_parameter_errors_for_future_requests(): void {
		$helper = Model_Option_Helper::get_instance();

		$option = $helper->record_unsupported_parameter_from_error(
			'openai',
			'gpt-test',
			new RuntimeException( "Unsupported parameter: 'top_p' is not supported with this model." )
		);

		$this->assertSame( 'top_p', $option );
		$this->assertTrue( $helper->has_learned_unsupported_generation_option( 'openai', 'gpt-test', 'top_p' ) );
		$this->assertFalse( $helper->supports_generation_option( 'openai', 'gpt-test', 'top_p', 0.9 ) );
	}

	public function test_generation_option_summary_exposes_unsupported_labels(): void {
		WordPress_Stubs::$ai_provider_models = [
			'openai' => [
				'gpt-test' => new ModelMetadata(
					'gpt-test',
					'GPT Test',
					[],
					[
						new SupportedOption( 'temperature' ),
					]
				),
			],
		];

		$helper  = Model_Option_Helper::get_instance();
		$summary = $helper->get_generation_option_summary( 'openai', 'gpt-test' );

		$this->assertContains( 'temperature', $summary['supported_options'] );
		$this->assertContains( 'top_p', $summary['unsupported_generation_options'] );
		$this->assertContains( 'Top P', $summary['unsupported_generation_option_labels'] );
	}
}

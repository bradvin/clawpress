<?php
/**
 * Tests for model helper.
 *
 * @package ClawPress\Tests
 */

declare( strict_types=1 );

namespace ClawPress\Tests\Unit;

use ClawPress\Helpers\Model_Helper;
use ClawPress\Tests\Support\TestCase;

final class ModelHelperTest extends TestCase {
	public function test_returns_options_for_supported_provider(): void {
		$model_helper = Model_Helper::get_instance();
		$options      = $model_helper->get_options_for_provider( 'openai' );

		$this->assertNotEmpty( $options );
		$this->assertSame( 'gpt-5.2-codex', $options[0]['id'] );
		$this->assertSame( 'GPT-5.2 Codex', $options[0]['label'] );
	}

	public function test_returns_empty_options_for_unsupported_provider(): void {
		$model_helper = Model_Helper::get_instance();
		$options      = $model_helper->get_options_for_provider( 'not-a-provider' );

		$this->assertSame( [], $options );
	}

	public function test_returns_default_model_for_supported_provider(): void {
		$model_helper = Model_Helper::get_instance();

		$this->assertSame( 'gpt-5.2-codex', $model_helper->get_default_model_for_provider( 'openai' ) );
	}

	public function test_returns_empty_default_model_for_unsupported_provider(): void {
		$model_helper = Model_Helper::get_instance();

		$this->assertSame( '', $model_helper->get_default_model_for_provider( 'not-a-provider' ) );
	}
}

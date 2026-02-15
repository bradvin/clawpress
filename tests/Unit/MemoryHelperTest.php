<?php
/**
 * Tests for memory helper.
 *
 * @package ClawPress\Tests
 */

declare( strict_types=1 );

namespace ClawPress\Tests\Unit;

use ClawPress\Helpers\Memory_Helper;
use ClawPress\Tests\Support\TestCase;

final class MemoryHelperTest extends TestCase {
	public function test_save_list_context_and_clear_memory_files(): void {
		$helper = Memory_Helper::get_instance();

		$long_term_result = $helper->save_long_term_memory( 'Long-term memory content.' );
		$daily_result     = $helper->save_daily_memory( 'Daily memory content.', strtotime( '2026-02-15 00:00:00 UTC' ) );

		$this->assertSame( true, $long_term_result['success'] );
		$this->assertSame( true, $daily_result['success'] );

		$rows = $helper->list_memories( 0 );
		$this->assertCount( 2, $rows );
		$this->assertSame( 'memory.md', $rows[0]['filename'] );
		$this->assertSame( 'long_term', $rows[0]['type'] );
		$this->assertSame( 'memory-15022026.md', $rows[1]['filename'] );
		$this->assertSame( 'daily', $rows[1]['type'] );

		$context = $helper->build_memory_context();
		$this->assertStringContainsString( '## memory.md', $context );
		$this->assertStringContainsString( 'Long-term memory content.', $context );
		$this->assertStringContainsString( '## memory-15022026.md', $context );
		$this->assertStringContainsString( 'Daily memory content.', $context );

		$deleted_count = $helper->clear_memories();
		$this->assertSame( 2, $deleted_count );
		$this->assertSame( [], $helper->list_memories( 0 ) );
	}

	public function test_build_daily_memory_filename_uses_expected_pattern(): void {
		$filename = Memory_Helper::get_instance()->build_daily_memory_filename( strtotime( '2026-02-05 12:30:00 UTC' ) );

		$this->assertSame( 'memory-05022026.md', $filename );
	}
}

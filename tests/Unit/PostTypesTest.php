<?php
/**
 * Tests for post type module.
 *
 * @package ClawPress\Tests
 */

declare( strict_types=1 );

namespace ClawPress\Tests\Unit;

use ClawPress\PostTypes\Post_Types;
use ClawPress\Tests\Support\TestCase;
use ClawPress\Tests\Support\WordPress_Stubs;

final class PostTypesTest extends TestCase {
	public function test_register_adds_init_hook(): void {
		new Post_Types();

		$hooks = array_column( WordPress_Stubs::$actions, 'hook' );

		$this->assertCount( 3, WordPress_Stubs::$actions );
		$this->assertContains( 'init', $hooks );
		$this->assertContains( 'use_block_editor_for_post_type', $hooks );
		$this->assertContains( 'admin_head', $hooks );
	}

	public function test_register_agent_file_post_type_uses_expected_args(): void {
		$post_types = new Post_Types();
		$post_types->register_agent_file_post_type();

		$this->assertCount( 1, WordPress_Stubs::$registered_post_types );
		$this->assertSame(
			Post_Types::AGENT_FILE_POST_TYPE,
			WordPress_Stubs::$registered_post_types[0]['post_type']
		);
		$this->assertTrue( WordPress_Stubs::$registered_post_types[0]['args']['show_in_rest'] );
		$this->assertSame(
			array( 'title', 'editor', 'revisions', 'page-attributes' ),
			WordPress_Stubs::$registered_post_types[0]['args']['supports']
		);
	}
}

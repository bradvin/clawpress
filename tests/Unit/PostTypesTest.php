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
		Post_Types::init();

		$this->assertCount( 1, WordPress_Stubs::$actions );
		$this->assertSame( 'init', WordPress_Stubs::$actions[0]['hook'] );
	}

	public function test_register_agent_file_post_type_uses_expected_args(): void {
		Post_Types::register_agent_file_post_type();

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

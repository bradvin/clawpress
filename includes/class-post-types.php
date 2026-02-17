<?php
/**
 * Custom post type registration.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\PostTypes;

defined( 'ABSPATH' ) || exit;

/**
 * Post type module.
 */
final class Post_Types {
	public const AGENT_FILE_POST_TYPE   = 'clawpress_agent_file';
	public const AGENT_MEMORY_POST_TYPE = 'clawpress_agent_mem';

	/**
	 * Register all hooks for post types.
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'register_post_types' ] );
		add_filter( 'use_block_editor_for_post_type', [ $this, 'use_block_editor_for_post_type' ], 10, 2 );
		add_action( 'admin_head', [ $this, 'handle_agent_post_type_admin_head' ] );
	}

	/**
	 * Register custom post types.
	 *
	 * @see https://developer.wordpress.org/reference/functions/register_post_type/
	 */
	public function register_post_types(): void {
		$this->register_agent_file_post_type();
		$this->register_agent_memory_post_type();
	}

	/**
	 * Register the Agent File custom post type.
	 */
	public function register_agent_file_post_type(): void {
		$labels = [
			'name'               => __( 'Agent Files', 'clawpress' ),
			'singular_name'      => __( 'Agent File', 'clawpress' ),
			'add_new'            => __( 'Add New', 'clawpress' ),
			'add_new_item'       => __( 'Add New Agent File', 'clawpress' ),
			'edit_item'          => __( 'Edit Agent File', 'clawpress' ),
			'new_item'           => __( 'New Agent File', 'clawpress' ),
			'view_item'          => __( 'View Agent File', 'clawpress' ),
			'search_items'       => __( 'Search Agent Files', 'clawpress' ),
			'not_found'          => __( 'No agent files found.', 'clawpress' ),
			'not_found_in_trash' => __( 'No agent files found in Trash.', 'clawpress' ),
			'all_items'          => __( 'Agent Files', 'clawpress' ),
			'menu_name'          => __( 'Agent Files', 'clawpress' ),
		];

		register_post_type(
			self::AGENT_FILE_POST_TYPE,
			[
				'labels'              => $labels,
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => 'clawpress',
				'show_in_rest'        => true,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'query_var'           => false,
				'rewrite'             => false,
				'supports'            => [ 'title', 'editor', 'revisions', 'page-attributes' ],
			]
		);
	}

	/**
	 * Register the Agent Memory custom post type.
	 */
	public function register_agent_memory_post_type(): void {
		$labels = [
			'name'               => __( 'Agent Memories', 'clawpress' ),
			'singular_name'      => __( 'Agent Memory', 'clawpress' ),
			'add_new'            => __( 'Add New', 'clawpress' ),
			'add_new_item'       => __( 'Add New Agent Memory', 'clawpress' ),
			'edit_item'          => __( 'Edit Agent Memory', 'clawpress' ),
			'new_item'           => __( 'New Agent Memory', 'clawpress' ),
			'view_item'          => __( 'View Agent Memory', 'clawpress' ),
			'search_items'       => __( 'Search Agent Memories', 'clawpress' ),
			'not_found'          => __( 'No agent memories found.', 'clawpress' ),
			'not_found_in_trash' => __( 'No agent memories found in Trash.', 'clawpress' ),
			'all_items'          => __( 'Agent Memories', 'clawpress' ),
			'menu_name'          => __( 'Agent Memories', 'clawpress' ),
		];

		register_post_type(
			self::AGENT_MEMORY_POST_TYPE,
			[
				'labels'              => $labels,
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => 'clawpress',
				'show_in_rest'        => true,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'query_var'           => false,
				'rewrite'             => false,
				'supports'            => [ 'title', 'editor', 'revisions', 'page-attributes' ],
			]
		);
	}

	/**
	 * Force classic editor for Agent Files while keeping REST support enabled.
	 *
	 * @param bool   $use_block_editor Whether the block editor should load for the post type.
	 * @param string $post_type        Post type slug.
	 * @return bool
	 */
	public function use_block_editor_for_post_type( bool $use_block_editor, string $post_type ): bool {
		$classic_editor_post_types = [
			self::AGENT_FILE_POST_TYPE,
			self::AGENT_MEMORY_POST_TYPE,
		];

		if ( in_array( $post_type, $classic_editor_post_types, true ) ) {
			return false;
		}

		return $use_block_editor;
	}

	/**
	 * Apply clean editor adjustments for agent post types.
	 */
	public function handle_agent_post_type_admin_head(): void {
		$screen                       = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$editor_adjustment_post_types = [
			self::AGENT_FILE_POST_TYPE,
			self::AGENT_MEMORY_POST_TYPE,
		];
		if ( ! $screen || ! in_array( $screen->post_type, $editor_adjustment_post_types, true ) ) {
			return;
		}

		// Remove any remaining media/editor button injections.
		remove_all_actions( 'media_buttons' );
		add_filter( 'quicktags_settings', [ $this, 'filter_agent_post_type_quicktags_settings' ] );
		add_filter( 'user_can_richedit', '__return_false' );
	}

	/**
	 * Disable Quicktags buttons for agent post types.
	 *
	 * @param array $settings Quicktags settings.
	 * @return array
	 */
	public function filter_agent_post_type_quicktags_settings( array $settings ): array {
		$settings['buttons'] = '';
		return $settings;
	}
}

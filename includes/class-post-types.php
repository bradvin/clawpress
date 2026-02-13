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
	public const AGENT_FILE_POST_TYPE = 'clawpress_agent_file';

	/**
	 * Register all hooks for post types.
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'register_post_types' ] );
	}

	/**
	 * Register custom post types.
	 *
	 * @see https://developer.wordpress.org/reference/functions/register_post_type/
	 */
	public function register_post_types(): void {
		$this->register_agent_file_post_type();
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
				'show_in_rest'        => true,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'query_var'           => false,
				'rewrite'             => false,
				'supports'            => [ 'title', 'editor', 'revisions', 'page-attributes' ],
			]
		);
	}
}

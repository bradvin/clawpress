<?php
/**
 * Block registration for ClawPress.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Blocks;

defined( 'ABSPATH' ) || exit;

/**
 * Register blocks from the build directory.
 *
 * @return void
 */
function register_blocks() {
	$blocks_dir = CLAWPRESS_DIR . 'build/blocks/';

	if ( ! is_dir( $blocks_dir ) ) {
		return;
	}

	$block_folders = glob( $blocks_dir . '*', GLOB_ONLYDIR );

	foreach ( $block_folders as $block_folder ) {
		register_block_type( $block_folder );
	}
}
add_action( 'init', __NAMESPACE__ . '\register_blocks' );

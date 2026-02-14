<?php
/**
 * Agent file helper.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Helpers;

use ClawPress\PostTypes\Post_Types;
use Throwable;

defined( 'ABSPATH' ) || exit;

/**
 * Shared helper for agent file bootstrap logic.
 */
final class Agent_File_Helper {
	/**
	 * Post meta key storing source template path.
	 */
	public const META_TEMPLATE_PATH = 'clawpress_agent_file_template_path';

	/**
	 * Post meta key storing logical path.
	 */
	public const META_LOGICAL_PATH = 'clawpress_agent_file_logical_path';

	/**
	 * Singleton instance.
	 *
	 * @var ?self
	 */
	private static ?self $instance = null;

	/**
	 * Constructor.
	 */
	private function __construct() {}

	/**
	 * Get singleton instance.
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Bootstrap default agent files from the plugin templates directory.
	 *
	 * This operation is idempotent: existing template-backed posts are skipped.
	 *
	 * @return array<string,mixed>
	 */
	public function create_default_agent_files_from_templates(): array {
		$template_files = $this->get_template_files();

		$created = [];
		$skipped = [];
		$errors  = [];

		foreach ( $template_files as $relative_path => $absolute_path ) {
			$slug             = $this->build_slug_from_template_path( $relative_path );
			$existing_post_id = $this->find_existing_agent_file_post_id( $relative_path, $slug );

			if ( $existing_post_id > 0 ) {
				$skipped[] = [
					'post_id'       => $existing_post_id,
					'template_path' => $relative_path,
				];
				continue;
			}

			$content = file_get_contents( $absolute_path );
			if ( false === $content ) {
				$errors[] = [
					'template_path' => $relative_path,
					'error'         => 'read_failed',
				];
				continue;
			}

			$post_id = wp_insert_post(
				[
					'post_type'    => Post_Types::AGENT_FILE_POST_TYPE,
					'post_status'  => 'publish',
					'post_title'   => basename( $relative_path ),
					'post_name'    => $slug,
					'post_content' => (string) $content,
				],
				true
			);

			if ( is_wp_error( $post_id ) ) {
				$errors[] = [
					'template_path' => $relative_path,
					'error'         => $post_id->get_error_message(),
				];
				continue;
			}

			$post_id = (int) $post_id;
			update_post_meta( $post_id, self::META_TEMPLATE_PATH, $relative_path );
			update_post_meta( $post_id, self::META_LOGICAL_PATH, $relative_path );

			$created[] = [
				'post_id'       => $post_id,
				'template_path' => $relative_path,
			];
		}

		return [
			'success' => [] === $errors,
			'created' => $created,
			'skipped' => $skipped,
			'errors'  => $errors,
		];
	}

	/**
	 * Check whether all default template-backed agent files exist.
	 */
	public function has_default_agent_files_from_templates(): bool {
		if ( ! function_exists( 'get_posts' ) ) {
			return false;
		}

		$template_files = $this->get_template_files();
		if ( [] === $template_files ) {
			return false;
		}

		foreach ( $template_files as $relative_path => $absolute_path ) {
			unset( $absolute_path );
			$slug             = $this->build_slug_from_template_path( $relative_path );
			$existing_post_id = $this->find_existing_agent_file_post_id( $relative_path, $slug );

			if ( $existing_post_id <= 0 ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Resolve all template files in templates directory.
	 *
	 * @return array<string,string> Relative path => absolute path.
	 */
	private function get_template_files(): array {
		$template_root = rtrim( (string) CLAWPRESS_DIR, '/\\' ) . '/templates';
		if ( ! is_dir( $template_root ) ) {
			return [];
		}

		$template_files = [];

		try {
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator(
					$template_root,
					\FilesystemIterator::SKIP_DOTS
				)
			);

			foreach ( $iterator as $file_info ) {
				if ( ! $file_info instanceof \SplFileInfo || ! $file_info->isFile() ) {
					continue;
				}

				$absolute_path = (string) $file_info->getPathname();
				$relative_path = ltrim( str_replace( [ $template_root, '\\' ], [ '', '/' ], $absolute_path ), '/' );
				if ( '' === $relative_path ) {
					continue;
				}

				$template_files[ $relative_path ] = $absolute_path;
			}
		} catch ( Throwable $throwable ) {
			unset( $throwable );
			return [];
		}

		ksort( $template_files );
		return $template_files;
	}

	/**
	 * Build deterministic post slug from relative template path.
	 *
	 * @param string $relative_path Relative template path.
	 */
	private function build_slug_from_template_path( string $relative_path ): string {
		$slug_source = strtolower( str_replace( [ '\\', '/', '.' ], '-', $relative_path ) );
		$slug_source = (string) preg_replace( '/-+/', '-', $slug_source );
		$slug_source = trim( $slug_source, '-' );
		$slug        = sanitize_title( $slug_source );

		if ( '' !== $slug ) {
			return $slug;
		}

		return 'agent-file-' . substr( md5( $relative_path ), 0, 8 );
	}

	/**
	 * Find an existing agent file post for template path/slug.
	 *
	 * @param string $relative_path Relative template path.
	 * @param string $slug Post slug candidate.
	 */
	private function find_existing_agent_file_post_id( string $relative_path, string $slug ): int {
		$existing_by_meta = get_posts(
			[
				'post_type'      => Post_Types::AGENT_FILE_POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => self::META_TEMPLATE_PATH,
				'meta_value'     => $relative_path,
			]
		);

		if ( is_array( $existing_by_meta ) && ! empty( $existing_by_meta[0] ) ) {
			return (int) $existing_by_meta[0];
		}

		$existing_by_slug = get_posts(
			[
				'post_type'      => Post_Types::AGENT_FILE_POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'name'           => $slug,
			]
		);

		if ( is_array( $existing_by_slug ) && ! empty( $existing_by_slug[0] ) ) {
			return (int) $existing_by_slug[0];
		}

		return 0;
	}
}

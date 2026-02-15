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

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads local plugin template file.
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
	 * Resolve content for a logical agent file path.
	 *
	 * Resolution order:
	 * 1. `agent-file` post content by logical path.
	 *
	 * @param string $logical_path Logical file path (e.g. `SOUL.md`).
	 */
	public function get_file_content_by_logical_path( string $logical_path ): string {
		$normalized_path = $this->normalize_logical_path( $logical_path );
		if ( '' === $normalized_path ) {
			return '';
		}

		$slug    = $this->build_slug_from_template_path( $normalized_path );
		$post_id = $this->find_existing_agent_file_post_id( $normalized_path, $slug );
		if ( $post_id > 0 && function_exists( 'get_post' ) ) {
			$post = get_post( $post_id );
			if ( $post instanceof \WP_Post && is_string( $post->post_content ) ) {
				return $post->post_content;
			}
		}
		return '';
	}

	/**
	 * Resolve one agent file entry by logical path.
	 *
	 * @param string $logical_path Logical file path.
	 * @return array<string,mixed>|null
	 */
	public function get_file_entry_by_logical_path( string $logical_path ): ?array {
		$normalized_path = $this->normalize_logical_path( $logical_path );
		if ( '' === $normalized_path || ! $this->is_safe_logical_path( $normalized_path ) ) {
			return null;
		}

		$slug    = $this->build_slug_from_template_path( $normalized_path );
		$post_id = $this->find_existing_agent_file_post_id( $normalized_path, $slug );
		if ( $post_id <= 0 || ! function_exists( 'get_post' ) ) {
			return null;
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return null;
		}

		$stored_path = get_post_meta( (int) $post->ID, self::META_LOGICAL_PATH, true );
		$stored_path = $this->normalize_logical_path( (string) $stored_path );
		if ( '' === $stored_path ) {
			$stored_path = $normalized_path;
		}

		return [
			'post_id'      => (int) $post->ID,
			'logical_path' => $stored_path,
			'title'        => (string) $post->post_title,
			'content'      => (string) $post->post_content,
		];
	}

	/**
	 * Upsert one agent-file by logical path.
	 *
	 * @param string   $logical_path Logical file path.
	 * @param string   $content File content.
	 * @param int|null $author_id Optional author ID.
	 * @return array<string,mixed>
	 */
	public function upsert_file_by_logical_path( string $logical_path, string $content, ?int $author_id = null ): array {
		$normalized_path = $this->normalize_logical_path( $logical_path );
		if ( '' === $normalized_path || ! $this->is_safe_logical_path( $normalized_path ) ) {
			return [
				'success' => false,
				'error'   => 'invalid_logical_path',
			];
		}

		if ( ! function_exists( 'wp_insert_post' ) || ! function_exists( 'is_wp_error' ) ) {
			return [
				'success' => false,
				'error'   => 'wp_insert_post_unavailable',
			];
		}

		$slug             = $this->build_slug_from_template_path( $normalized_path );
		$existing_post_id = $this->find_existing_agent_file_post_id( $normalized_path, $slug );
		$resolved_author  = null === $author_id || $author_id <= 0
			? ( function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0 )
			: $author_id;

		$post_payload = [
			'post_type'    => Post_Types::AGENT_FILE_POST_TYPE,
			'post_status'  => 'publish',
			'post_title'   => basename( $normalized_path ),
			'post_name'    => $slug,
			'post_content' => $content,
			'post_author'  => $resolved_author,
		];

		if ( $existing_post_id > 0 ) {
			$post_payload['ID'] = $existing_post_id;
		}

		$post_id = wp_insert_post( $post_payload, true );
		if ( is_wp_error( $post_id ) ) {
			return [
				'success' => false,
				'error'   => $post_id->get_error_message(),
			];
		}

		$post_id = (int) $post_id;
		update_post_meta( $post_id, self::META_LOGICAL_PATH, $normalized_path );

		return [
			'success'      => true,
			'post_id'      => $post_id,
			'logical_path' => $normalized_path,
			'source'       => 'agent-file',
		];
	}

	/**
	 * Delete one agent-file by logical path.
	 *
	 * @param string $logical_path Logical file path.
	 * @return array<string,mixed>
	 */
	public function delete_file_by_logical_path( string $logical_path ): array {
		$normalized_path = $this->normalize_logical_path( $logical_path );
		if ( '' === $normalized_path || ! $this->is_safe_logical_path( $normalized_path ) ) {
			return [
				'success' => false,
				'error'   => 'invalid_logical_path',
			];
		}

		$slug             = $this->build_slug_from_template_path( $normalized_path );
		$existing_post_id = $this->find_existing_agent_file_post_id( $normalized_path, $slug );
		if ( $existing_post_id <= 0 ) {
			return [
				'success' => false,
				'error'   => 'file_not_found',
			];
		}

		$deleted = function_exists( 'wp_delete_post' )
			? wp_delete_post( $existing_post_id, false )
			: false;
		if ( false === $deleted || null === $deleted ) {
			return [
				'success' => false,
				'error'   => 'delete_failed',
			];
		}

		return [
			'success'      => true,
			'post_id'      => $existing_post_id,
			'logical_path' => $normalized_path,
			'source'       => 'agent-file',
		];
	}

	/**
	 * List available agent-file records.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function list_files(): array {
		if ( ! function_exists( 'get_posts' ) ) {
			return [];
		}

		$posts = get_posts(
			[
				'post_type'      => Post_Types::AGENT_FILE_POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			]
		);

		if ( ! is_array( $posts ) ) {
			return [];
		}

		$entries = [];
		foreach ( $posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			$logical_path = get_post_meta( (int) $post->ID, self::META_LOGICAL_PATH, true );
			$logical_path = $this->normalize_logical_path( (string) $logical_path );
			if ( '' === $logical_path ) {
				$logical_path = $this->normalize_logical_path( (string) $post->post_title );
			}

			if ( '' === $logical_path || ! $this->is_safe_logical_path( $logical_path ) ) {
				continue;
			}

			$entries[] = [
				'post_id'      => (int) $post->ID,
				'logical_path' => $logical_path,
				'title'        => (string) $post->post_title,
				'source'       => 'agent-file',
			];
		}

		return $entries;
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
		if ( ! function_exists( 'get_posts' ) ) {
			return 0;
		}

		$normalized_path = $this->normalize_logical_path( $relative_path );
		if ( '' === $normalized_path ) {
			return 0;
		}

		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Deterministic single-row lookup by logical/template path.
		$existing_by_logical_meta = get_posts(
			[
				'post_type'      => Post_Types::AGENT_FILE_POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => self::META_LOGICAL_PATH,
				'meta_value'     => $normalized_path,
			]
		);

		if ( is_array( $existing_by_logical_meta ) && ! empty( $existing_by_logical_meta[0] ) ) {
			return (int) $existing_by_logical_meta[0];
		}

		$existing_by_meta = get_posts(
			[
				'post_type'      => Post_Types::AGENT_FILE_POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => self::META_TEMPLATE_PATH,
				'meta_value'     => $normalized_path,
			]
		);
		// phpcs:enable

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

	/**
	 * Normalize a logical path into a canonical slash-based value.
	 *
	 * @param string $logical_path Raw logical path.
	 */
	private function normalize_logical_path( string $logical_path ): string {
		$normalized_path = str_replace( '\\', '/', trim( $logical_path ) );
		$normalized_path = ltrim( $normalized_path, '/' );
		$normalized_path = (string) preg_replace( '#/+#', '/', $normalized_path );
		return trim( $normalized_path );
	}

	/**
	 * Check whether the logical path is safe.
	 *
	 * @param string $logical_path Normalized logical path.
	 */
	private function is_safe_logical_path( string $logical_path ): bool {
		if ( '' === $logical_path ) {
			return false;
		}

		foreach ( explode( '/', $logical_path ) as $segment ) {
			if ( '' === $segment || '.' === $segment || '..' === $segment ) {
				return false;
			}
		}

		return true;
	}
}

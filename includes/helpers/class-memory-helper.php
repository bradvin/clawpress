<?php
/**
 * Memory helper.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Helpers;

use ClawPress\PostTypes\Post_Types;

defined( 'ABSPATH' ) || exit;

/**
 * Shared memory persistence helper backed by the memory CPT.
 */
final class Memory_Helper {
	/**
	 * Long-term memory filename.
	 */
	public const LONG_TERM_MEMORY_FILENAME = 'memory.md';

	/**
	 * Daily memory filename regex.
	 */
	private const DAILY_MEMORY_FILENAME_REGEX = '/^memory-(\d{8})\.md$/';

	/**
	 * Singleton instance.
	 *
	 * @var ?self
	 */
	private static ?self $instance = null;

	/**
	 * Settings helper.
	 *
	 * @var Settings_Helper
	 */
	private Settings_Helper $settings_helper;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->settings_helper = Settings_Helper::get_instance();
	}

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
	 * Build a daily memory filename for a timestamp.
	 *
	 * @param int|null $timestamp Optional Unix timestamp.
	 */
	public function build_daily_memory_filename( ?int $timestamp = null ): string {
		$resolved_timestamp = null === $timestamp || $timestamp <= 0 ? time() : $timestamp;
		$date_fragment      = function_exists( 'wp_date' )
			? wp_date( 'dmY', $resolved_timestamp )
			: gmdate( 'dmY', $resolved_timestamp );

		return sprintf( 'memory-%s.md', $date_fragment );
	}

	/**
	 * Save long-term memory content (`memory.md`).
	 *
	 * @param string $content Memory content.
	 * @return array<string,mixed>
	 */
	public function save_long_term_memory( string $content ): array {
		return $this->upsert_memory_file( self::LONG_TERM_MEMORY_FILENAME, $content );
	}

	/**
	 * Save daily memory content for a date (`memory-ddmmyyyy.md`).
	 *
	 * @param string   $content Memory content.
	 * @param int|null $timestamp Optional timestamp.
	 * @return array<string,mixed>
	 */
	public function save_daily_memory( string $content, ?int $timestamp = null ): array {
		return $this->upsert_memory_file(
			$this->build_daily_memory_filename( $timestamp ),
			$content
		);
	}

	/**
	 * Append one entry to a daily memory file.
	 *
	 * @param string   $entry Entry text.
	 * @param int|null $timestamp Optional timestamp.
	 * @return array<string,mixed>
	 */
	public function append_daily_memory_entry( string $entry, ?int $timestamp = null ): array {
		$entry = trim( $entry );
		if ( '' === $entry ) {
			return [
				'success' => false,
				'error'   => 'empty_entry',
			];
		}

		$filename       = $this->build_daily_memory_filename( $timestamp );
		$existing       = $this->get_memory_content_by_filename( $filename );
		$merged_content = '' === trim( $existing ) ? $entry : trim( $existing ) . "\n\n" . $entry;

		return $this->upsert_memory_file( $filename, $merged_content );
	}

	/**
	 * Add one short-term memory entry to a daily file.
	 *
	 * @param string   $entry Entry content.
	 * @param int|null $timestamp Optional timestamp.
	 * @return array<string,mixed>
	 */
	public function add_short_term_memory( string $entry, ?int $timestamp = null ): array {
		return $this->append_daily_memory_entry( $entry, $timestamp );
	}

	/**
	 * Replace a short-term memory daily file with new content.
	 *
	 * @param string   $content Memory content.
	 * @param int|null $timestamp Optional timestamp.
	 * @return array<string,mixed>
	 */
	public function update_short_term_memory( string $content, ?int $timestamp = null ): array {
		$content = trim( $content );
		if ( '' === $content ) {
			return [
				'success' => false,
				'error'   => 'empty_content',
			];
		}

		return $this->save_daily_memory( $content, $timestamp );
	}

	/**
	 * Delete a short-term daily memory file.
	 *
	 * @param int|null $timestamp Optional timestamp.
	 * @return array<string,mixed>
	 */
	public function delete_short_term_memory( ?int $timestamp = null ): array {
		$filename = $this->build_daily_memory_filename( $timestamp );
		return $this->delete_memory_file( $filename );
	}

	/**
	 * Add an entry to long-term memory.
	 *
	 * @param string $entry Entry content.
	 * @return array<string,mixed>
	 */
	public function add_long_term_memory( string $entry ): array {
		$entry = trim( $entry );
		if ( '' === $entry ) {
			return [
				'success' => false,
				'error'   => 'empty_entry',
			];
		}

		$existing = trim( $this->get_long_term_memory_content() );
		$content  = '' === $existing ? $entry : $existing . "\n\n" . $entry;

		return $this->save_long_term_memory( $content );
	}

	/**
	 * Replace long-term memory content.
	 *
	 * @param string $content Memory content.
	 * @return array<string,mixed>
	 */
	public function update_long_term_memory( string $content ): array {
		$content = trim( $content );
		if ( '' === $content ) {
			return [
				'success' => false,
				'error'   => 'empty_content',
			];
		}

		return $this->save_long_term_memory( $content );
	}

	/**
	 * Delete long-term memory file.
	 *
	 * @return array<string,mixed>
	 */
	public function delete_long_term_memory(): array {
		return $this->delete_memory_file( self::LONG_TERM_MEMORY_FILENAME );
	}

	/**
	 * Get content for `memory.md`.
	 */
	public function get_long_term_memory_content(): string {
		return $this->get_memory_content_by_filename( self::LONG_TERM_MEMORY_FILENAME );
	}

	/**
	 * Get content for a daily memory file.
	 *
	 * @param int|null $timestamp Optional timestamp.
	 */
	public function get_daily_memory_content( ?int $timestamp = null ): string {
		return $this->get_memory_content_by_filename(
			$this->build_daily_memory_filename( $timestamp )
		);
	}

	/**
	 * Build memory context text for prompt assembly.
	 *
	 * Includes long-term memory plus newest daily memories.
	 *
	 * @param int $daily_limit Number of daily files to include.
	 */
	public function build_memory_context( int $daily_limit = 5 ): string {
		$entries  = $this->list_memories( 0 );
		$sections = [];

		foreach ( $entries as $entry ) {
			if ( 'long_term' !== $entry['type'] || '' === trim( $entry['content'] ) ) {
				continue;
			}

			$sections[] = sprintf( "## %s\n\n%s", $entry['filename'], trim( $entry['content'] ) );
			break;
		}

		$daily_added = 0;
		foreach ( $entries as $entry ) {
			if ( 'daily' !== $entry['type'] || '' === trim( $entry['content'] ) ) {
				continue;
			}

			$sections[] = sprintf( "## %s\n\n%s", $entry['filename'], trim( $entry['content'] ) );
			++$daily_added;

			if ( $daily_added >= max( 0, $daily_limit ) ) {
				break;
			}
		}

		return implode( "\n\n", $sections );
	}

	/**
	 * List memories from the memory CPT.
	 *
	 * @param int $limit Max entries to return; `0` for all.
	 * @return array<int,array{post_id:int,filename:string,type:string,content:string,daily_timestamp:int|null}>
	 */
	public function list_memories( int $limit = 20 ): array {
		if ( ! function_exists( 'get_posts' ) ) {
			return [];
		}

		$posts = get_posts(
			[
				'post_type'      => Post_Types::AGENT_MEMORY_POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
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

			$filename = $this->normalize_filename( (string) $post->post_title );
			if ( ! $this->is_valid_filename( $filename ) ) {
				continue;
			}

			$entries[] = [
				'post_id'         => (int) $post->ID,
				'filename'        => $filename,
				'type'            => self::LONG_TERM_MEMORY_FILENAME === $filename ? 'long_term' : 'daily',
				'content'         => (string) $post->post_content,
				'daily_timestamp' => $this->parse_daily_memory_timestamp( $filename ),
			];
		}

		usort(
			$entries,
			static function ( array $left, array $right ): int {
				if ( $left['type'] !== $right['type'] ) {
					return 'long_term' === $left['type'] ? -1 : 1;
				}

				$left_daily_timestamp  = null === $left['daily_timestamp'] ? 0 : (int) $left['daily_timestamp'];
				$right_daily_timestamp = null === $right['daily_timestamp'] ? 0 : (int) $right['daily_timestamp'];
				if ( $left_daily_timestamp !== $right_daily_timestamp ) {
					return $right_daily_timestamp <=> $left_daily_timestamp;
				}

				return (int) $right['post_id'] <=> (int) $left['post_id'];
			}
		);

		if ( $limit > 0 ) {
			$entries = array_slice( $entries, 0, $limit );
		}

		return $entries;
	}

	/**
	 * Clear all memory files from the memory CPT.
	 *
	 * @return int Number of deleted rows.
	 */
	public function clear_memories(): int {
		$entries       = $this->list_memories( 0 );
		$deleted_count = 0;

		foreach ( $entries as $entry ) {
			$post_id = isset( $entry['post_id'] ) ? (int) $entry['post_id'] : 0;
			if ( $post_id <= 0 || ! function_exists( 'wp_delete_post' ) ) {
				continue;
			}

			$deleted_post = wp_delete_post( $post_id, true );
			if ( false !== $deleted_post && null !== $deleted_post ) {
				++$deleted_count;
			}
		}

		return $deleted_count;
	}

	/**
	 * Get memory content by filename.
	 *
	 * @param string $filename Memory filename.
	 */
	private function get_memory_content_by_filename( string $filename ): string {
		$filename = $this->normalize_filename( $filename );
		if ( ! $this->is_valid_filename( $filename ) ) {
			return '';
		}

		$post = $this->find_memory_post_by_filename( $filename );
		if ( ! $post instanceof \WP_Post ) {
			return '';
		}

		return (string) $post->post_content;
	}

	/**
	 * Insert or update one memory file post.
	 *
	 * @param string $filename Memory filename.
	 * @param string $content Memory content.
	 * @return array<string,mixed>
	 */
	private function upsert_memory_file( string $filename, string $content ): array {
		if ( ! function_exists( 'wp_insert_post' ) || ! function_exists( 'is_wp_error' ) ) {
			return [
				'success' => false,
				'error'   => 'wp_insert_post_unavailable',
			];
		}

		$filename = $this->normalize_filename( $filename );
		if ( ! $this->is_valid_filename( $filename ) ) {
			return [
				'success' => false,
				'error'   => 'invalid_filename',
			];
		}

		$existing_post = $this->find_memory_post_by_filename( $filename );
		$agent_user_id = $this->settings_helper->resolve_agent_user_id();
		$author_id     = $agent_user_id > 0
			? $agent_user_id
			: ( function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0 );

		$post_data = [
			'post_type'    => Post_Types::AGENT_MEMORY_POST_TYPE,
			'post_status'  => 'publish',
			'post_title'   => $filename,
			'post_name'    => $this->build_slug_from_filename( $filename ),
			'post_content' => (string) $content,
			'post_author'  => $author_id,
		];

		if ( $existing_post instanceof \WP_Post ) {
			$post_data['ID'] = (int) $existing_post->ID;
		}

		$post_id = wp_insert_post( $post_data, true );
		if ( is_wp_error( $post_id ) ) {
			return [
				'success' => false,
				'error'   => $post_id->get_error_message(),
			];
		}

		return [
			'success'  => true,
			'post_id'  => (int) $post_id,
			'filename' => $filename,
			'type'     => self::LONG_TERM_MEMORY_FILENAME === $filename ? 'long_term' : 'daily',
		];
	}

	/**
	 * Find one memory post by filename.
	 *
	 * @param string $filename Memory filename.
	 */
	private function find_memory_post_by_filename( string $filename ): ?\WP_Post {
		if ( ! function_exists( 'get_posts' ) || ! function_exists( 'get_post' ) ) {
			return null;
		}

		$slug = $this->build_slug_from_filename( $filename );
		$ids  = get_posts(
			[
				'post_type'      => Post_Types::AGENT_MEMORY_POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'name'           => $slug,
			]
		);

		if ( is_array( $ids ) && ! empty( $ids[0] ) ) {
			$post = get_post( (int) $ids[0] );
			if ( $post instanceof \WP_Post && $filename === $this->normalize_filename( (string) $post->post_title ) ) {
				return $post;
			}
		}

		$posts = get_posts(
			[
				'post_type'      => Post_Types::AGENT_MEMORY_POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
			]
		);
		if ( ! is_array( $posts ) ) {
			return null;
		}

		foreach ( $posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			if ( $filename === $this->normalize_filename( (string) $post->post_title ) ) {
				return $post;
			}
		}

		return null;
	}

	/**
	 * Normalize memory filename to canonical lowercase format.
	 *
	 * @param string $filename Raw filename.
	 */
	private function normalize_filename( string $filename ): string {
		$normalized = strtolower( trim( str_replace( '\\', '/', $filename ) ) );
		$normalized = basename( $normalized );
		return trim( $normalized );
	}

	/**
	 * Check whether a filename is supported by this memory store.
	 *
	 * @param string $filename Normalized filename.
	 */
	private function is_valid_filename( string $filename ): bool {
		return self::LONG_TERM_MEMORY_FILENAME === $filename || 1 === preg_match( self::DAILY_MEMORY_FILENAME_REGEX, $filename );
	}

	/**
	 * Parse daily memory filename into timestamp.
	 *
	 * @param string $filename Normalized filename.
	 */
	private function parse_daily_memory_timestamp( string $filename ): ?int {
		if ( 1 !== preg_match( self::DAILY_MEMORY_FILENAME_REGEX, $filename, $matches ) ) {
			return null;
		}

		$date = \DateTimeImmutable::createFromFormat( 'dmY', (string) $matches[1], new \DateTimeZone( 'UTC' ) );
		if ( ! $date instanceof \DateTimeImmutable ) {
			return null;
		}

		return $date->getTimestamp();
	}

	/**
	 * Build deterministic slug for memory file names.
	 *
	 * @param string $filename Normalized filename.
	 */
	private function build_slug_from_filename( string $filename ): string {
		if ( ! function_exists( 'sanitize_title' ) ) {
			return 'memory-' . substr( md5( $filename ), 0, 8 );
		}

		$slug_source = str_replace( '.', '-', strtolower( $filename ) );
		$slug        = sanitize_title( $slug_source );

		if ( '' !== $slug ) {
			return $slug;
		}

		return 'memory-' . substr( md5( $filename ), 0, 8 );
	}

	/**
	 * Delete one memory file by filename.
	 *
	 * @param string $filename Memory filename.
	 * @return array<string,mixed>
	 */
	private function delete_memory_file( string $filename ): array {
		$filename = $this->normalize_filename( $filename );
		if ( ! $this->is_valid_filename( $filename ) ) {
			return [
				'success' => false,
				'error'   => 'invalid_filename',
			];
		}

		$post = $this->find_memory_post_by_filename( $filename );
		if ( ! $post instanceof \WP_Post ) {
			return [
				'success' => false,
				'error'   => 'memory_not_found',
			];
		}

		if ( ! function_exists( 'wp_delete_post' ) ) {
			return [
				'success' => false,
				'error'   => 'wp_delete_post_unavailable',
			];
		}

		$deleted = wp_delete_post( (int) $post->ID, true );
		if ( false === $deleted || null === $deleted ) {
			return [
				'success' => false,
				'error'   => 'delete_failed',
			];
		}

		return [
			'success'  => true,
			'post_id'  => (int) $post->ID,
			'filename' => $filename,
		];
	}
}

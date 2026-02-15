<?php
/**
 * Workspace helper.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Helpers;

use Throwable;

defined( 'ABSPATH' ) || exit;

/**
 * Shared workspace helper.
 */
final class Workspace_Helper {
	/**
	 * User meta key for persisted workspace hash.
	 */
	public const USER_META_WORKSPACE_HASH = 'clawpress_workspace_hash';

	/**
	 * Number of random bytes used to generate the workspace hash.
	 */
	private const WORKSPACE_HASH_BYTES = 8;

	/**
	 * Filesystem permissions.
	 */
	private const DIRECTORY_PERMISSIONS = 0750;
	private const FILE_PERMISSIONS      = 0640;

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
	 * Create (or ensure) workspace for an agent user.
	 *
	 * The persisted value is only a hash stored in user meta.
	 * The workspace path is always derived from uploads base + user ID + hash.
	 *
	 * @param int $user_id Agent user ID.
	 * @return array<string,mixed>
	 */
	public function create_workspace_for_agent_user( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return [
				'success' => false,
				'error'   => __( 'Invalid user ID for workspace creation.', 'clawpress' ),
			];
		}

		$workspace_hash = $this->get_workspace_hash_for_user( $user_id );

		if ( '' === $workspace_hash ) {
			$workspace_hash = $this->generate_workspace_hash();
			update_user_meta( $user_id, self::USER_META_WORKSPACE_HASH, $workspace_hash );
		}

		$workspace_path = $this->build_workspace_path_from_hash( $user_id, $workspace_hash );
		if ( '' === $workspace_path ) {
			return [
				'success' => false,
				'error'   => __( 'Unable to resolve uploads directory for workspace.', 'clawpress' ),
			];
		}

		$created = $this->ensure_workspace_structure( $workspace_path );
		if ( ! $created ) {
			return [
				'success' => false,
				'error'   => __( 'Unable to create workspace directory structure.', 'clawpress' ),
			];
		}

		return [
			'success'        => true,
			'user_id'        => $user_id,
			'workspace_hash' => $workspace_hash,
			'workspace_path' => $workspace_path,
		];
	}

	/**
	 * Backward-compatible wrapper with legacy typo in method name.
	 *
	 * @param int $user_id Agent user ID.
	 * @return array<string,mixed>
	 */
	public function create_worspace_for_agent_user( int $user_id ): array {
		return $this->create_workspace_for_agent_user( $user_id );
	}

	/**
	 * Return workspace path for an agent user.
	 *
	 * Path is always derived from stored hash; path itself is never persisted.
	 *
	 * @param int $user_id Agent user ID.
	 */
	public function get_workspace_path_for_agent_user( int $user_id ): string {
		if ( $user_id <= 0 ) {
			return '';
		}

		$workspace_hash = $this->get_workspace_hash_for_user( $user_id );
		if ( '' === $workspace_hash ) {
			return '';
		}

		return $this->build_workspace_path_from_hash( $user_id, $workspace_hash );
	}

	/**
	 * Return workspace path for a user, creating and persisting a hash if needed.
	 *
	 * This method does not create any directories; it only ensures a stable path can be displayed.
	 *
	 * @param int $user_id Agent user ID.
	 */
	public function ensure_workspace_path_for_agent_user( int $user_id ): string {
		if ( $user_id <= 0 ) {
			return '';
		}

		$workspace_hash = $this->get_workspace_hash_for_user( $user_id );
		if ( '' === $workspace_hash ) {
			$workspace_hash = $this->generate_workspace_hash();
			update_user_meta( $user_id, self::USER_META_WORKSPACE_HASH, $workspace_hash );
		}

		return $this->build_workspace_path_from_hash( $user_id, $workspace_hash );
	}

	/**
	 * Read one workspace file for a user.
	 *
	 * @param int    $user_id User ID.
	 * @param string $logical_path Workspace-relative path.
	 * @return array<string,mixed>
	 */
	public function read_workspace_file( int $user_id, string $logical_path ): array {
		$resolved_file = $this->resolve_workspace_file_path( $user_id, $logical_path, false );
		if ( isset( $resolved_file['error'] ) ) {
			return [
				'success' => false,
				'error'   => $resolved_file['error'],
			];
		}

		$file_path = isset( $resolved_file['file_path'] ) ? (string) $resolved_file['file_path'] : '';
		if ( '' === $file_path || ! is_file( $file_path ) ) {
			return [
				'success' => false,
				'error'   => 'file_not_found',
			];
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Workspace file read operation.
		$bytes = file_get_contents( $file_path );
		if ( false === $bytes ) {
			return [
				'success' => false,
				'error'   => 'read_failed',
			];
		}

		return [
			'success'      => true,
			'logical_path' => (string) $resolved_file['logical_path'],
			'content'      => (string) $bytes,
			'source'       => 'workspace',
		];
	}

	/**
	 * Write one workspace file for a user.
	 *
	 * @param int    $user_id User ID.
	 * @param string $logical_path Workspace-relative path.
	 * @param string $content File content.
	 * @return array<string,mixed>
	 */
	public function write_workspace_file( int $user_id, string $logical_path, string $content ): array {
		$resolved_file = $this->resolve_workspace_file_path( $user_id, $logical_path, true );
		if ( isset( $resolved_file['error'] ) ) {
			return [
				'success' => false,
				'error'   => $resolved_file['error'],
			];
		}

		$file_path = isset( $resolved_file['file_path'] ) ? (string) $resolved_file['file_path'] : '';
		if ( '' === $file_path ) {
			return [
				'success' => false,
				'error'   => 'invalid_file_path',
			];
		}

		$directory = dirname( $file_path );
		if ( '' === $directory || ! is_dir( $directory ) ) {
			if ( ! wp_mkdir_p( $directory ) ) {
				return [
					'success' => false,
					'error'   => 'mkdir_failed',
				];
			}
		}

		$written = file_put_contents( $file_path, $content );
		if ( false === $written ) {
			return [
				'success' => false,
				'error'   => 'write_failed',
			];
		}

		$this->apply_permissions( $file_path, self::FILE_PERMISSIONS );

		return [
			'success'      => true,
			'logical_path' => (string) $resolved_file['logical_path'],
			'bytes'        => (int) $written,
			'source'       => 'workspace',
		];
	}

	/**
	 * Delete one workspace file for a user.
	 *
	 * @param int    $user_id User ID.
	 * @param string $logical_path Workspace-relative path.
	 * @return array<string,mixed>
	 */
	public function delete_workspace_file( int $user_id, string $logical_path ): array {
		$resolved_file = $this->resolve_workspace_file_path( $user_id, $logical_path, false );
		if ( isset( $resolved_file['error'] ) ) {
			return [
				'success' => false,
				'error'   => $resolved_file['error'],
			];
		}

		$file_path = isset( $resolved_file['file_path'] ) ? (string) $resolved_file['file_path'] : '';
		if ( '' === $file_path || ! file_exists( $file_path ) ) {
			return [
				'success' => false,
				'error'   => 'file_not_found',
			];
		}

		$deleted = is_dir( $file_path )
			? $this->delete_directory_recursively( $file_path )
			: @unlink( $file_path );
		if ( ! $deleted ) {
			return [
				'success' => false,
				'error'   => 'delete_failed',
			];
		}

		return [
			'success'      => true,
			'logical_path' => (string) $resolved_file['logical_path'],
			'source'       => 'workspace',
		];
	}

	/**
	 * List workspace files for a user.
	 *
	 * @param int $user_id User ID.
	 * @return array<int,array<string,mixed>>
	 */
	public function list_workspace_files( int $user_id ): array {
		$workspace_path = $this->get_workspace_path_for_agent_user( $user_id );
		if ( '' === $workspace_path || ! is_dir( $workspace_path ) ) {
			return [];
		}

		$entries = [];
		try {
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator(
					$workspace_path,
					\FilesystemIterator::SKIP_DOTS
				)
			);

			foreach ( $iterator as $file_info ) {
				if ( ! $file_info instanceof \SplFileInfo || ! $file_info->isFile() ) {
					continue;
				}

				$path = (string) $file_info->getPathname();
				if ( '' === $path ) {
					continue;
				}

				$logical_path = ltrim( str_replace( [ $workspace_path, '\\' ], [ '', '/' ], $path ), '/' );
				$logical_path = $this->normalize_workspace_relative_path( $logical_path );
				if ( '' === $logical_path || ! $this->is_listable_workspace_file( $logical_path ) ) {
					continue;
				}

				$entries[] = [
					'logical_path' => $logical_path,
					'size'         => (int) $file_info->getSize(),
					'source'       => 'workspace',
				];
			}
		} catch ( Throwable $throwable ) {
			unset( $throwable );
			return [];
		}

		usort(
			$entries,
			static fn ( array $left, array $right ): int => strcmp( (string) $left['logical_path'], (string) $right['logical_path'] )
		);

		return $entries;
	}

	/**
	 * Return stored workspace hash for a user when valid.
	 *
	 * @param int $user_id User ID.
	 */
	private function get_workspace_hash_for_user( int $user_id ): string {
		$workspace_hash = get_user_meta( $user_id, self::USER_META_WORKSPACE_HASH, true );
		$workspace_hash = strtolower( trim( (string) $workspace_hash ) );

		// Accept both current (16 chars) and legacy (32 chars) hashes.
		if ( ! preg_match( '/^[a-f0-9]{16}(?:[a-f0-9]{16})?$/', $workspace_hash ) ) {
			return '';
		}

		return $workspace_hash;
	}

	/**
	 * Build workspace path using user ID and hash.
	 *
	 * @param int    $user_id User ID.
	 * @param string $workspace_hash Workspace hash.
	 */
	private function build_workspace_path_from_hash( int $user_id, string $workspace_hash ): string {
		$uploads = wp_upload_dir( null, false );
		if ( ! is_array( $uploads ) || ! isset( $uploads['basedir'] ) || ! is_string( $uploads['basedir'] ) ) {
			return '';
		}

		$base_dir = rtrim( $uploads['basedir'], '/\\' );
		if ( '' === $base_dir ) {
			return '';
		}

		return $base_dir . DIRECTORY_SEPARATOR . $user_id . DIRECTORY_SEPARATOR . $workspace_hash;
	}

	/**
	 * Ensure workspace directory and anti-browsing artifacts exist.
	 *
	 * @param string $workspace_path Absolute workspace path.
	 */
	private function ensure_workspace_structure( string $workspace_path ): bool {
		if ( '' === $workspace_path ) {
			return false;
		}

		if ( ! wp_mkdir_p( $workspace_path ) ) {
			return false;
		}

		$user_workspace_root = dirname( $workspace_path );

		$this->create_protection_files( $user_workspace_root );
		$this->create_protection_files( $workspace_path );

		$this->apply_permissions( $user_workspace_root, self::DIRECTORY_PERMISSIONS );
		$this->apply_permissions( $workspace_path, self::DIRECTORY_PERMISSIONS );
		$this->apply_permissions( $workspace_path . DIRECTORY_SEPARATOR . 'index.html', self::FILE_PERMISSIONS );
		$this->apply_permissions( $workspace_path . DIRECTORY_SEPARATOR . '.htaccess', self::FILE_PERMISSIONS );

		return true;
	}

	/**
	 * Create anti-browsing files in a directory.
	 *
	 * @param string $directory Absolute directory path.
	 */
	private function create_protection_files( string $directory ): void {
		if ( '' === $directory || ! is_dir( $directory ) ) {
			return;
		}

		$index_file_path = $directory . DIRECTORY_SEPARATOR . 'index.html';
		if ( ! file_exists( $index_file_path ) ) {
			file_put_contents( $index_file_path, '' );
		}

		$htaccess_path = $directory . DIRECTORY_SEPARATOR . '.htaccess';
		if ( ! file_exists( $htaccess_path ) ) {
			file_put_contents(
				$htaccess_path,
				"<IfModule mod_autoindex.c>\nOptions -Indexes\n</IfModule>\n"
			);
		}
	}

	/**
	 * Apply filesystem permissions when possible.
	 *
	 * @param string $path Absolute path.
	 * @param int    $permissions Octal permission mask.
	 */
	private function apply_permissions( string $path, int $permissions ): void {
		if ( '' === $path || ! file_exists( $path ) ) {
			return;
		}

		@chmod( $path, $permissions );
	}

	/**
	 * Resolve absolute file path for a workspace-relative path.
	 *
	 * @param int    $user_id User ID.
	 * @param string $logical_path Workspace-relative logical path.
	 * @param bool   $ensure_workspace Whether to ensure workspace directories exist.
	 * @return array<string,mixed>
	 */
	private function resolve_workspace_file_path( int $user_id, string $logical_path, bool $ensure_workspace ): array {
		if ( $user_id <= 0 ) {
			return [ 'error' => 'invalid_user' ];
		}

		$normalized_relative_path = $this->normalize_workspace_relative_path( $logical_path );
		if ( '' === $normalized_relative_path ) {
			return [ 'error' => 'invalid_logical_path' ];
		}

		if ( $ensure_workspace ) {
			$workspace_result = $this->create_workspace_for_agent_user( $user_id );
			if ( empty( $workspace_result['success'] ) ) {
				return [ 'error' => 'workspace_unavailable' ];
			}
		}

		$workspace_path = $this->get_workspace_path_for_agent_user( $user_id );
		if ( '' === $workspace_path ) {
			return [ 'error' => 'workspace_unavailable' ];
		}

		$file_path = $workspace_path . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $normalized_relative_path );
		if ( '' === $file_path ) {
			return [ 'error' => 'invalid_file_path' ];
		}

		$workspace_realpath = realpath( $workspace_path );
		if ( false !== $workspace_realpath ) {
			$file_path_directory = dirname( $file_path );
			$directory_realpath  = realpath( $file_path_directory );
			if ( false !== $directory_realpath && 0 !== strpos( $directory_realpath, $workspace_realpath ) ) {
				return [ 'error' => 'path_outside_workspace' ];
			}
		}

		return [
			'file_path'    => $file_path,
			'logical_path' => $normalized_relative_path,
		];
	}

	/**
	 * Normalize workspace-relative path.
	 *
	 * @param string $logical_path Workspace-relative path.
	 */
	private function normalize_workspace_relative_path( string $logical_path ): string {
		$logical_path = str_replace( '\\', '/', trim( $logical_path ) );
		$logical_path = ltrim( $logical_path, '/' );
		$logical_path = (string) preg_replace( '#/+#', '/', $logical_path );

		if ( '' === $logical_path ) {
			return '';
		}

		$segments = explode( '/', $logical_path );
		foreach ( $segments as $segment ) {
			if ( '' === $segment || '.' === $segment || '..' === $segment ) {
				return '';
			}
		}

		return $logical_path;
	}

	/**
	 * Determine whether a workspace file should appear in file listings.
	 *
	 * @param string $logical_path Workspace-relative logical path.
	 */
	private function is_listable_workspace_file( string $logical_path ): bool {
		$basename = basename( $logical_path );
		return ! in_array( $basename, [ 'index.html', '.htaccess' ], true );
	}

	/**
	 * Delete a directory recursively.
	 *
	 * @param string $directory Absolute directory path.
	 */
	private function delete_directory_recursively( string $directory ): bool {
		if ( '' === $directory || ! is_dir( $directory ) ) {
			return false;
		}

		try {
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator(
					$directory,
					\FilesystemIterator::SKIP_DOTS
				),
				\RecursiveIteratorIterator::CHILD_FIRST
			);

			foreach ( $iterator as $item ) {
				if ( $item->isDir() ) {
					if ( ! @rmdir( (string) $item->getPathname() ) ) {
						return false;
					}
					continue;
				}

				if ( ! @unlink( (string) $item->getPathname() ) ) {
					return false;
				}
			}
		} catch ( Throwable $throwable ) {
			unset( $throwable );
			return false;
		}

		return @rmdir( $directory );
	}

	/**
	 * Generate a random workspace hash.
	 */
	private function generate_workspace_hash(): string {
		try {
			return bin2hex( random_bytes( self::WORKSPACE_HASH_BYTES ) );
		} catch ( Throwable $throwable ) {
			unset( $throwable );
			return substr( hash( 'sha256', wp_generate_password( 64, true, true ) . microtime( true ) ), 0, self::WORKSPACE_HASH_BYTES * 2 );
		}
	}
}

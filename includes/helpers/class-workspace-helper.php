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

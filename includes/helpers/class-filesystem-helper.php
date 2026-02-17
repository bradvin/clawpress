<?php
/**
 * Filesystem helper using WP_Filesystem API.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Wrapper for WP_Filesystem operations.
 *
 * Provides a safe, WordPress-standard way to perform filesystem operations
 * with proper error handling and permission management.
 */
final class Filesystem_Helper {
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
	 * Initialize WP_Filesystem and return the instance.
	 *
	 * @return \WP_Filesystem_Base|false Filesystem object or false on failure.
	 */
	private function get_filesystem() {
		global $wp_filesystem;

		if ( $wp_filesystem instanceof \WP_Filesystem_Base ) {
			return $wp_filesystem;
		}

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$initialized = \WP_Filesystem();
		if ( ! $initialized || ! $wp_filesystem instanceof \WP_Filesystem_Base ) {
			return false;
		}

		return $wp_filesystem;
	}

	/**
	 * Check if a file or directory exists.
	 *
	 * @param string $path Absolute path.
	 * @return bool
	 */
	public function exists( string $path ): bool {
		$fs = $this->get_filesystem();
		if ( false === $fs ) {
			return file_exists( $path ); // Fallback to native PHP.
		}

		return $fs->exists( $path );
	}

	/**
	 * Check if path is a file.
	 *
	 * @param string $path Absolute path.
	 * @return bool
	 */
	public function is_file( string $path ): bool {
		$fs = $this->get_filesystem();
		if ( false === $fs ) {
			return is_file( $path ); // Fallback.
		}

		return $fs->is_file( $path );
	}

	/**
	 * Check if path is a directory.
	 *
	 * @param string $path Absolute path.
	 * @return bool
	 */
	public function is_dir( string $path ): bool {
		$fs = $this->get_filesystem();
		if ( false === $fs ) {
			return is_dir( $path ); // Fallback.
		}

		return $fs->is_dir( $path );
	}

	/**
	 * Read file contents.
	 *
	 * @param string $path Absolute file path.
	 * @return string|false File contents or false on failure.
	 */
	public function get_contents( string $path ) {
		$fs = $this->get_filesystem();
		if ( false === $fs ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Fallback.
			return file_get_contents( $path );
		}

		return $fs->get_contents( $path );
	}

	/**
	 * Write contents to file.
	 *
	 * @param string $path Absolute file path.
	 * @param string $contents File contents.
	 * @param int    $mode Optional. File permissions (octal).
	 * @return bool True on success, false on failure.
	 */
	public function put_contents( string $path, string $contents, int $mode = 0640 ): bool {
		$fs = $this->get_filesystem();
		if ( false === $fs ) {
			$result = file_put_contents( $path, $contents ); // Fallback.
			if ( false !== $result ) {
				@chmod( $path, $mode );
			}
			return false !== $result;
		}

		$result = $fs->put_contents( $path, $contents, $mode );
		return false !== $result;
	}

	/**
	 * Delete a file.
	 *
	 * @param string $path Absolute file path.
	 * @return bool True on success, false on failure.
	 */
	public function delete( string $path ): bool {
		$fs = $this->get_filesystem();
		if ( false === $fs ) {
			return @unlink( $path ); // Fallback.
		}

		return $fs->delete( $path );
	}

	/**
	 * Create a directory recursively.
	 *
	 * @param string $path Absolute directory path.
	 * @param int    $chmod Optional. Directory permissions (octal).
	 * @return bool True on success, false on failure.
	 */
	public function mkdir( string $path, int $chmod = 0750 ): bool {
		$fs = $this->get_filesystem();
		if ( false === $fs ) {
			return wp_mkdir_p( $path ); // Use WordPress helper as fallback.
		}

		return $fs->mkdir( $path, $chmod );
	}

	/**
	 * Delete a directory recursively.
	 *
	 * @param string $path Absolute directory path.
	 * @return bool True on success, false on failure.
	 */
	public function rmdir( string $path ): bool {
		$fs = $this->get_filesystem();
		if ( false === $fs ) {
			return $this->rmdir_recursive_fallback( $path );
		}

		return $fs->rmdir( $path, true );
	}

	/**
	 * Get file size.
	 *
	 * @param string $path Absolute file path.
	 * @return int|false File size in bytes or false on failure.
	 */
	public function size( string $path ) {
		$fs = $this->get_filesystem();
		if ( false === $fs ) {
			return @filesize( $path ); // Fallback.
		}

		return $fs->size( $path );
	}

	/**
	 * Change file/directory permissions.
	 *
	 * @param string $path Absolute path.
	 * @param int    $mode Permission mode (octal).
	 * @return bool True on success, false on failure.
	 */
	public function chmod( string $path, int $mode ): bool {
		$fs = $this->get_filesystem();
		if ( false === $fs ) {
			return @chmod( $path, $mode ); // Fallback.
		}

		return $fs->chmod( $path, $mode );
	}

	/**
	 * List files in a directory.
	 *
	 * @param string $path Absolute directory path.
	 * @param bool   $include_hidden Whether to include hidden files.
	 * @param bool   $recursive Whether to list recursively.
	 * @return array<string>|false Array of file paths or false on failure.
	 */
	public function dirlist( string $path, bool $include_hidden = true, bool $recursive = false ) {
		$fs = $this->get_filesystem();
		if ( false === $fs ) {
			return false;
		}

		$list = $fs->dirlist( $path, $include_hidden, $recursive );
		if ( false === $list || ! is_array( $list ) ) {
			return false;
		}

		return $list;
	}

	/**
	 * Fallback: Delete directory recursively using native PHP.
	 *
	 * @param string $directory Absolute directory path.
	 * @return bool
	 */
	private function rmdir_recursive_fallback( string $directory ): bool {
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
		} catch ( \Throwable $throwable ) {
			unset( $throwable );
			return false;
		}

		return @rmdir( $directory );
	}
}

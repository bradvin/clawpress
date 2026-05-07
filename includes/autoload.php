<?php
/**
 * Plugin class autoload fallback.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress;

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( __NAMESPACE__ . '\clawpress_autoload_slug' ) ) {
	/**
	 * Normalize a class or namespace segment into its file slug.
	 *
	 * @param string $segment Class or namespace segment.
	 */
	function clawpress_autoload_slug( string $segment ): string {
		$slug = strtolower( str_replace( '_', '-', $segment ) );

		$directory_map = [
			'adminpage' => 'admin-page',
			'builtin'   => 'built-in',
			'posttypes' => 'post-types',
			'restapi'   => 'rest',
			'webfetch'  => 'web-fetch',
		];

		return $directory_map[ $slug ] ?? $slug;
	}
}

if ( ! function_exists( __NAMESPACE__ . '\clawpress_autoload_class_file_candidates' ) ) {
	/**
	 * Resolve candidate class file paths for a plugin class.
	 *
	 * @param string $class_name Fully qualified class name.
	 * @return array<int,string>
	 */
	function clawpress_autoload_class_file_candidates( string $class_name ): array {
		$prefix = __NAMESPACE__ . '\\';
		if ( ! str_starts_with( $class_name, $prefix ) ) {
			return [];
		}

		$relative_class = substr( $class_name, strlen( $prefix ) );
		if ( '' === $relative_class ) {
			return [];
		}

		$parts            = explode( '\\', $relative_class );
		$short_class_name = array_pop( $parts );
		if ( ! is_string( $short_class_name ) || '' === $short_class_name ) {
			return [];
		}

		$class_slug = clawpress_autoload_slug( $short_class_name );
		$candidates = [
			__DIR__ . '/class-' . $class_slug . '.php',
		];

		if ( [] !== $parts ) {
			$directories  = array_map( __NAMESPACE__ . '\clawpress_autoload_slug', $parts );
			$candidates[] = __DIR__ . '/' . implode( '/', $directories ) . '/class-' . $class_slug . '.php';

			while ( [] !== $directories && in_array( $directories[ count( $directories ) - 1 ], [ 'built-in', 'controllers' ], true ) ) {
				array_pop( $directories );
				if ( [] !== $directories ) {
					$candidates[] = __DIR__ . '/' . implode( '/', $directories ) . '/class-' . $class_slug . '.php';
				}
			}
		}

		return array_values( array_unique( $candidates ) );
	}
}

if ( ! function_exists( __NAMESPACE__ . '\clawpress_autoload_class' ) ) {
	/**
	 * Load a plugin class when Composer's classmap does not know about it yet.
	 *
	 * @param string $class_name Fully qualified class name.
	 */
	function clawpress_autoload_class( string $class_name ): void {
		foreach ( clawpress_autoload_class_file_candidates( $class_name ) as $file ) {
			if ( is_readable( $file ) ) {
				require_once $file;
				return;
			}
		}
	}
}

spl_autoload_register( __NAMESPACE__ . '\clawpress_autoload_class' );

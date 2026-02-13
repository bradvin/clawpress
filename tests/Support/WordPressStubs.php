<?php
/**
 * WordPress function and class stubs for unit tests.
 *
 * @package ClawPress\Tests
 */

declare( strict_types=1 );

namespace ClawPress\Tests\Support {

final class WordPress_Stubs {
	/** @var array<int,array<string,mixed>> */
	public static array $actions = array();

	/** @var array<int,array<string,mixed>> */
	public static array $menu_pages = array();

	/** @var array<int,array<string,mixed>> */
	public static array $enqueued_scripts = array();

	/** @var array<int,array<string,mixed>> */
	public static array $enqueued_styles = array();

	/** @var array<int,array<string,mixed>> */
	public static array $localized_scripts = array();

	/** @var array<int,array<string,mixed>> */
	public static array $registered_post_types = array();

	/** @var array<int,array<string,mixed>> */
	public static array $rest_routes = array();

	/** @var array<int,array<string,mixed>> */
	public static array $scheduled_actions = array();

	/** @var array<int,array<string,mixed>> */
	public static array $triggered_actions = array();

	/** @var array<string,mixed> */
	public static array $options = array();

	/** @var array<int,array<string,mixed>> */
	public static array $user_meta = array();

	public static bool $can_manage_options = true;

	public static bool $is_rtl = false;

	public static bool $has_scheduled_action = false;

	public static int $current_user_id = 1;

	public static function reset(): void {
		self::$actions              = array();
		self::$menu_pages           = array();
		self::$enqueued_scripts     = array();
		self::$enqueued_styles      = array();
		self::$localized_scripts    = array();
		self::$registered_post_types = array();
		self::$rest_routes          = array();
		self::$scheduled_actions    = array();
		self::$triggered_actions    = array();
		self::$options              = array();
		self::$user_meta            = array();
		self::$can_manage_options   = true;
		self::$is_rtl               = false;
		self::$has_scheduled_action = false;
		self::$current_user_id      = 1;
	}
}

WordPress_Stubs::reset();
}

namespace {

	use ClawPress\Tests\Support\WordPress_Stubs;

	if ( ! function_exists( 'add_action' ) ) {
		function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ): bool {
			WordPress_Stubs::$actions[] = array(
				'hook'          => $hook,
				'callback'      => $callback,
				'priority'      => $priority,
				'accepted_args' => $accepted_args,
			);
			return true;
		}
	}

	if ( ! function_exists( 'do_action' ) ) {
		function do_action( $hook, ...$args ): void {
			WordPress_Stubs::$triggered_actions[] = array(
				'hook' => $hook,
				'args' => $args,
			);
		}
	}

	if ( ! function_exists( '__' ) ) {
		function __( string $text, ?string $domain = null ): string {
			unset( $domain );
			return $text;
		}
	}

	if ( ! function_exists( 'esc_html_e' ) ) {
		function esc_html_e( string $text, ?string $domain = null ): void {
			unset( $domain );
			echo $text;
		}
	}

	if ( ! function_exists( 'sanitize_text_field' ) ) {
		function sanitize_text_field( $text ): string {
			return trim( (string) $text );
		}
	}

	if ( ! function_exists( 'add_menu_page' ) ) {
		function add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $callback, $icon_url = '', $position = null ): string {
			WordPress_Stubs::$menu_pages[] = array(
				'page_title' => $page_title,
				'menu_title' => $menu_title,
				'capability' => $capability,
				'menu_slug'  => $menu_slug,
				'callback'   => $callback,
				'icon_url'   => $icon_url,
				'position'   => $position,
			);
			return (string) $menu_slug;
		}
	}

	if ( ! function_exists( 'wp_enqueue_script' ) ) {
		function wp_enqueue_script( $handle, $src, $deps = array(), $ver = false, $in_footer = false ): void {
			WordPress_Stubs::$enqueued_scripts[] = array(
				'handle'    => $handle,
				'src'       => $src,
				'deps'      => $deps,
				'version'   => $ver,
				'in_footer' => $in_footer,
			);
		}
	}

	if ( ! function_exists( 'wp_enqueue_style' ) ) {
		function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false, $media = 'all' ): void {
			WordPress_Stubs::$enqueued_styles[] = array(
				'handle'  => $handle,
				'src'     => $src,
				'deps'    => $deps,
				'version' => $ver,
				'media'   => $media,
			);
		}
	}

	if ( ! function_exists( 'wp_localize_script' ) ) {
		function wp_localize_script( $handle, $object_name, $l10n ): bool {
			WordPress_Stubs::$localized_scripts[] = array(
				'handle'      => $handle,
				'object_name' => $object_name,
				'data'        => $l10n,
			);
			return true;
		}
	}

	if ( ! function_exists( 'current_user_can' ) ) {
		function current_user_can( string $capability ): bool {
			if ( 'manage_options' === $capability ) {
				return WordPress_Stubs::$can_manage_options;
			}

			return true;
		}
	}

	if ( ! function_exists( 'register_post_type' ) ) {
		function register_post_type( string $post_type, array $args = array() ): string {
			WordPress_Stubs::$registered_post_types[] = array(
				'post_type' => $post_type,
				'args'      => $args,
			);
			return $post_type;
		}
	}

	if ( ! function_exists( 'register_rest_route' ) ) {
		function register_rest_route( string $route_namespace, string $route, array $args = array(), bool $override = false ): bool {
			WordPress_Stubs::$rest_routes[] = array(
				'namespace' => $route_namespace,
				'route'     => $route,
				'args'      => $args,
				'override'  => $override,
			);
			return true;
		}
	}

	if ( ! function_exists( 'get_option' ) ) {
		function get_option( string $option, $default = false ) {
			return WordPress_Stubs::$options[ $option ] ?? $default;
		}
	}

	if ( ! function_exists( 'update_option' ) ) {
		function update_option( string $option, $value, ?bool $autoload = null ): bool {
			unset( $autoload );
			WordPress_Stubs::$options[ $option ] = $value;
			return true;
		}
	}

	if ( ! function_exists( 'get_user_meta' ) ) {
		function get_user_meta( int $user_id, string $key, bool $single = false ) {
			$meta_value = WordPress_Stubs::$user_meta[ $user_id ][ $key ] ?? null;

			if ( $single ) {
				return $meta_value ?? '';
			}

			return null === $meta_value ? array() : array( $meta_value );
		}
	}

	if ( ! function_exists( 'update_user_meta' ) ) {
		function update_user_meta( int $user_id, string $key, $value, $prev_value = '' ): bool {
			unset( $prev_value );
			if ( ! isset( WordPress_Stubs::$user_meta[ $user_id ] ) ) {
				WordPress_Stubs::$user_meta[ $user_id ] = array();
			}
			WordPress_Stubs::$user_meta[ $user_id ][ $key ] = $value;
			return true;
		}
	}

	if ( ! function_exists( 'as_has_scheduled_action' ) ) {
		function as_has_scheduled_action( string $hook, array $args = array(), ?string $group = null ) {
			unset( $hook, $args, $group );
			return WordPress_Stubs::$has_scheduled_action;
		}
	}

	if ( ! function_exists( 'as_schedule_recurring_action' ) ) {
		function as_schedule_recurring_action( int $timestamp, int $interval_in_seconds, string $hook, array $args = array(), string $group = '', bool $unique = false, int $priority = 10 ): int {
			unset( $unique, $priority );
			WordPress_Stubs::$scheduled_actions[] = array(
				'timestamp' => $timestamp,
				'interval'  => $interval_in_seconds,
				'hook'      => $hook,
				'args'      => $args,
				'group'     => $group,
			);
			return 1;
		}
	}

	if ( ! function_exists( 'is_rtl' ) ) {
		function is_rtl(): bool {
			return WordPress_Stubs::$is_rtl;
		}
	}

	if ( ! function_exists( 'rest_url' ) ) {
		function rest_url( string $path = '' ): string {
			return 'https://example.test/wp-json/' . ltrim( $path, '/' );
		}
	}

	if ( ! function_exists( 'esc_url_raw' ) ) {
		function esc_url_raw( string $url ): string {
			return $url;
		}
	}

	if ( ! function_exists( 'wp_create_nonce' ) ) {
		function wp_create_nonce( string $action = '-1' ): string {
			return 'nonce-' . $action;
		}
	}

	if ( ! function_exists( 'get_current_user_id' ) ) {
		function get_current_user_id(): int {
			return WordPress_Stubs::$current_user_id;
		}
	}

	if ( ! class_exists( 'WP_REST_Response' ) ) {
		class WP_REST_Response {
			/** @var mixed */
			protected $data;

			protected int $status;

			/**
			 * @param mixed $data Response data.
			 */
			public function __construct( $data = null, int $status = 200 ) {
				$this->data   = $data;
				$this->status = $status;
			}

			/**
			 * @return mixed
			 */
			public function get_data() {
				return $this->data;
			}

			public function get_status(): int {
				return $this->status;
			}
		}
	}

	if ( ! class_exists( 'WP_REST_Request' ) ) {
		class WP_REST_Request {
			/** @var array<string,mixed> */
			private array $params;

			/**
			 * @param array<string,mixed> $params Request parameters.
			 */
			public function __construct( array $params = array() ) {
				$this->params = $params;
			}

			/**
			 * @return mixed
			 */
			public function get_param( string $key ) {
				return $this->params[ $key ] ?? null;
			}
		}
	}

	if ( ! class_exists( 'WP_Admin_Bar' ) ) {
		class WP_Admin_Bar {
			/** @var array<int,array<string,mixed>> */
			public array $nodes = array();

			/**
			 * @param array<string,mixed> $node Node data.
			 */
			public function add_node( array $node ): void {
				$this->nodes[] = $node;
			}
		}
	}
}

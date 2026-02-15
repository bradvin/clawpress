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

	/** @var array<string,int> */
	public static array $did_actions = array();

	/** @var array<int,string> */
	public static array $doing_actions = array();

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

	/** @var array<string,mixed> */
	public static array $abilities = array();

	/** @var array<string,mixed> */
	public static array $ability_categories = array();

	/** @var array<int,array<string,mixed>> */
	public static array $scheduled_actions = array();

	/** @var array<int,array<string,mixed>> */
	public static array $triggered_actions = array();

	/** @var array<string,mixed> */
	public static array $options = array();

	/** @var array<int,array<string,mixed>> */
	public static array $user_meta = array();

	/** @var array<int,array<string,mixed>> */
	public static array $posts = array();

	/** @var array<int,array<string,mixed>> */
	public static array $post_meta = array();

	public static int $next_post_id = 1;

	public static bool $can_manage_options = true;

	public static bool $is_rtl = false;

	public static bool $has_scheduled_action = false;

	public static int $current_user_id = 1;

	public static string $site_name = 'Test Site';

	public static string $site_url = 'https://example.test/';

	public static string $wp_version = '6.9';

	public static function reset(): void {
		self::$actions              = array();
		self::$did_actions          = array();
		self::$doing_actions        = array();
		self::$menu_pages           = array();
		self::$enqueued_scripts     = array();
		self::$enqueued_styles      = array();
		self::$localized_scripts    = array();
		self::$registered_post_types = array();
		self::$rest_routes          = array();
		self::$abilities            = array();
		self::$ability_categories   = array();
		self::$scheduled_actions    = array();
		self::$triggered_actions    = array();
		self::$options              = array();
		self::$user_meta            = array();
		self::$posts                = array();
		self::$post_meta            = array();
		self::$next_post_id         = 1;
		self::$can_manage_options   = true;
		self::$is_rtl               = false;
		self::$has_scheduled_action = false;
		self::$current_user_id      = 1;
		self::$site_name            = 'Test Site';
		self::$site_url             = 'https://example.test/';
		self::$wp_version           = '6.9';
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

	if ( ! function_exists( 'add_filter' ) ) {
		function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ): bool {
			return add_action( $hook, $callback, $priority, $accepted_args );
		}
	}

	if ( ! function_exists( 'apply_filters' ) ) {
		function apply_filters( string $hook, $value, ...$args ) {
			unset( $hook );
			unset( $args );
			return $value;
		}
	}

	if ( ! function_exists( 'do_action' ) ) {
		function do_action( $hook, ...$args ): void {
			WordPress_Stubs::$doing_actions[] = (string) $hook;

			foreach ( WordPress_Stubs::$actions as $action ) {
				if ( ! isset( $action['hook'], $action['callback'] ) || $hook !== $action['hook'] ) {
					continue;
				}

				if ( ! is_callable( $action['callback'] ) ) {
					continue;
				}

				call_user_func_array( $action['callback'], $args );
			}

			array_pop( WordPress_Stubs::$doing_actions );
			WordPress_Stubs::$did_actions[ (string) $hook ] = ( WordPress_Stubs::$did_actions[ (string) $hook ] ?? 0 ) + 1;

			WordPress_Stubs::$triggered_actions[] = array(
				'hook' => $hook,
				'args' => $args,
			);
		}
	}

	if ( ! function_exists( 'doing_action' ) ) {
		function doing_action( ?string $hook = null ): bool {
			if ( null === $hook || '' === $hook ) {
				return [] !== WordPress_Stubs::$doing_actions;
			}

			return in_array( $hook, WordPress_Stubs::$doing_actions, true );
		}
	}

	if ( ! function_exists( 'did_action' ) ) {
		function did_action( string $hook ): int {
			return (int) ( WordPress_Stubs::$did_actions[ $hook ] ?? 0 );
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

	if ( ! function_exists( 'sanitize_title' ) ) {
		function sanitize_title( string $title ): string {
			$title = strtolower( trim( $title ) );
			$title = preg_replace( '/[^a-z0-9\-]+/', '-', $title );
			$title = preg_replace( '/-+/', '-', (string) $title );
			return trim( (string) $title, '-' );
		}
	}

	if ( ! function_exists( 'wp_json_encode' ) ) {
		function wp_json_encode( $value, int $flags = 0, int $depth = 512 ) {
			return json_encode( $value, $flags, $depth );
		}
	}

	if ( ! function_exists( 'wp_date' ) ) {
		function wp_date( string $format, ?int $timestamp = null, ?\DateTimeZone $timezone = null ): string {
			unset( $timezone );
			$resolved_timestamp = null === $timestamp ? time() : $timestamp;
			return gmdate( $format, $resolved_timestamp );
		}
	}

	if ( ! function_exists( 'is_wp_error' ) ) {
		function is_wp_error( $thing ): bool {
			return $thing instanceof \WP_Error;
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

	if ( ! function_exists( 'user_can' ) ) {
		function user_can( int $user_id, string $capability ): bool {
			unset( $user_id );
			return current_user_can( $capability );
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

	if ( ! function_exists( 'wp_insert_post' ) ) {
		function wp_insert_post( array $postarr, bool $wp_error = false ) {
			unset( $wp_error );

			$post_id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
			if ( $post_id <= 0 ) {
				$post_id = WordPress_Stubs::$next_post_id++;
			}

			$existing = WordPress_Stubs::$posts[ $post_id ] ?? array();
			$post     = array_merge(
				array(
					'ID'           => $post_id,
					'post_type'    => 'post',
					'post_status'  => 'publish',
					'post_title'   => '',
					'post_name'    => '',
					'post_content' => '',
					'post_author'  => 0,
					'post_date'    => gmdate( 'Y-m-d H:i:s' ),
				),
				$existing,
				$postarr
			);

			$post['ID'] = $post_id;
			WordPress_Stubs::$posts[ $post_id ] = $post;

			return $post_id;
		}
	}

	if ( ! function_exists( 'wp_delete_post' ) ) {
		function wp_delete_post( int $post_id, bool $force_delete = false ) {
			unset( $force_delete );
			if ( ! isset( WordPress_Stubs::$posts[ $post_id ] ) ) {
				return false;
			}

			$post_data = WordPress_Stubs::$posts[ $post_id ];
			unset( WordPress_Stubs::$posts[ $post_id ], WordPress_Stubs::$post_meta[ $post_id ] );

			return new \WP_Post( $post_data );
		}
	}

	if ( ! function_exists( 'get_post' ) ) {
		function get_post( $post ) {
			$post_id = is_numeric( $post ) ? (int) $post : 0;
			if ( $post instanceof \WP_Post ) {
				$post_id = (int) $post->ID;
			}

			if ( $post_id <= 0 || ! isset( WordPress_Stubs::$posts[ $post_id ] ) ) {
				return null;
			}

			return new \WP_Post( WordPress_Stubs::$posts[ $post_id ] );
		}
	}

	if ( ! function_exists( 'get_posts' ) ) {
		function get_posts( array $args = array() ): array {
			$posts = array_values( WordPress_Stubs::$posts );

			if ( isset( $args['post_type'] ) ) {
				$post_type = (string) $args['post_type'];
				$posts     = array_values(
					array_filter(
						$posts,
						static fn( array $post ): bool => $post_type === (string) ( $post['post_type'] ?? '' )
					)
				);
			}

			if ( isset( $args['post_status'] ) && 'any' !== $args['post_status'] ) {
				$post_status = (string) $args['post_status'];
				$posts       = array_values(
					array_filter(
						$posts,
						static fn( array $post ): bool => $post_status === (string) ( $post['post_status'] ?? '' )
					)
				);
			}

			if ( isset( $args['name'] ) && '' !== (string) $args['name'] ) {
				$name  = (string) $args['name'];
				$posts = array_values(
					array_filter(
						$posts,
						static fn( array $post ): bool => $name === (string) ( $post['post_name'] ?? '' )
					)
				);
			}

			if ( isset( $args['meta_key'], $args['meta_value'] ) ) {
				$meta_key   = (string) $args['meta_key'];
				$meta_value = $args['meta_value'];
				$posts      = array_values(
					array_filter(
						$posts,
						static function ( array $post ) use ( $meta_key, $meta_value ): bool {
							$post_id = (int) ( $post['ID'] ?? 0 );
							if ( $post_id <= 0 ) {
								return false;
							}

							$stored_value = WordPress_Stubs::$post_meta[ $post_id ][ $meta_key ] ?? null;
							return $stored_value === $meta_value;
						}
					)
				);
			}

			$orderby = isset( $args['orderby'] ) ? (string) $args['orderby'] : 'ID';
			$order   = isset( $args['order'] ) ? strtoupper( (string) $args['order'] ) : 'DESC';

			usort(
				$posts,
				static function ( array $left, array $right ) use ( $orderby, $order ): int {
					if ( 'date' === strtolower( $orderby ) ) {
						$left_value  = strtotime( (string) ( $left['post_date'] ?? '' ) ) ?: 0;
						$right_value = strtotime( (string) ( $right['post_date'] ?? '' ) ) ?: 0;
					} else {
						$left_value  = (int) ( $left['ID'] ?? 0 );
						$right_value = (int) ( $right['ID'] ?? 0 );
					}

					$comparison = $left_value <=> $right_value;
					return 'ASC' === $order ? $comparison : -$comparison;
				}
			);

			$posts_per_page = isset( $args['posts_per_page'] ) ? (int) $args['posts_per_page'] : 5;
			if ( $posts_per_page > -1 ) {
				$posts = array_slice( $posts, 0, $posts_per_page );
			}

			$fields = isset( $args['fields'] ) ? (string) $args['fields'] : '';
			if ( 'ids' === $fields ) {
				return array_values(
					array_map(
						static fn( array $post ): int => (int) $post['ID'],
						$posts
					)
				);
			}

			return array_values(
				array_map(
					static fn( array $post ): \WP_Post => new \WP_Post( $post ),
					$posts
				)
			);
		}
	}

	if ( ! function_exists( 'update_post_meta' ) ) {
		function update_post_meta( int $post_id, string $meta_key, $meta_value, $prev_value = '' ): bool {
			unset( $prev_value );
			if ( $post_id <= 0 ) {
				return false;
			}

			if ( ! isset( WordPress_Stubs::$post_meta[ $post_id ] ) ) {
				WordPress_Stubs::$post_meta[ $post_id ] = array();
			}

			WordPress_Stubs::$post_meta[ $post_id ][ $meta_key ] = $meta_value;
			return true;
		}
	}

	if ( ! function_exists( 'get_post_meta' ) ) {
		function get_post_meta( int $post_id, string $key = '', bool $single = false ) {
			$meta = WordPress_Stubs::$post_meta[ $post_id ] ?? array();

			if ( '' === $key ) {
				return $meta;
			}

			$value = $meta[ $key ] ?? null;
			if ( $single ) {
				return null === $value ? '' : $value;
			}

			return null === $value ? array() : array( $value );
		}
	}

	if ( ! function_exists( 'get_user_meta' ) ) {
		function get_user_meta( int $user_id, string $key = '', bool $single = false ) {
			$user_meta = WordPress_Stubs::$user_meta[ $user_id ] ?? array();

			if ( '' === $key ) {
				$all_meta = array();

				foreach ( $user_meta as $meta_key => $meta_value ) {
					$all_meta[ $meta_key ] = array( $meta_value );
				}

				return $all_meta;
			}

			$meta_value = $user_meta[ $key ] ?? null;

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

	if ( ! function_exists( 'delete_user_meta' ) ) {
		function delete_user_meta( int $user_id, string $meta_key, $meta_value = '' ): bool {
			unset( $meta_value );

			if ( ! isset( WordPress_Stubs::$user_meta[ $user_id ][ $meta_key ] ) ) {
				return false;
			}

			unset( WordPress_Stubs::$user_meta[ $user_id ][ $meta_key ] );
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

	if ( ! function_exists( 'get_bloginfo' ) ) {
		function get_bloginfo( string $show = '', string $filter = 'raw' ): string {
			unset( $filter );

			if ( 'name' === $show ) {
				return WordPress_Stubs::$site_name;
			}

			if ( 'version' === $show ) {
				return WordPress_Stubs::$wp_version;
			}

			return '';
		}
	}

	if ( ! function_exists( 'home_url' ) ) {
		function home_url( string $path = '', ?string $scheme = null ): string {
			unset( $scheme );
			$base = rtrim( WordPress_Stubs::$site_url, '/' );
			$path = ltrim( $path, '/' );
			return '' === $path ? $base . '/' : $base . '/' . $path;
		}
	}

	if ( ! function_exists( 'wp_upload_dir' ) ) {
		function wp_upload_dir( ?string $time = null, bool $create_dir = true, bool $refresh_cache = false ): array {
			unset( $time, $refresh_cache );

			$base_dir = sys_get_temp_dir() . '/clawpress-uploads';
			if ( $create_dir && ! is_dir( $base_dir ) ) {
				mkdir( $base_dir, 0777, true );
			}

			return [
				'basedir' => $base_dir,
				'baseurl' => 'https://example.test/wp-content/uploads',
			];
		}
	}

	if ( ! function_exists( 'wp_mkdir_p' ) ) {
		function wp_mkdir_p( string $target ): bool {
			if ( '' === $target ) {
				return false;
			}

			if ( is_dir( $target ) ) {
				return true;
			}

			return mkdir( $target, 0777, true );
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

	if ( ! function_exists( 'wp_set_current_user' ) ) {
		function wp_set_current_user( int $user_id ): int {
			WordPress_Stubs::$current_user_id = $user_id;
			return $user_id;
		}
	}

	if ( ! class_exists( 'WP_Ability' ) ) {
		class WP_Ability {
			private string $name;
			/** @var array<string,mixed> */
			private array $args;

			/**
			 * @param string               $name Ability name.
			 * @param array<string,mixed>  $args Ability args.
			 */
			public function __construct( string $name, array $args ) {
				$this->name = $name;
				$this->args = $args;
			}

			public function get_name(): string {
				return $this->name;
			}

			public function get_label(): string {
				return (string) ( $this->args['label'] ?? '' );
			}

			public function get_description(): string {
				return (string) ( $this->args['description'] ?? '' );
			}

			/**
			 * @return array<string,mixed>
			 */
			public function get_input_schema(): array {
				return isset( $this->args['input_schema'] ) && is_array( $this->args['input_schema'] )
					? $this->args['input_schema']
					: [];
			}

			/**
			 * @return array<string,mixed>
			 */
			public function get_output_schema(): array {
				return isset( $this->args['output_schema'] ) && is_array( $this->args['output_schema'] )
					? $this->args['output_schema']
					: [];
			}

			/**
			 * @return array<string,mixed>
			 */
			public function get_meta(): array {
				return isset( $this->args['meta'] ) && is_array( $this->args['meta'] )
					? $this->args['meta']
					: [];
			}

			/**
			 * @param mixed $default_value Default value.
			 * @return mixed
			 */
			public function get_meta_item( string $key, $default_value = null ) {
				$meta = $this->get_meta();
				return array_key_exists( $key, $meta ) ? $meta[ $key ] : $default_value;
			}

			/**
			 * @param mixed $input Optional input.
			 * @return mixed
			 */
			public function execute( $input = null ) {
				$permission_callback = $this->args['permission_callback'] ?? null;
				if ( ! is_callable( $permission_callback ) ) {
					return new \WP_Error( 'ability_invalid_permission_callback', 'Invalid permission callback.' );
				}

				$has_permissions = [] === $this->get_input_schema()
					? call_user_func( $permission_callback )
					: call_user_func( $permission_callback, $input );
				if ( true !== $has_permissions ) {
					if ( is_wp_error( $has_permissions ) ) {
						return $has_permissions;
					}
					return new \WP_Error( 'ability_invalid_permissions', 'Ability permission denied.' );
				}

				$execute_callback = $this->args['execute_callback'] ?? null;
				if ( ! is_callable( $execute_callback ) ) {
					return new \WP_Error( 'ability_invalid_execute_callback', 'Invalid execute callback.' );
				}

				return [] === $this->get_input_schema()
					? call_user_func( $execute_callback )
					: call_user_func( $execute_callback, $input );
			}
		}
	}

	if ( ! class_exists( 'WP_Ability_Category' ) ) {
		class WP_Ability_Category {
			private string $slug;
			/** @var array<string,mixed> */
			private array $args;

			/**
			 * @param string              $slug Category slug.
			 * @param array<string,mixed> $args Category args.
			 */
			public function __construct( string $slug, array $args ) {
				$this->slug = $slug;
				$this->args = $args;
			}

			public function get_slug(): string {
				return $this->slug;
			}
		}
	}

	if ( ! function_exists( 'wp_register_ability' ) ) {
		/**
		 * @param string              $name Ability name.
		 * @param array<string,mixed> $args Ability args.
		 */
		function wp_register_ability( string $name, array $args ): ?\WP_Ability {
			$ability = new \WP_Ability( $name, $args );
			WordPress_Stubs::$abilities[ $name ] = $ability;
			return $ability;
		}
	}

	if ( ! function_exists( 'wp_get_ability' ) ) {
		function wp_get_ability( string $name ): ?\WP_Ability {
			$ability = WordPress_Stubs::$abilities[ $name ] ?? null;
			return $ability instanceof \WP_Ability ? $ability : null;
		}
	}

	if ( ! function_exists( 'wp_get_abilities' ) ) {
		/**
		 * @return array<string,\WP_Ability>
		 */
		function wp_get_abilities(): array {
			return WordPress_Stubs::$abilities;
		}
	}

	if ( ! function_exists( 'wp_has_ability' ) ) {
		function wp_has_ability( string $name ): bool {
			return isset( WordPress_Stubs::$abilities[ $name ] );
		}
	}

	if ( ! function_exists( 'wp_register_ability_category' ) ) {
		/**
		 * @param string              $slug Category slug.
		 * @param array<string,mixed> $args Category args.
		 */
		function wp_register_ability_category( string $slug, array $args ): ?\WP_Ability_Category {
			$category = new \WP_Ability_Category( $slug, $args );
			WordPress_Stubs::$ability_categories[ $slug ] = $category;
			return $category;
		}
	}

	if ( ! function_exists( 'wp_has_ability_category' ) ) {
		function wp_has_ability_category( string $slug ): bool {
			return isset( WordPress_Stubs::$ability_categories[ $slug ] );
		}
	}

	if ( ! class_exists( 'WP_Error' ) ) {
		class WP_Error {
			private string $error_code;

			private string $error_message;

			public function __construct( string $error_code = '', string $error_message = '' ) {
				$this->error_code    = $error_code;
				$this->error_message = $error_message;
			}

			public function get_error_code(): string {
				return $this->error_code;
			}

			public function get_error_message(): string {
				return $this->error_message;
			}
		}
	}

	if ( ! class_exists( 'WP_Post' ) ) {
		class WP_Post {
			public int $ID = 0;

			public string $post_type = '';

			public string $post_status = '';

			public string $post_title = '';

			public string $post_name = '';

			public string $post_content = '';

			public int $post_author = 0;

			public string $post_date = '';

			/**
			 * @param array<string,mixed> $data Post payload.
			 */
			public function __construct( array $data = array() ) {
				foreach ( $data as $key => $value ) {
					$this->$key = $value;
				}
			}
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

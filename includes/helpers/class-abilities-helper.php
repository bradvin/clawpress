<?php
/**
 * Abilities helper.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Helpers;

use ClawPress\Security\Security;
use WordPress\AiClient\Tools\DTO\FunctionDeclaration;

defined( 'ABSPATH' ) || exit;

/**
 * Central helper for ClawPress ability settings and execution.
 */
final class Abilities_Helper {
	/**
	 * Enabled abilities option key.
	 */
	public const ENABLED_ABILITIES_OPTION = 'clawpress_enabled_abilities';

	/**
	 * ClawPress ability namespace.
	 */
	private const CLAWPRESS_ABILITY_NAMESPACE = 'clawpress';

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
	 * Security helper.
	 *
	 * @var Security
	 */
	private Security $security;

	/**
	 * Agent event helper.
	 *
	 * @var Agent_Event_Helper
	 */
	private Agent_Event_Helper $agent_event_helper;

	/**
	 * Policy helper.
	 *
	 * @var Policy_Helper
	 */
	private Policy_Helper $policy_helper;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->settings_helper    = Settings_Helper::get_instance();
		$this->security           = Security::get_instance();
		$this->agent_event_helper = Agent_Event_Helper::get_instance();
		$this->policy_helper      = Policy_Helper::get_instance();
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
	 * Get registered tool aliases.
	 *
	 * @return array<int,string>
	 */
	public function get_allowlisted_tool_names(): array {
		$ability_ids = $this->get_registered_ability_ids();
		$tool_map    = $this->build_tool_alias_map( $ability_ids );
		return array_keys( $tool_map );
	}

	/**
	 * Get registered ability IDs.
	 *
	 * @return array<int,string>
	 */
	public function get_allowlisted_ability_ids(): array {
		return $this->get_registered_ability_ids();
	}

	/**
	 * Build model function declarations from enabled registered abilities.
	 *
	 * @return array<int,FunctionDeclaration>
	 */
	public function get_tool_declarations(): array {
		$registered_abilities = $this->get_registered_abilities();
		if ( [] === $registered_abilities ) {
			return [];
		}

		$enabled_abilities = array_fill_keys( $this->get_enabled_ability_ids(), true );
		$tool_alias_map    = $this->build_tool_alias_map( array_keys( $registered_abilities ) );
		$declarations = [];
		foreach ( $tool_alias_map as $tool_name => $ability_name ) {
			if ( ! isset( $enabled_abilities[ $ability_name ] ) ) {
				continue;
			}

			$ability = $registered_abilities[ $ability_name ] ?? null;
			if ( ! $ability instanceof \WP_Ability ) {
				continue;
			}

			$parameters = $ability->get_input_schema();
			if ( [] === $parameters ) {
				$parameters = [
					'type'                 => 'object',
					'properties'           => new \stdClass(),
					'additionalProperties' => false,
				];
			}

			$declarations[] = new FunctionDeclaration(
				$tool_name,
				(string) $ability->get_description(),
				$parameters
			);
		}

		return $declarations;
	}

	/**
	 * Resolve a tool/ability status list for display.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_tool_status_list(): array {
		$abilities = $this->get_ability_settings_state()['abilities'];

		return array_map(
			static function ( array $ability ): array {
				return [
					'tool_name'    => $ability['tool_name'] ?? '',
					'ability_name' => $ability['ability_name'] ?? '',
					'registered'   => ! empty( $ability['registered'] ),
					'enabled'      => ! empty( $ability['enabled'] ),
					'safety_class' => isset( $ability['safety_class'] ) ? (string) $ability['safety_class'] : 'unknown',
				];
			},
			$abilities
		);
	}

	/**
	 * Get ability settings state for UI/API usage.
	 *
	 * @return array<string,mixed>
	 */
	public function get_ability_settings_state(): array {
		$registered_abilities = $this->get_registered_abilities();
		$registered_ids       = array_keys( $registered_abilities );
		$tool_alias_map       = $this->build_tool_alias_map( $registered_ids );
		$ability_alias_map    = [];
		foreach ( $tool_alias_map as $tool_alias => $ability_name ) {
			$ability_alias_map[ $ability_name ] = $tool_alias;
		}

		$defaults       = $this->get_default_enabled_ability_ids();
		$enabled        = $this->get_enabled_ability_ids();
		$enabled_lookup = array_fill_keys( $enabled, true );
		$abilities      = [];
		$category_index = [];

		foreach ( $registered_abilities as $ability_name => $ability ) {
			$annotations = $this->get_ability_annotations( $ability );

			$category_slug = $this->resolve_ability_category_slug( $ability );
			$category      = $this->build_category_payload( $category_slug );

			if ( ! isset( $category_index[ $category['slug'] ] ) ) {
				$category_index[ $category['slug'] ] = $category;
			}

			$abilities[] = [
				'tool_name'    => 0 === strpos( $ability_name, self::CLAWPRESS_ABILITY_NAMESPACE . '/' ) && isset( $ability_alias_map[ $ability_name ] )
					? (string) $ability_alias_map[ $ability_name ]
					: '',
				'ability_name' => $ability_name,
				'label'        => (string) $ability->get_label(),
				'description'  => (string) $ability->get_description(),
				'registered'   => true,
				'enabled'      => isset( $enabled_lookup[ $ability_name ] ),
				'safety_class' => $this->infer_safety_class( $ability ),
				'annotations'  => $annotations,
				'category'     => $category,
			];
		}

		usort(
			$abilities,
			static fn( array $left, array $right ): int => strcmp(
				strtolower( (string) ( $left['label'] ?? '' ) ),
				strtolower( (string) ( $right['label'] ?? '' ) )
			)
		);

		$categories = array_values( $category_index );
		usort(
			$categories,
			static fn( array $left, array $right ): int => strcmp(
				strtolower( (string) ( $left['label'] ?? '' ) ),
				strtolower( (string) ( $right['label'] ?? '' ) )
			)
		);

		return [
			'abilities'          => $abilities,
			'enabled_abilities'  => $enabled,
			'default_abilities'  => $defaults,
			'categories'         => $categories,
		];
	}

	/**
	 * Persist enabled abilities.
	 *
	 * @param array<int,mixed> $enabled_abilities Ability IDs.
	 * @return array<int,string>
	 */
	public function set_enabled_ability_ids( array $enabled_abilities ): array {
		$sanitized = $this->sanitize_enabled_ability_ids( $enabled_abilities );
		update_option( self::ENABLED_ABILITIES_OPTION, $sanitized, false );
		return $sanitized;
	}

	/**
	 * Reset enabled abilities to defaults (all ClawPress abilities).
	 *
	 * @return array<int,string>
	 */
	public function reset_enabled_ability_ids_to_defaults(): array {
		$defaults = $this->get_default_enabled_ability_ids();
		update_option( self::ENABLED_ABILITIES_OPTION, $defaults, false );
		return $defaults;
	}

	/**
	 * Get enabled abilities; defaults to all ClawPress abilities when option is missing.
	 *
	 * @return array<int,string>
	 */
	public function get_enabled_ability_ids(): array {
		$stored = get_option( self::ENABLED_ABILITIES_OPTION, null );
		if ( null === $stored ) {
			return $this->get_default_enabled_ability_ids();
		}

		if ( ! is_array( $stored ) ) {
			return $this->get_default_enabled_ability_ids();
		}

		return $this->sanitize_enabled_ability_ids( $stored );
	}

	/**
	 * Whether a specific allowlisted ability is enabled.
	 */
	public function is_ability_enabled( string $ability_name ): bool {
		return in_array( $ability_name, $this->get_enabled_ability_ids(), true );
	}

	/**
	 * Execute an allowlisted tool call under execution-user context.
	 *
	 * @param string              $tool_name Tool name.
	 * @param mixed               $raw_args Tool arguments.
	 * @param array<string,mixed> $execution_context Optional execution context.
	 * @return array<string,mixed>
	 */
	public function execute_tool_call( string $tool_name, $raw_args = null, array $execution_context = [] ): array {
		$normalized_tool_name        = strtolower( trim( $tool_name ) );
		$ability_name                = $this->resolve_ability_name_from_tool_name( $normalized_tool_name );
		$args                        = $this->normalize_tool_args( $raw_args );
		$requesting_user_id          = isset( $execution_context['requesting_user_id'] )
			? (int) $execution_context['requesting_user_id']
			: ( function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0 );
		$execution_user_id           = isset( $execution_context['execution_user_id'] ) && (int) $execution_context['execution_user_id'] > 0
			? (int) $execution_context['execution_user_id']
			: $this->resolve_execution_user_id();
		$confirmation_scope          = isset( $execution_context['confirmation_scope'] )
			? strtolower( trim( (string) $execution_context['confirmation_scope'] ) )
			: '';
		$skip_confirmation           = isset( $execution_context['skip_confirmation'] ) && function_exists( 'clawpress_sanitize_boolean' )
			? clawpress_sanitize_boolean( $execution_context['skip_confirmation'] )
			: false;
		$has_confirmation_allowlist  = array_key_exists( 'allowed_confirmation_tokens', $execution_context );
		$allowed_confirmation_tokens = $this->normalize_allowed_confirmation_tokens(
			$execution_context['allowed_confirmation_tokens'] ?? null
		);
		$event_context               = [
			'session_id' => isset( $execution_context['session_id'] ) ? (int) $execution_context['session_id'] : 0,
			'run_id'     => isset( $execution_context['run_id'] ) ? (int) $execution_context['run_id'] : 0,
		];
		$trigger_type                = isset( $execution_context['trigger_type'] )
			? (string) $execution_context['trigger_type']
			: 'chat';
		$runtime_policy              = isset( $execution_context['runtime_policy'] ) && is_array( $execution_context['runtime_policy'] )
			? $execution_context['runtime_policy']
			: $this->policy_helper->resolve_runtime_policy(
				$trigger_type,
				isset( $execution_context['session_metadata'] ) && is_array( $execution_context['session_metadata'] )
					? $execution_context['session_metadata']
					: [],
				isset( $execution_context['policy_overrides'] ) && is_array( $execution_context['policy_overrides'] )
						? $execution_context['policy_overrides']
						: []
			);

		$args_json = wp_json_encode( $args );
		$args_hash = false !== $args_json ? hash( 'sha256', (string) $args_json ) : '';

		if ( '' === $ability_name ) {
			$payload = [
				'success' => false,
				'error'   => [
					'code'    => 'clawpress_tool_not_registered',
					'message' => __( 'The requested tool is not registered.', 'clawpress' ),
				],
				'tool'    => $normalized_tool_name,
			];
			$this->log_tool_call( $normalized_tool_name, $ability_name, $requesting_user_id, $execution_user_id, 'error', $args_hash, $payload, $event_context );
			return $payload;
		}

		if ( ! $this->is_ability_enabled( $ability_name ) ) {
			$payload = [
				'success' => false,
				'error'   => [
					'code'    => 'clawpress_ability_disabled',
					'message' => __( 'The requested ability is disabled in settings.', 'clawpress' ),
				],
				'tool'    => $normalized_tool_name,
				'ability' => $ability_name,
			];
			$this->log_tool_call( $normalized_tool_name, $ability_name, $requesting_user_id, $execution_user_id, 'error', $args_hash, $payload, $event_context );
			return $payload;
		}

		if ( ! function_exists( 'wp_get_ability' ) ) {
			$payload = [
				'success' => false,
				'error'   => [
					'code'    => 'clawpress_abilities_api_unavailable',
					'message' => __( 'The WordPress Abilities API is unavailable.', 'clawpress' ),
				],
				'tool'    => $normalized_tool_name,
				'ability' => $ability_name,
			];
			$this->log_tool_call( $normalized_tool_name, $ability_name, $requesting_user_id, $execution_user_id, 'error', $args_hash, $payload, $event_context );
			return $payload;
		}

		$ability = wp_get_ability( $ability_name );
		if ( ! $ability instanceof \WP_Ability ) {
			$payload = [
				'success' => false,
				'error'   => [
					'code'    => 'clawpress_ability_missing',
					'message' => __( 'The requested ability is not registered.', 'clawpress' ),
				],
				'tool'    => $normalized_tool_name,
				'ability' => $ability_name,
			];
			$this->log_tool_call( $normalized_tool_name, $ability_name, $requesting_user_id, $execution_user_id, 'error', $args_hash, $payload, $event_context );
			return $payload;
		}

		$access_check = $this->security->assert_requesting_user_allowed(
			$requesting_user_id > 0 ? $requesting_user_id : null
		);
		if ( is_wp_error( $access_check ) ) {
			$payload = [
				'success' => false,
				'error'   => [
					'code'    => $access_check->get_error_code(),
					'message' => $access_check->get_error_message(),
				],
				'tool'    => $normalized_tool_name,
				'ability' => $ability_name,
			];
			$this->log_tool_call( $normalized_tool_name, $ability_name, $requesting_user_id, $execution_user_id, 'error', $args_hash, $payload, $event_context );
			return $payload;
		}

		$safety_class   = $this->infer_safety_class( $ability );
		$is_destructive = 'destructive' === $safety_class;

		if ( ! $this->is_policy_enabled( $runtime_policy['allow_tools'] ?? true ) ) {
			$payload = $this->build_policy_violation_payload(
				'clawpress_policy_tools_denied',
				__( 'Tool execution is blocked by runtime policy.', 'clawpress' ),
				$normalized_tool_name,
				$ability_name,
				$safety_class,
				$runtime_policy,
				'deny_tools'
			);
			$this->log_tool_call(
				$normalized_tool_name,
				$ability_name,
				$requesting_user_id,
				$execution_user_id,
				$this->resolve_policy_violation_log_status( $payload ),
				$args_hash,
				$payload,
				$event_context
			);
			return $payload;
		}

		if ( $is_destructive && ! $this->is_policy_enabled( $runtime_policy['allow_destructive_tools'] ?? true ) ) {
			$payload = $this->build_policy_violation_payload(
				'clawpress_policy_destructive_tools_denied',
				__( 'Destructive tools are not allowed for this runtime trigger.', 'clawpress' ),
				$normalized_tool_name,
				$ability_name,
				$safety_class,
				$runtime_policy,
				'deny_destructive_tools'
			);
			$this->log_tool_call(
				$normalized_tool_name,
				$ability_name,
				$requesting_user_id,
				$execution_user_id,
				$this->resolve_policy_violation_log_status( $payload ),
				$args_hash,
				$payload,
				$event_context
			);
			return $payload;
		}

		if ( 'clawpress/file-delete' === $ability_name && ! $this->is_policy_enabled( $runtime_policy['allow_file_delete'] ?? true ) ) {
			$payload = $this->build_policy_violation_payload(
				'clawpress_policy_file_delete_denied',
				__( 'File delete is blocked by runtime policy.', 'clawpress' ),
				$normalized_tool_name,
				$ability_name,
				$safety_class,
				$runtime_policy,
				'deny_file_delete'
			);
			$this->log_tool_call(
				$normalized_tool_name,
				$ability_name,
				$requesting_user_id,
				$execution_user_id,
				$this->resolve_policy_violation_log_status( $payload ),
				$args_hash,
				$payload,
				$event_context
			);
			return $payload;
		}

		if ( $is_destructive && ! $skip_confirmation && $this->is_policy_enabled( $runtime_policy['require_confirmation_for_destructive'] ?? true ) && $this->security->requires_confirmation_for_safety_class( $safety_class ) ) {
			if ( 'batch' === $confirmation_scope ) {
				$payload = [
					'success'               => false,
					'requires_confirmation' => true,
					'error'                 => [
						'code'    => 'clawpress_batch_confirmation_required',
						'message' => __( 'This destructive action is pending batch confirmation.', 'clawpress' ),
					],
					'tool'                  => $normalized_tool_name,
					'ability'               => $ability_name,
					'safety_class'          => $safety_class,
				];
				$this->log_tool_call( $normalized_tool_name, $ability_name, $requesting_user_id, $execution_user_id, 'warning', $args_hash, $payload, $event_context );
				return $payload;
			}

			$confirm_token    = $this->normalize_confirmation_token( $args['confirm_token'] ?? null );
			$is_confirmed     = isset( $args['confirm'] ) && function_exists( 'clawpress_sanitize_boolean' )
				? clawpress_sanitize_boolean( $args['confirm'] )
				: false;
			$token_is_allowed = ! $has_confirmation_allowlist
				? true
				: ( null !== $confirm_token && in_array( strtolower( $confirm_token ), $allowed_confirmation_tokens, true ) );

			if ( ! $is_confirmed || ! $token_is_allowed || ! $this->security->consume_destructive_confirmation( $ability_name, $confirm_token, $requesting_user_id ) ) {
				$issued  = $this->security->issue_destructive_confirmation( $ability_name, $requesting_user_id );
				$payload = [
					'success'               => false,
					'requires_confirmation' => true,
					'error'                 => [
						'code'       => 'clawpress_confirmation_required',
						'message'    => __( 'Explicit confirmation is required for this destructive action.', 'clawpress' ),
						'token'      => $issued['token'],
						'expires_at' => $issued['expires_at'],
					],
					'tool'                  => $normalized_tool_name,
					'ability'               => $ability_name,
					'safety_class'          => $safety_class,
				];
				$this->log_tool_call( $normalized_tool_name, $ability_name, $requesting_user_id, $execution_user_id, 'warning', $args_hash, $payload, $event_context );
				return $payload;
			}
		}

		unset( $args['confirm'], $args['confirm_token'] );

		$result = $this->run_as_execution_user(
			$execution_user_id,
			static fn() => $ability->execute( [] === $args ? new \stdClass() : $args )
		);

		if ( is_wp_error( $result ) ) {
			$payload = [
				'success'      => false,
				'error'        => [
					'code'    => $result->get_error_code(),
					'message' => $result->get_error_message(),
				],
				'tool'         => $normalized_tool_name,
				'ability'      => $ability_name,
				'safety_class' => $safety_class,
			];
			$this->log_tool_call( $normalized_tool_name, $ability_name, $requesting_user_id, $execution_user_id, 'error', $args_hash, $payload, $event_context );
			return $payload;
		}

		$payload = [
			'success'      => true,
			'tool'         => $normalized_tool_name,
			'ability'      => $ability_name,
			'safety_class' => $safety_class,
			'result'       => $result,
		];

		$this->log_tool_call( $normalized_tool_name, $ability_name, $requesting_user_id, $execution_user_id, 'success', $args_hash, $payload, $event_context );

		return $payload;
	}

	/**
	 * Normalize model tool-call arguments.
	 *
	 * @param mixed $raw_args Raw args payload.
	 * @return array<string,mixed>
	 */
	private function normalize_tool_args( $raw_args ): array {
		if ( is_array( $raw_args ) ) {
			return $raw_args;
		}

		if ( is_object( $raw_args ) ) {
			return (array) $raw_args;
		}

		if ( ! is_string( $raw_args ) || '' === trim( $raw_args ) ) {
			return [];
		}

		$decoded = json_decode( $raw_args, true );
		return is_array( $decoded ) ? $decoded : [];
	}

	/**
	 * Normalize a confirmation token argument from model-produced input.
	 *
	 * @param mixed $raw_token Raw token value.
	 */
	private function normalize_confirmation_token( $raw_token ): ?string {
		if ( null === $raw_token ) {
			return null;
		}

		$token = trim( (string) $raw_token );
		if ( '' === $token ) {
			return null;
		}

		// Some providers echo escaped or quoted token text; normalize before checks.
		$token = str_replace( [ '\\"', "\\'" ], [ '"', "'" ], $token );
		$token = trim( $token, " \t\n\r\0\x0B\"'`" );
		if ( '' === $token ) {
			return null;
		}

		if ( preg_match( '/([a-f0-9]{10,64})/i', $token, $matches ) ) {
			$token = $matches[1];
		}

		return strtolower( $token );
	}

	/**
	 * Normalize allowlisted confirmation tokens from execution context.
	 *
	 * @param mixed $raw_tokens Raw token list payload.
	 * @return array<int,string>
	 */
	private function normalize_allowed_confirmation_tokens( $raw_tokens ): array {
		if ( ! is_array( $raw_tokens ) ) {
			return [];
		}

		$tokens = [];
		foreach ( $raw_tokens as $token ) {
			$normalized = strtolower( trim( (string) $token ) );
			if ( '' === $normalized ) {
				continue;
			}

			$tokens[] = $normalized;
		}

		return array_values( array_unique( $tokens ) );
	}

	/**
	 * Resolve execution-user ID.
	 */
	private function resolve_execution_user_id(): int {
		$agent_user_id = $this->settings_helper->resolve_agent_user_id();
		if ( $agent_user_id > 0 ) {
			return $agent_user_id;
		}

		return function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0;
	}

	/**
	 * Execute callback under execution-user context and always restore requester.
	 *
	 * @param int      $execution_user_id Execution user ID.
	 * @param callable $callback Callback to execute.
	 * @return mixed
	 */
	private function run_as_execution_user( int $execution_user_id, callable $callback ) {
		$original_user_id = function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0;

		if ( $execution_user_id > 0 && function_exists( 'wp_set_current_user' ) ) {
			wp_set_current_user( $execution_user_id );
		}

		try {
			return $callback();
		} finally {
			if ( function_exists( 'wp_set_current_user' ) ) {
				wp_set_current_user( $original_user_id );
			}
		}
	}

	/**
	 * Infer safety class from ability annotations.
	 *
	 * @param \WP_Ability $ability Ability instance.
	 */
	private function infer_safety_class( \WP_Ability $ability ): string {
		$annotations = $ability->get_meta_item( 'annotations', [] );
		if ( ! is_array( $annotations ) ) {
			return 'write';
		}

		if ( isset( $annotations['destructive'] ) && true === $annotations['destructive'] ) {
			return 'destructive';
		}

		if ( isset( $annotations['readonly'] ) && true === $annotations['readonly'] ) {
			return 'read';
		}

		return 'write';
	}

	/**
	 * Resolve ability annotations from meta.
	 *
	 * @param \WP_Ability $ability Ability instance.
	 * @return array<string,bool>
	 */
	private function get_ability_annotations( \WP_Ability $ability ): array {
		$annotations = $ability->get_meta_item( 'annotations', [] );
		if ( ! is_array( $annotations ) ) {
			$annotations = [];
		}

		return [
			'readonly'    => true === ( $annotations['readonly'] ?? false ),
			'destructive' => true === ( $annotations['destructive'] ?? false ),
			'idempotent'  => true === ( $annotations['idempotent'] ?? false ),
		];
	}

	/**
	 * Resolve ability category slug.
	 *
	 * @param \WP_Ability $ability Ability instance.
	 */
	private function resolve_ability_category_slug( \WP_Ability $ability ): string {
		if ( method_exists( $ability, 'get_category' ) ) {
			$category = (string) $ability->get_category();
			if ( '' !== $category ) {
				return $category;
			}
		}

		if ( method_exists( $ability, 'get_category_slug' ) ) {
			$category = (string) $ability->get_category_slug();
			if ( '' !== $category ) {
				return $category;
			}
		}

		if ( method_exists( $ability, 'get_name' ) ) {
			$ability_name = strtolower( trim( (string) $ability->get_name() ) );
			if ( false !== strpos( $ability_name, '/' ) ) {
				$parts = explode( '/', $ability_name, 2 );
				if ( '' !== $parts[0] ) {
					return $parts[0];
				}
			}
		}

		return self::CLAWPRESS_ABILITY_NAMESPACE;
	}

	/**
	 * Build category payload for UI consumers.
	 *
	 * @param string $category_slug Category slug.
	 * @return array<string,string>
	 */
	private function build_category_payload( string $category_slug ): array {
		$slug = '' !== trim( $category_slug ) ? sanitize_key( $category_slug ) : 'uncategorized';
		if ( '' === $slug ) {
			$slug = 'uncategorized';
		}

		$label = ucwords( str_replace( [ '-', '_' ], ' ', $slug ) );

		if ( function_exists( 'wp_get_ability_category' ) ) {
			$category = wp_get_ability_category( $slug );
			if ( is_object( $category ) ) {
				if ( method_exists( $category, 'get_label' ) ) {
					$resolved_label = trim( (string) $category->get_label() );
					if ( '' !== $resolved_label ) {
						$label = $resolved_label;
					}
				}
			}
		}

		return [
			'slug'  => $slug,
			'label' => $label,
		];
	}

	/**
	 * Default enabled abilities (all ClawPress namespaced registered abilities).
	 *
	 * @return array<int,string>
	 */
	private function get_default_enabled_ability_ids(): array {
		$defaults = [];
		foreach ( $this->get_registered_ability_ids() as $ability_id ) {
			if ( 0 === strpos( $ability_id, self::CLAWPRESS_ABILITY_NAMESPACE . '/' ) ) {
				$defaults[] = $ability_id;
			}
		}

		return array_values( array_unique( $defaults ) );
	}

	/**
	 * Sanitize enabled ability IDs.
	 *
	 * @param array<int,mixed> $enabled_abilities Raw ability IDs.
	 * @return array<int,string>
	 */
	private function sanitize_enabled_ability_ids( array $enabled_abilities ): array {
		$registered = array_fill_keys( $this->get_registered_ability_ids(), true );
		$sanitized = [];

		foreach ( $enabled_abilities as $ability_name ) {
			$normalized = strtolower( trim( (string) $ability_name ) );
			if ( '' === $normalized || ! isset( $registered[ $normalized ] ) ) {
				continue;
			}

			$sanitized[] = $normalized;
		}

		return array_values( array_unique( $sanitized ) );
	}

	/**
	 * Resolve registered ability IDs.
	 *
	 * @return array<int,string>
	 */
	private function get_registered_ability_ids(): array {
		return array_keys( $this->get_registered_abilities() );
	}

	/**
	 * Resolve registered abilities keyed by ability ID.
	 *
	 * @return array<string,\WP_Ability>
	 */
	private function get_registered_abilities(): array {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return [];
		}

		$registered = wp_get_abilities();
		if ( ! is_array( $registered ) ) {
			return [];
		}

		$abilities = [];
		foreach ( $registered as $ability_id => $ability ) {
			$normalized_id = strtolower( trim( (string) $ability_id ) );
			if ( '' === $normalized_id || ! $ability instanceof \WP_Ability ) {
				continue;
			}

			$abilities[ $normalized_id ] = $ability;
		}

		ksort( $abilities );
		return $abilities;
	}

	/**
	 * Build deterministic tool-alias map for ability IDs.
	 *
	 * @param array<int,string> $ability_ids Ability IDs.
	 * @return array<string,string> Tool alias => ability ID.
	 */
	private function build_tool_alias_map( array $ability_ids ): array {
		$aliases = [];
		sort( $ability_ids );

		foreach ( $ability_ids as $ability_id ) {
			$normalized_id = strtolower( trim( (string) $ability_id ) );
			if ( '' === $normalized_id ) {
				continue;
			}

			$alias = $this->build_tool_alias_base( $normalized_id );
			if ( isset( $aliases[ $alias ] ) && $aliases[ $alias ] !== $normalized_id ) {
				$suffix       = '_' . substr( md5( $normalized_id ), 0, 8 );
				$max_base_len = max( 1, 64 - strlen( $suffix ) );
				$alias        = substr( $alias, 0, $max_base_len ) . $suffix;
			}

			$aliases[ $alias ] = $normalized_id;
		}

		return $aliases;
	}

	/**
	 * Build one tool alias from an ability ID.
	 *
	 * @param string $ability_id Ability ID.
	 */
	private function build_tool_alias_base( string $ability_id ): string {
		$namespace = '';
		$name      = $ability_id;

		if ( false !== strpos( $ability_id, '/' ) ) {
			$parts     = explode( '/', $ability_id, 2 );
			$namespace = $parts[0];
			$name      = $parts[1];
		}

		$raw_alias = self::CLAWPRESS_ABILITY_NAMESPACE === $namespace || '' === $namespace
			? $name
			: $namespace . '__' . $name;

		$alias = (string) preg_replace( '/[^a-z0-9_]+/', '_', strtolower( $raw_alias ) );
		$alias = (string) preg_replace( '/_+/', '_', $alias );
		$alias = trim( $alias, '_' );

		if ( '' === $alias ) {
			$alias = 'ability_' . substr( md5( $ability_id ), 0, 8 );
		}

		if ( preg_match( '/^[0-9]/', $alias ) ) {
			$alias = 'ability_' . $alias;
		}

		return substr( $alias, 0, 64 );
	}

	/**
	 * Resolve ability ID from a model tool/function name.
	 *
	 * @param string $tool_name Tool/function name.
	 */
	private function resolve_ability_name_from_tool_name( string $tool_name ): string {
		$normalized = strtolower( trim( $tool_name ) );
		if ( '' === $normalized ) {
			return '';
		}

		$registered = $this->get_registered_abilities();
		if ( isset( $registered[ $normalized ] ) ) {
			return $normalized;
		}

		$tool_alias_map = $this->build_tool_alias_map( array_keys( $registered ) );
		return isset( $tool_alias_map[ $normalized ] ) ? (string) $tool_alias_map[ $normalized ] : '';
	}

	/**
	 * Check whether a policy field is enabled.
	 *
	 * @param mixed $value Raw value.
	 */
	private function is_policy_enabled( $value ): bool {
		return function_exists( 'clawpress_sanitize_boolean' )
			? clawpress_sanitize_boolean( $value )
			: (bool) $value;
	}

	/**
	 * Build a structured policy-violation payload.
	 *
	 * @param string              $code Error code.
	 * @param string              $message Error message.
	 * @param string              $tool_name Tool name.
	 * @param string              $ability_name Ability ID.
	 * @param string              $safety_class Safety class.
	 * @param array<string,mixed> $runtime_policy Resolved runtime policy.
	 * @param string              $decision Decision outcome.
	 * @return array<string,mixed>
	 */
	private function build_policy_violation_payload(
		string $code,
		string $message,
		string $tool_name,
		string $ability_name,
		string $safety_class,
		array $runtime_policy,
		string $decision
	): array {
		$on_violation = isset( $runtime_policy['on_policy_violation'] )
			? strtolower( trim( (string) $runtime_policy['on_policy_violation'] ) )
			: 'deny';
		if ( ! in_array( $on_violation, [ 'deny', 'degrade', 'fail' ], true ) ) {
			$on_violation = 'deny';
		}

		$policy = [
			'trigger_type'   => isset( $runtime_policy['trigger_type'] ) ? (string) $runtime_policy['trigger_type'] : 'chat',
			'policy_profile' => isset( $runtime_policy['policy_profile'] ) ? (string) $runtime_policy['policy_profile'] : 'default',
			'on_violation'   => $on_violation,
			'decision'       => $decision,
		];

		if ( 'degrade' === $on_violation ) {
			return [
				'success'      => true,
				'degraded'     => true,
				'tool'         => $tool_name,
				'ability'      => $ability_name,
				'safety_class' => $safety_class,
				'result'       => [
					'message'         => $message,
					'policy_decision' => $decision,
				],
				'policy'       => $policy,
			];
		}

		$error_code = 'fail' === $on_violation ? $code . '_fail' : $code;

		return [
			'success'      => false,
			'error'        => [
				'code'    => $error_code,
				'message' => $message,
			],
			'tool'         => $tool_name,
			'ability'      => $ability_name,
			'safety_class' => $safety_class,
			'policy'       => $policy,
		];
	}

	/**
	 * Determine action-log status for policy violations.
	 *
	 * @param array<string,mixed> $payload Policy violation payload.
	 */
	private function resolve_policy_violation_log_status( array $payload ): string {
		if ( ! empty( $payload['success'] ) ) {
			return 'success';
		}

		$on_violation = isset( $payload['policy']['on_violation'] )
			? strtolower( trim( (string) $payload['policy']['on_violation'] ) )
			: 'deny';

		return 'fail' === $on_violation ? 'error' : 'warning';
	}

	/**
	 * Emit one tool-call event row.
	 *
	 * @param string              $tool_name Tool name.
	 * @param string              $ability_name Ability ID.
	 * @param int                 $requesting_user_id Requesting user ID.
	 * @param int                 $execution_user_id Execution user ID.
	 * @param string              $status Log status.
	 * @param string              $args_hash Hash of arguments.
	 * @param array<string,mixed> $payload Tool payload.
	 * @param array<string,mixed> $event_context Optional run/session context.
	 */
	private function log_tool_call(
		string $tool_name,
		string $ability_name,
		int $requesting_user_id,
		int $execution_user_id,
		string $status,
		string $args_hash,
		array $payload,
		array $event_context
	): void {
		$this->agent_event_helper->emit_tool_call(
			$tool_name,
			$ability_name,
			$requesting_user_id,
			$execution_user_id,
			$status,
			$args_hash,
			$payload,
			$event_context
		);
	}
}

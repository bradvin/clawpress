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
 * Central helper for ClawPress ability allowlisting and execution.
 */
final class Abilities_Helper {
	/**
	 * Tool-name to ability-id mapping.
	 *
	 * @var array<string,string>
	 */
	private const TOOL_TO_ABILITY = [
		'file_read'                => 'clawpress/file-read',
		'file_write'               => 'clawpress/file-write',
		'file_delete'              => 'clawpress/file-delete',
		'file_list'                => 'clawpress/file-list',
		'memory_short_term_add'    => 'clawpress/memory-short-term-add',
		'memory_short_term_update' => 'clawpress/memory-short-term-update',
		'memory_short_term_delete' => 'clawpress/memory-short-term-delete',
		'memory_long_term_add'     => 'clawpress/memory-long-term-add',
		'memory_long_term_update'  => 'clawpress/memory-long-term-update',
		'memory_long_term_delete'  => 'clawpress/memory-long-term-delete',
	];

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
	 * Action log helper.
	 *
	 * @var Action_Log_Helper
	 */
	private Action_Log_Helper $action_log_helper;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->settings_helper   = Settings_Helper::get_instance();
		$this->security          = Security::get_instance();
		$this->action_log_helper = Action_Log_Helper::get_instance();
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
	 * Get allowlisted tool names.
	 *
	 * @return array<int,string>
	 */
	public function get_allowlisted_tool_names(): array {
		return array_keys( self::TOOL_TO_ABILITY );
	}

	/**
	 * Get allowlisted ability IDs.
	 *
	 * @return array<int,string>
	 */
	public function get_allowlisted_ability_ids(): array {
		return array_values( self::TOOL_TO_ABILITY );
	}

	/**
	 * Build model function declarations from registered allowlisted abilities.
	 *
	 * @return array<int,FunctionDeclaration>
	 */
	public function get_tool_declarations(): array {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			return [];
		}

		$declarations = [];
		foreach ( self::TOOL_TO_ABILITY as $tool_name => $ability_name ) {
			$ability = wp_get_ability( $ability_name );
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
		$rows = [];

		foreach ( self::TOOL_TO_ABILITY as $tool_name => $ability_name ) {
			$ability = function_exists( 'wp_get_ability' )
				? wp_get_ability( $ability_name )
				: null;

			$safety_class = $ability instanceof \WP_Ability
				? $this->infer_safety_class( $ability )
				: 'unknown';

			$rows[] = [
				'tool_name'    => $tool_name,
				'ability_name' => $ability_name,
				'registered'   => $ability instanceof \WP_Ability,
				'safety_class' => $safety_class,
			];
		}

		return $rows;
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
		$ability_name                = self::TOOL_TO_ABILITY[ $normalized_tool_name ] ?? '';
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

		$args_json = wp_json_encode( $args );
		$args_hash = false !== $args_json ? hash( 'sha256', (string) $args_json ) : '';

		if ( '' === $ability_name ) {
			$payload = [
				'success' => false,
				'error'   => [
					'code'    => 'clawpress_tool_not_allowlisted',
					'message' => __( 'The requested tool is not allowlisted.', 'clawpress' ),
				],
				'tool'    => $normalized_tool_name,
			];
			$this->log_tool_call( $normalized_tool_name, $ability_name, $requesting_user_id, $execution_user_id, 'error', $args_hash, $payload );
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
			$this->log_tool_call( $normalized_tool_name, $ability_name, $requesting_user_id, $execution_user_id, 'error', $args_hash, $payload );
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
			$this->log_tool_call( $normalized_tool_name, $ability_name, $requesting_user_id, $execution_user_id, 'error', $args_hash, $payload );
			return $payload;
		}

		$access_check = $this->security->assert_requesting_user_allowed();
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
			$this->log_tool_call( $normalized_tool_name, $ability_name, $requesting_user_id, $execution_user_id, 'error', $args_hash, $payload );
			return $payload;
		}

		$safety_class = $this->infer_safety_class( $ability );
		if ( ! $skip_confirmation && $this->security->requires_confirmation_for_safety_class( $safety_class ) ) {
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
				$this->log_tool_call( $normalized_tool_name, $ability_name, $requesting_user_id, $execution_user_id, 'warning', $args_hash, $payload );
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
				$this->log_tool_call( $normalized_tool_name, $ability_name, $requesting_user_id, $execution_user_id, 'warning', $args_hash, $payload );
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
			$this->log_tool_call( $normalized_tool_name, $ability_name, $requesting_user_id, $execution_user_id, 'error', $args_hash, $payload );
			return $payload;
		}

		$payload = [
			'success'      => true,
			'tool'         => $normalized_tool_name,
			'ability'      => $ability_name,
			'safety_class' => $safety_class,
			'result'       => $result,
		];

		$this->log_tool_call( $normalized_tool_name, $ability_name, $requesting_user_id, $execution_user_id, 'success', $args_hash, $payload );

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
	 * Write one tool-call action ledger row.
	 *
	 * @param string              $tool_name Tool name.
	 * @param string              $ability_name Ability ID.
	 * @param int                 $requesting_user_id Requesting user ID.
	 * @param int                 $execution_user_id Execution user ID.
	 * @param string              $status Log status.
	 * @param string              $args_hash Hash of arguments.
	 * @param array<string,mixed> $payload Tool payload.
	 */
	private function log_tool_call(
		string $tool_name,
		string $ability_name,
		int $requesting_user_id,
		int $execution_user_id,
		string $status,
		string $args_hash,
		array $payload
	): void {
		$this->action_log_helper->log_event(
			'tool/' . $tool_name,
			[
				'event_type'         => 'tool_call',
				'status'             => $status,
				'message'            => isset( $payload['error']['message'] )
					? (string) $payload['error']['message']
					: __( 'Tool execution completed.', 'clawpress' ),
				'requesting_user_id' => $requesting_user_id > 0 ? $requesting_user_id : null,
				'execution_user_id'  => $execution_user_id > 0 ? $execution_user_id : null,
				'context'            => [
					'tool_name'    => $tool_name,
					'ability_name' => $ability_name,
					'args_hash'    => $args_hash,
					'success'      => ! empty( $payload['success'] ),
					'result'       => isset( $payload['result'] ) ? $payload['result'] : null,
					'error'        => isset( $payload['error'] ) ? $payload['error'] : null,
				],
			]
		);
	}
}

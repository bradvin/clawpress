<?php
/**
 * Policy helper for trigger-based runtime guardrails.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Resolve runtime policy from trigger context and profile overrides.
 */
final class Policy_Helper {
	/**
	 * Singleton instance.
	 *
	 * @var ?self
	 */
	private static ?self $instance = null;

	/**
	 * Base policy defaults.
	 *
	 * @var array<string,mixed>
	 */
	private const BASE_POLICY = [
		'allow_tools'                          => true,
		'allow_destructive_tools'              => true,
		'require_confirmation_for_destructive' => true,
		'allow_file_delete'                    => true,
		'max_tool_rounds'                      => 4,
		'max_tool_calls_per_round'             => 6,
		'max_wall_time_seconds'                => 120,
		'allow_network'                        => false,
		'allow_background_followups'           => true,
		'on_policy_violation'                  => 'deny',
	];

	/**
	 * Trigger-specific policy overrides.
	 *
	 * @var array<string,array<string,mixed>>
	 */
	private const TRIGGER_POLICY_OVERRIDES = [
		'chat'          => [
			// Extended during runtime-loop testing to reduce premature wall-time exits.
			'max_wall_time_seconds'    => 1200,
			'max_tool_rounds'          => 6,
			'max_tool_calls_per_round' => 8,
		],
		'heartbeat'     => [
			'allow_destructive_tools'    => false,
			'allow_file_delete'          => false,
			'max_tool_rounds'            => 2,
			'max_tool_calls_per_round'   => 3,
			'max_wall_time_seconds'      => 45,
			'allow_background_followups' => false,
		],
		'spawned_agent' => [
			'allow_destructive_tools'  => false,
			'allow_file_delete'        => false,
			'max_tool_rounds'          => 3,
			'max_tool_calls_per_round' => 4,
			'max_wall_time_seconds'    => 90,
		],
	];

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
	 * Resolve a normalized runtime policy for the current run.
	 *
	 * @param string              $trigger_type Trigger type (`chat`, `heartbeat`, `spawned_agent`).
	 * @param array<string,mixed> $session_metadata Optional session metadata.
	 * @param array<string,mixed> $profile_overrides Optional direct policy overrides.
	 * @return array<string,mixed>
	 */
	public function resolve_runtime_policy( string $trigger_type, array $session_metadata = [], array $profile_overrides = [] ): array {
		$normalized_trigger = $this->normalize_trigger_type( $trigger_type );
		$policy_profile     = isset( $session_metadata['policy_profile'] )
			? $this->normalize_key( (string) $session_metadata['policy_profile'] )
			: 'default';

		$resolved = self::BASE_POLICY;
		$resolved = array_merge( $resolved, self::TRIGGER_POLICY_OVERRIDES[ $normalized_trigger ] ?? [] );

		if ( isset( $session_metadata['policy_overrides'] ) && is_array( $session_metadata['policy_overrides'] ) ) {
			$resolved = array_merge( $resolved, $session_metadata['policy_overrides'] );
		}

		if ( [] !== $profile_overrides ) {
			$resolved = array_merge( $resolved, $profile_overrides );
		}

		return [
			'trigger_type'                         => $normalized_trigger,
			'policy_profile'                       => '' !== $policy_profile ? $policy_profile : 'default',
			'allow_tools'                          => clawpress_sanitize_boolean( $resolved['allow_tools'] ?? true ),
			'allow_destructive_tools'              => clawpress_sanitize_boolean( $resolved['allow_destructive_tools'] ?? true ),
			'require_confirmation_for_destructive' => clawpress_sanitize_boolean( $resolved['require_confirmation_for_destructive'] ?? true ),
			'allow_file_delete'                    => clawpress_sanitize_boolean( $resolved['allow_file_delete'] ?? true ),
			'max_tool_rounds'                      => max( 1, (int) ( $resolved['max_tool_rounds'] ?? 4 ) ),
			'max_tool_calls_per_round'             => max( 1, (int) ( $resolved['max_tool_calls_per_round'] ?? 6 ) ),
			'max_wall_time_seconds'                => max( 1, (int) ( $resolved['max_wall_time_seconds'] ?? 120 ) ),
			'allow_network'                        => clawpress_sanitize_boolean( $resolved['allow_network'] ?? false ),
			'allow_background_followups'           => clawpress_sanitize_boolean( $resolved['allow_background_followups'] ?? true ),
			'on_policy_violation'                  => $this->normalize_violation_mode( $resolved['on_policy_violation'] ?? 'deny' ),
		];
	}

	/**
	 * Normalize trigger type.
	 *
	 * @param string $trigger_type Raw trigger type.
	 */
	private function normalize_trigger_type( string $trigger_type ): string {
		$normalized = $this->normalize_key( $trigger_type );
		if ( '' === $normalized ) {
			return 'chat';
		}

		if ( isset( self::TRIGGER_POLICY_OVERRIDES[ $normalized ] ) ) {
			return $normalized;
		}

		return 'chat';
	}

	/**
	 * Normalize policy violation handling mode.
	 *
	 * @param mixed $raw_mode Raw mode value.
	 */
	private function normalize_violation_mode( $raw_mode ): string {
		$mode = $this->normalize_key( (string) $raw_mode );
		if ( in_array( $mode, [ 'deny', 'degrade', 'fail' ], true ) ) {
			return $mode;
		}

		return 'deny';
	}

	/**
	 * Normalize key-like text without requiring WordPress globals.
	 *
	 * @param string $value Raw key-like text.
	 */
	private function normalize_key( string $value ): string {
		return sanitize_key( $value );
	}
}

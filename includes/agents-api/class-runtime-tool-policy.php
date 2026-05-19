<?php
/**
 * Agents API runtime policy adapter for ClawPress tools.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\AgentsAPI;

defined( 'ABSPATH' ) || exit;

/**
 * Adapts ClawPress runtime guardrails to Agents API tool policy contracts.
 */
final class Runtime_Tool_Policy implements \WP_Agent_Tool_Access_Policy, \WP_Agent_Action_Policy_Provider {
	/**
	 * Return an Agents API tool visibility policy for the current runtime.
	 *
	 * @param array<string,mixed> $context Runtime context.
	 * @return array<string,mixed>|null Policy fragment, or null for no opinion.
	 */
	public function get_tool_policy( array $context ): ?array {
		$runtime_policy = $this->runtime_policy( $context );
		if ( [] === $runtime_policy || $this->allows_degraded_policy_violation( $runtime_policy ) ) {
			return null;
		}

		if ( ! $this->policy_enabled( $runtime_policy['allow_tools'] ?? true ) ) {
			return [
				'mode'       => 'allow',
				'tools'      => [],
				'categories' => [],
			];
		}

		$deny_tools      = [];
		$deny_categories = [];

		if ( ! $this->policy_enabled( $runtime_policy['allow_network'] ?? true ) ) {
			$deny_categories[] = 'network';
		}

		if ( ! $this->policy_enabled( $runtime_policy['allow_destructive_tools'] ?? true ) ) {
			$deny_categories[] = 'destructive';
		}

		if ( ! $this->policy_enabled( $runtime_policy['allow_file_delete'] ?? true ) ) {
			$deny_tools = array_merge( $deny_tools, $this->file_delete_tool_names() );
		}

		if ( [] === $deny_tools && [] === $deny_categories ) {
			return null;
		}

		return [
			'mode'       => 'deny',
			'tools'      => array_values( array_unique( $deny_tools ) ),
			'categories' => array_values( array_unique( $deny_categories ) ),
		];
	}

	/**
	 * Resolve a tool action policy override for the current runtime.
	 *
	 * @param array<string,mixed> $context Action policy context.
	 */
	public function get_action_policy( array $context ): ?string {
		$runtime_policy = $this->runtime_policy( $context );
		$tool_def       = $this->tool_definition( $context );
		$tool_name      = $this->tool_name( $context, $tool_def );

		if ( [] !== $runtime_policy && $this->tool_is_hard_blocked( $runtime_policy, $tool_name, $tool_def ) ) {
			return 'forbidden';
		}

		if (
			$this->is_destructive_tool( $tool_name, $tool_def )
			&& ( [] === $runtime_policy || $this->policy_enabled( $runtime_policy['require_confirmation_for_destructive'] ?? true ) )
		) {
			return 'preview';
		}

		return null;
	}

	/**
	 * Whether the runtime policy blocks execution of a tool.
	 *
	 * @param array<string,mixed> $runtime_policy Runtime policy.
	 * @param string              $tool_name Tool name.
	 * @param array<string,mixed> $tool_def Tool declaration.
	 */
	private function tool_is_hard_blocked( array $runtime_policy, string $tool_name, array $tool_def ): bool {
		if ( $this->allows_degraded_policy_violation( $runtime_policy ) ) {
			return false;
		}

		if ( ! $this->policy_enabled( $runtime_policy['allow_tools'] ?? true ) ) {
			return true;
		}

		if ( $this->is_network_tool( $tool_def ) && ! $this->policy_enabled( $runtime_policy['allow_network'] ?? true ) ) {
			return true;
		}

		if ( $this->is_destructive_tool( $tool_name, $tool_def ) && ! $this->policy_enabled( $runtime_policy['allow_destructive_tools'] ?? true ) ) {
			return true;
		}

		return $this->is_file_delete_tool( $tool_name, $tool_def ) && ! $this->policy_enabled( $runtime_policy['allow_file_delete'] ?? true );
	}

	/**
	 * Extract runtime policy from context.
	 *
	 * @param array<string,mixed> $context Runtime context.
	 * @return array<string,mixed>
	 */
	private function runtime_policy( array $context ): array {
		return isset( $context['runtime_policy'] ) && is_array( $context['runtime_policy'] )
			? $context['runtime_policy']
			: [];
	}

	/**
	 * Extract tool declaration from action policy context.
	 *
	 * @param array<string,mixed> $context Runtime context.
	 * @return array<string,mixed>
	 */
	private function tool_definition( array $context ): array {
		if ( isset( $context['tool_def'] ) && is_array( $context['tool_def'] ) ) {
			return $context['tool_def'];
		}

		return isset( $context['tool_definition'] ) && is_array( $context['tool_definition'] )
			? $context['tool_definition']
			: [];
	}

	/**
	 * Resolve the tool name from context/declaration.
	 *
	 * @param array<string,mixed> $context Runtime context.
	 * @param array<string,mixed> $tool_def Tool declaration.
	 */
	private function tool_name( array $context, array $tool_def ): string {
		$tool_name = isset( $context['tool_name'] ) ? trim( (string) $context['tool_name'] ) : '';
		if ( '' !== $tool_name ) {
			return strtolower( $tool_name );
		}

		$tool_name = isset( $tool_def['name'] ) ? trim( (string) $tool_def['name'] ) : '';
		return strtolower( $tool_name );
	}

	/**
	 * Whether a tool is destructive.
	 *
	 * @param string              $tool_name Tool name.
	 * @param array<string,mixed> $tool_def Tool declaration.
	 */
	private function is_destructive_tool( string $tool_name, array $tool_def ): bool {
		$annotations = isset( $tool_def['annotations'] ) && is_array( $tool_def['annotations'] ) ? $tool_def['annotations'] : [];
		if ( true === ( $annotations['destructive'] ?? false ) ) {
			return true;
		}

		if ( 'destructive' === strtolower( trim( (string) ( $tool_def['safety_class'] ?? '' ) ) ) ) {
			return true;
		}

		return $this->tool_matches_category( $tool_def, 'destructive' ) || $this->is_file_delete_tool( $tool_name, $tool_def );
	}

	/**
	 * Whether a tool requires network access.
	 *
	 * @param array<string,mixed> $tool_def Tool declaration.
	 */
	private function is_network_tool( array $tool_def ): bool {
		$annotations = isset( $tool_def['annotations'] ) && is_array( $tool_def['annotations'] ) ? $tool_def['annotations'] : [];
		return true === ( $annotations['network'] ?? false ) || $this->tool_matches_category( $tool_def, 'network' );
	}

	/**
	 * Whether a tool maps to file deletion.
	 *
	 * @param string              $tool_name Tool name.
	 * @param array<string,mixed> $tool_def Tool declaration.
	 */
	private function is_file_delete_tool( string $tool_name, array $tool_def ): bool {
		$names = $this->file_delete_tool_names();
		if ( in_array( strtolower( $tool_name ), $names, true ) ) {
			return true;
		}

		$ability = isset( $tool_def['ability'] ) ? strtolower( trim( (string) $tool_def['ability'] ) ) : '';
		return in_array( $ability, $names, true );
	}

	/**
	 * Whether a tool declaration has a category.
	 *
	 * @param array<string,mixed> $tool_def Tool declaration.
	 * @param string              $category Category slug.
	 */
	private function tool_matches_category( array $tool_def, string $category ): bool {
		$categories = [];

		foreach ( [ 'category', 'ability_category' ] as $key ) {
			if ( isset( $tool_def[ $key ] ) && is_string( $tool_def[ $key ] ) ) {
				$categories[] = strtolower( trim( $tool_def[ $key ] ) );
			}
		}

		foreach ( [ 'categories', 'ability_categories' ] as $key ) {
			if ( ! isset( $tool_def[ $key ] ) || ! is_array( $tool_def[ $key ] ) ) {
				continue;
			}

			foreach ( $tool_def[ $key ] as $item ) {
				if ( is_string( $item ) ) {
					$categories[] = strtolower( trim( $item ) );
				}
			}
		}

		return in_array( strtolower( trim( $category ) ), array_filter( $categories ), true );
	}

	/**
	 * Normalize common runtime boolean values.
	 *
	 * @param mixed $value Raw value.
	 */
	private function policy_enabled( $value ): bool {
		if ( function_exists( 'clawpress_sanitize_boolean' ) ) {
			return \clawpress_sanitize_boolean( $value );
		}

		if ( is_bool( $value ) ) {
			return $value;
		}

		return in_array( strtolower( (string) $value ), [ '1', 'true', 'yes', 'on' ], true );
	}

	/**
	 * Whether policy violations should remain visible and return degraded results.
	 *
	 * @param array<string,mixed> $runtime_policy Runtime policy.
	 */
	private function allows_degraded_policy_violation( array $runtime_policy ): bool {
		return 'degrade' === strtolower( trim( (string) ( $runtime_policy['on_policy_violation'] ?? '' ) ) );
	}

	/**
	 * Tool names and ability IDs for file deletion.
	 *
	 * @return array<int,string>
	 */
	private function file_delete_tool_names(): array {
		return [
			'clawpress/file-delete',
			'file_delete',
		];
	}
}

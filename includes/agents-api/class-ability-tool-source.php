<?php
/**
 * Agents API tool source for ClawPress abilities.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\AgentsAPI;

use ClawPress\Helpers\Abilities_Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Exposes registered WP abilities as Agents API tool declarations.
 */
final class Ability_Tool_Source {
	/**
	 * Abilities helper.
	 *
	 * @var Abilities_Helper
	 */
	private Abilities_Helper $abilities_helper;

	/**
	 * Constructor.
	 *
	 * @param Abilities_Helper|null $abilities_helper Optional helper override.
	 */
	public function __construct( ?Abilities_Helper $abilities_helper = null ) {
		$this->abilities_helper = $abilities_helper ?? Abilities_Helper::get_instance();
	}

	/**
	 * Gather tool declarations keyed by tool name.
	 *
	 * @param array<string,mixed> $context Runtime context.
	 * @return array<string,array<string,mixed>>
	 */
	public function gather( array $context = [] ): array {
		$state = $this->abilities_helper->get_ability_settings_state();
		if ( ! isset( $state['abilities'] ) || ! is_array( $state['abilities'] ) ) {
			return [];
		}

		$tools = [];
		foreach ( $state['abilities'] as $ability_state ) {
			if ( ! is_array( $ability_state ) || empty( $ability_state['registered'] ) || empty( $ability_state['enabled'] ) ) {
				continue;
			}

			$ability_name = isset( $ability_state['ability_name'] ) ? strtolower( trim( (string) $ability_state['ability_name'] ) ) : '';
			if ( '' === $ability_name ) {
				continue;
			}

			$ability = function_exists( 'wp_get_ability' ) ? wp_get_ability( $ability_name ) : null;
			if ( ! $ability instanceof \WP_Ability ) {
				continue;
			}

			$declaration            = $this->build_tool_declaration( $ability_name, $ability, $ability_state, $context );
			$tools[ $ability_name ] = $declaration;

			$tool_alias = isset( $ability_state['tool_name'] ) ? strtolower( trim( (string) $ability_state['tool_name'] ) ) : '';
			if ( '' !== $tool_alias && ! isset( $tools[ $tool_alias ] ) ) {
				$tools[ $tool_alias ]         = $declaration;
				$tools[ $tool_alias ]['name'] = $tool_alias;
			}
		}

		return $this->apply_tool_policy( $tools, $context );
	}

	/**
	 * Build one Agents API declaration from a WP ability.
	 *
	 * @param string              $ability_name Ability ID.
	 * @param \WP_Ability         $ability Ability object.
	 * @param array<string,mixed> $ability_state UI/settings state.
	 * @param array<string,mixed> $context Runtime context.
	 * @return array<string,mixed>
	 */
	private function build_tool_declaration( string $ability_name, \WP_Ability $ability, array $ability_state, array $context ): array {
		$annotations  = isset( $ability_state['annotations'] ) && is_array( $ability_state['annotations'] ) ? $ability_state['annotations'] : [];
		$safety_class = isset( $ability_state['safety_class'] ) ? (string) $ability_state['safety_class'] : 'write';
		$category     = isset( $ability_state['category']['slug'] ) ? (string) $ability_state['category']['slug'] : 'clawpress';

		$categories = array_values(
			array_unique(
				array_filter(
					[
						'clawpress',
						$category,
						$safety_class,
						! empty( $annotations['readonly'] ) ? 'read' : '',
						! empty( $annotations['destructive'] ) ? 'destructive' : '',
						! empty( $annotations['network'] ) ? 'network' : '',
					]
				)
			)
		);

		$declaration = [
			'name'          => $ability_name,
			'source'        => 'clawpress',
			'description'   => (string) $ability->get_description(),
			'parameters'    => $this->normalize_parameters( $ability->get_input_schema() ),
			'ability'       => $ability_name,
			'categories'    => $categories,
			'safety_class'  => $safety_class,
			'modes'         => [ 'chat', 'pipeline', 'system' ],
			'action_policy' => 'destructive' === $safety_class ? 'preview' : 'direct',
			'annotations'   => $annotations,
		];

		$declaration['action_policy'] = $this->resolve_action_policy( $ability_name, $declaration, $context );

		return $declaration;
	}

	/**
	 * Apply Agents API tool visibility policy to gathered tools.
	 *
	 * @param array<string,array<string,mixed>> $tools Tool declarations.
	 * @param array<string,mixed>               $context Runtime context.
	 * @return array<string,array<string,mixed>>
	 */
	private function apply_tool_policy( array $tools, array $context ): array {
		if (
			[] === $tools
			|| ! class_exists( '\WP_Agent_Tool_Policy' )
			|| ! interface_exists( '\WP_Agent_Tool_Access_Policy' )
			|| ! interface_exists( '\WP_Agent_Action_Policy_Provider' )
		) {
			return $tools;
		}

		$context['tool_policy_providers'] = $this->append_policy_provider(
			isset( $context['tool_policy_providers'] ) && is_array( $context['tool_policy_providers'] )
				? $context['tool_policy_providers']
				: []
		);

		return ( new \WP_Agent_Tool_Policy() )->resolve( $tools, $context );
	}

	/**
	 * Resolve the canonical Agents API action policy for one declaration.
	 *
	 * @param string              $tool_name Tool name.
	 * @param array<string,mixed> $declaration Tool declaration.
	 * @param array<string,mixed> $context Runtime context.
	 */
	private function resolve_action_policy( string $tool_name, array $declaration, array $context ): string {
		if ( ! class_exists( '\WP_Agent_Action_Policy_Resolver' ) || ! interface_exists( '\WP_Agent_Action_Policy_Provider' ) ) {
			return isset( $declaration['action_policy'] ) ? (string) $declaration['action_policy'] : 'direct';
		}

		$context['tool_name']               = $tool_name;
		$context['tool_def']                = $declaration;
		$context['action_policy_providers'] = $this->append_policy_provider(
			isset( $context['action_policy_providers'] ) && is_array( $context['action_policy_providers'] )
				? $context['action_policy_providers']
				: []
		);

		$resolved = ( new \WP_Agent_Action_Policy_Resolver() )->resolve_for_tool( $context );
		return is_string( $resolved ) && '' !== trim( $resolved ) ? $resolved : 'direct';
	}

	/**
	 * Append the ClawPress runtime policy provider when the contract is available.
	 *
	 * @param array<int,mixed> $providers Existing providers.
	 * @return array<int,mixed>
	 */
	private function append_policy_provider( array $providers ): array {
		$providers[] = new Runtime_Tool_Policy();
		return $providers;
	}

	/**
	 * Normalize an ability input schema for Agents API declarations.
	 *
	 * @param mixed $schema Raw ability input schema.
	 * @return array<string,mixed>
	 */
	private function normalize_parameters( $schema ): array {
		if ( ! is_array( $schema ) || [] === $schema ) {
			return [
				'type'                 => 'object',
				'properties'           => new \stdClass(),
				'additionalProperties' => false,
			];
		}

		return $schema;
	}
}

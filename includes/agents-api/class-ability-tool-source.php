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
		unset( $context );

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

			$declaration = $this->build_tool_declaration( $ability_name, $ability, $ability_state );
			$tools[ $ability_name ] = $declaration;

			$tool_alias = isset( $ability_state['tool_name'] ) ? strtolower( trim( (string) $ability_state['tool_name'] ) ) : '';
			if ( '' !== $tool_alias && ! isset( $tools[ $tool_alias ] ) ) {
				$tools[ $tool_alias ]         = $declaration;
				$tools[ $tool_alias ]['name'] = $tool_alias;
			}
		}

		return $tools;
	}

	/**
	 * Build one Agents API declaration from a WP ability.
	 *
	 * @param string              $ability_name Ability ID.
	 * @param \WP_Ability         $ability Ability object.
	 * @param array<string,mixed> $ability_state UI/settings state.
	 * @return array<string,mixed>
	 */
	private function build_tool_declaration( string $ability_name, \WP_Ability $ability, array $ability_state ): array {
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
					]
				)
			)
		);

		return [
			'name'          => $ability_name,
			'source'        => 'clawpress',
			'description'   => (string) $ability->get_description(),
			'parameters'    => $this->normalize_parameters( $ability->get_input_schema() ),
			'ability'       => $ability_name,
			'categories'    => $categories,
			'modes'         => [ 'chat', 'pipeline', 'system' ],
			'action_policy' => 'destructive' === $safety_class ? 'preview' : 'direct',
			'annotations'   => $annotations,
		];
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

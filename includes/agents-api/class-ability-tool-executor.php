<?php
/**
 * Agents API tool executor for ClawPress abilities.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\AgentsAPI;

use AgentsAPI\AI\Tools\WP_Agent_Tool_Executor;
use ClawPress\Helpers\Abilities_Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Executes prepared Agents API tool calls through ClawPress abilities.
 */
final class Ability_Tool_Executor implements WP_Agent_Tool_Executor {
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
	 * Execute a prepared tool call.
	 *
	 * @param array<string,mixed> $tool_call Prepared tool call.
	 * @param array<string,mixed> $tool_definition Tool declaration.
	 * @param array<string,mixed> $context Runtime context.
	 * @return array<string,mixed>
	 */
	public function executeWP_Agent_Tool_Call( array $tool_call, array $tool_definition, array $context = [] ): array {
		$tool_name    = isset( $tool_call['tool_name'] ) ? (string) $tool_call['tool_name'] : '';
		$ability_name = isset( $tool_definition['ability'] ) ? (string) $tool_definition['ability'] : $tool_name;
		$parameters   = isset( $tool_call['parameters'] ) && is_array( $tool_call['parameters'] ) ? $tool_call['parameters'] : [];

		$payload = $this->abilities_helper->execute_clawpress_tool_call( $ability_name, $parameters, $context );
		$success = ! empty( $payload['success'] );

		$metadata = [
			'ability'           => $ability_name,
			'clawpress_payload' => $payload,
		];

		if ( $success ) {
			return [
				'success'   => true,
				'tool_name' => $tool_name,
				'result'    => $payload,
				'metadata'  => $metadata,
			];
		}

		return [
			'success'   => false,
			'tool_name' => $tool_name,
			'error'     => $this->resolve_error_message( $payload ),
			'metadata'  => $metadata,
		];
	}

	/**
	 * Resolve a readable error message from a ClawPress payload.
	 *
	 * @param array<string,mixed> $payload ClawPress tool payload.
	 */
	private function resolve_error_message( array $payload ): string {
		if ( isset( $payload['error']['message'] ) ) {
			return (string) $payload['error']['message'];
		}

		if ( isset( $payload['error'] ) && is_string( $payload['error'] ) ) {
			return $payload['error'];
		}

		return __( 'Tool execution failed.', 'clawpress' );
	}
}

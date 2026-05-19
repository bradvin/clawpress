<?php
/**
 * Agents API completion policy for ClawPress confirmation pauses.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\AgentsAPI;

use AgentsAPI\AI\WP_Agent_Conversation_Completion_Decision;
use AgentsAPI\AI\WP_Agent_Conversation_Completion_Policy;

defined( 'ABSPATH' ) || exit;

/**
 * Stops the shared conversation loop when a ClawPress tool needs confirmation.
 */
final class Confirmation_Completion_Policy implements WP_Agent_Conversation_Completion_Policy {
	/**
	 * Record a tool result and decide whether the run is complete.
	 *
	 * @param string                    $tool_name Tool name.
	 * @param array<string,mixed>|null  $tool_def Tool declaration.
	 * @param array<string,mixed>       $tool_result Tool execution result.
	 * @param array<string,mixed>       $runtime_context Runtime context.
	 * @param int                       $turn_count Current turn count.
	 */
	public function recordToolResult( string $tool_name, ?array $tool_def, array $tool_result, array $runtime_context, int $turn_count ): WP_Agent_Conversation_Completion_Decision {
		unset( $tool_def, $runtime_context );

		$payload = isset( $tool_result['metadata']['clawpress_payload'] ) && is_array( $tool_result['metadata']['clawpress_payload'] )
			? $tool_result['metadata']['clawpress_payload']
			: [];

		if ( empty( $payload['requires_confirmation'] ) ) {
			return WP_Agent_Conversation_Completion_Decision::incomplete();
		}

		return WP_Agent_Conversation_Completion_Decision::complete(
			__( 'Tool execution requires user confirmation.', 'clawpress' ),
			[
				'tool_name'  => $tool_name,
				'turn_count' => max( 1, $turn_count ),
			]
		);
	}
}

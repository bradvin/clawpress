<?php
/**
 * Memory short-term delete ability.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Abilities\BuiltIn;

use ClawPress\Abilities\Abilities;
use ClawPress\Helpers\Memory_Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `memory_short_term_delete` ability.
 */
final class Memory_Short_Term_Delete_Ability {
	/**
	 * Ability ID.
	 */
	private const ABILITY_NAME = 'clawpress/memory-short-term-delete';

	/**
	 * Register ability.
	 */
	public static function register(): void {
		wp_register_ability(
			self::ABILITY_NAME,
			[
				'label'               => __( 'Delete Short-Term Memory', 'clawpress' ),
				'description'         => __( 'Delete short-term daily memory for a specific day.', 'clawpress' ),
				'category'            => Abilities::CATEGORY_SLUG,
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => [
						'timestamp'     => [
							'type'        => 'integer',
							'description' => __( 'Optional UNIX timestamp for the target day.', 'clawpress' ),
						],
						'confirm'       => [
							'type'        => 'boolean',
							'description' => __( 'Explicit confirmation flag for destructive actions.', 'clawpress' ),
						],
						'confirm_token' => [
							'type'        => 'string',
							'description' => __( 'Confirmation token for destructive actions.', 'clawpress' ),
						],
					],
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'                 => 'object',
					'additionalProperties' => true,
				],
				'execute_callback'    => static fn( $input = [] ) => self::execute( is_array( $input ) ? $input : [] ),
				'permission_callback' => static fn(): bool => current_user_can( 'delete_posts' ),
				'meta'                => [
					'annotations' => [
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => true,
					],
				],
			]
		);
	}

	/**
	 * Execute ability callback.
	 *
	 * @param array<string,mixed> $input Ability input.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function execute( array $input ) {
		$timestamp = isset( $input['timestamp'] ) ? (int) $input['timestamp'] : null;
		$result    = Memory_Helper::get_instance()->delete_short_term_memory( $timestamp );
		if ( empty( $result['success'] ) ) {
			return new \WP_Error( 'clawpress_memory_short_term_delete_failed', __( 'Unable to delete short-term memory.', 'clawpress' ) );
		}

		return $result;
	}
}

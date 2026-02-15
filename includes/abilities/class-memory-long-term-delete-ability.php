<?php
/**
 * Memory long-term delete ability.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Abilities\BuiltIn;

use ClawPress\Abilities\Abilities;
use ClawPress\Helpers\Memory_Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `memory_long_term_delete` ability.
 */
final class Memory_Long_Term_Delete_Ability {
	/**
	 * Ability ID.
	 */
	private const ABILITY_NAME = 'clawpress/memory-long-term-delete';

	/**
	 * Register ability.
	 */
	public static function register(): void {
		wp_register_ability(
			self::ABILITY_NAME,
			[
				'label'               => __( 'Delete Long-Term Memory', 'clawpress' ),
				'description'         => __( 'Delete long-term memory.', 'clawpress' ),
				'category'            => Abilities::CATEGORY_SLUG,
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => [
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
	public static function execute( array $input = [] ) {
		unset( $input );

		$result = Memory_Helper::get_instance()->delete_long_term_memory();
		if ( empty( $result['success'] ) ) {
			return new \WP_Error( 'clawpress_memory_long_term_delete_failed', __( 'Unable to delete long-term memory.', 'clawpress' ) );
		}

		return $result;
	}
}

<?php
/**
 * Memory long-term update ability.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Abilities\BuiltIn;

use ClawPress\Abilities\Abilities;
use ClawPress\Helpers\Memory_Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `memory_long_term_update` ability.
 */
final class Memory_Long_Term_Update_Ability {
	/**
	 * Ability ID.
	 */
	private const ABILITY_NAME = 'clawpress/memory-long-term-update';

	/**
	 * Register ability.
	 */
	public static function register(): void {
		wp_register_ability(
			self::ABILITY_NAME,
			[
				'label'               => __( 'Update Long-Term Memory', 'clawpress' ),
				'description'         => __( 'Replace long-term memory content.', 'clawpress' ),
				'category'            => Abilities::CATEGORY_SLUG,
				'input_schema'        => [
					'type'                 => 'object',
					'required'             => [ 'content' ],
					'properties'           => [
						'content' => [
							'type'        => 'string',
							'description' => __( 'Updated memory content.', 'clawpress' ),
						],
					],
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'                 => 'object',
					'additionalProperties' => true,
				],
				'execute_callback'    => static fn( $input = [] ) => self::execute( is_array( $input ) ? $input : [] ),
				'permission_callback' => static fn(): bool => current_user_can( 'edit_posts' ),
				'meta'                => [
					'annotations' => [
						'readonly'    => false,
						'destructive' => false,
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
		$content = isset( $input['content'] ) ? (string) $input['content'] : '';
		$result  = Memory_Helper::get_instance()->update_long_term_memory( $content );
		if ( empty( $result['success'] ) ) {
			return new \WP_Error( 'clawpress_memory_long_term_update_failed', __( 'Unable to update long-term memory.', 'clawpress' ) );
		}

		return $result;
	}
}

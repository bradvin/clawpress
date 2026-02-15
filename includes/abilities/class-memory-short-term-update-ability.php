<?php
/**
 * Memory short-term update ability.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Abilities\BuiltIn;

use ClawPress\Abilities\Abilities;
use ClawPress\Helpers\Memory_Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `memory_short_term_update` ability.
 */
final class Memory_Short_Term_Update_Ability {
	/**
	 * Ability ID.
	 */
	private const ABILITY_NAME = 'clawpress/memory-short-term-update';

	/**
	 * Register ability.
	 */
	public static function register(): void {
		wp_register_ability(
			self::ABILITY_NAME,
			[
				'label'               => __( 'Update Short-Term Memory', 'clawpress' ),
				'description'         => __( 'Replace short-term daily memory content.', 'clawpress' ),
				'category'            => Abilities::CATEGORY_SLUG,
				'input_schema'        => [
					'type'                 => 'object',
					'required'             => [ 'content' ],
					'properties'           => [
						'content'   => [
							'type'        => 'string',
							'description' => __( 'Updated memory content.', 'clawpress' ),
						],
						'timestamp' => [
							'type'        => 'integer',
							'description' => __( 'Optional UNIX timestamp for the target day.', 'clawpress' ),
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
		$content   = isset( $input['content'] ) ? (string) $input['content'] : '';
		$timestamp = isset( $input['timestamp'] ) ? (int) $input['timestamp'] : null;
		$result    = Memory_Helper::get_instance()->update_short_term_memory( $content, $timestamp );
		if ( empty( $result['success'] ) ) {
			return new \WP_Error( 'clawpress_memory_short_term_update_failed', __( 'Unable to update short-term memory.', 'clawpress' ) );
		}

		return $result;
	}
}

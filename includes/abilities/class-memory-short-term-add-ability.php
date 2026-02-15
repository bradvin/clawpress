<?php
/**
 * Memory short-term add ability.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Abilities\BuiltIn;

use ClawPress\Abilities\Abilities;
use ClawPress\Helpers\Memory_Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `memory_short_term_add` ability.
 */
final class Memory_Short_Term_Add_Ability {
	/**
	 * Ability ID.
	 */
	private const ABILITY_NAME = 'clawpress/memory-short-term-add';

	/**
	 * Register ability.
	 */
	public static function register(): void {
		wp_register_ability(
			self::ABILITY_NAME,
			[
				'label'               => __( 'Add Short-Term Memory', 'clawpress' ),
				'description'         => __( 'Append an entry to short-term daily memory.', 'clawpress' ),
				'category'            => Abilities::CATEGORY_SLUG,
				'input_schema'        => [
					'type'                 => 'object',
					'required'             => [ 'entry' ],
					'properties'           => [
						'entry'     => [
							'type'        => 'string',
							'description' => __( 'Memory entry content.', 'clawpress' ),
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
						'idempotent'  => false,
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
		$entry     = isset( $input['entry'] ) ? (string) $input['entry'] : '';
		$timestamp = isset( $input['timestamp'] ) ? (int) $input['timestamp'] : null;
		$result    = Memory_Helper::get_instance()->add_short_term_memory( $entry, $timestamp );
		if ( empty( $result['success'] ) ) {
			return new \WP_Error( 'clawpress_memory_short_term_add_failed', __( 'Unable to add short-term memory.', 'clawpress' ) );
		}

		return $result;
	}
}

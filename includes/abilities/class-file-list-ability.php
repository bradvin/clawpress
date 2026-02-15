<?php
/**
 * File list ability.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Abilities\BuiltIn;

use ClawPress\Abilities\Abilities;
use ClawPress\Helpers\Agent_File_Helper;
use ClawPress\Helpers\Settings_Helper;
use ClawPress\Helpers\Workspace_Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `file_list` ability.
 */
final class File_List_Ability {
	/**
	 * Ability ID.
	 */
	private const ABILITY_NAME = 'clawpress/file-list';

	/**
	 * Register ability.
	 */
	public static function register(): void {
		wp_register_ability(
			self::ABILITY_NAME,
			[
				'label'               => __( 'List Files', 'clawpress' ),
				'description'         => __( 'List available files from agent-file and workspace storage.', 'clawpress' ),
				'category'            => Abilities::CATEGORY_SLUG,
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => [],
					'additionalProperties' => false,
					'default'              => [],
				],
				'output_schema'       => [
					'type'                 => 'object',
					'required'             => [ 'items' ],
					'properties'           => [
						'items' => [
							'type'  => 'array',
							'items' => [
								'type'                 => 'object',
								'properties'           => [
									'logical_path' => [ 'type' => 'string' ],
									'source'       => [
										'type' => 'string',
										'enum' => [ 'agent-file', 'workspace' ],
									],
								],
								'additionalProperties' => true,
							],
						],
					],
					'additionalProperties' => false,
				],
				'execute_callback'    => static fn( $input = [] ) => self::execute( is_array( $input ) ? $input : [] ),
				'permission_callback' => static fn(): bool => current_user_can( 'read' ),
				'meta'                => [
					'annotations' => [
						'readonly'    => true,
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
	 * @return array<string,mixed>
	 */
	public static function execute( array $input = [] ): array {
		unset( $input );

		$items = array_merge(
			Agent_File_Helper::get_instance()->list_files(),
			Workspace_Helper::get_instance()->list_workspace_files( self::resolve_workspace_user_id() )
		);

		usort(
			$items,
			static fn ( array $left, array $right ): int => strcmp(
				(string) ( $left['logical_path'] ?? '' ),
				(string) ( $right['logical_path'] ?? '' )
			)
		);

		return [
			'items' => $items,
		];
	}

	/**
	 * Resolve user ID used for workspace operations.
	 */
	private static function resolve_workspace_user_id(): int {
		$current_user_id = function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0;
		if ( $current_user_id > 0 ) {
			return $current_user_id;
		}

		return Settings_Helper::get_instance()->resolve_agent_user_id();
	}
}

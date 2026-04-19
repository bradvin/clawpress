<?php
/**
 * File delete ability.
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
 * Registers the `file_delete` ability.
 */
final class File_Delete_Ability {
	/**
	 * Ability ID.
	 */
	private const ABILITY_NAME = 'clawpress/file-delete';

	/**
	 * Register ability.
	 */
	public static function register(): void {
		wp_register_ability(
			self::ABILITY_NAME,
			[
				'label'               => __( 'Delete File', 'clawpress' ),
				'description'         => __( 'Delete a file from agent-file or workspace storage.', 'clawpress' ),
				'category'            => Abilities::CATEGORY_SLUG,
				'input_schema'        => [
					'type'                 => 'object',
					'required'             => [ 'path' ],
					'properties'           => [
						'path'          => [
							'type'        => 'string',
							'description' => __( 'Logical file path.', 'clawpress' ),
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
					'required'             => [ 'logical_path', 'source' ],
					'properties'           => [
						'logical_path' => [ 'type' => 'string' ],
						'source'       => [
							'type' => 'string',
							'enum' => [ 'agent-file', 'workspace' ],
						],
					],
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
		$logical_path = self::normalize_file_logical_path( $input['path'] ?? '' );
		if ( '' === $logical_path ) {
			return new \WP_Error( 'clawpress_invalid_path', __( 'A valid `path` is required.', 'clawpress' ) );
		}

		$agent_result = Agent_File_Helper::get_instance()->delete_file_by_logical_path( $logical_path );
		if ( ! empty( $agent_result['success'] ) ) {
			return [
				'logical_path' => $logical_path,
				'source'       => 'agent-file',
			];
		}

		$workspace_result = Workspace_Helper::get_instance()->delete_workspace_file( self::resolve_workspace_user_id(), $logical_path );
		if ( ! empty( $workspace_result['success'] ) ) {
			return [
				'logical_path' => $logical_path,
				'source'       => 'workspace',
			];
		}

		return new \WP_Error( 'clawpress_file_not_found', __( 'No file was found to delete.', 'clawpress' ) );
	}

	/**
	 * Resolve user ID used for workspace operations.
	 */
	private static function resolve_workspace_user_id(): int {
		$current_user_id = get_current_user_id();
		if ( $current_user_id > 0 ) {
			return $current_user_id;
		}

		return Settings_Helper::get_instance()->resolve_agent_user_id();
	}

	/**
	 * Normalize an incoming logical path.
	 *
	 * @param mixed $raw_path Raw path value.
	 */
	private static function normalize_file_logical_path( $raw_path ): string {
		$path = str_replace( '\\', '/', trim( (string) $raw_path ) );
		$path = ltrim( $path, '/' );
		$path = (string) preg_replace( '#/+#', '/', $path );

		if ( '' === $path ) {
			return '';
		}

		foreach ( explode( '/', $path ) as $segment ) {
			if ( '' === $segment || '.' === $segment || '..' === $segment ) {
				return '';
			}
		}

		return $path;
	}
}

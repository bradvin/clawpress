<?php
/**
 * File write ability.
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
 * Registers the `file_write` ability.
 */
final class File_Write_Ability {
	/**
	 * Ability ID.
	 */
	private const ABILITY_NAME = 'clawpress/file-write';

	/**
	 * Register ability.
	 */
	public static function register(): void {
		wp_register_ability(
			self::ABILITY_NAME,
			[
				'label'               => __( 'Write File', 'clawpress' ),
				'description'         => __( 'Write files. Markdown is stored in agent-file; other files are stored in workspace.', 'clawpress' ),
				'category'            => Abilities::CATEGORY_SLUG,
				'input_schema'        => [
					'type'                 => 'object',
					'required'             => [ 'path', 'content' ],
					'properties'           => [
						'path'     => [
							'type'        => 'string',
							'description' => __( 'Logical file path.', 'clawpress' ),
						],
						'content'  => [
							'type'        => 'string',
							'description' => __( 'File content.', 'clawpress' ),
						],
						'encoding' => [
							'type'        => 'string',
							'enum'        => [ 'text', 'base64' ],
							'default'     => 'text',
							'description' => __( 'Content encoding.', 'clawpress' ),
						],
					],
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'                 => 'object',
					'properties'           => [
						'logical_path' => [ 'type' => 'string' ],
						'source'       => [
							'type' => 'string',
							'enum' => [ 'agent-file', 'workspace' ],
						],
						'post_id'      => [ 'type' => 'integer' ],
						'bytes'        => [ 'type' => 'integer' ],
					],
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
		$logical_path = self::normalize_file_logical_path( $input['path'] ?? '' );
		if ( '' === $logical_path ) {
			return new \WP_Error( 'clawpress_invalid_path', __( 'A valid `path` is required.', 'clawpress' ) );
		}

		$content  = isset( $input['content'] ) ? (string) $input['content'] : '';
		$encoding = isset( $input['encoding'] ) ? strtolower( trim( (string) $input['encoding'] ) ) : 'text';
		if ( 'base64' === $encoding ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decoding user-provided file bytes.
			$decoded = base64_decode( $content, true );
			if ( false === $decoded ) {
				return new \WP_Error( 'clawpress_invalid_base64', __( 'Invalid base64 content.', 'clawpress' ) );
			}

			$content = $decoded;
		}

		$user_id = self::resolve_workspace_user_id();

		if ( self::is_markdown_file( $logical_path ) ) {
			$result = Agent_File_Helper::get_instance()->upsert_file_by_logical_path( $logical_path, $content, $user_id );
			if ( empty( $result['success'] ) ) {
				return new \WP_Error( 'clawpress_file_write_failed', __( 'Unable to write the agent file.', 'clawpress' ) );
			}

			return [
				'logical_path' => $logical_path,
				'source'       => 'agent-file',
				'post_id'      => (int) $result['post_id'],
			];
		}

		$result = Workspace_Helper::get_instance()->write_workspace_file( $user_id, $logical_path, $content );
		if ( empty( $result['success'] ) ) {
			return new \WP_Error( 'clawpress_workspace_write_failed', __( 'Unable to write the workspace file.', 'clawpress' ) );
		}

		return [
			'logical_path' => $logical_path,
			'source'       => 'workspace',
			'bytes'        => (int) $result['bytes'],
		];
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

	/**
	 * Determine whether a path represents a Markdown file.
	 *
	 * @param string $path Logical path.
	 */
	private static function is_markdown_file( string $path ): bool {
		return 'md' === strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
	}
}

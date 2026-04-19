<?php
/**
 * Web fetch ability.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Abilities\BuiltIn;

use ClawPress\Abilities\Abilities;
use ClawPress\Helpers\Web_Fetch_Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `web_fetch` ability.
 */
final class Web_Fetch_Ability {
	/**
	 * Ability ID.
	 */
	private const ABILITY_NAME = 'clawpress/web-fetch';

	/**
	 * Register ability.
	 */
	public static function register(): void {
		wp_register_ability(
			self::ABILITY_NAME,
			[
				'label'               => __( 'Web Fetch', 'clawpress' ),
				'description'         => __( 'Fetch a remote URL using the configured fetcher.', 'clawpress' ),
				'category'            => Abilities::CATEGORY_SLUG,
				'input_schema'        => [
					'type'                 => 'object',
					'required'             => [ 'url' ],
					'properties'           => [
						'url'         => [
							'type'        => 'string',
							'description' => __( 'Remote URL to fetch.', 'clawpress' ),
						],
						'fetcher'     => [
							'type'        => 'string',
							'description' => __( 'Fetcher provider slug. Defaults to `wp`.', 'clawpress' ),
						],
						'method'      => [
							'type'        => 'string',
							'enum'        => [ 'GET', 'HEAD' ],
							'description' => __( 'Read-only HTTP method. Defaults to `GET`.', 'clawpress' ),
						],
						'headers'     => [
							'type'                 => 'object',
							'description'          => __( 'Optional request headers.', 'clawpress' ),
							'additionalProperties' => [
								'type' => 'string',
							],
						],
						'timeout'     => [
							'type'        => 'integer',
							'description' => __( 'Request timeout in seconds. Defaults to `15`.', 'clawpress' ),
						],
						'redirection' => [
							'type'        => 'integer',
							'description' => __( 'Maximum redirect count. Defaults to `5`.', 'clawpress' ),
						],
						'arguments'   => [
							'type'                 => 'object',
							'description'          => __( 'Optional fetcher-specific arguments for future providers.', 'clawpress' ),
							'additionalProperties' => true,
						],
					],
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'                 => 'object',
					'required'             => [
						'fetcher',
						'url',
						'method',
						'status_code',
						'status_message',
						'headers',
						'content_type',
						'body',
						'truncated',
						'body_bytes',
					],
					'properties'           => [
						'fetcher'        => [ 'type' => 'string' ],
						'url'            => [ 'type' => 'string' ],
						'method'         => [ 'type' => 'string' ],
						'status_code'    => [ 'type' => 'integer' ],
						'status_message' => [ 'type' => 'string' ],
						'headers'        => [
							'type'                 => 'object',
							'additionalProperties' => [
								'type' => 'string',
							],
						],
						'content_type'   => [ 'type' => 'string' ],
						'body'           => [ 'type' => 'string' ],
						'truncated'      => [ 'type' => 'boolean' ],
						'body_bytes'     => [ 'type' => 'integer' ],
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
						'network'     => true,
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
		return Web_Fetch_Helper::get_instance()->fetch( $input );
	}
}

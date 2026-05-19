<?php
/**
 * Tests for ClawPress runtime policy adapters for Agents API tools.
 *
 * @package ClawPress\Tests
 */

declare( strict_types=1 );

namespace {
	if ( ! interface_exists( 'WP_Agent_Tool_Access_Policy' ) ) {
		interface WP_Agent_Tool_Access_Policy {
			/**
			 * @param array<string,mixed> $context Runtime context.
			 * @return array<string,mixed>|null
			 */
			public function get_tool_policy( array $context ): ?array;
		}
	}

	if ( ! interface_exists( 'WP_Agent_Action_Policy_Provider' ) ) {
		interface WP_Agent_Action_Policy_Provider {
			/**
			 * @param array<string,mixed> $context Runtime context.
			 */
			public function get_action_policy( array $context ): ?string;
		}
	}

	if ( ! class_exists( 'WP_Agent_Tool_Policy' ) ) {
		class WP_Agent_Tool_Policy {
			/**
			 * @param array<string,array<string,mixed>> $tools Tool declarations.
			 * @param array<string,mixed>               $context Runtime context.
			 * @return array<string,array<string,mixed>>
			 */
			public function resolve( array $tools, array $context = [] ): array {
				$providers = isset( $context['tool_policy_providers'] ) && is_array( $context['tool_policy_providers'] )
					? $context['tool_policy_providers']
					: [];

				foreach ( $providers as $provider ) {
					if ( ! $provider instanceof WP_Agent_Tool_Access_Policy ) {
						continue;
					}

					$policy = $provider->get_tool_policy( $context );
					if ( ! is_array( $policy ) ) {
						continue;
					}

					$mode       = isset( $policy['mode'] ) ? (string) $policy['mode'] : 'deny';
					$names      = $this->string_list( $policy['tools'] ?? [] );
					$categories = $this->string_list( $policy['categories'] ?? [] );

					if ( 'allow' === $mode && [] === $names && [] === $categories ) {
						$tools = [];
						continue;
					}

					$filtered = [];
					foreach ( $tools as $name => $tool ) {
						$matches = in_array( $name, $names, true ) || $this->tool_matches_categories( $tool, $categories );
						if ( 'allow' === $mode && $matches ) {
							$filtered[ $name ] = $tool;
						} elseif ( 'deny' === $mode && ! $matches ) {
							$filtered[ $name ] = $tool;
						}
					}

					$tools = $filtered;
				}

				return $tools;
			}

			/**
			 * @param mixed $values Raw values.
			 * @return array<int,string>
			 */
			private function string_list( $values ): array {
				$values = is_array( $values ) ? $values : [ $values ];
				return array_values(
					array_unique(
						array_filter(
							array_map(
								static fn( $value ): string => is_string( $value ) ? trim( $value ) : '',
								$values
							)
						)
					)
				);
			}

			/**
			 * @param array<string,mixed> $tool Tool declaration.
			 * @param array<int,string>   $categories Category slugs.
			 */
			private function tool_matches_categories( array $tool, array $categories ): bool {
				if ( [] === $categories ) {
					return false;
				}

				$tool_categories = [];
				if ( isset( $tool['categories'] ) && is_array( $tool['categories'] ) ) {
					$tool_categories = array_merge( $tool_categories, $this->string_list( $tool['categories'] ) );
				}

				if ( isset( $tool['category'] ) && is_string( $tool['category'] ) ) {
					$tool_categories[] = $tool['category'];
				}

				return (bool) array_intersect( $tool_categories, $categories );
			}
		}
	}

	if ( ! class_exists( 'WP_Agent_Action_Policy_Resolver' ) ) {
		class WP_Agent_Action_Policy_Resolver {
			/**
			 * @param array<string,mixed> $context Runtime context.
			 */
			public function resolve_for_tool( array $context ): string {
				$providers = isset( $context['action_policy_providers'] ) && is_array( $context['action_policy_providers'] )
					? $context['action_policy_providers']
					: [];

				foreach ( $providers as $provider ) {
					if ( ! $provider instanceof WP_Agent_Action_Policy_Provider ) {
						continue;
					}

					$policy = $provider->get_action_policy( $context );
					if ( is_string( $policy ) && '' !== trim( $policy ) ) {
						return $policy;
					}
				}

				$tool_def = isset( $context['tool_def'] ) && is_array( $context['tool_def'] ) ? $context['tool_def'] : [];
				return isset( $tool_def['action_policy'] ) ? (string) $tool_def['action_policy'] : 'direct';
			}
		}
	}
}

namespace ClawPress\Tests\Unit {

use ClawPress\Abilities\Abilities;
use ClawPress\AgentsAPI\Ability_Tool_Source;
use ClawPress\AgentsAPI\Runtime_Tool_Policy;
use ClawPress\Helpers\Abilities_Helper;
use ClawPress\Tests\Support\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/agents-api/class-runtime-tool-policy.php';

final class AgentsApiRuntimeToolPolicyTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		( new Abilities() );
		do_action( 'wp_abilities_api_categories_init' );
		do_action( 'wp_abilities_api_init' );
	}

	public function test_runtime_policy_filters_network_and_destructive_tools_from_declarations(): void {
		$tools = ( new Ability_Tool_Source( Abilities_Helper::get_instance() ) )->gather(
			[
				'mode'           => 'chat',
				'runtime_policy' => [
					'allow_tools'                          => true,
					'allow_network'                        => false,
					'allow_destructive_tools'              => false,
					'allow_file_delete'                    => false,
					'require_confirmation_for_destructive' => true,
					'on_policy_violation'                  => 'deny',
				],
			]
		);

		$this->assertArrayHasKey( 'file_read', $tools );
		$this->assertArrayHasKey( 'clawpress/file-read', $tools );
		$this->assertArrayNotHasKey( 'web_fetch', $tools );
		$this->assertArrayNotHasKey( 'clawpress/web-fetch', $tools );
		$this->assertArrayNotHasKey( 'file_delete', $tools );
		$this->assertArrayNotHasKey( 'clawpress/file-delete', $tools );
	}

	public function test_degraded_policy_keeps_tools_visible_for_degraded_execution_payloads(): void {
		$tools = ( new Ability_Tool_Source( Abilities_Helper::get_instance() ) )->gather(
			[
				'mode'           => 'chat',
				'runtime_policy' => [
					'allow_tools'                          => true,
					'allow_network'                        => false,
					'allow_destructive_tools'              => true,
					'allow_file_delete'                    => true,
					'require_confirmation_for_destructive' => true,
					'on_policy_violation'                  => 'degrade',
				],
			]
		);

		$this->assertArrayHasKey( 'web_fetch', $tools );
		$this->assertArrayHasKey( 'clawpress/web-fetch', $tools );
	}

	public function test_destructive_tool_declares_preview_action_policy_when_confirmation_is_required(): void {
		$tools = ( new Ability_Tool_Source( Abilities_Helper::get_instance() ) )->gather(
			[
				'mode'           => 'chat',
				'runtime_policy' => [
					'allow_tools'                          => true,
					'allow_network'                        => true,
					'allow_destructive_tools'              => true,
					'allow_file_delete'                    => true,
					'require_confirmation_for_destructive' => true,
					'on_policy_violation'                  => 'deny',
				],
			]
		);

		$this->assertSame( 'preview', $tools['file_delete']['action_policy'] );
		$this->assertSame( 'preview', $tools['clawpress/file-delete']['action_policy'] );
	}

	public function test_runtime_policy_marks_hard_blocked_tool_action_as_forbidden(): void {
		$policy = new Runtime_Tool_Policy();

		$resolved = $policy->get_action_policy(
			[
				'tool_name'      => 'web_fetch',
				'tool_def'       => [
					'name'        => 'web_fetch',
					'categories'  => [ 'clawpress', 'read', 'network' ],
					'annotations' => [
						'network' => true,
					],
				],
				'runtime_policy' => [
					'allow_tools'             => true,
					'allow_network'           => false,
					'on_policy_violation'     => 'deny',
					'allow_destructive_tools' => true,
					'allow_file_delete'       => true,
				],
			]
		);

		$this->assertSame( 'forbidden', $resolved );
	}

	public function test_network_ability_declares_network_category(): void {
		$tools = ( new Ability_Tool_Source( Abilities_Helper::get_instance() ) )->gather( [ 'mode' => 'chat' ] );

		$this->assertContains( 'network', $tools['web_fetch']['categories'] );
		$this->assertTrue( $tools['web_fetch']['annotations']['network'] );
	}
}
}

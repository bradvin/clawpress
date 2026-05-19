<?php
/**
 * Tests for Agents API pending-action store integration.
 *
 * @package ClawPress\Tests
 */

declare( strict_types=1 );

namespace AgentsAPI\AI\Approvals {
	if ( ! class_exists( WP_Agent_Approval_Decision::class ) ) {
		final class WP_Agent_Approval_Decision {
			public const ACCEPTED = 'accepted';
			public const REJECTED = 'rejected';

			private string $value;

			private function __construct( string $value ) {
				$this->value = $value;
			}

			public static function accepted(): self {
				return new self( self::ACCEPTED );
			}

			public static function rejected(): self {
				return new self( self::REJECTED );
			}

			public function is_accepted(): bool {
				return self::ACCEPTED === $this->value;
			}

			public function value(): string {
				return $this->value;
			}
		}
	}

	if ( ! class_exists( WP_Agent_Pending_Action_Status::class ) ) {
		final class WP_Agent_Pending_Action_Status {
			public const PENDING  = 'pending';
			public const ACCEPTED = 'accepted';
			public const REJECTED = 'rejected';
			public const EXPIRED  = 'expired';
			public const DELETED  = 'deleted';
		}
	}

	if ( ! class_exists( WP_Agent_Pending_Action::class ) ) {
		final class WP_Agent_Pending_Action {
			/** @var array<string,mixed> */
			private array $data;

			/**
			 * @param array<string,mixed> $data Action data.
			 */
			private function __construct( array $data ) {
				$this->data = $data;
			}

			/**
			 * @param array<string,mixed> $action Action data.
			 */
			public static function from_array( array $action ): self {
				$defaults = [
					'workspace'           => null,
					'agent'               => null,
					'creator'             => null,
					'status'              => WP_Agent_Pending_Action_Status::PENDING,
					'expires_at'          => null,
					'resolved_at'         => null,
					'resolver'            => null,
					'resolution_result'   => null,
					'resolution_error'    => null,
					'resolution_metadata' => [],
					'metadata'            => [],
				];

				return new self( array_merge( $defaults, $action ) );
			}

			/**
			 * @return array<string,mixed>
			 */
			public function to_array(): array {
				return $this->data;
			}

			public function get_action_id(): string {
				return (string) $this->data['action_id'];
			}

			public function get_kind(): string {
				return (string) $this->data['kind'];
			}

			public function get_status(): string {
				return (string) $this->data['status'];
			}

			public function get_created_at(): string {
				return (string) $this->data['created_at'];
			}

			public function get_expires_at(): ?string {
				return is_string( $this->data['expires_at'] ) ? $this->data['expires_at'] : null;
			}

			public function get_workspace(): ?object {
				$workspace = $this->data['workspace'];
				if ( ! is_array( $workspace ) ) {
					return null;
				}

				return (object) [
					'workspace_type' => (string) ( $workspace['workspace_type'] ?? '' ),
					'workspace_id'   => (string) ( $workspace['workspace_id'] ?? '' ),
				];
			}
		}
	}

	if ( ! interface_exists( WP_Agent_Pending_Action_Store::class ) ) {
		interface WP_Agent_Pending_Action_Store {
			public function store( WP_Agent_Pending_Action $action ): bool;

			public function get( string $action_id, bool $include_resolved = false ): ?WP_Agent_Pending_Action;

			/**
			 * @return array<int,WP_Agent_Pending_Action>
			 */
			public function list( array $filters = [] ): array;

			/**
			 * @return array<string,mixed>
			 */
			public function summary( array $filters = [] ): array;

			/**
			 * @param mixed|null $result Resolution result.
			 */
			public function record_resolution( string $action_id, WP_Agent_Approval_Decision $decision, string $resolver, $result = null, ?string $error = null, array $metadata = [] ): bool;

			public function expire( ?string $before = null ): int;

			public function delete( string $action_id ): bool;
		}
	}

	if ( ! interface_exists( WP_Agent_Pending_Action_Resolver::class ) ) {
		interface WP_Agent_Pending_Action_Resolver {
			public function resolve_pending_action( string $pending_action_id, WP_Agent_Approval_Decision $decision, string $resolver, array $payload = [], array $context = [] ): mixed;
		}
	}
}

namespace ClawPress\Tests\Unit {

use AgentsAPI\AI\Approvals\WP_Agent_Approval_Decision;
use AgentsAPI\AI\Approvals\WP_Agent_Pending_Action;
use AgentsAPI\AI\Approvals\WP_Agent_Pending_Action_Status;
use ClawPress\AgentsAPI\Agents_API;
use ClawPress\AgentsAPI\Pending_Action_Store;
use ClawPress\Tests\Support\TestCase;

final class AgentsApiPendingActionStoreTest extends TestCase {
	public function test_store_lists_and_resolves_pending_actions(): void {
		$store = new Pending_Action_Store();

		$this->assertTrue(
			$store->store(
				WP_Agent_Pending_Action::from_array(
					[
						'action_id'   => 'batch-123',
						'kind'        => 'clawpress.tool_batch',
						'summary'     => 'Confirm one action.',
						'preview'     => [
							'calls' => [],
						],
						'apply_input' => [
							'batch_id'   => 'batch-123',
							'created_at' => 100,
							'expires_at' => time() + 300,
							'calls'      => [
								[
									'tool_name' => 'file_delete',
									'args'      => [
										'path' => '/tmp/example.txt',
									],
								],
							],
						],
						'agent'       => Agents_API::AGENT_SLUG,
						'creator'     => 'user:1',
						'created_at'  => '2026-05-19T00:00:00Z',
						'expires_at'  => gmdate( 'c', time() + 300 ),
					]
				)
			)
		);

		$listed = $store->list(
			[
				'status'  => WP_Agent_Pending_Action_Status::PENDING,
				'kind'    => 'clawpress.tool_batch',
				'agent'   => Agents_API::AGENT_SLUG,
				'creator' => 'user:1',
			]
		);

		$this->assertCount( 1, $listed );
		$this->assertSame( 'batch-123', $listed[0]->get_action_id() );
		$this->assertSame( 'Confirm one action.', $store->get( 'batch-123' )->to_array()['summary'] );
		$this->assertSame(
			[
				'total'     => 1,
				'by_status' => [
					'pending' => 1,
				],
				'by_kind'   => [
					'clawpress.tool_batch' => 1,
				],
			],
			$store->summary( [ 'kind' => 'clawpress.tool_batch' ] )
		);

		$this->assertTrue(
			$store->record_resolution(
				'batch-123',
				WP_Agent_Approval_Decision::accepted(),
				'user:1',
				[
					'batch_id' => 'batch-123',
				]
			)
		);

		$this->assertNull( $store->get( 'batch-123' ) );
		$this->assertSame( 'accepted', $store->get( 'batch-123', true )->get_status() );
	}

	public function test_expire_marks_due_pending_actions(): void {
		$store = new Pending_Action_Store();
		$store->store(
			WP_Agent_Pending_Action::from_array(
				[
					'action_id'   => 'expired-1',
					'kind'        => 'clawpress.tool_batch',
					'summary'     => 'Expired action.',
					'preview'     => [],
					'apply_input' => [],
					'created_at'  => '2026-05-19T00:00:00Z',
					'expires_at'  => '2026-05-19T00:01:00Z',
				]
			)
		);

		$this->assertSame( 1, $store->expire( '2026-05-19T00:02:00Z' ) );
		$this->assertNull( $store->get( 'expired-1' ) );
		$this->assertSame( 'expired', $store->get( 'expired-1', true )->get_status() );
	}

	public function test_agents_api_module_resolves_pending_action_store_and_resolver(): void {
		$module = new Agents_API();

		$this->assertInstanceOf(
			Pending_Action_Store::class,
			$module->resolve_pending_action_store( null, [] )
		);
		$this->assertInstanceOf(
			Pending_Action_Store::class,
			$module->resolve_pending_action_resolver( null, [] )
		);
	}
}
}

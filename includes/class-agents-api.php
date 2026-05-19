<?php
/**
 * Agents API integration module.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\AgentsAPI;

use AgentsAPI\AI\WP_Agent_Execution_Principal;
use AgentsAPI\Core\Database\Chat\WP_Agent_Conversation_Store;
use AgentsAPI\Core\FilesRepository\WP_Agent_Memory_Store;
use ClawPress\Helpers\Context_Helper;
use ClawPress\Helpers\Settings_Helper;
use ClawPress\Helpers\Workspace_Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Bridges ClawPress product behavior into the shared Agents API substrate.
 */
final class Agents_API {
	/**
	 * Registered Agents API slug for the bundled ClawPress agent.
	 */
	public const AGENT_SLUG = 'clawpress';

	/**
	 * Tool source adapter.
	 *
	 * @var ?Ability_Tool_Source
	 */
	private ?Ability_Tool_Source $tool_source = null;

	/**
	 * Conversation store adapter.
	 *
	 * @var ?Conversation_Store
	 */
	private ?Conversation_Store $conversation_store = null;

	/**
	 * Memory store adapter.
	 *
	 * @var ?Memory_Store
	 */
	private ?Memory_Store $memory_store = null;

	/**
	 * Register integration hooks.
	 */
	public function __construct() {
		if ( ! self::is_available() ) {
			return;
		}

		add_action( 'wp_agents_api_init', [ $this, 'register_agent' ] );
		add_action( 'agents_api_memory_sources', [ $this, 'register_memory_sources' ] );
		add_action( 'agents_api_context_sections', [ $this, 'register_context_sections' ] );
		add_filter( 'agents_api_execution_principal', [ $this, 'resolve_execution_principal' ], 10, 2 );
		add_filter( 'agents_api_tool_sources', [ $this, 'register_tool_sources' ], 10, 3 );
		add_filter( 'wp_agent_conversation_store', [ $this, 'resolve_conversation_store' ], 10, 2 );
		add_filter( 'wp_agent_memory_store', [ $this, 'resolve_memory_store' ], 10, 2 );
	}

	/**
	 * Check whether Agents API is loaded.
	 */
	public static function is_available(): bool {
		return function_exists( 'clawpress_is_agents_api_available' ) && clawpress_is_agents_api_available();
	}

	/**
	 * Register the bundled ClawPress agent definition.
	 */
	public function register_agent(): void {
		if ( ! function_exists( 'wp_register_agent' ) ) {
			return;
		}

		wp_register_agent(
			self::AGENT_SLUG,
			[
				'label'                            => __( 'ClawPress Agent', 'clawpress' ),
				'description'                      => __( 'WordPress admin agent that can reason over site context, use ClawPress abilities, and persist memory.', 'clawpress' ),
				'supports_conversation_compaction' => true,
				'conversation_compaction_policy'   => [
					'max_messages' => 40,
				],
				'default_config'                   => [
					'tool_policy'   => [
						'mode'       => 'allow',
						'categories' => [ 'clawpress' ],
					],
					'action_policy' => [
						'categories' => [
							'destructive' => 'preview',
						],
					],
				],
				'meta'                             => [
					'source_plugin'  => function_exists( 'plugin_basename' ) ? plugin_basename( CLAWPRESS_FILE ) : 'clawpress/clawpress.php',
					'source_type'    => 'bundled-agent',
					'source_package' => 'clawpress',
					'source_version' => CLAWPRESS_VERSION,
				],
			]
		);
	}

	/**
	 * Register ClawPress memory/context sources in the Agents API registry.
	 *
	 * @param array<string,array<string,mixed>> $sources Current source map.
	 */
	public function register_memory_sources( array $sources ): void {
		unset( $sources );

		if ( ! class_exists( '\WP_Agent_Memory_Registry' ) || ! class_exists( '\WP_Agent_Memory_Layer' ) || ! class_exists( '\WP_Agent_Context_Injection_Policy' ) ) {
			return;
		}

		\WP_Agent_Memory_Registry::register(
			'clawpress/bootstrap',
			[
				'layer'            => \WP_Agent_Memory_Layer::WORKSPACE,
				'priority'         => 10,
				'protected'        => true,
				'editable'         => false,
				'modes'            => [ 'chat', 'pipeline', 'system' ],
				'retrieval_policy' => \WP_Agent_Context_Injection_Policy::ALWAYS,
				'composable'       => true,
				'context_slug'     => 'clawpress-bootstrap',
				'convention_path'  => 'AGENTS.md',
				'label'            => __( 'ClawPress Bootstrap Files', 'clawpress' ),
				'description'      => __( 'Bootstrap and operating instructions stored as ClawPress agent files.', 'clawpress' ),
			]
		);

		\WP_Agent_Memory_Registry::register(
			'clawpress/memory',
			[
				'layer'            => \WP_Agent_Memory_Layer::USER,
				'priority'         => 30,
				'protected'        => false,
				'editable'         => true,
				'modes'            => [ 'chat', 'pipeline' ],
				'retrieval_policy' => \WP_Agent_Context_Injection_Policy::ALWAYS,
				'composable'       => true,
				'context_slug'     => 'clawpress-memory',
				'convention_path'  => 'memory.md',
				'label'            => __( 'ClawPress Memory', 'clawpress' ),
				'description'      => __( 'Long-term and daily ClawPress memory backed by the agent memory store.', 'clawpress' ),
			]
		);
	}

	/**
	 * Register context section renderers backed by existing ClawPress context helpers.
	 */
	public function register_context_sections(): void {
		if ( ! class_exists( '\WP_Agent_Context_Section_Registry' ) ) {
			return;
		}

		\WP_Agent_Context_Section_Registry::register(
			'clawpress-bootstrap',
			'clawpress-agent-files',
			10,
			static function ( array $context, array $section ): string {
				unset( $context, $section );
				return Context_Helper::get_instance()->build_agent_files_context();
			},
			[
				'modes'            => [ 'chat', 'pipeline', 'system' ],
				'retrieval_policy' => \WP_Agent_Context_Injection_Policy::ALWAYS,
			]
		);

		\WP_Agent_Context_Section_Registry::register(
			'clawpress-memory',
			'clawpress-memory-files',
			30,
			static function ( array $context, array $section ): string {
				unset( $context, $section );
				return Context_Helper::get_instance()->build_memory_context();
			},
			[
				'modes'            => [ 'chat', 'pipeline' ],
				'retrieval_policy' => \WP_Agent_Context_Injection_Policy::ALWAYS,
			]
		);
	}

	/**
	 * Resolve a ClawPress execution principal for Agents API consumers.
	 *
	 * @param mixed               $principal Existing principal.
	 * @param array<string,mixed> $context Request context.
	 */
	public function resolve_execution_principal( $principal, array $context ) {
		if ( $principal instanceof WP_Agent_Execution_Principal ) {
			return $principal;
		}

		$request_context = isset( $context['request_context'] ) ? (string) $context['request_context'] : WP_Agent_Execution_Principal::REQUEST_CONTEXT_REST;
		$user_id         = get_current_user_id();
		$settings        = Settings_Helper::get_instance()->get_settings();
		$agent_user_id   = Settings_Helper::get_instance()->resolve_agent_user_id( $settings );
		$workspace_id    = $agent_user_id > 0 ? Workspace_Helper::get_instance()->get_workspace_path_for_agent_user( $agent_user_id ) : null;

		return WP_Agent_Execution_Principal::user_session(
			$user_id > 0 ? $user_id : 0,
			self::AGENT_SLUG,
			'' !== $request_context ? $request_context : WP_Agent_Execution_Principal::REQUEST_CONTEXT_REST,
			[
				'source' => 'clawpress',
			],
			$workspace_id
		);
	}

	/**
	 * Register the ClawPress ability catalog as an Agents API tool source.
	 *
	 * @param array<string,callable> $sources Existing tool sources.
	 * @param array<string,mixed>    $context Runtime context.
	 * @param mixed                  $registry Tool source registry.
	 * @return array<string,callable>
	 */
	public function register_tool_sources( array $sources, array $context, $registry ): array {
		unset( $context, $registry );

		$sources['clawpress'] = [ $this->get_tool_source(), 'gather' ];

		return $sources;
	}

	/**
	 * Resolve the generic conversation store.
	 *
	 * @param mixed               $store Existing store.
	 * @param array<string,mixed> $context Request context.
	 */
	public function resolve_conversation_store( $store, array $context ) {
		if ( $store instanceof WP_Agent_Conversation_Store ) {
			return $store;
		}

		unset( $context );

		return $this->get_conversation_store();
	}

	/**
	 * Resolve the generic memory store.
	 *
	 * @param mixed               $store Existing store.
	 * @param array<string,mixed> $context Request context.
	 */
	public function resolve_memory_store( $store, array $context ) {
		if ( $store instanceof WP_Agent_Memory_Store ) {
			return $store;
		}

		unset( $context );

		return $this->get_memory_store();
	}

	/**
	 * Get the ability tool source adapter.
	 */
	private function get_tool_source(): Ability_Tool_Source {
		if ( null === $this->tool_source ) {
			$this->tool_source = new Ability_Tool_Source();
		}

		return $this->tool_source;
	}

	/**
	 * Get the conversation store adapter.
	 */
	private function get_conversation_store(): Conversation_Store {
		if ( null === $this->conversation_store ) {
			$this->conversation_store = new Conversation_Store();
		}

		return $this->conversation_store;
	}

	/**
	 * Get the memory store adapter.
	 */
	private function get_memory_store(): Memory_Store {
		if ( null === $this->memory_store ) {
			$this->memory_store = new Memory_Store();
		}

		return $this->memory_store;
	}
}

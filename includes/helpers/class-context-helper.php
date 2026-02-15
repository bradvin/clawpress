<?php
/**
 * Agent context helper.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Helpers;

use Throwable;
use WordPress\AiClient\Builders\MessageBuilder;
use WordPress\AiClient\Messages\DTO\Message;

defined( 'ABSPATH' ) || exit;

/**
 * Central context builder for LLM system prompt + message assembly.
 */
final class Context_Helper {
	/**
	 * Bootstrap files loaded into system prompt context.
	 *
	 * @var array<int,string>
	 */
	private const BOOTSTRAP_FILES = [
		'AGENTS.md',
		'SOUL.md',
		'USER.md',
		'TOOLS.md',
		'IDENTITY.md',
		'HEARTBEAT.md',
	];

	/**
	 * Option key for optional skill context metadata.
	 */
	private const SKILLS_OPTION = 'clawpress_context_skills';

	/**
	 * Maximum history messages included in model history.
	 */
	private const HISTORY_LIMIT = 40;

	/**
	 * Singleton instance.
	 *
	 * @var ?self
	 */
	private static ?self $instance = null;

	/**
	 * Agent file helper.
	 *
	 * @var Agent_File_Helper
	 */
	private Agent_File_Helper $agent_file_helper;

	/**
	 * Chat history helper.
	 *
	 * @var Chat_History_Helper
	 */
	private Chat_History_Helper $chat_history_helper;

	/**
	 * Settings helper.
	 *
	 * @var Settings_Helper
	 */
	private Settings_Helper $settings_helper;

	/**
	 * Memory helper.
	 *
	 * @var Memory_Helper
	 */
	private Memory_Helper $memory_helper;

	/**
	 * Workspace helper.
	 *
	 * @var Workspace_Helper
	 */
	private Workspace_Helper $workspace_helper;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->agent_file_helper   = Agent_File_Helper::get_instance();
		$this->chat_history_helper = Chat_History_Helper::get_instance();
		$this->settings_helper     = Settings_Helper::get_instance();
		$this->memory_helper       = Memory_Helper::get_instance();
		$this->workspace_helper    = Workspace_Helper::get_instance();
	}

	/**
	 * Get singleton instance.
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Build system prompt from identity, bootstrap files, memory, and skills.
	 *
	 * @param array<int,string>|null $skill_names Optional skill names to include.
	 * @param int|null               $user_id Optional user ID.
	 */
	public function build_system_prompt( ?array $skill_names = null, ?int $user_id = null ): string {
		$parts = [];

		$parts[] = $this->get_identity_section();

		$bootstrap = $this->load_bootstrap_files();
		if ( '' !== $bootstrap ) {
			$parts[] = $bootstrap;
		}

		$memory_context = $this->get_memory_context();
		if ( '' !== $memory_context ) {
			$parts[] = "# Memory\n\n{$memory_context}";
		}

		$skills_context = $this->get_skills_context( $skill_names, $user_id );
		if ( '' !== $skills_context ) {
			$parts[] = $skills_context;
		}

		return implode( "\n\n---\n\n", array_filter( $parts ) );
	}

	/**
	 * Build normalized prompt messages with system prompt + history + current message.
	 *
	 * @param array<int,array<string,mixed>> $history Previous history messages.
	 * @param string                         $current_message Current user message.
	 * @param array<int,string>|null         $skill_names Optional skill names to include.
	 * @param array<int,string>|null         $media Optional media local paths.
	 * @param string|null                    $channel Optional channel label.
	 * @param string|null                    $chat_id Optional chat/session ID.
	 * @param int|null                       $user_id Optional user ID.
	 * @return array<int,array<string,mixed>>
	 */
	public function build_messages(
		array $history,
		string $current_message,
		?array $skill_names = null,
		?array $media = null,
		?string $channel = null,
		?string $chat_id = null,
		?int $user_id = null
	): array {
		$messages      = [];
		$system_prompt = $this->build_system_prompt( $skill_names, $user_id );

		if ( null !== $channel && '' !== trim( $channel ) && null !== $chat_id && '' !== trim( $chat_id ) ) {
			$system_prompt .= sprintf(
				"\n\n## Current Session\nChannel: %s\nChat ID: %s",
				trim( $channel ),
				trim( $chat_id )
			);
		}

		$messages[] = [
			'role'    => 'system',
			'content' => $system_prompt,
		];

		foreach ( $history as $history_message ) {
			$normalized = $this->normalize_message_shape( $history_message );
			if ( null === $normalized ) {
				continue;
			}

			$messages[] = $normalized;
		}

		$messages[] = [
			'role'    => 'user',
			'content' => $this->build_user_content( $current_message, $media ),
		];

		return $messages;
	}

	/**
	 * Build model-facing context payload for a current user message.
	 *
	 * @param string                 $current_message Current message text.
	 * @param int|null               $user_id Optional user ID.
	 * @param array<int,string>|null $skill_names Optional skill names.
	 * @return array<string,mixed>
	 */
	public function build_model_context( string $current_message, ?int $user_id = null, ?array $skill_names = null ): array {
		$history_items          = $this->chat_history_helper->get_history_items( $user_id );
		$normalized_history     = [];
		$model_history_messages = [];

		foreach ( array_slice( $history_items, -self::HISTORY_LIMIT ) as $history_item ) {
			$normalized_item = $this->normalize_history_item_for_prompt( $history_item );
			if ( null === $normalized_item ) {
				continue;
			}

			$normalized_history[] = $normalized_item;

			$model_history_message = $this->convert_prompt_message_to_model_message( $normalized_item );
			if ( $model_history_message instanceof Message ) {
				$model_history_messages[] = $model_history_message;
			}
		}

		$messages = $this->build_messages(
			$normalized_history,
			$current_message,
			$skill_names,
			null,
			null,
			null,
			$user_id
		);

		return [
			'system_prompt'    => isset( $messages[0]['content'] ) ? (string) $messages[0]['content'] : '',
			'history_messages' => $model_history_messages,
			'messages'         => $messages,
			'message'          => $current_message,
		];
	}

	/**
	 * Add a tool result message to a message list.
	 *
	 * @param array<int,array<string,mixed>> $messages Message list.
	 * @param string                         $tool_call_id Tool call ID.
	 * @param string                         $tool_name Tool name.
	 * @param string                         $result Tool execution result.
	 * @return array<int,array<string,mixed>>
	 */
	public function add_tool_result( array $messages, string $tool_call_id, string $tool_name, string $result ): array {
		$messages[] = [
			'role'         => 'tool',
			'tool_call_id' => $tool_call_id,
			'name'         => $tool_name,
			'content'      => $result,
		];

		return $messages;
	}

	/**
	 * Add an assistant message to a message list.
	 *
	 * @param array<int,array<string,mixed>>      $messages Message list.
	 * @param string|null                         $content Assistant content.
	 * @param array<int,array<string,mixed>>|null $tool_calls Optional tool calls.
	 * @param string|null                         $reasoning_content Optional reasoning content.
	 * @return array<int,array<string,mixed>>
	 */
	public function add_assistant_message(
		array $messages,
		?string $content,
		?array $tool_calls = null,
		?string $reasoning_content = null
	): array {
		$message = [
			'role'    => 'assistant',
			'content' => null === $content ? '' : $content,
		];

		if ( is_array( $tool_calls ) && [] !== $tool_calls ) {
			$message['tool_calls'] = array_values( $tool_calls );
		}

		if ( null !== $reasoning_content && '' !== trim( $reasoning_content ) ) {
			$message['reasoning_content'] = trim( $reasoning_content );
		}

		$messages[] = $message;
		return $messages;
	}

	/**
	 * Build the identity section.
	 */
	private function get_identity_section(): string {
		$settings      = $this->settings_helper->get_settings();
		$agent_user_id = $this->settings_helper->resolve_agent_user_id( $settings );
		$workspace     = $agent_user_id > 0
			? $this->workspace_helper->get_workspace_path_for_agent_user( $agent_user_id )
			: '';

		$workspace_label = '' !== $workspace ? $workspace : '(not configured)';
		$now             = gmdate( 'Y-m-d H:i (l)' );
		$runtime         = sprintf( '%s, PHP %s', PHP_OS_FAMILY, PHP_VERSION );

		return sprintf(
			"# ClawPress\n\nYou are ClawPress, a helpful AI assistant for WordPress admin.\n\n## Current Time\n%s (UTC)\n\n## Runtime\n%s\n\n## Workspace\n%s",
			$now,
			$runtime,
			$workspace_label
		);
	}

	/**
	 * Load bootstrap files and format as sections.
	 */
	private function load_bootstrap_files(): string {
		$sections = [];

		foreach ( self::BOOTSTRAP_FILES as $filename ) {
			$content = trim( $this->agent_file_helper->get_file_content_by_logical_path( $filename ) );
			if ( '' === $content ) {
				continue;
			}

			$sections[] = sprintf( "## %s\n\n%s", $filename, $content );
		}

		return implode( "\n\n", $sections );
	}

	/**
	 * Resolve memory context text.
	 */
	private function get_memory_context(): string {
		$settings = $this->settings_helper->get_settings();
		if ( ! $this->settings_helper->get_memory_enabled( $settings ) ) {
			return '';
		}

		return $this->memory_helper->build_memory_context();
	}

	/**
	 * Resolve skills context with always-loaded and summary sections.
	 *
	 * @param array<int,string>|null $skill_names Optional skill names.
	 * @param int|null               $user_id Optional user ID.
	 */
	private function get_skills_context( ?array $skill_names = null, ?int $user_id = null ): string {
		$raw_skills = get_option( self::SKILLS_OPTION, [] );
		if ( ! is_array( $raw_skills ) ) {
			$raw_skills = [];
		}

		$raw_skills = apply_filters( 'clawpress_context_skills', $raw_skills, $user_id );
		if ( ! is_array( $raw_skills ) ) {
			return '';
		}

		$normalized_skills = [];
		foreach ( $raw_skills as $raw_skill ) {
			$normalized_skill = $this->normalize_skill( $raw_skill );
			if ( null === $normalized_skill ) {
				continue;
			}

			$normalized_skills[] = $normalized_skill;
		}

		if ( [] === $normalized_skills ) {
			return '';
		}

		if ( is_array( $skill_names ) && [] !== $skill_names ) {
			$allowed_names = array_map(
				static fn( $name ): string => strtolower( trim( (string) $name ) ),
				$skill_names
			);

			$normalized_skills = array_values(
				array_filter(
					$normalized_skills,
					static fn( array $skill ): bool => in_array( strtolower( $skill['name'] ), $allowed_names, true )
				)
			);

			if ( [] === $normalized_skills ) {
				return '';
			}
		}

		$parts                = [];
		$always_skill_content = [];
		$skills_summary_lines = [];

		foreach ( $normalized_skills as $skill ) {
			if ( true === $skill['always'] && '' !== $skill['content'] ) {
				$always_skill_content[] = sprintf( "## %s\n\n%s", $skill['name'], $skill['content'] );
			}

			$availability_suffix    = true === $skill['available'] ? '' : ' (available: false)';
			$skills_summary_lines[] = sprintf(
				'- %s: %s%s',
				$skill['name'],
				$skill['summary'],
				$availability_suffix
			);
		}

		if ( [] !== $always_skill_content ) {
			$parts[] = "# Active Skills\n\n" . implode( "\n\n", $always_skill_content );
		}

		if ( [] !== $skills_summary_lines ) {
			$parts[] = "# Skills\n\nThe following skills extend your capabilities. To use a skill, read its SKILL.md file.\n\n"
				. implode( "\n", $skills_summary_lines );
		}

		return implode( "\n\n", $parts );
	}

	/**
	 * Normalize one skill row.
	 *
	 * @param mixed $raw_skill Raw skill row.
	 * @return array{name:string,summary:string,content:string,always:bool,available:bool}|null
	 */
	private function normalize_skill( $raw_skill ): ?array {
		if ( is_string( $raw_skill ) ) {
			$name = trim( $raw_skill );
			if ( '' === $name ) {
				return null;
			}

			return [
				'name'      => $name,
				'summary'   => $name,
				'content'   => '',
				'always'    => false,
				'available' => true,
			];
		}

		if ( ! is_array( $raw_skill ) ) {
			return null;
		}

		$name = isset( $raw_skill['name'] ) ? trim( (string) $raw_skill['name'] ) : '';
		if ( '' === $name ) {
			return null;
		}

		$summary = isset( $raw_skill['summary'] ) && '' !== trim( (string) $raw_skill['summary'] )
			? trim( (string) $raw_skill['summary'] )
			: $name;

		$content = isset( $raw_skill['content'] ) ? trim( (string) $raw_skill['content'] ) : '';

		$always = isset( $raw_skill['always'] ) && function_exists( 'clawpress_sanitize_boolean' )
			? clawpress_sanitize_boolean( $raw_skill['always'] )
			: ! empty( $raw_skill['always'] );

		$available = isset( $raw_skill['available'] ) && function_exists( 'clawpress_sanitize_boolean' )
			? clawpress_sanitize_boolean( $raw_skill['available'] )
			: ! isset( $raw_skill['available'] ) || ! empty( $raw_skill['available'] );

		return [
			'name'      => $name,
			'summary'   => $summary,
			'content'   => $content,
			'always'    => $always,
			'available' => $available,
		];
	}

	/**
	 * Normalize a message shape for prompt arrays.
	 *
	 * @param mixed $message Raw message row.
	 * @return array{role:string,content:mixed}|null
	 */
	private function normalize_message_shape( $message ): ?array {
		if ( ! is_array( $message ) ) {
			return null;
		}

		$role = isset( $message['role'] ) ? strtolower( trim( (string) $message['role'] ) ) : '';
		if ( ! in_array( $role, [ 'user', 'assistant', 'system' ], true ) ) {
			return null;
		}

		if ( ! array_key_exists( 'content', $message ) ) {
			return null;
		}

		if ( is_array( $message['content'] ) ) {
			$content = $message['content'];
		} else {
			$content = (string) $message['content'];
		}

		return [
			'role'    => $role,
			'content' => $content,
		];
	}

	/**
	 * Normalize one history row into a prompt message shape.
	 *
	 * @param mixed $history_item Raw history row.
	 * @return array{role:string,content:string}|null
	 */
	private function normalize_history_item_for_prompt( $history_item ): ?array {
		if ( ! is_array( $history_item ) ) {
			return null;
		}

		$role    = isset( $history_item['role'] ) ? strtolower( trim( (string) $history_item['role'] ) ) : '';
		$content = isset( $history_item['content'] ) ? trim( (string) $history_item['content'] ) : '';

		if ( '' === $content ) {
			return null;
		}

		if ( ! in_array( $role, [ 'user', 'assistant', 'system' ], true ) ) {
			$role = 'assistant';
		}

		return [
			'role'    => $role,
			'content' => $content,
		];
	}

	/**
	 * Convert a prompt-style message row into a model-compatible history message.
	 *
	 * @param array{role:string,content:mixed} $message Prompt message row.
	 */
	private function convert_prompt_message_to_model_message( array $message ): ?Message {
		if ( is_array( $message['content'] ) ) {
			$encoded_content = function_exists( 'wp_json_encode' )
				? wp_json_encode( $message['content'] )
				: false;
			$content         = trim( false === $encoded_content ? '' : (string) $encoded_content );
		} else {
			$content = trim( (string) $message['content'] );
		}

		if ( '' === $content ) {
			return null;
		}

		try {
			$builder = new MessageBuilder( $content );

			if ( 'user' === $message['role'] ) {
				$builder->usingUserRole();
			} else {
				$builder->usingModelRole();
			}

			return $builder->get();
		} catch ( Throwable $throwable ) {
			unset( $throwable );
			return null;
		}
	}

	/**
	 * Build user content with optional local image attachments.
	 *
	 * @param string                 $text Text content.
	 * @param array<int,string>|null $media Optional media file paths.
	 * @return string|array<int,mixed>
	 */
	private function build_user_content( string $text, ?array $media ) {
		if ( ! is_array( $media ) || [] === $media ) {
			return $text;
		}

		$images = [];

		foreach ( $media as $path ) {
			$path = trim( (string) $path );
			if ( '' === $path || ! is_file( $path ) || ! is_readable( $path ) ) {
				continue;
			}

			$mime_type = function_exists( 'mime_content_type' ) ? mime_content_type( $path ) : '';
			if ( ! is_string( $mime_type ) || 0 !== strpos( $mime_type, 'image/' ) ) {
				continue;
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local agent media path read.
			$bytes = file_get_contents( $path );
			if ( false === $bytes ) {
				continue;
			}

			$images[] = [
				'type'      => 'image_url',
				'image_url' => [
					// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required for data URL image payloads.
					'url' => sprintf( 'data:%s;base64,%s', $mime_type, base64_encode( $bytes ) ),
				],
			];
		}

		if ( [] === $images ) {
			return $text;
		}

		$images[] = [
			'type' => 'text',
			'text' => $text,
		];

		return $images;
	}
}

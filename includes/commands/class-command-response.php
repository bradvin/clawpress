<?php
/**
 * Command response envelope.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Commands;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable command response.
 */
final class Command_Response {
	/**
	 * Response text.
	 *
	 * @var string
	 */
	private string $text;

	/**
	 * Whether the command failed validation/execution.
	 *
	 * @var bool
	 */
	private bool $is_error;

	/**
	 * Command name.
	 *
	 * @var string
	 */
	private string $command;

	/**
	 * Whether the command is destructive.
	 *
	 * @var bool
	 */
	private bool $is_destructive;

	/**
	 * Whether a confirmation step is pending.
	 *
	 * @var bool
	 */
	private bool $requires_confirmation;

	/**
	 * Constructor.
	 *
	 * @param string $text Response text.
	 * @param bool   $is_error Error flag.
	 * @param string $command Command name.
	 * @param bool   $is_destructive Destructive flag.
	 * @param bool   $requires_confirmation Confirmation flag.
	 */
	public function __construct(
		string $text,
		bool $is_error,
		string $command,
		bool $is_destructive,
		bool $requires_confirmation
	) {
		$this->text                  = $text;
		$this->is_error              = $is_error;
		$this->command               = $command;
		$this->is_destructive        = $is_destructive;
		$this->requires_confirmation = $requires_confirmation;
	}

	/**
	 * Build a success response.
	 *
	 * @param string $text Response text.
	 * @param string $command Command name.
	 * @param bool   $is_destructive Destructive flag.
	 * @param bool   $requires_confirmation Confirmation flag.
	 */
	public static function success(
		string $text,
		string $command,
		bool $is_destructive = false,
		bool $requires_confirmation = false
	): self {
		return new self( $text, false, $command, $is_destructive, $requires_confirmation );
	}

	/**
	 * Build an error response.
	 *
	 * @param string $text Response text.
	 * @param string $command Command name.
	 * @param bool   $is_destructive Destructive flag.
	 * @param bool   $requires_confirmation Confirmation flag.
	 */
	public static function error(
		string $text,
		string $command,
		bool $is_destructive = false,
		bool $requires_confirmation = false
	): self {
		return new self( $text, true, $command, $is_destructive, $requires_confirmation );
	}

	/**
	 * Get response text.
	 */
	public function get_text(): string {
		return $this->text;
	}

	/**
	 * Get command name.
	 */
	public function get_command(): string {
		return $this->command;
	}

	/**
	 * Whether response is an error.
	 */
	public function is_error(): bool {
		return $this->is_error;
	}

	/**
	 * Whether command is destructive.
	 */
	public function is_destructive(): bool {
		return $this->is_destructive;
	}

	/**
	 * Whether command requires confirmation.
	 */
	public function requires_confirmation(): bool {
		return $this->requires_confirmation;
	}
}

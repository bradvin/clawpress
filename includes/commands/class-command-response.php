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
	 * Optional command side-effects metadata.
	 *
	 * @var array<string,mixed>
	 */
	private array $effects;

	/**
	 * Optional follow-up suggestions.
	 *
	 * @var array<int,string>
	 */
	private array $suggestions;

	/**
	 * Optional card metadata for custom panel rendering.
	 *
	 * @var array<string,mixed>|null
	 */
	private ?array $card;

	/**
	 * Constructor.
	 *
	 * @param string                   $text Response text.
	 * @param bool                     $is_error Error flag.
	 * @param string                   $command Command name.
	 * @param bool                     $is_destructive Destructive flag.
	 * @param bool                     $requires_confirmation Confirmation flag.
	 * @param array<string,mixed>      $effects Optional side-effects metadata.
	 * @param array<int,string>        $suggestions Optional follow-up suggestions.
	 * @param array<string,mixed>|null $card Optional card metadata.
	 */
	public function __construct(
		string $text,
		bool $is_error,
		string $command,
		bool $is_destructive,
		bool $requires_confirmation,
		array $effects = [],
		array $suggestions = [],
		?array $card = null
	) {
		$this->text                  = $text;
		$this->is_error              = $is_error;
		$this->command               = $command;
		$this->is_destructive        = $is_destructive;
		$this->requires_confirmation = $requires_confirmation;
		$this->effects               = $effects;
		$this->suggestions           = array_values(
			array_filter(
				array_map(
					static fn ( $suggestion ): string => trim( (string) $suggestion ),
					$suggestions
				),
				static fn ( string $suggestion ): bool => '' !== $suggestion
			)
		);
		$this->card                  = $this->normalize_card( $card );
	}

	/**
	 * Build a success response.
	 *
	 * @param string                   $text Response text.
	 * @param string                   $command Command name.
	 * @param bool                     $is_destructive Destructive flag.
	 * @param bool                     $requires_confirmation Confirmation flag.
	 * @param array<string,mixed>      $effects Optional side-effects metadata.
	 * @param array<int,string>        $suggestions Optional follow-up suggestions.
	 * @param array<string,mixed>|null $card Optional card metadata.
	 */
	public static function success(
		string $text,
		string $command,
		bool $is_destructive = false,
		bool $requires_confirmation = false,
		array $effects = [],
		array $suggestions = [],
		?array $card = null
	): self {
		return new self( $text, false, $command, $is_destructive, $requires_confirmation, $effects, $suggestions, $card );
	}

	/**
	 * Build an error response.
	 *
	 * @param string                   $text Response text.
	 * @param string                   $command Command name.
	 * @param bool                     $is_destructive Destructive flag.
	 * @param bool                     $requires_confirmation Confirmation flag.
	 * @param array<string,mixed>      $effects Optional side-effects metadata.
	 * @param array<int,string>        $suggestions Optional follow-up suggestions.
	 * @param array<string,mixed>|null $card Optional card metadata.
	 */
	public static function error(
		string $text,
		string $command,
		bool $is_destructive = false,
		bool $requires_confirmation = false,
		array $effects = [],
		array $suggestions = [],
		?array $card = null
	): self {
		$text = trim( $text );
		if ( '' === $text ) {
			$text = __( 'An unknown error occurred.', 'clawpress' );
		}
		if ( false === strpos( $text, '🚨' ) ) {
			$text = '🚨 ' . $text;
		}
		return new self( $text, true, $command, $is_destructive, $requires_confirmation, $effects, $suggestions, $card );
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

	/**
	 * Get command side-effects metadata.
	 *
	 * @return array<string,mixed>
	 */
	public function get_effects(): array {
		return $this->effects;
	}

	/**
	 * Get follow-up suggestions.
	 *
	 * @return array<int,string>
	 */
	public function get_suggestions(): array {
		return $this->suggestions;
	}

	/**
	 * Get card metadata.
	 *
	 * @return array<string,mixed>|null
	 */
	public function get_card(): ?array {
		return $this->card;
	}

	/**
	 * Normalize card payload.
	 *
	 * @param array<string,mixed>|null $card Raw card payload.
	 * @return array<string,mixed>|null
	 */
	private function normalize_card( ?array $card ): ?array {
		if ( ! is_array( $card ) ) {
			return null;
		}

		$type = isset( $card['type'] ) ? strtolower( sanitize_text_field( (string) $card['type'] ) ) : '';
		$type = (string) preg_replace( '/[^a-z0-9_\-]/', '', $type );
		if ( '' === $type ) {
			return null;
		}

		$normalized = [ 'type' => $type ];

		if ( isset( $card['data'] ) && is_array( $card['data'] ) ) {
			$normalized['data'] = $card['data'];
		}

		return $normalized;
	}
}

<?php
/**
 * Parsed command request value object.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Commands;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable parsed command request.
 */
final class Command_Request {
	/**
	 * Raw incoming message.
	 *
	 * @var string
	 */
	private string $raw_message;

	/**
	 * Whitespace-normalized message.
	 *
	 * @var string
	 */
	private string $normalized_message;

	/**
	 * Parsed command token.
	 *
	 * @var string
	 */
	private string $command;

	/**
	 * Parsed command arguments.
	 *
	 * @var array<int,string>
	 */
	private array $arguments;

	/**
	 * Constructor.
	 *
	 * @param string            $raw_message Raw message.
	 * @param string            $normalized_message Normalized message.
	 * @param string            $command Parsed command token.
	 * @param array<int,string> $arguments Parsed arguments.
	 */
	public function __construct( string $raw_message, string $normalized_message, string $command, array $arguments ) {
		$this->raw_message        = $raw_message;
		$this->normalized_message = $normalized_message;
		$this->command            = $command;
		$this->arguments          = array_values(
			array_filter(
				array_map(
					static fn ( $argument ): string => trim( (string) $argument ),
					$arguments
				),
				static fn ( string $argument ): bool => '' !== $argument
			)
		);
	}

	/**
	 * Parse a message into a command request.
	 *
	 * @param string $message Raw message.
	 */
	public static function from_message( string $message ): ?self {
		$normalized_message = trim( preg_replace( '/\s+/', ' ', $message ) ?? '' );
		if ( '' === $normalized_message || '/' !== $normalized_message[0] ) {
			return null;
		}

		$tokens = preg_split( '/\s+/', $normalized_message );
		if ( ! is_array( $tokens ) || [] === $tokens ) {
			return null;
		}

		$command = strtolower( (string) array_shift( $tokens ) );
		if ( '/' === $command || '/?' === $command ) {
			$command = '/help';
		}

		return new self( $message, $normalized_message, $command, $tokens );
	}

	/**
	 * Get raw message.
	 */
	public function get_raw_message(): string {
		return $this->raw_message;
	}

	/**
	 * Get normalized message.
	 */
	public function get_normalized_message(): string {
		return $this->normalized_message;
	}

	/**
	 * Get command token.
	 */
	public function get_command(): string {
		return $this->command;
	}

	/**
	 * Get all arguments.
	 *
	 * @return array<int,string>
	 */
	public function get_arguments(): array {
		return $this->arguments;
	}

	/**
	 * Get one argument by index.
	 *
	 * @param int $index Argument index.
	 */
	public function get_argument( int $index ): string {
		return $this->arguments[ $index ] ?? '';
	}

	/**
	 * Case-insensitive argument matcher.
	 *
	 * @param string $needle Argument to match.
	 */
	public function has_argument( string $needle ): bool {
		$needle = strtolower( trim( $needle ) );
		if ( '' === $needle ) {
			return false;
		}

		foreach ( $this->arguments as $argument ) {
			if ( strtolower( $argument ) === $needle ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get `--key=value` style option value.
	 *
	 * @param string $option_name Option name without leading dashes.
	 */
	public function get_option_value( string $option_name ): ?string {
		$option_name = strtolower( trim( $option_name ) );
		if ( '' === $option_name ) {
			return null;
		}

		$prefix = '--' . $option_name . '=';
		foreach ( $this->arguments as $argument ) {
			$normalized_argument = strtolower( $argument );
			if ( 0 !== strpos( $normalized_argument, $prefix ) ) {
				continue;
			}

			$value = substr( $argument, strlen( $prefix ) );
			$value = trim( (string) $value );
			return '' !== $value ? $value : null;
		}

		return null;
	}
}

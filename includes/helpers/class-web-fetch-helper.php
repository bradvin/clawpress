<?php
/**
 * Web fetch helper.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Helpers;

use ClawPress\WebFetch\Fetcher_Interface;
use ClawPress\WebFetch\WP_Fetcher;

defined( 'ABSPATH' ) || exit;

/**
 * Registry and request normalizer for web fetch providers.
 */
final class Web_Fetch_Helper {
	/**
	 * Default fetcher slug.
	 */
	private const DEFAULT_FETCHER = 'wp';

	/**
	 * Default HTTP method.
	 */
	private const DEFAULT_METHOD = 'GET';

	/**
	 * Default timeout in seconds.
	 */
	private const DEFAULT_TIMEOUT = 15;

	/**
	 * Default redirect count.
	 */
	private const DEFAULT_REDIRECTION = 5;

	/**
	 * Maximum returned response body bytes.
	 */
	private const MAX_BODY_BYTES = 204800;

	/**
	 * Allowed read-only HTTP methods for v1.
	 *
	 * @var array<int,string>
	 */
	private const ALLOWED_METHODS = [ 'GET', 'HEAD' ];

	/**
	 * Singleton instance.
	 *
	 * @var ?self
	 */
	private static ?self $instance = null;

	/**
	 * Registered fetchers by slug.
	 *
	 * @var array<string,Fetcher_Interface>
	 */
	private array $fetchers = [];

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->register_fetcher( new WP_Fetcher() );
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
	 * Register the generic tool-call listener used for fetch action logging.
	 */
	public static function register_logging_hook(): void {
		add_action( 'clawpress_tool_call_logged', [ __CLASS__, 'handle_tool_call_logged' ] );
	}

	/**
	 * Write action-log rows for web_fetch tool attempts.
	 *
	 * @param array<string,mixed> $event Generic tool-call event payload.
	 */
	public static function handle_tool_call_logged( array $event ): void {
		$ability_name = isset( $event['ability_name'] ) ? (string) $event['ability_name'] : '';
		if ( 'clawpress/web-fetch' !== $ability_name ) {
			return;
		}

		$args               = isset( $event['args'] ) && is_array( $event['args'] ) ? $event['args'] : [];
		$payload            = isset( $event['payload'] ) && is_array( $event['payload'] ) ? $event['payload'] : [];
		$requesting_user_id = isset( $event['requesting_user_id'] ) ? (int) $event['requesting_user_id'] : 0;
		$execution_user_id  = isset( $event['execution_user_id'] ) ? (int) $event['execution_user_id'] : 0;
		$result             = isset( $payload['result'] ) && is_array( $payload['result'] ) ? $payload['result'] : [];
		$fetcher            = isset( $result['fetcher'] ) && '' !== trim( (string) $result['fetcher'] )
			? (string) $result['fetcher']
			: strtolower( trim( (string) ( $args['fetcher'] ?? 'wp' ) ) );
		$url                = isset( $result['url'] ) && '' !== trim( (string) $result['url'] )
			? (string) $result['url']
			: trim( (string) ( $args['url'] ?? '' ) );
		$method             = isset( $result['method'] ) && '' !== trim( (string) $result['method'] )
			? strtoupper( trim( (string) $result['method'] ) )
			: strtoupper( trim( (string) ( $args['method'] ?? 'GET' ) ) );
		$status_code        = isset( $result['status_code'] ) ? (int) $result['status_code'] : null;
		$body_bytes         = isset( $result['body_bytes'] ) ? (int) $result['body_bytes'] : null;
		$log_status         = ! empty( $payload['success'] )
			? ( ! empty( $payload['degraded'] ) ? 'warning' : 'success' )
			: ( isset( $payload['policy'] ) && is_array( $payload['policy'] )
				? self::resolve_policy_violation_log_status( $payload )
				: 'error' );
		$error_code         = isset( $payload['error']['code'] ) ? (string) $payload['error']['code'] : '';
		$error_message      = isset( $payload['error']['message'] ) ? (string) $payload['error']['message'] : '';

		if ( ! empty( $payload['success'] ) && null !== $status_code ) {
			$message = sprintf(
				/* translators: 1: HTTP method, 2: URL, 3: HTTP status code. */
				__( 'Web fetch %1$s %2$s completed with status %3$d.', 'clawpress' ),
				$method,
				$url,
				$status_code
			);
		} elseif ( '' !== $error_message ) {
			$message = sprintf(
				/* translators: 1: HTTP method, 2: URL, 3: error message. */
				__( 'Web fetch %1$s %2$s failed: %3$s', 'clawpress' ),
				$method,
				$url,
				$error_message
			);
		} else {
			$message = sprintf(
				/* translators: 1: HTTP method, 2: URL. */
				__( 'Web fetch %1$s %2$s was blocked.', 'clawpress' ),
				$method,
				$url
			);
		}

		$context = [
			'tool'               => isset( $event['tool_name'] ) ? (string) $event['tool_name'] : 'web_fetch',
			'ability'            => $ability_name,
			'fetcher'            => '' !== $fetcher ? $fetcher : 'wp',
			'url'                => $url,
			'method'             => '' !== $method ? $method : 'GET',
			'status_code'        => $status_code,
			'truncated'          => ! empty( $result['truncated'] ),
			'body_bytes'         => $body_bytes,
			'requesting_user_id' => $requesting_user_id,
			'execution_user_id'  => $execution_user_id,
		];

		if ( isset( $payload['policy'] ) && is_array( $payload['policy'] ) ) {
			$context['policy'] = $payload['policy'];
		}

		if ( '' !== $error_code || '' !== $error_message ) {
			$context['error'] = [
				'code'    => $error_code,
				'message' => $error_message,
			];
		}

		if ( ! empty( $payload['degraded'] ) ) {
			$context['degraded'] = true;
		}

		Action_Log_Helper::get_instance()->log_event(
			'web_fetch',
			[
				'event_type'         => 'tool_call',
				'status'             => $log_status,
				'message'            => $message,
				'requesting_user_id' => $requesting_user_id,
				'execution_user_id'  => $execution_user_id,
				'context'            => $context,
			]
		);
	}

	/**
	 * Register a fetcher implementation.
	 */
	public function register_fetcher( Fetcher_Interface $fetcher ): void {
		$slug = $this->normalize_fetcher_slug( $fetcher->get_slug() );
		if ( '' === $slug ) {
			return;
		}

		$this->fetchers[ $slug ] = $fetcher;
	}

	/**
	 * Execute a normalized fetch request.
	 *
	 * @param array<string,mixed> $input Raw ability input.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function fetch( array $input ) {
		$request = $this->normalize_request( $input );
		if ( is_wp_error( $request ) ) {
			return $request;
		}

		$fetcher = $this->fetchers[ $request['fetcher'] ] ?? null;
		if ( ! $fetcher instanceof Fetcher_Interface ) {
			return new \WP_Error(
				'clawpress_web_fetch_unknown_fetcher',
				__( 'The requested `fetcher` is not registered.', 'clawpress' )
			);
		}

		$response = $fetcher->fetch( $request );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $this->normalize_response( $request, $response );
	}

	/**
	 * Normalize one raw request payload.
	 *
	 * @param array<string,mixed> $input Raw ability input.
	 * @return array<string,mixed>|\WP_Error
	 */
	private function normalize_request( array $input ) {
		$raw_url = isset( $input['url'] ) ? trim( (string) $input['url'] ) : '';
		$url     = '' !== $raw_url ? esc_url_raw( $raw_url ) : '';
		$url     = '' !== $url ? wp_http_validate_url( $url ) : false;

		if ( ! is_string( $url ) || '' === trim( $url ) ) {
			return new \WP_Error(
				'clawpress_web_fetch_invalid_url',
				__( 'A valid `url` is required.', 'clawpress' )
			);
		}

		$fetcher = $this->normalize_fetcher_slug( $input['fetcher'] ?? self::DEFAULT_FETCHER );
		if ( '' === $fetcher ) {
			$fetcher = self::DEFAULT_FETCHER;
		}

		$method = strtoupper( trim( (string) ( $input['method'] ?? self::DEFAULT_METHOD ) ) );
		if ( ! in_array( $method, self::ALLOWED_METHODS, true ) ) {
			return new \WP_Error(
				'clawpress_web_fetch_invalid_method',
				__( 'The provided `method` is not supported.', 'clawpress' )
			);
		}

		return [
			'url'         => $url,
			'fetcher'     => $fetcher,
			'method'      => $method,
			'headers'     => $this->normalize_headers( $input['headers'] ?? [] ),
			'timeout'     => $this->normalize_timeout( $input['timeout'] ?? self::DEFAULT_TIMEOUT ),
			'redirection' => $this->normalize_redirection( $input['redirection'] ?? self::DEFAULT_REDIRECTION ),
			'arguments'   => is_array( $input['arguments'] ?? null ) ? $input['arguments'] : [],
		];
	}

	/**
	 * Normalize one fetcher response payload.
	 *
	 * @param array<string,mixed> $request Normalized request payload.
	 * @param array<string,mixed> $response Raw fetcher response.
	 * @return array<string,mixed>|\WP_Error
	 */
	private function normalize_response( array $request, array $response ) {
		$headers        = $this->normalize_headers( $response['headers'] ?? [] );
		$body           = isset( $response['body'] ) ? (string) $response['body'] : '';
		$body_bytes     = strlen( $body );
		$truncated      = $body_bytes > self::MAX_BODY_BYTES;
		$truncated_body = $truncated ? substr( $body, 0, self::MAX_BODY_BYTES ) : $body;

		$content_type = isset( $headers['content-type'] ) ? (string) $headers['content-type'] : '';
		if ( '' !== $content_type && false !== strpos( $content_type, ';' ) ) {
			$content_type = trim( (string) strtok( $content_type, ';' ) );
		}

		return [
			'fetcher'        => (string) $request['fetcher'],
			'url'            => (string) $request['url'],
			'method'         => (string) $request['method'],
			'status_code'    => isset( $response['status_code'] ) ? (int) $response['status_code'] : 0,
			'status_message' => isset( $response['status_message'] ) ? (string) $response['status_message'] : '',
			'headers'        => $headers,
			'content_type'   => $content_type,
			'body'           => $truncated_body,
			'truncated'      => $truncated,
			'body_bytes'     => $body_bytes,
		];
	}

	/**
	 * Normalize a fetcher slug.
	 *
	 * @param mixed $value Raw slug value.
	 */
	private function normalize_fetcher_slug( $value ): string {
		return strtolower( sanitize_key( sanitize_text_field( (string) $value ) ) );
	}

	/**
	 * Normalize HTTP headers into a lower-case string map.
	 *
	 * @param mixed $headers Raw headers payload.
	 * @return array<string,string>
	 */
	private function normalize_headers( $headers ): array {
		if ( is_object( $headers ) && method_exists( $headers, 'getAll' ) ) {
			$headers = $headers->getAll();
		}

		if ( ! is_array( $headers ) ) {
			return [];
		}

		$normalized = [];

		foreach ( $headers as $name => $value ) {
			$normalized_name = strtolower( trim( preg_replace( '/[^A-Za-z0-9\-]/', '', (string) $name ) ?? '' ) );
			if ( '' === $normalized_name ) {
				continue;
			}

			if ( is_array( $value ) ) {
				$value = implode(
					', ',
					array_map(
						static fn( $item ): string => trim( str_replace( [ "\r", "\n" ], '', (string) $item ) ),
						$value
					)
				);
			}

			$normalized[ $normalized_name ] = trim( str_replace( [ "\r", "\n" ], '', (string) $value ) );
		}

		return $normalized;
	}

	/**
	 * Normalize timeout.
	 *
	 * @param mixed $value Raw timeout.
	 */
	private function normalize_timeout( $value ): int {
		$timeout = (int) $value;
		if ( $timeout <= 0 ) {
			return self::DEFAULT_TIMEOUT;
		}

		return min( $timeout, 120 );
	}

	/**
	 * Normalize redirect count.
	 *
	 * @param mixed $value Raw redirect count.
	 */
	private function normalize_redirection( $value ): int {
		$redirection = (int) $value;
		if ( $redirection < 0 ) {
			return self::DEFAULT_REDIRECTION;
		}

		return min( $redirection, 10 );
	}

	/**
	 * Determine action-log status for policy violations.
	 *
	 * @param array<string,mixed> $payload Policy violation payload.
	 */
	private static function resolve_policy_violation_log_status( array $payload ): string {
		if ( ! empty( $payload['success'] ) ) {
			return 'success';
		}

		$on_violation = isset( $payload['policy']['on_violation'] )
			? strtolower( trim( (string) $payload['policy']['on_violation'] ) )
			: 'deny';

		return 'fail' === $on_violation ? 'error' : 'warning';
	}
}

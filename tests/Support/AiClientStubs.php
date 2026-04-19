<?php
/**
 * Minimal WordPress AI client stubs for PHPUnit.
 *
 * These allow the plugin test suite to load the streaming package without
 * depending on the Composer `wordpress/php-ai-client` package. Production
 * always uses WordPress core's bundled AI client on 7.0+.
 *
 * @package ClawPress\Tests
 */

declare( strict_types=1 );

namespace {
	$core_ai_client_autoload = dirname( __DIR__, 5 ) . '/wp-includes/php-ai-client/autoload.php';
	if ( file_exists( $core_ai_client_autoload ) ) {
		require_once $core_ai_client_autoload;
	}

	$load_test_stubs = defined( 'CLAWPRESS_ALLOW_AI_CLIENT_STUBS' ) && true === CLAWPRESS_ALLOW_AI_CLIENT_STUBS;
	if ( ! $load_test_stubs && 'cli' === PHP_SAPI ) {
		$load_test_stubs = ! interface_exists( 'WordPress\AiClientDependencies\Psr\Http\Client\ClientInterface', false )
			&& ! class_exists( 'WordPress\AiClient\AiClient', false );
	}

	if (
		! $load_test_stubs &&
		(
			interface_exists( 'WordPress\AiClientDependencies\Psr\Http\Client\ClientInterface', false ) ||
			class_exists( 'WordPress\AiClient\AiClient', false )
		)
	) {
		return;
	}

	if ( ! $load_test_stubs ) {
		return;
	}
}

namespace WordPress\AiClient\Messages\DTO {

	if ( ! enum_exists( __NAMESPACE__ . '\MessageRole', false ) ) {
		enum MessageRole: string {
			case SYSTEM = 'system';
			case USER   = 'user';
			case MODEL  = 'model';
		}
	}

	if ( ! class_exists( __NAMESPACE__ . '\MessagePart', false ) ) {
		final class MessagePart {
			private ?string $text;

			private ?\WordPress\AiClient\Tools\DTO\FunctionCall $function_call;

			public function __construct( ?string $text = null, ?\WordPress\AiClient\Tools\DTO\FunctionCall $function_call = null ) {
				$this->text          = $text;
				$this->function_call = $function_call;
			}

			public function getText(): ?string {
				return $this->text;
			}

			public function getFunctionCall(): ?\WordPress\AiClient\Tools\DTO\FunctionCall {
				return $this->function_call;
			}

			public function toArray(): array {
				$data = [];

				if ( null !== $this->text ) {
					$data['text'] = $this->text;
				}

				if ( null !== $this->function_call ) {
					$data['function_call'] = [
						'id'   => $this->function_call->getId(),
						'name' => $this->function_call->getName(),
						'args' => $this->function_call->getArgs(),
					];
				}

				return $data;
			}
		}
	}

	if ( ! class_exists( __NAMESPACE__ . '\Message', false ) ) {
		final class Message {
			private MessageRole $role;

			/** @var array<int,MessagePart> */
			private array $parts;

			/**
			 * @param array<int,MessagePart> $parts Message parts.
			 */
			public function __construct( MessageRole $role, array $parts = [] ) {
				$this->role  = $role;
				$this->parts = $parts;
			}

			public function getRole(): MessageRole {
				return $this->role;
			}

			/**
			 * @return array<int,MessagePart>
			 */
			public function getParts(): array {
				return $this->parts;
			}

			public function toArray(): array {
				$content = [];

				foreach ( $this->parts as $part ) {
					$content[] = $part->toArray();
				}

				return [
					'role'    => $this->role->value,
					'content' => $content,
				];
			}
		}
	}
}

namespace WordPress\AiClient\Tools\DTO {

	if ( ! class_exists( __NAMESPACE__ . '\FunctionDeclaration', false ) ) {
		final class FunctionDeclaration {
			private string $name;

			private string $description;

			/** @var array<string,mixed>|null */
			private ?array $parameters;

			/**
			 * @param array<string,mixed>|null $parameters Parameters schema.
			 */
			public function __construct( string $name, string $description = '', ?array $parameters = null ) {
				$this->name        = $name;
				$this->description = $description;
				$this->parameters  = $parameters;
			}

			public function getName(): string {
				return $this->name;
			}

			public function getDescription(): string {
				return $this->description;
			}

			/**
			 * @return array<string,mixed>|null
			 */
			public function getParameters(): ?array {
				return $this->parameters;
			}
		}
	}

	if ( ! class_exists( __NAMESPACE__ . '\FunctionCall', false ) ) {
		final class FunctionCall {
			private string $id;

			private string $name;

			/** @var array<string,mixed> */
			private array $args;

			/**
			 * @param array<string,mixed> $args Function call arguments.
			 */
			public function __construct( string $id = '', string $name = '', array $args = [] ) {
				$this->id   = $id;
				$this->name = $name;
				$this->args = $args;
			}

			public function getId(): string {
				return $this->id;
			}

			public function getName(): string {
				return $this->name;
			}

			/**
			 * @return array<string,mixed>
			 */
			public function getArgs(): array {
				return $this->args;
			}
		}
	}

	if ( ! class_exists( __NAMESPACE__ . '\FunctionResponse', false ) ) {
		final class FunctionResponse {
			private string $id;

			private string $name;

			/** @var array<string,mixed> */
			private array $response;

			/**
			 * @param array<string,mixed> $response Function response payload.
			 */
			public function __construct( string $id, string $name, array $response ) {
				$this->id       = $id;
				$this->name     = $name;
				$this->response = $response;
			}

			public function getId(): string {
				return $this->id;
			}

			public function getName(): string {
				return $this->name;
			}

			/**
			 * @return array<string,mixed>
			 */
			public function getResponse(): array {
				return $this->response;
			}
		}
	}
}

namespace WordPress\AiClient\Results\DTO {

	if ( ! class_exists( __NAMESPACE__ . '\TokenUsage', false ) ) {
		final class TokenUsage {
			private int $prompt_tokens;

			private int $completion_tokens;

			private int $total_tokens;

			public function __construct( int $prompt_tokens = 0, int $completion_tokens = 0, int $total_tokens = 0 ) {
				$this->prompt_tokens     = $prompt_tokens;
				$this->completion_tokens = $completion_tokens;
				$this->total_tokens      = $total_tokens;
			}

			public function getPromptTokens(): int {
				return $this->prompt_tokens;
			}

			public function getCompletionTokens(): int {
				return $this->completion_tokens;
			}

			public function getTotalTokens(): int {
				return $this->total_tokens;
			}
		}
	}

	if ( ! class_exists( __NAMESPACE__ . '\GenerativeAiResult', false ) ) {
		final class GenerativeAiResult {
			private \WordPress\AiClient\Messages\DTO\Message $message;

			private TokenUsage $token_usage;

			public function __construct( ?\WordPress\AiClient\Messages\DTO\Message $message = null, ?TokenUsage $token_usage = null ) {
				$this->message     = $message ?? new \WordPress\AiClient\Messages\DTO\Message(
					\WordPress\AiClient\Messages\DTO\MessageRole::MODEL,
					[
						new \WordPress\AiClient\Messages\DTO\MessagePart( '' ),
					]
				);
				$this->token_usage = $token_usage ?? new TokenUsage();
			}

			public function toMessage(): \WordPress\AiClient\Messages\DTO\Message {
				return $this->message;
			}

			public function getTokenUsage(): TokenUsage {
				return $this->token_usage;
			}
		}
	}
}

namespace WordPress\AiClient\Providers\Http\DTO {

	if ( ! class_exists( __NAMESPACE__ . '\RequestOptions', false ) ) {
		final class RequestOptions {
			private float $timeout = 0.0;

			private float $connect_timeout = 0.0;

			private int $max_redirects = 0;

			public function setTimeout( float $timeout ): self {
				$this->timeout = $timeout;
				return $this;
			}

			public function setConnectTimeout( float $connect_timeout ): self {
				$this->connect_timeout = $connect_timeout;
				return $this;
			}

			public function setMaxRedirects( int $max_redirects ): self {
				$this->max_redirects = $max_redirects;
				return $this;
			}

			public function getTimeout(): float {
				return $this->timeout;
			}

			public function getConnectTimeout(): float {
				return $this->connect_timeout;
			}

			public function getMaxRedirects(): int {
				return $this->max_redirects;
			}
		}
	}
}

namespace WordPress\AiClient\Providers\Models\DTO {

	if ( ! class_exists( __NAMESPACE__ . '\ModelConfig', false ) ) {
		final class ModelConfig {
			/** @var array<string,mixed> */
			private array $data;

			/**
			 * @param array<string,mixed> $data Config payload.
			 */
			private function __construct( array $data ) {
				$this->data = $data;
			}

			/**
			 * @param array<string,mixed> $data Config payload.
			 */
			public static function fromArray( array $data ): self {
				return new self( $data );
			}

			/**
			 * @return array<string,mixed>
			 */
			public function toArray(): array {
				return $this->data;
			}
		}
	}

	if ( ! class_exists( __NAMESPACE__ . '\ModelMetadata', false ) ) {
		final class ModelMetadata {
			private string $id;

			private string $name;

			public function __construct( string $id, string $name = '' ) {
				$this->id   = $id;
				$this->name = $name;
			}

			public function getId(): string {
				return $this->id;
			}

			public function getName(): string {
				return $this->name;
			}
		}
	}
}

namespace WordPress\AiClient\Providers\Contracts {

	if ( ! interface_exists( __NAMESPACE__ . '\ProviderAvailabilityInterface', false ) ) {
		interface ProviderAvailabilityInterface {
			public function isConfigured(): bool;
		}
	}

	if ( ! interface_exists( __NAMESPACE__ . '\ModelMetadataDirectoryInterface', false ) ) {
		interface ModelMetadataDirectoryInterface {
			/**
			 * @return array<int,\WordPress\AiClient\Providers\Models\DTO\ModelMetadata>
			 */
			public function listModelMetadata(): array;
		}
	}
}

namespace WordPress\AiClient\Providers\Http\Contracts {

	if ( ! interface_exists( __NAMESPACE__ . '\ClientWithOptionsInterface', false ) ) {
		interface ClientWithOptionsInterface {
			public function sendRequestWithOptions( \WordPress\AiClientDependencies\Psr\Http\Message\RequestInterface $request, \WordPress\AiClient\Providers\Http\DTO\RequestOptions $options ): \WordPress\AiClientDependencies\Psr\Http\Message\ResponseInterface;
		}
	}
}

namespace WordPress\AiClient\Providers\Http\Abstracts {

	if ( ! class_exists( __NAMESPACE__ . '\AbstractClientDiscoveryStrategy', false ) ) {
		abstract class AbstractClientDiscoveryStrategy {
			public static function init(): void {}
		}
	}
}

namespace WordPress\AiClient\Providers\Http\Exception {

	if ( ! class_exists( __NAMESPACE__ . '\NetworkException', false ) ) {
		class NetworkException extends \RuntimeException {}
	}
}

namespace WordPress\AiClient\Providers\Http {

	if ( ! class_exists( __NAMESPACE__ . '\HttpTransporter', false ) ) {
		class HttpTransporter {}
	}
}

namespace WordPress\AiClient\Providers {

	if ( ! class_exists( __NAMESPACE__ . '\ProviderRegistry', false ) ) {
		final class ProviderRegistry {
			/**
			 * @return array<int,string>
			 */
			public function getRegisteredProviderIds(): array {
				return [];
			}

			public function hasProvider( string $provider ): bool {
				unset( $provider );
				return false;
			}

			public function getProviderClassName( string $provider ): string {
				unset( $provider );
				return '';
			}

			public function getHttpTransporter(): ?object {
				return null;
			}

			public function getProviderModel( string $provider, string $model, \WordPress\AiClient\Providers\Models\DTO\ModelConfig $config ): object {
				unset( $provider, $model, $config );

				return new class() {
					public function metadata(): object {
						return new class() {
							/**
							 * @return array<int,object>
							 */
							public function getSupportedOptions(): array {
								return [];
							}
						};
					}
				};
			}
		}
	}
}

namespace WordPress\AiClient\Builders {

	if ( ! class_exists( __NAMESPACE__ . '\MessageBuilder', false ) ) {
		final class MessageBuilder {
			private string $content;

			private \WordPress\AiClient\Messages\DTO\MessageRole $role;

			private ?\WordPress\AiClient\Tools\DTO\FunctionResponse $function_response = null;

			public function __construct( string $content = '' ) {
				$this->content = $content;
				$this->role    = \WordPress\AiClient\Messages\DTO\MessageRole::USER;
			}

			public function usingUserRole(): self {
				$this->role = \WordPress\AiClient\Messages\DTO\MessageRole::USER;
				return $this;
			}

			public function usingModelRole(): self {
				$this->role = \WordPress\AiClient\Messages\DTO\MessageRole::MODEL;
				return $this;
			}

			public function usingSystemRole(): self {
				$this->role = \WordPress\AiClient\Messages\DTO\MessageRole::SYSTEM;
				return $this;
			}

			public function withFunctionResponse( \WordPress\AiClient\Tools\DTO\FunctionResponse $function_response ): self {
				$this->function_response = $function_response;
				return $this;
			}

			public function get(): \WordPress\AiClient\Messages\DTO\Message {
				$parts = [];

				if ( '' !== $this->content ) {
					$parts[] = new \WordPress\AiClient\Messages\DTO\MessagePart( $this->content );
				}

				if ( null !== $this->function_response ) {
					$payload = wp_json_encode( $this->function_response->getResponse() );
					$parts[] = new \WordPress\AiClient\Messages\DTO\MessagePart( false === $payload ? '' : (string) $payload );
				}

				return new \WordPress\AiClient\Messages\DTO\Message( $this->role, $parts );
			}
		}
	}

	if ( ! class_exists( __NAMESPACE__ . '\PromptBuilder', false ) ) {
		class PromptBuilder {
			/**
			 * @param mixed ...$args Unused test-only arguments.
			 */
			public function __construct( ...$args ) {
				unset( $args );
			}

			public function usingProvider( string $provider ): self {
				unset( $provider );
				return $this;
			}

			public function usingSystemInstruction( string $system_prompt ): self {
				unset( $system_prompt );
				return $this;
			}

			public function usingModel( object $model ): self {
				unset( $model );
				return $this;
			}

			public function usingModelPreference( array $preference ): self {
				unset( $preference );
				return $this;
			}

			public function usingRequestOptions( \WordPress\AiClient\Providers\Http\DTO\RequestOptions $request_options ): self {
				unset( $request_options );
				return $this;
			}

			public function usingFunctionDeclarations( \WordPress\AiClient\Tools\DTO\FunctionDeclaration ...$tool_declarations ): self {
				unset( $tool_declarations );
				return $this;
			}

			public function usingModelConfig( \WordPress\AiClient\Providers\Models\DTO\ModelConfig $model_config ): self {
				unset( $model_config );
				return $this;
			}

			public function generateResult(): \WordPress\AiClient\Results\DTO\GenerativeAiResult {
				return new \WordPress\AiClient\Results\DTO\GenerativeAiResult();
			}
		}
	}
}

namespace WordPress\AiClient {

	if ( ! class_exists( __NAMESPACE__ . '\AiClient', false ) ) {
		final class AiClient {
			private static ?\WordPress\AiClient\Providers\ProviderRegistry $registry = null;

			public static function defaultRegistry(): \WordPress\AiClient\Providers\ProviderRegistry {
				if ( null === self::$registry ) {
					self::$registry = new \WordPress\AiClient\Providers\ProviderRegistry();
				}

				return self::$registry;
			}

			/**
			 * @param mixed ...$args Prompt payload.
			 */
			public static function prompt( ...$args ): \WordPress\AiClient\Builders\PromptBuilder {
				return new \WordPress\AiClient\Builders\PromptBuilder( ...$args );
			}
		}
	}
}

namespace WordPress\AiClientDependencies\Psr\Http\Client {

	if ( ! interface_exists( __NAMESPACE__ . '\ClientInterface', false ) ) {
		interface ClientInterface {
			public function sendRequest( \WordPress\AiClientDependencies\Psr\Http\Message\RequestInterface $request ): \WordPress\AiClientDependencies\Psr\Http\Message\ResponseInterface;
		}
	}
}

namespace WordPress\AiClientDependencies\Psr\Http\Message {

	if ( ! interface_exists( __NAMESPACE__ . '\RequestInterface', false ) ) {
		interface RequestInterface {}
	}

	if ( ! interface_exists( __NAMESPACE__ . '\ResponseInterface', false ) ) {
		interface ResponseInterface {}
	}

	if ( ! interface_exists( __NAMESPACE__ . '\ResponseFactoryInterface', false ) ) {
		interface ResponseFactoryInterface {}
	}

	if ( ! interface_exists( __NAMESPACE__ . '\StreamFactoryInterface', false ) ) {
		interface StreamFactoryInterface {}
	}
}

namespace WordPress\AiClientDependencies\Nyholm\Psr7\Factory {

	if ( ! class_exists( __NAMESPACE__ . '\Psr17Factory', false ) ) {
		final class Psr17Factory implements \WordPress\AiClientDependencies\Psr\Http\Message\ResponseFactoryInterface, \WordPress\AiClientDependencies\Psr\Http\Message\StreamFactoryInterface {}
	}
}

namespace {

	if ( ! class_exists( 'WP_AI_Client_HTTP_Client', false ) ) {
		final class WP_AI_Client_HTTP_Client {
			public function __construct( ...$args ) {
				unset( $args );
			}

			public function sendRequest( $request ) {
				unset( $request );
				return null;
			}

			public function sendRequestWithOptions( $request, $options ) {
				unset( $request, $options );
				return null;
			}
		}
	}

	if ( ! class_exists( 'WP_AI_Client_Prompt_Builder', false ) ) {
		class WP_AI_Client_Prompt_Builder {
			private \WordPress\AiClient\Builders\PromptBuilder $builder;

			public function __construct( ...$args ) {
				$this->builder = new \WordPress\AiClient\Builders\PromptBuilder( ...$args );
			}

			public function __call( string $name, array $arguments ) {
				if ( method_exists( $this->builder, $name ) ) {
					$result = $this->builder->$name( ...$arguments );
					return $result instanceof \WordPress\AiClient\Builders\PromptBuilder ? $this : $result;
				}

				if ( 0 === strpos( $name, 'generate_' ) ) {
					return new \WordPress\AiClient\Results\DTO\GenerativeAiResult();
				}

				return $this;
			}
		}
	}
}

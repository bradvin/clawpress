<?php
/**
 * Tests for the web fetch ability.
 *
 * @package ClawPress\Tests
 */

declare( strict_types=1 );

namespace ClawPress\Tests\Unit;

use ClawPress\Abilities\Abilities;
use ClawPress\Helpers\Abilities_Helper;
use ClawPress\Helpers\Web_Fetch_Helper;
use ClawPress\Tests\Support\TestCase;
use ClawPress\Tests\Support\WordPress_Stubs;
use ClawPress\WebFetch\Fetcher_Interface;

/**
 * Minimal wpdb stub for web fetch ability tests.
 */
final class WebFetchAbilityTestWpdb {
	/**
	 * Table prefix.
	 *
	 * @var string
	 */
	public string $prefix = 'wp_';

	/**
	 * Captured insert calls.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	public array $insert_calls = [];

	/**
	 * Capture insert operation.
	 *
	 * @param string              $table Table name.
	 * @param array<string,mixed> $data Insert row.
	 * @param array<int,string>   $format Insert formats.
	 */
	public function insert( string $table, array $data, array $format ) {
		$this->insert_calls[] = [
			'table'  => $table,
			'data'   => $data,
			'format' => $format,
		];

		return 1;
	}
}

/**
 * Test fetcher used to verify passthrough request arguments.
 */
final class CaptureFetcher implements Fetcher_Interface {
	/**
	 * Last received request payload.
	 *
	 * @var array<string,mixed>
	 */
	public static array $last_request = [];

	/**
	 * Get fetcher slug.
	 */
	public function get_slug(): string {
		return 'capture';
	}

	/**
	 * Capture the normalized request and return a response payload.
	 *
	 * @param array<string,mixed> $request Normalized request payload.
	 * @return array<string,mixed>
	 */
	public function fetch( array $request ) {
		self::$last_request = $request;

		return [
			'status_code'    => 204,
			'status_message' => 'No Content',
			'headers'        => [
				'content-type' => 'text/plain',
			],
			'body'           => '',
		];
	}
}

final class WebFetchAbilityTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['wpdb'] = new WebFetchAbilityTestWpdb();
		CaptureFetcher::$last_request = [];

		Web_Fetch_Helper::get_instance()->register_fetcher( new CaptureFetcher() );

		( new Abilities() );
		do_action( 'wp_abilities_api_categories_init' );
		do_action( 'wp_abilities_api_init' );
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}

	public function test_execute_tool_call_fetches_url_with_default_wp_fetcher_and_logs_action(): void {
		WordPress_Stubs::$remote_request_responses['GET https://example.test/feed'] = [
			'response' => [
				'code'    => 200,
				'message' => 'OK',
			],
			'headers'  => [
				'content-type' => 'text/plain; charset=utf-8',
				'x-test'       => 'yes',
			],
			'body'     => 'hello world',
		];

		$result = Abilities_Helper::get_instance()->execute_tool_call(
			'web_fetch',
			[
				'url' => 'https://example.test/feed',
			],
			[
				'requesting_user_id' => 7,
				'execution_user_id'  => 11,
			]
		);

		$this->assertTrue( $result['success'] );
		$this->assertSame( 'wp', $result['result']['fetcher'] );
		$this->assertSame( 200, $result['result']['status_code'] );
		$this->assertSame( 'text/plain', $result['result']['content_type'] );
		$this->assertSame( 'hello world', $result['result']['body'] );
		$this->assertCount( 1, WordPress_Stubs::$remote_requests );
		$this->assertSame( 'https://example.test/feed', WordPress_Stubs::$validated_urls[0] );
		$this->assertSame( 'GET', WordPress_Stubs::$remote_requests[0]['args']['method'] );

		$log_call = $this->get_last_insert_for_table( 'wp_clawpress_action_logs' );
		$this->assertNotNull( $log_call );
		$this->assertSame( 'web_fetch', $log_call['data']['action_name'] );
		$this->assertSame( 'success', $log_call['data']['status'] );

		$context = json_decode( (string) $log_call['data']['context'], true );
		$this->assertIsArray( $context );
		$this->assertSame( 'wp', $context['fetcher'] );
		$this->assertSame( 'https://example.test/feed', $context['url'] );
		$this->assertSame( 200, $context['status_code'] );
		$this->assertSame( 11, $log_call['data']['execution_user_id'] );
	}

	public function test_execute_tool_call_supports_head_requests(): void {
		WordPress_Stubs::$remote_request_responses['HEAD https://example.test/health'] = [
			'response' => [
				'code'    => 200,
				'message' => 'OK',
			],
			'headers'  => [
				'content-type' => 'text/plain',
			],
			'body'     => '',
		];

		$result = Abilities_Helper::get_instance()->execute_tool_call(
			'web_fetch',
			[
				'url'    => 'https://example.test/health',
				'method' => 'HEAD',
			],
			[
				'requesting_user_id' => 1,
				'execution_user_id'  => 1,
			]
		);

		$this->assertTrue( $result['success'] );
		$this->assertSame( 'HEAD', $result['result']['method'] );
		$this->assertSame( 'HEAD', WordPress_Stubs::$remote_requests[0]['args']['method'] );
	}

	public function test_execute_tool_call_rejects_invalid_urls_and_logs_failure(): void {
		WordPress_Stubs::$http_validate_url_results['notaurl'] = false;

		$result = Abilities_Helper::get_instance()->execute_tool_call(
			'web_fetch',
			[
				'url' => 'notaurl',
			],
			[
				'requesting_user_id' => 2,
				'execution_user_id'  => 2,
			]
		);

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'clawpress_web_fetch_invalid_url', $result['error']['code'] );
		$this->assertSame( [], WordPress_Stubs::$remote_requests );

		$log_call = $this->get_last_insert_for_table( 'wp_clawpress_action_logs' );
		$this->assertNotNull( $log_call );
		$this->assertSame( 'error', $log_call['data']['status'] );

		$context = json_decode( (string) $log_call['data']['context'], true );
		$this->assertIsArray( $context );
		$this->assertSame( 'clawpress_web_fetch_invalid_url', $context['error']['code'] );
	}

	public function test_execute_tool_call_rejects_unknown_fetcher(): void {
		$result = Abilities_Helper::get_instance()->execute_tool_call(
			'web_fetch',
			[
				'url'     => 'https://example.test/feed',
				'fetcher' => 'missing',
			],
			[
				'requesting_user_id' => 3,
				'execution_user_id'  => 3,
			]
		);

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'clawpress_web_fetch_unknown_fetcher', $result['error']['code'] );
	}

	public function test_execute_tool_call_rejects_unsupported_methods(): void {
		$result = Abilities_Helper::get_instance()->execute_tool_call(
			'web_fetch',
			[
				'url'    => 'https://example.test/feed',
				'method' => 'POST',
			],
			[
				'requesting_user_id' => 4,
				'execution_user_id'  => 4,
			]
		);

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'clawpress_web_fetch_invalid_method', $result['error']['code'] );
	}

	public function test_execute_tool_call_passes_arguments_through_to_custom_fetchers(): void {
		$result = Abilities_Helper::get_instance()->execute_tool_call(
			'web_fetch',
			[
				'url'       => 'https://example.test/custom',
				'fetcher'   => 'capture',
				'arguments' => [
					'token' => 'abc123',
				],
			],
			[
				'requesting_user_id' => 5,
				'execution_user_id'  => 5,
			]
		);

		$this->assertTrue( $result['success'] );
		$this->assertSame( 'capture', $result['result']['fetcher'] );
		$this->assertSame( 'abc123', CaptureFetcher::$last_request['arguments']['token'] );
	}

	public function test_execute_tool_call_truncates_large_response_bodies(): void {
		$large_body = str_repeat( 'a', 205000 );

		WordPress_Stubs::$remote_request_responses['GET https://example.test/large'] = [
			'response' => [
				'code'    => 200,
				'message' => 'OK',
			],
			'headers'  => [
				'content-type' => 'text/plain',
			],
			'body'     => $large_body,
		];

		$result = Abilities_Helper::get_instance()->execute_tool_call(
			'web_fetch',
			[
				'url' => 'https://example.test/large',
			],
			[
				'requesting_user_id' => 6,
				'execution_user_id'  => 6,
			]
		);

		$this->assertTrue( $result['success'] );
		$this->assertTrue( $result['result']['truncated'] );
		$this->assertSame( 205000, $result['result']['body_bytes'] );
		$this->assertSame( 204800, strlen( $result['result']['body'] ) );
	}

	public function test_execute_tool_call_propagates_remote_errors_and_logs_failure(): void {
		WordPress_Stubs::$remote_request_responses['GET https://example.test/error'] = new \WP_Error(
			'http_request_failed',
			'Network down'
		);

		$result = Abilities_Helper::get_instance()->execute_tool_call(
			'web_fetch',
			[
				'url' => 'https://example.test/error',
			],
			[
				'requesting_user_id' => 8,
				'execution_user_id'  => 8,
			]
		);

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'http_request_failed', $result['error']['code'] );

		$log_call = $this->get_last_insert_for_table( 'wp_clawpress_action_logs' );
		$this->assertNotNull( $log_call );
		$this->assertSame( 'error', $log_call['data']['status'] );

		$context = json_decode( (string) $log_call['data']['context'], true );
		$this->assertIsArray( $context );
		$this->assertSame( 'http_request_failed', $context['error']['code'] );
	}

	/**
	 * Find the most recent insert call for a specific table.
	 *
	 * @return array<string,mixed>|null
	 */
	private function get_last_insert_for_table( string $table ): ?array {
		for ( $index = count( $GLOBALS['wpdb']->insert_calls ) - 1; $index >= 0; --$index ) {
			$call = $GLOBALS['wpdb']->insert_calls[ $index ];
			if ( $table === $call['table'] ) {
				return $call;
			}
		}

		return null;
	}
}

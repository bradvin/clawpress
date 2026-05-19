<?php
/**
 * Agents API memory-store adapter.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\AgentsAPI;

use AgentsAPI\Core\FilesRepository\WP_Agent_Memory_List_Entry;
use AgentsAPI\Core\FilesRepository\WP_Agent_Memory_Metadata;
use AgentsAPI\Core\FilesRepository\WP_Agent_Memory_Query;
use AgentsAPI\Core\FilesRepository\WP_Agent_Memory_Read_Result;
use AgentsAPI\Core\FilesRepository\WP_Agent_Memory_Scope;
use AgentsAPI\Core\FilesRepository\WP_Agent_Memory_Store;
use AgentsAPI\Core\FilesRepository\WP_Agent_Memory_Store_Capabilities;
use AgentsAPI\Core\FilesRepository\WP_Agent_Memory_Write_Result;
use ClawPress\Helpers\Memory_Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Adapts ClawPress CPT-backed memory to the Agents API memory store contract.
 */
final class Memory_Store implements WP_Agent_Memory_Store {
	/**
	 * Daily memory filename regex.
	 */
	private const DAILY_MEMORY_FILENAME_REGEX = '/^memory-(\d{8})\.md$/';

	/**
	 * Memory helper.
	 *
	 * @var Memory_Helper
	 */
	private Memory_Helper $memory_helper;

	/**
	 * Constructor.
	 *
	 * @param Memory_Helper|null $memory_helper Optional helper override.
	 */
	public function __construct( ?Memory_Helper $memory_helper = null ) {
		$this->memory_helper = $memory_helper ?? Memory_Helper::get_instance();
	}

	/**
	 * Declare metadata support.
	 */
	public function capabilities(): WP_Agent_Memory_Store_Capabilities {
		return WP_Agent_Memory_Store_Capabilities::none();
	}

	/**
	 * Read a memory file.
	 *
	 * @param WP_Agent_Memory_Scope $scope Memory scope.
	 * @param array<int,string>     $metadata_fields Requested metadata fields.
	 */
	public function read( WP_Agent_Memory_Scope $scope, array $metadata_fields = WP_Agent_Memory_Metadata::FIELDS ): WP_Agent_Memory_Read_Result {
		$entry = $this->find_entry( $scope );
		if ( null === $entry ) {
			return WP_Agent_Memory_Read_Result::not_found();
		}

		$content = (string) $entry['content'];

		return new WP_Agent_Memory_Read_Result(
			true,
			$content,
			sha1( $content ),
			strlen( $content ),
			isset( $entry['daily_timestamp'] ) && null !== $entry['daily_timestamp'] ? (int) $entry['daily_timestamp'] : null,
			null,
			$this->capabilities()->unsupported_metadata_fields( $metadata_fields, 'read' )
		);
	}

	/**
	 * Write a memory file.
	 */
	public function write( WP_Agent_Memory_Scope $scope, string $content, ?string $if_match = null, ?WP_Agent_Memory_Metadata $metadata = null ): WP_Agent_Memory_Write_Result {
		$filename = $this->normalize_filename( $scope->filename );
		if ( ! $this->is_valid_filename( $filename ) ) {
			return WP_Agent_Memory_Write_Result::failure( 'invalid_filename' );
		}

		if ( null !== $if_match ) {
			$current = $this->read( $scope );
			if ( $current->exists && $current->hash !== $if_match ) {
				return WP_Agent_Memory_Write_Result::failure( 'conflict' );
			}
		}

		$result = Memory_Helper::LONG_TERM_MEMORY_FILENAME === $filename
			? $this->memory_helper->save_long_term_memory( $content )
			: $this->memory_helper->save_daily_memory( $content, $this->timestamp_from_daily_filename( $filename ) );

		if ( empty( $result['success'] ) ) {
			return WP_Agent_Memory_Write_Result::failure( isset( $result['error'] ) ? (string) $result['error'] : 'write_failed' );
		}

		unset( $metadata );

		return WP_Agent_Memory_Write_Result::ok(
			sha1( $content ),
			strlen( $content ),
			null,
			$this->capabilities()->unsupported_metadata_fields( WP_Agent_Memory_Metadata::FIELDS, 'persist' )
		);
	}

	/**
	 * Check if a memory file exists.
	 */
	public function exists( WP_Agent_Memory_Scope $scope ): bool {
		return null !== $this->find_entry( $scope );
	}

	/**
	 * Delete a memory file.
	 */
	public function delete( WP_Agent_Memory_Scope $scope ): WP_Agent_Memory_Write_Result {
		$filename = $this->normalize_filename( $scope->filename );
		if ( ! $this->is_valid_filename( $filename ) ) {
			return WP_Agent_Memory_Write_Result::failure( 'invalid_filename' );
		}

		if ( ! $this->exists( $scope ) ) {
			return WP_Agent_Memory_Write_Result::ok( '', 0 );
		}

		$result = Memory_Helper::LONG_TERM_MEMORY_FILENAME === $filename
			? $this->memory_helper->delete_long_term_memory()
			: $this->memory_helper->delete_short_term_memory( $this->timestamp_from_daily_filename( $filename ) );

		return empty( $result['success'] )
			? WP_Agent_Memory_Write_Result::failure( isset( $result['error'] ) ? (string) $result['error'] : 'delete_failed' )
			: WP_Agent_Memory_Write_Result::ok( '', 0 );
	}

	/**
	 * List top-level memory files.
	 *
	 * @return array<int,WP_Agent_Memory_List_Entry>
	 */
	public function list_layer( WP_Agent_Memory_Scope $scope_query, ?WP_Agent_Memory_Query $query = null ): array {
		unset( $query );

		return array_map(
			fn( array $entry ): WP_Agent_Memory_List_Entry => $this->build_list_entry( $scope_query, $entry ),
			$this->memory_helper->list_memories( 0 )
		);
	}

	/**
	 * List memory files under a path prefix.
	 *
	 * ClawPress currently stores memory as top-level `memory.md` and
	 * `memory-ddmmyyyy.md` files, so subtrees are empty.
	 *
	 * @return array<int,WP_Agent_Memory_List_Entry>
	 */
	public function list_subtree( WP_Agent_Memory_Scope $scope_query, string $prefix, ?WP_Agent_Memory_Query $query = null ): array {
		unset( $scope_query, $prefix, $query );
		return [];
	}

	/**
	 * Find one ClawPress memory entry by scope filename.
	 *
	 * @param WP_Agent_Memory_Scope $scope Memory scope.
	 * @return array<string,mixed>|null
	 */
	private function find_entry( WP_Agent_Memory_Scope $scope ): ?array {
		$filename = $this->normalize_filename( $scope->filename );
		if ( ! $this->is_valid_filename( $filename ) ) {
			return null;
		}

		foreach ( $this->memory_helper->list_memories( 0 ) as $entry ) {
			if ( $filename === $this->normalize_filename( isset( $entry['filename'] ) ? (string) $entry['filename'] : '' ) ) {
				return $entry;
			}
		}

		return null;
	}

	/**
	 * Build a list entry value object.
	 *
	 * @param WP_Agent_Memory_Scope $scope Scope query.
	 * @param array<string,mixed>   $entry ClawPress memory entry.
	 */
	private function build_list_entry( WP_Agent_Memory_Scope $scope, array $entry ): WP_Agent_Memory_List_Entry {
		$content = isset( $entry['content'] ) ? (string) $entry['content'] : '';

		return new WP_Agent_Memory_List_Entry(
			isset( $entry['filename'] ) ? (string) $entry['filename'] : '',
			$scope->layer,
			strlen( $content ),
			isset( $entry['daily_timestamp'] ) && null !== $entry['daily_timestamp'] ? (int) $entry['daily_timestamp'] : null,
			null,
			$this->capabilities()->unsupported_metadata_fields( WP_Agent_Memory_Metadata::FIELDS, 'read' )
		);
	}

	/**
	 * Normalize memory filename.
	 */
	private function normalize_filename( string $filename ): string {
		$normalized = strtolower( trim( str_replace( '\\', '/', $filename ) ) );
		return basename( $normalized );
	}

	/**
	 * Validate supported ClawPress memory filenames.
	 */
	private function is_valid_filename( string $filename ): bool {
		return Memory_Helper::LONG_TERM_MEMORY_FILENAME === $filename || 1 === preg_match( self::DAILY_MEMORY_FILENAME_REGEX, $filename );
	}

	/**
	 * Convert a daily memory filename to a timestamp.
	 */
	private function timestamp_from_daily_filename( string $filename ): ?int {
		if ( 1 !== preg_match( self::DAILY_MEMORY_FILENAME_REGEX, $filename, $matches ) ) {
			return null;
		}

		$date = \DateTimeImmutable::createFromFormat( 'dmY', (string) $matches[1], new \DateTimeZone( 'UTC' ) );
		return $date instanceof \DateTimeImmutable ? $date->getTimestamp() : null;
	}
}

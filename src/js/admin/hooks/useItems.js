/**
 * Custom hook for fetching items using WordPress Data Layer.
 *
 * Uses @wordpress/core-data to fetch ClawPress Agent Files.
 */
import { useEntityRecords } from '@wordpress/core-data';

const POST_TYPE = 'clawpress_agent_file';

export function useItems( view ) {
	const { records, totalItems, totalPages, isResolving, hasResolved } =
		useEntityRecords( 'postType', POST_TYPE, {
			per_page: view.perPage,
			page: view.page,
			orderby: view.sort?.field === 'title' ? 'title' : 'date',
			order: view.sort?.direction || 'desc',
			search: view.search || undefined,
			status: 'any',
		} );

	return {
		records: records || [],
		total: totalItems || 0,
		totalPages: totalPages || 0,
		isLoading: isResolving,
		hasResolved,
	};
}

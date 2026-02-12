/**
 * Custom hook for fetching items using WordPress Data Layer.
 *
 * Uses @wordpress/core-data to fetch WordPress pages as demo data.
 * Replace 'page' with your custom post type slug when customizing.
 */
import { useEntityRecords } from '@wordpress/core-data';

export function useItems( view ) {
	const { records, totalItems, totalPages, isResolving, hasResolved } =
		useEntityRecords( 'postType', 'page', {
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

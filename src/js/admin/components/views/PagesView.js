/**
 * Pages view component with DataViews table.
 *
 * Displays ClawPress Agent Files using the DataViews component.
 */
import { DataViews } from '@wordpress/dataviews/wp';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useItems } from '../../hooks/useItems';
import { fields, actions } from '../../config/itemConfig';

export default function PagesView() {
	const [ view, setView ] = useState( {
		type: 'table',
		perPage: 10,
		page: 1,
		sort: { field: 'date', direction: 'desc' },
		search: '',
		filters: [],
		fields: [ 'title', 'status', 'date' ],
	} );

	const { records, total, totalPages, isLoading } = useItems( view );

	return (
		<div className="clawpress-dataviews">
			<p>{ __( 'Showing ClawPress Agent Files.', 'clawpress' ) }</p>
			<DataViews
				data={ records }
				fields={ fields }
				view={ view }
				onChangeView={ setView }
				actions={ actions() }
				paginationInfo={ {
					totalItems: total,
					totalPages,
				} }
				isLoading={ isLoading }
				getItemId={ ( item ) => item.id }
				defaultLayouts={ { table: {}, grid: {} } }
				search
			/>
		</div>
	);
}

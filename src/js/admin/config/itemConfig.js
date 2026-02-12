/**
 * DataViews field and action configuration.
 *
 * This example uses WordPress pages as demo data.
 * Replace with your custom post type fields when customizing.
 */
import { trash, pencil, external } from '@wordpress/icons';
import { Button, Flex } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { useDispatch } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';

export const fields = [
	{
		id: 'title',
		label: __( 'Title', 'clawpress' ),
		type: 'text',
		enableHiding: false,
		enableSorting: true,
		enableGlobalSearch: true,
		getValue: ( { item } ) => item.title?.rendered || '',
		render: ( { item } ) => (
			<a href={ item.link } target="_blank" rel="noopener noreferrer">
				{ item.title?.rendered ||
					__( '(no title)', 'clawpress' ) }
			</a>
		),
	},
	{
		id: 'status',
		label: __( 'Status', 'clawpress' ),
		type: 'text',
		enableHiding: true,
		enableSorting: false,
		getValue: ( { item } ) => item.status,
		render: ( { item } ) => {
			const statusLabels = {
				publish: __( 'Published', 'clawpress' ),
				draft: __( 'Draft', 'clawpress' ),
				pending: __( 'Pending', 'clawpress' ),
				private: __( 'Private', 'clawpress' ),
				trash: __( 'Trash', 'clawpress' ),
			};
			return (
				<span className={ `status-badge status-${ item.status }` }>
					{ statusLabels[ item.status ] || item.status }
				</span>
			);
		},
	},
	{
		id: 'date',
		label: __( 'Date', 'clawpress' ),
		type: 'text',
		enableHiding: true,
		enableSorting: true,
		getValue: ( { item } ) => item.date,
		render: ( { item } ) => {
			const date = new Date( item.date );
			return date.toLocaleDateString( undefined, {
				year: 'numeric',
				month: 'short',
				day: 'numeric',
			} );
		},
	},
	{
		id: 'modified',
		label: __( 'Modified', 'clawpress' ),
		type: 'text',
		enableHiding: true,
		enableSorting: false,
		getValue: ( { item } ) => item.modified,
		render: ( { item } ) => {
			const date = new Date( item.modified );
			return date.toLocaleDateString( undefined, {
				year: 'numeric',
				month: 'short',
				day: 'numeric',
			} );
		},
	},
];

export const actions = () => [
	{
		id: 'view',
		label: __( 'View', 'clawpress' ),
		icon: external,
		callback: ( items ) => {
			window.open( items[ 0 ].link, '_blank' );
		},
	},
	{
		id: 'edit',
		label: __( 'Edit', 'clawpress' ),
		icon: pencil,
		callback: ( items ) => {
			// Navigate to the WordPress edit screen
			window.location.href = `/wp-admin/post.php?post=${ items[ 0 ].id }&action=edit`;
		},
	},
	{
		id: 'trash',
		label: __( 'Move to Trash', 'clawpress' ),
		icon: trash,
		isDestructive: true,
		supportsBulk: true,
		RenderModal: ( { items, closeModal } ) => {
			const { deleteEntityRecord } = useDispatch( coreStore );

			const handleTrash = async () => {
				for ( const item of items ) {
					await deleteEntityRecord( 'postType', 'page', item.id );
				}
				closeModal();
			};

			return (
				<div>
					<p>
						{ items.length === 1
							? __(
									'Move this page to trash?',
									'clawpress'
							  )
							: sprintf(
									/* translators: %d: number of pages */
									__(
										'Move %d pages to trash?',
										'clawpress'
									),
									items.length
							  ) }
					</p>
					<Flex justify="flex-end" gap={ 2 }>
						<Button variant="secondary" onClick={ closeModal }>
							{ __( 'Cancel', 'clawpress' ) }
						</Button>
						<Button
							variant="primary"
							isDestructive
							onClick={ handleTrash }
						>
							{ __(
								'Move to Trash',
								'clawpress'
							) }
						</Button>
					</Flex>
				</div>
			);
		},
	},
];

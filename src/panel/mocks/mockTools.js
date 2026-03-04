import { __ } from '@wordpress/i18n';

export const runMockTool = async (
	tool,
	args,
	{ mockDelay = 'normal' } = {}
) => {
	if ( mockDelay === 'infinite' ) {
		await new Promise( () => {} );
	} else {
		const baseDelay = mockDelay === 'slow' ? 3000 : 300;
		await new Promise( ( r ) => setTimeout( r, baseDelay ) );
	}

	const searchText = typeof args?.search === 'string' ? args.search : '';
	if ( searchText.includes( 'ERROR' ) ) {
		throw new Error(
			__( 'Mock tool error: execution failed.', 'clawpress' )
		);
	}

	return {
		step: {
			data: {
				result: {
					dry_run: true,
					total: 2,
					changed: [
						{
							id: 123,
							title: __( 'Hello World', 'clawpress' ),
							count: 1,
						},
						{
							id: 456,
							title: __( 'Sample Post', 'clawpress' ),
							count: 2,
						},
					],
					tool,
					args,
				},
			},
		},
	};
};

import { __ } from '@wordpress/i18n';

const getAdminConfig = () => {
	if ( typeof window === 'undefined' ) {
		return { restBase: '/wp-json/clawpress/v1', nonce: '' };
	}

	return {
		restBase: window.CLAWPRESS_ADMIN?.restBase || '/wp-json/clawpress/v1',
		nonce: window.CLAWPRESS_ADMIN?.nonce || '',
	};
};

export const requestJson = async ( path, { method = 'GET', body } = {} ) => {
	const { restBase, nonce } = getAdminConfig();
	const url = `${ restBase }/${ path.replace( /^\//, '' ) }`;

	const response = await fetch( url, {
		method,
		credentials: 'same-origin',
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce': nonce,
		},
		body: body ? JSON.stringify( body ) : undefined,
	} );

	const text = await response.text();
	let payload = {};

	if ( text ) {
		try {
			payload = JSON.parse( text );
		} catch {
			payload = { message: text };
		}
	}

	if ( ! response.ok ) {
		throw new Error(
			payload?.message ||
				payload?.error ||
				__( 'Request failed.', 'clawpress' )
		);
	}

	return payload;
};

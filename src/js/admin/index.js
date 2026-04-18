/**
 * ClawPress Admin Page
 *
 * Entry point for the ClawPress admin interface.
 */

import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';
import App from './components/App';
import './style.scss';

/**
 * Mount the admin app on the plugin settings screen.
 */
domReady( () => {
	const rootElement = document.getElementById( 'clawpress-admin-root' );
	if ( ! rootElement ) {
		return;
	}

	const screen =
		typeof window !== 'undefined' &&
		typeof window.CLAWPRESS_ADMIN?.screen === 'string'
			? window.CLAWPRESS_ADMIN.screen
			: 'main';

	createRoot( rootElement ).render( <App screen={ screen } /> );
} );

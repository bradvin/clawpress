/**
 * Navigation hook for tab management.
 *
 * Manages tab state with URL parameter support for browser history.
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import { VIEWS, URL_PARAMS } from '../constants/navigation';

/**
 * Parse URL parameters and return initial navigation state.
 *
 * @return {string} The current view from URL or default.
 */
function parseUrlState() {
	const urlParams = new URLSearchParams( window.location.search );
	const view = urlParams.get( URL_PARAMS.VIEW );

	if ( view && Object.values( VIEWS ).includes( view ) ) {
		return view;
	}

	return VIEWS.PAGES;
}

/**
 * Navigation hook that reads URL on load and provides navigation methods.
 *
 * @return {Object} Navigation state and methods.
 */
export function useNavigation() {
	const [ currentView, setCurrentView ] = useState( parseUrlState );
	const [ isInitialized, setIsInitialized ] = useState( false );

	// Mark as initialized after first render.
	useEffect( () => {
		setIsInitialized( true );
	}, [] );

	// Listen for browser back/forward buttons.
	useEffect( () => {
		const handlePopState = () => {
			setCurrentView( parseUrlState() );
		};

		window.addEventListener( 'popstate', handlePopState );
		return () => window.removeEventListener( 'popstate', handlePopState );
	}, [] );

	// Handle tab selection.
	const handleTabSelect = useCallback(
		( tabName ) => {
			// Prevent navigation during initialization.
			if ( ! isInitialized ) {
				return;
			}

			// Only navigate if the tab is actually changing.
			if ( tabName === currentView ) {
				return;
			}

			// Update URL to reflect the new tab.
			const url = new URL( window.location );
			url.searchParams.set( URL_PARAMS.VIEW, tabName );
			window.history.pushState( {}, '', url );
			setCurrentView( tabName );
		},
		[ isInitialized, currentView ]
	);

	return {
		currentView,
		handleTabSelect,
		VIEWS,
	};
}

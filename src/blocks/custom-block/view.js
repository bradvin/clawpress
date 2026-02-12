/**
 * Interactivity API store for the toggle content block.
 */
import { store, getContext } from '@wordpress/interactivity';

const { state } = store( 'clawpress/toggle-content', {
	state: {
		get buttonText() {
			const context = getContext();
			return context.isOpen ? 'Hide Content' : 'Show Content';
		},
	},
	actions: {
		toggle() {
			const context = getContext();
			context.isOpen = ! context.isOpen;
		},
	},
} );

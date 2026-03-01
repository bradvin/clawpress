/**
 * Main App component with tab navigation.
 *
 * Uses WordPress TabPanel for navigation between views.
 */
import { TabPanel } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useNavigation } from '../hooks/useNavigation';
import AbilitiesView from './views/AbilitiesView';
import PagesView from './views/PagesView';
import SettingsView from './views/SettingsView';

export default function App() {
	const { currentView, handleTabSelect, VIEWS } = useNavigation();

	const tabs = [
		{
			name: VIEWS.PAGES,
			title: __( 'Agent Files', 'clawpress' ),
			content: <PagesView />,
		},
		{
			name: VIEWS.SETTINGS,
			title: __( 'Settings', 'clawpress' ),
			content: <SettingsView />,
		},
		{
			name: VIEWS.ABILITIES,
			title: __( 'Abilities', 'clawpress' ),
			content: <AbilitiesView />,
		},
	];

	return (
		<div className="clawpress-app">
			<TabPanel
				tabs={ tabs }
				initialTabName={ currentView }
				onSelect={ handleTabSelect }
			>
				{ ( { content } ) => (
					<div className="clawpress-tab-content">{ content }</div>
				) }
			</TabPanel>
		</div>
	);
}

/**
 * Settings view placeholder component.
 *
 * Add your plugin settings UI here.
 */
import { Card, CardBody, CardHeader } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function SettingsView() {
	return (
		<div className="clawpress-settings">
			<Card>
				<CardHeader>
					<h3>{ __( 'Settings', 'clawpress' ) }</h3>
				</CardHeader>
				<CardBody>
					<p>
						{ __(
							'Add your plugin settings here. Use @wordpress/components for form controls.',
							'clawpress'
						) }
					</p>
				</CardBody>
			</Card>
		</div>
	);
}

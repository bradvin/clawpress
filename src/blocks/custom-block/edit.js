/**
 * Block editor component.
 */
import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

export default function Edit() {
	const blockProps = useBlockProps( { className: 'clawpress-toggle-block' } );

	return (
		<div { ...blockProps }>
			<button className="clawpress-toggle-block__button" disabled>
				{ __( 'Show Content', 'clawpress' ) }
			</button>
			<div className="clawpress-toggle-block__content">
				<p>
					{ __(
						'This content is toggled by the Interactivity API. Click the button to hide it.',
						'clawpress'
					) }
				</p>
			</div>
			<p className="clawpress-toggle-block__editor-note">
				<em>
					{ __(
						'Toggle interaction works on the frontend.',
						'clawpress'
					) }
				</em>
			</p>
		</div>
	);
}

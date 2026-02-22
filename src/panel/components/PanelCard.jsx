import { __ } from '@wordpress/i18n';
import WelcomeCard from './cards/WelcomeCard';
import SetupCard from './cards/SetupCard';
import ErrorCard from './cards/ErrorCard';
import UserConfirmationCard from './cards/UserConfirmationCard';
import { normalizeCardActions } from '../utils/cardActions';

const PanelCard = ( { card, fallbackText, onSendAction, isBusy = false } ) => {
	if ( ! card || typeof card !== 'object' ) {
		return (
			<div className="clawpress-msg-content">{ fallbackText || '' }</div>
		);
	}

	switch ( card.type ) {
		case 'welcome':
			return (
				<WelcomeCard
					card={ card }
					onSendAction={ onSendAction }
					isBusy={ isBusy }
				/>
			);
		case 'setup':
			return (
				<SetupCard
					card={ card }
					onSendAction={ onSendAction }
					isBusy={ isBusy }
				/>
			);
		case 'error':
			return <ErrorCard card={ card } />;
		case 'user_confirmation':
			return (
				<UserConfirmationCard
					card={ card }
					onSendAction={ onSendAction }
					isBusy={ isBusy }
				/>
			);
		default:
			break;
	}

	const title =
		typeof card?.data?.title === 'string' && card.data.title.trim()
			? card.data.title
			: __( 'Card', 'clawpress' );
	const message =
		typeof card?.data?.message === 'string' && card.data.message.trim()
			? card.data.message
			: fallbackText || '';
	const subtitle =
		typeof card?.data?.subtitle === 'string' && card.data.subtitle.trim()
			? card.data.subtitle
			: '';
	const actions = normalizeCardActions( card );

	if ( ! message && actions.length === 0 ) {
		return (
			<div className="clawpress-msg-content">{ fallbackText || '' }</div>
		);
	}

	return (
		<div className="clawpress-card clawpress-card-generic">
			<div className="clawpress-card-body">
				{ title ? (
					<div className="clawpress-card-section-title">
						<div className="clawpress-card-title">{ title }</div>
					</div>
				) : null }
				{ subtitle ? (
					<div className="clawpress-card-section-subtitle">
						<div className="clawpress-card-subtitle">
							{ subtitle }
						</div>
					</div>
				) : null }
				{ message ? (
					<div className="clawpress-card-section-content">
						<div className="clawpress-card-text">{ message }</div>
					</div>
				) : null }
				{ actions.length > 0 ? (
					<div className="clawpress-card-section-buttons">
						<div className="clawpress-card-actions">
							{ actions.map( ( action ) => (
								<button
									key={ action.id }
									type="button"
									className="button button-secondary button-small"
									onClick={ () => onSendAction?.( action ) }
									disabled={ isBusy }
								>
									{ action.label }
								</button>
							) ) }
						</div>
					</div>
				) : null }
			</div>
		</div>
	);
};

export default PanelCard;

import { __ } from '@wordpress/i18n';
import WelcomeCard from './cards/WelcomeCard';
import { normalizeCardActions } from '../utils/cardActions';

const PanelCard = ({ card, fallbackText, onSendAction, isBusy = false }) => {
  if (!card || typeof card !== 'object') {
    return <div className="clawpress-msg-content">{fallbackText || ''}</div>;
  }

  switch (card.type) {
    case 'welcome':
      return <WelcomeCard card={card} onSendAction={onSendAction} isBusy={isBusy} />;
    default:
      break;
  }

  const title =
    typeof card?.data?.title === 'string' && card.data.title.trim()
      ? card.data.title
      : __('Card', 'clawpress');
  const message =
    typeof card?.data?.message === 'string' && card.data.message.trim()
      ? card.data.message
      : fallbackText || '';
  const actions = normalizeCardActions(card);

  if (!message && actions.length === 0) {
    return <div className="clawpress-msg-content">{fallbackText || ''}</div>;
  }

  return (
    <div className="clawpress-card clawpress-card-generic">
      <div className="clawpress-card-body">
        {title ? <div className="clawpress-card-title">{title}</div> : null}
        {message ? <div className="clawpress-card-text">{message}</div> : null}
        {actions.length > 0 ? (
          <div className="clawpress-card-actions">
            {actions.map((action) => (
              <button
                key={action.id}
                type="button"
                className="button button-secondary button-small"
                onClick={() => onSendAction?.(action.prompt)}
                disabled={isBusy}
              >
                {action.label}
              </button>
            ))}
          </div>
        ) : null}
      </div>
    </div>
  );
};

export default PanelCard;

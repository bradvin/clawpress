import { __ } from '@wordpress/i18n';
import { normalizeCardActions } from '../../utils/cardActions';

const WelcomeCard = ({ card, onSendAction, isBusy = false }) => {
  const title =
    typeof card?.data?.title === 'string' && card.data.title.trim()
      ? card.data.title
      : __('Welcome to ClawPress', 'clawpress');
  const message =
    typeof card?.data?.message === 'string' && card.data.message.trim()
      ? card.data.message
      : __('Hello! I am ready to help with your WordPress tasks.', 'clawpress');
  const emoji =
    typeof card?.data?.emoji === 'string' && card.data.emoji.trim()
      ? card.data.emoji
      : '👋';
  const actions = normalizeCardActions(card);

  return (
    <div className="clawpress-card clawpress-card-welcome">
      <div className="clawpress-card-emoji" aria-hidden="true">
        {emoji}
      </div>
      <div className="clawpress-card-body">
        <div className="clawpress-card-title">{title}</div>
        <div className="clawpress-card-text">{message}</div>
        {actions.length > 0 ? (
          <div className="clawpress-card-actions">
            {actions.map((action) => (
              <button
                key={action.id}
                type="button"
                className="button button-secondary button-small"
                onClick={() => onSendAction?.(action)}
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

export default WelcomeCard;

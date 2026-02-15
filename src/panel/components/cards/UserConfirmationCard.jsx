import { __ } from '@wordpress/i18n';
import { normalizeCardActions } from '../../utils/cardActions';

const UserConfirmationCard = ({ card, onSendAction, isBusy = false }) => {
  const title =
    typeof card?.data?.title === 'string' && card.data.title.trim()
      ? card.data.title
      : __('User Confirmation Required', 'clawpress');
  const subtitle =
    typeof card?.data?.subtitle === 'string' && card.data.subtitle.trim()
      ? card.data.subtitle
      : __('Destructive action pending', 'clawpress');
  const message =
    typeof card?.data?.message === 'string' && card.data.message.trim()
      ? card.data.message
      : __('Please confirm or decline this action.', 'clawpress');
  const actions = normalizeCardActions(card);

  return (
    <div className="clawpress-card clawpress-card-user-confirmation" role="alert" aria-live="polite">
      <div className="clawpress-card-body">
        <div className="clawpress-card-section-title">
          <div className="clawpress-card-title">{title}</div>
        </div>
        {subtitle ? (
          <div className="clawpress-card-section-subtitle">
            <div className="clawpress-card-subtitle">{subtitle}</div>
          </div>
        ) : null}
        <div className="clawpress-card-section-content">
          <div className="clawpress-card-text">{message}</div>
        </div>
        {actions.length > 0 ? (
          <div className="clawpress-card-section-buttons">
            <div className="clawpress-card-actions">
              {actions.map((action, index) => (
                <button
                  key={action.id}
                  type="button"
                  className={`button button-small ${
                    index === 0 ? 'button-primary' : 'button-secondary'
                  }`}
                  onClick={() => onSendAction?.(action)}
                  disabled={isBusy}
                >
                  {action.label}
                </button>
              ))}
            </div>
          </div>
        ) : null}
      </div>
    </div>
  );
};

export default UserConfirmationCard;

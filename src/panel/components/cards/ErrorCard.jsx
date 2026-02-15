import { __ } from '@wordpress/i18n';

const ErrorCard = ({ card }) => {
  const title =
    typeof card?.data?.title === 'string' && card.data.title.trim()
      ? card.data.title
      : __('Request Error', 'clawpress');
  const message =
    typeof card?.data?.message === 'string' && card.data.message.trim()
      ? card.data.message
      : __('An unknown error occurred.', 'clawpress');
  const subtitle =
    typeof card?.data?.subtitle === 'string' && card.data.subtitle.trim()
      ? card.data.subtitle
      : '';

  return (
    <div className="clawpress-card clawpress-card-error" role="alert" aria-live="polite">
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
      </div>
    </div>
  );
};

export default ErrorCard;

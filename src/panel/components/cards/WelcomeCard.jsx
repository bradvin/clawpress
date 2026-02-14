import { __ } from '@wordpress/i18n';

const WelcomeCard = ({ card }) => {
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

  return (
    <div className="clawpress-card clawpress-card-welcome">
      <div className="clawpress-card-emoji" aria-hidden="true">
        {emoji}
      </div>
      <div className="clawpress-card-body">
        <div className="clawpress-card-title">{title}</div>
        <div className="clawpress-card-text">{message}</div>
      </div>
    </div>
  );
};

export default WelcomeCard;

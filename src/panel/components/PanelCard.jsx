import WelcomeCard from './cards/WelcomeCard';

const PanelCard = ({ card, fallbackText }) => {
  if (!card || typeof card !== 'object') {
    return <div className="clawpress-msg-content">{fallbackText || ''}</div>;
  }

  switch (card.type) {
    case 'welcome':
      return <WelcomeCard card={card} />;
    default:
      return <div className="clawpress-msg-content">{fallbackText || ''}</div>;
  }
};

export default PanelCard;

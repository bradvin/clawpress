import { __ } from '@wordpress/i18n';

const PanelToggle = ({ onToggle }) => (
  <button
    className="button button-primary clawpress-toggle"
    onClick={onToggle}
    type="button"
  >
    {__('ClawPress', 'clawpress')}
  </button>
);

export default PanelToggle;

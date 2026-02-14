import { __ } from '@wordpress/i18n';

const getStatusLabel = (statusMode) => {
  if (statusMode === 'online') {
    return __('Online', 'clawpress');
  }
  if (statusMode === 'offline') {
    return __('Offline', 'clawpress');
  }
  return statusMode;
};

const PanelHeader = ({ onClose, onToggleTheme, statusMode, statusLabel, statusLoading }) => {
  const resolvedStatusMode = statusMode || 'offline';

  return (
    <div className="clawpress-header">
      <div className="clawpress-header-meta">
        <div className="clawpress-title">{__('ClawPress Agent', 'clawpress')}</div>
        <div className={`clawpress-status clawpress-status-${resolvedStatusMode}`}>
          <span className="clawpress-status-dot" />
          <span className="clawpress-status-mode">
            {statusLoading
              ? __('Checking…', 'clawpress')
              : getStatusLabel(resolvedStatusMode)}
          </span>
          {statusLabel ? <span className="clawpress-status-label">{statusLabel}</span> : null}
        </div>
      </div>
      <button
        className="clawpress-theme-toggle"
        type="button"
        onClick={onToggleTheme}
        aria-label={__('Toggle theme', 'clawpress')}
      >
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path d="M12 18q2.484 0 4.242-1.758t1.758-4.242-1.758-4.242-4.242-1.758q-1.219 0-2.484 0.563 1.547 0.703 2.508 2.18t0.961 3.258-0.961 3.258-2.508 2.18q1.266 0.563 2.484 0.563zM20.016 8.672l3.281 3.328-3.281 3.328v4.688h-4.688l-3.328 3.281-3.328-3.281h-4.688v-4.688l-3.281-3.328 3.281-3.328v-4.688h4.688l3.328-3.281 3.328 3.281h4.688v4.688z"></path>
        </svg>
      </button>
      <button
        className="clawpress-close"
        onClick={onClose}
        type="button"
        aria-label={__('Close panel', 'clawpress')}
      >
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path d="M18.984 6.422l-5.578 5.578 5.578 5.578-1.406 1.406-5.578-5.578-5.578 5.578-1.406-1.406 5.578-5.578-5.578-5.578 1.406-1.406 5.578 5.578 5.578-5.578z"></path>
        </svg>
      </button>
    </div>
  );
};

export default PanelHeader;

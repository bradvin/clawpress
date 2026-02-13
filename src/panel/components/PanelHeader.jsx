const PanelHeader = ({ onClose, themeMode, onToggleTheme }) => (
  <div className="clawpress-header">
    <div className="clawpress-title">ClawPress Agent</div>
    <button className="clawpress-theme-toggle" type="button" onClick={onToggleTheme}>
      <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <path d="M12 18q2.484 0 4.242-1.758t1.758-4.242-1.758-4.242-4.242-1.758q-1.219 0-2.484 0.563 1.547 0.703 2.508 2.18t0.961 3.258-0.961 3.258-2.508 2.18q1.266 0.563 2.484 0.563zM20.016 8.672l3.281 3.328-3.281 3.328v4.688h-4.688l-3.328 3.281-3.328-3.281h-4.688v-4.688l-3.281-3.328 3.281-3.328v-4.688h4.688l3.328-3.281 3.328 3.281h4.688v4.688z"></path>
      </svg>
    </button>
    <button className="clawpress-close" onClick={onClose} type="button">
      <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <path d="M18.984 6.422l-5.578 5.578 5.578 5.578-1.406 1.406-5.578-5.578-5.578 5.578-1.406-1.406 5.578-5.578-5.578-5.578 1.406-1.406 5.578 5.578 5.578-5.578z"></path>
      </svg>
    </button>
  </div>
);

export default PanelHeader;

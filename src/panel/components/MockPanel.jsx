const MockPanel = ({
  mockEnabled,
  mockScenario,
  mockDelay,
  onSelectScenario,
  onSelectDelay,
  onRunScenario,
  themeMode,
}) => {
  if (!mockEnabled) return null;

  return (
    <div className="clawpress-mock-panel" data-theme={themeMode}>
      <div className="clawpress-mock-badge">Mock</div>
      <fieldset className="clawpress-mock-fieldset">
        <legend>Scenario</legend>
        <div className="clawpress-mock-buttons">
          {['normal', 'long', 'tool', 'tool_error', 'error'].map((mode) => (
            <button
              key={mode}
              className={`button ${mockScenario === mode ? 'button-primary' : ''}`}
              type="button"
              onClick={() => onSelectScenario(mode)}
            >
              {mode}
            </button>
          ))}
        </div>
      </fieldset>
      <fieldset className="clawpress-mock-fieldset">
        <legend>Response delay</legend>
        <div className="clawpress-mock-buttons">
          {[
            { key: 'normal', label: 'normal' },
            { key: 'slow', label: 'slow (3s)' },
            { key: 'infinite', label: 'infinite' },
          ].map((mode) => (
            <button
              key={mode.key}
              className={`button ${mockDelay === mode.key ? 'button-primary' : ''}`}
              type="button"
              onClick={() => onSelectDelay(mode.key)}
            >
              {mode.label}
            </button>
          ))}
        </div>
      </fieldset>
      <button className="button" type="button" onClick={onRunScenario}>
        Run
      </button>
    </div>
  );
};

export default MockPanel;

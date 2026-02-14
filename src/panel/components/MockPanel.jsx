import { __ } from '@wordpress/i18n';

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

  const scenarios = [
    { key: 'normal', label: __('Normal', 'clawpress') },
    { key: 'long', label: __('Long', 'clawpress') },
    { key: 'tool', label: __('Tool', 'clawpress') },
    { key: 'tool_error', label: __('Tool Error', 'clawpress') },
    { key: 'error', label: __('Error', 'clawpress') },
  ];

  const delays = [
    { key: 'normal', label: __('Normal', 'clawpress') },
    { key: 'slow', label: __('Slow (3s)', 'clawpress') },
    { key: 'infinite', label: __('Infinite', 'clawpress') },
  ];

  return (
    <div className="clawpress-mock-panel" data-theme={themeMode}>
      <div className="clawpress-mock-badge">{__('Mock', 'clawpress')}</div>
      <fieldset className="clawpress-mock-fieldset">
        <legend>{__('Scenario', 'clawpress')}</legend>
        <div className="clawpress-mock-buttons">
          {scenarios.map((scenario) => (
            <button
              key={scenario.key}
              className={`button ${mockScenario === scenario.key ? 'button-primary' : ''}`}
              type="button"
              onClick={() => onSelectScenario(scenario.key)}
            >
              {scenario.label}
            </button>
          ))}
        </div>
      </fieldset>
      <fieldset className="clawpress-mock-fieldset">
        <legend>{__('Response delay', 'clawpress')}</legend>
        <div className="clawpress-mock-buttons">
          {delays.map((mode) => (
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
        {__('Run', 'clawpress')}
      </button>
    </div>
  );
};

export default MockPanel;

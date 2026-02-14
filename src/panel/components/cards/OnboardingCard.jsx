import { useEffect, useMemo, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { normalizeCardActions } from '../../utils/cardActions';

const getChoiceActions = (actions, prefix) =>
  actions
    .filter(
      (action) =>
        action?.type === 'send_prompt' &&
        typeof action.prompt === 'string' &&
        action.prompt.startsWith(prefix)
    )
    .map((action) => ({
      ...action,
      value: action.prompt.slice(prefix.length).trim(),
    }))
    .filter((action) => action.value.length > 0);

const getRelativeWorkspacePath = (value) => {
  if (typeof value !== 'string') {
    return '';
  }

  const normalized = value.trim().replace(/\\/g, '/');
  if (!normalized) {
    return '';
  }

  const index = normalized.indexOf('/wp-content');
  if (index === -1) {
    return normalized;
  }

  return normalized.slice(index);
};

const OnboardingCard = ({ card, onSendAction, isBusy = false }) => {
  const data =
    card?.data && typeof card.data === 'object' && !Array.isArray(card.data)
      ? card.data
      : {};
  const title =
    typeof data.title === 'string' && data.title.trim()
      ? data.title
      : __('Onboarding Wizard', 'clawpress');
  const emoji =
    typeof data.emoji === 'string' && data.emoji.trim() ? data.emoji : '🧙';
  const message =
    typeof data.message === 'string' && data.message.trim()
      ? data.message
      : __('Follow these steps to finish setup.', 'clawpress');
  const detail =
    typeof data.detail === 'string' && data.detail.trim() ? data.detail : '';
  const error =
    typeof data.error === 'string' && data.error.trim() ? data.error : '';
  const step = typeof data.step === 'string' ? data.step.trim() : '';
  const stepLabel =
    typeof data.step_label === 'string' && data.step_label.trim()
      ? data.step_label
      : step;
  const stepIndex = Number.isFinite(Number(data.step_index))
    ? Number(data.step_index)
    : null;
  const stepTotal = Number.isFinite(Number(data.step_total))
    ? Number(data.step_total)
    : null;
  const stepItems = Array.isArray(data.steps) ? data.steps : [];
  const actions = normalizeCardActions(card);
  const providerChoiceActions = useMemo(
    () => getChoiceActions(actions, '/onboarding provider '),
    [actions]
  );
  const modelChoiceActions = useMemo(
    () => getChoiceActions(actions, '/onboarding model '),
    [actions]
  );

  const filteredActions = useMemo(() => {
    const hiddenIds = new Set([
      ...providerChoiceActions.map((action) => action.id),
      ...modelChoiceActions.map((action) => action.id),
    ]);

    return actions.filter((action) => !hiddenIds.has(action.id));
  }, [actions, providerChoiceActions, modelChoiceActions]);

  const actionsWithBack = useMemo(() => {
    const hasBackAction = filteredActions.some(
      (action) =>
        action?.type === 'send_prompt' &&
        typeof action.prompt === 'string' &&
        action.prompt.trim() === '/onboarding back'
    );

    if (step === 'provider' || hasBackAction) {
      return filteredActions;
    }

    return [
      {
        id: 'wizard-back-fallback',
        label: __('Back', 'clawpress'),
        type: 'send_prompt',
        prompt: '/onboarding back',
      },
      ...filteredActions,
    ];
  }, [filteredActions, step]);

  const [selectedProviderId, setSelectedProviderId] = useState(
    providerChoiceActions[0]?.id || ''
  );
  const [selectedModelId, setSelectedModelId] = useState(
    modelChoiceActions[0]?.id || ''
  );

  useEffect(() => {
    const ids = providerChoiceActions.map((action) => action.id);
    if (ids.length === 0) {
      setSelectedProviderId('');
      return;
    }

    if (!ids.includes(selectedProviderId)) {
      setSelectedProviderId(ids[0]);
    }
  }, [providerChoiceActions, selectedProviderId]);

  useEffect(() => {
    const ids = modelChoiceActions.map((action) => action.id);
    if (ids.length === 0) {
      setSelectedModelId('');
      return;
    }

    if (!ids.includes(selectedModelId)) {
      setSelectedModelId(ids[0]);
    }
  }, [modelChoiceActions, selectedModelId]);

  const selectedProviderAction =
    providerChoiceActions.find((action) => action.id === selectedProviderId) ||
    providerChoiceActions[0] ||
    null;
  const selectedModelAction =
    modelChoiceActions.find((action) => action.id === selectedModelId) ||
    modelChoiceActions[0] ||
    null;

  const settingsUrl =
    typeof data.settings_url === 'string' && data.settings_url.trim()
      ? data.settings_url.trim()
      : '';
  const onProviderSettingsPage = useMemo(() => {
    if (!settingsUrl || typeof window === 'undefined') {
      return false;
    }

    try {
      const currentUrl = new URL(window.location.href);
      const targetUrl = new URL(settingsUrl, window.location.origin);
      return (
        currentUrl.pathname === targetUrl.pathname &&
        currentUrl.search === targetUrl.search
      );
    } catch {
      return false;
    }
  }, [settingsUrl]);

  const stepHeaderText =
    stepIndex && stepTotal
      ? sprintf(
          /* translators: 1: current step number, 2: total step count, 3: step label. */
          __('Step %1$d OF %2$d : %3$s', 'clawpress'),
          stepIndex,
          stepTotal,
          stepLabel || ''
        )
      : '';
  const workspacePathValue =
    step === 'workspace' &&
    typeof data.workspace_path === 'string' &&
    data.workspace_path.trim()
      ? getRelativeWorkspacePath(data.workspace_path)
      : '';
  const workspaceExistsValue = (() => {
    if (step !== 'workspace') {
      return '';
    }

    if (
      typeof data.workspace_exists === 'string' &&
      data.workspace_exists.trim()
    ) {
      return data.workspace_exists.trim();
    }

    if (
      typeof data.workspace_exists_line === 'string' &&
      data.workspace_exists_line.trim()
    ) {
      return data.workspace_exists_line.replace(/^Exists\s*:\s*/i, '').trim();
    }

    return '';
  })();
  const showProviderPicker = step === 'provider' && providerChoiceActions.length > 1;
  const showModelPicker = step === 'model' && modelChoiceActions.length > 1;
  const showSingleProviderAction =
    step === 'provider' && providerChoiceActions.length === 1;
  const showSingleModelAction = step === 'model' && modelChoiceActions.length === 1;
  const showActionButtons =
    showSingleProviderAction || showSingleModelAction || actionsWithBack.length > 0;
  const hasButtonsSection =
    showProviderPicker || showModelPicker || showActionButtons;

  return (
    <div className="clawpress-card clawpress-card-onboarding">
      <div className="clawpress-card-body">
        <div className="clawpress-card-section-title">
          <div className="clawpress-card-onboarding-title-line">
            <span className="clawpress-card-onboarding-emoji" aria-hidden="true">
              {emoji}
            </span>
            <div className="clawpress-card-title">{title}</div>
          </div>
        </div>
        {stepHeaderText || stepItems.length > 0 ? (
          <div className="clawpress-card-section-subtitle">
            {stepHeaderText ? (
              <div className="clawpress-card-subtitle clawpress-card-onboarding-step-line">
                {stepHeaderText}
              </div>
            ) : null}
            {stepItems.length > 0 ? (
              <div className="clawpress-card-onboarding-progress" aria-hidden="true">
                {stepItems.map((item, index) => {
                  const status =
                    typeof item?.status === 'string' ? item.status : 'pending';

                  return (
                    <div
                      key={`${item?.id || index}`}
                      className={`clawpress-card-onboarding-progress-item clawpress-card-onboarding-progress-${status}`}
                    >
                      <span className="clawpress-card-onboarding-progress-dot">
                        {index + 1}
                      </span>
                      {index < stepItems.length - 1 ? (
                        <span className="clawpress-card-onboarding-progress-line" />
                      ) : null}
                    </div>
                  );
                })}
              </div>
            ) : null}
          </div>
        ) : null}
        <div className="clawpress-card-section-content">
          <div className="clawpress-card-text">{message}</div>
          {workspacePathValue || workspaceExistsValue ? (
            <div className="clawpress-card-onboarding-field-list">
              {workspacePathValue ? (
                <div className="clawpress-card-onboarding-field-row">
                  <span className="clawpress-card-onboarding-field-label">
                    {__('Path', 'clawpress')} :
                  </span>
				  <span className="clawpress-card-onboarding-field-value">
					{workspacePathValue}
				  </span>
                </div>
              ) : null}
              {workspaceExistsValue ? (
                <div className="clawpress-card-onboarding-field-row">
                  <span className="clawpress-card-onboarding-field-label">
                    {__('Exists', 'clawpress')} :
                  </span>
				  <span className="clawpress-card-onboarding-field-value">
					{workspaceExistsValue}
				  </span>
                </div>
              ) : null}
            </div>
          ) : null}
          {detail ? (
            <div className="clawpress-card-onboarding-detail">{detail}</div>
          ) : null}
          {onProviderSettingsPage && step === 'provider' ? (
            <div className="clawpress-card-onboarding-detail">
              {__(
                'Provider settings page detected. After saving credentials, click Refresh Providers here.',
                'clawpress'
              )}
            </div>
          ) : null}
          {error ? (
            <div className="clawpress-card-onboarding-error">{error}</div>
          ) : null}
        </div>

        {hasButtonsSection ? (
          <div className="clawpress-card-section-buttons clawpress-card-onboarding-buttons">
            {showProviderPicker || showModelPicker ? (
              <div className="clawpress-card-onboarding-inline-controls">
                {showProviderPicker ? (
                  <select
                    className="clawpress-card-onboarding-select"
                    value={selectedProviderId}
                    onChange={(event) => setSelectedProviderId(event.target.value)}
                    disabled={isBusy}
                  >
                    {providerChoiceActions.map((action) => (
                      <option key={action.id} value={action.id}>
                        {action.label}
                      </option>
                    ))}
                  </select>
                ) : null}
                {showModelPicker ? (
                  <select
                    className="clawpress-card-onboarding-select"
                    value={selectedModelId}
                    onChange={(event) => setSelectedModelId(event.target.value)}
                    disabled={isBusy}
                  >
                    {modelChoiceActions.map((action) => (
                      <option key={action.id} value={action.id}>
                        {action.label}
                      </option>
                    ))}
                  </select>
                ) : null}
                <button
                  type="button"
                  className="button button-primary button-small"
                  disabled={
                    isBusy ||
                    (step === 'provider' && !selectedProviderAction) ||
                    (step === 'model' && !selectedModelAction)
                  }
                  onClick={() =>
                    onSendAction?.(
                      step === 'provider'
                        ? selectedProviderAction?.prompt || ''
                        : selectedModelAction?.prompt || ''
                    )
                  }
                >
                  {step === 'provider'
                    ? __('Use Selected Provider', 'clawpress')
                    : __('Use Selected Model', 'clawpress')}
                </button>
              </div>
            ) : null}

            {showActionButtons ? (
              <div className="clawpress-card-actions">
                {showSingleProviderAction ? (
                  <button
                    type="button"
                    className="button button-primary button-small"
                    onClick={() => onSendAction?.(providerChoiceActions[0])}
                    disabled={isBusy}
                  >
                    {providerChoiceActions[0].label}
                  </button>
                ) : null}
                {showSingleModelAction ? (
                  <button
                    type="button"
                    className="button button-primary button-small"
                    onClick={() => onSendAction?.(modelChoiceActions[0])}
                    disabled={isBusy}
                  >
                    {modelChoiceActions[0].label}
                  </button>
                ) : null}
                {actionsWithBack.map((action) => (
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
        ) : null}
      </div>
    </div>
  );
};

export default OnboardingCard;

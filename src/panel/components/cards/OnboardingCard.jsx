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

const OnboardingCard = ({ card, onSendAction, isBusy = false }) => {
  const data =
    card?.data && typeof card.data === 'object' && !Array.isArray(card.data)
      ? card.data
      : {};
  const title =
    typeof data.title === 'string' && data.title.trim()
      ? data.title
      : __('Onboarding Wizard', 'clawpress');
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
  const steps = Array.isArray(data.steps) ? data.steps : [];

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

  const stepProgressText =
    stepIndex && stepTotal
      ? sprintf(
          /* translators: 1: current step number, 2: total step count. */
          __('Step %1$d of %2$d', 'clawpress'),
          stepIndex,
          stepTotal
        )
      : '';

  return (
    <div className="clawpress-card clawpress-card-onboarding">
      <div className="clawpress-card-body">
        <div className="clawpress-card-title">{title}</div>
        {stepProgressText ? (
          <div className="clawpress-card-step-progress">{stepProgressText}</div>
        ) : null}
        {stepLabel ? (
          <div className="clawpress-card-step-label">{stepLabel}</div>
        ) : null}
        <div className="clawpress-card-text">{message}</div>
        {detail ? <div className="clawpress-card-detail">{detail}</div> : null}
        {onProviderSettingsPage && step === 'provider' ? (
          <div className="clawpress-card-detail">
            {__(
              'Provider settings page detected. After saving credentials, click Refresh Providers here.',
              'clawpress'
            )}
          </div>
        ) : null}
        {error ? <div className="clawpress-card-error">{error}</div> : null}

        {steps.length > 0 ? (
          <div className="clawpress-card-steps" aria-label={__('Onboarding steps', 'clawpress')}>
            {steps.map((item, index) => {
              const status =
                typeof item?.status === 'string' ? item.status : 'pending';
              const label =
                typeof item?.label === 'string' && item.label.trim()
                  ? item.label
                  : String(item?.id || '');

              return (
                <span
                  key={`${item?.id || label}-${index}`}
                  className={`clawpress-card-step clawpress-card-step-${status}`}
                >
                  {label}
                </span>
              );
            })}
          </div>
        ) : null}

        {(step === 'provider' && providerChoiceActions.length > 1) ||
        (step === 'model' && modelChoiceActions.length > 1) ? (
          <div className="clawpress-card-inline-controls">
            {step === 'provider' && providerChoiceActions.length > 1 ? (
              <select
                className="clawpress-card-select"
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
            {step === 'model' && modelChoiceActions.length > 1 ? (
              <select
                className="clawpress-card-select"
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

        {((step === 'provider' && providerChoiceActions.length === 1) ||
          (step === 'model' && modelChoiceActions.length === 1) ||
          filteredActions.length > 0) && (
          <div className="clawpress-card-actions">
            {step === 'provider' && providerChoiceActions.length === 1 ? (
              <button
                type="button"
                className="button button-primary button-small"
                onClick={() => onSendAction?.(providerChoiceActions[0])}
                disabled={isBusy}
              >
                {providerChoiceActions[0].label}
              </button>
            ) : null}
            {step === 'model' && modelChoiceActions.length === 1 ? (
              <button
                type="button"
                className="button button-primary button-small"
                onClick={() => onSendAction?.(modelChoiceActions[0])}
                disabled={isBusy}
              >
                {modelChoiceActions[0].label}
              </button>
            ) : null}
            {filteredActions.map((action) => (
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
        )}
      </div>
    </div>
  );
};

export default OnboardingCard;

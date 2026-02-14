import { __, sprintf } from '@wordpress/i18n';

const requestJson = async ({ url, method = 'GET', nonce, body, signal }) => {
  const res = await fetch(url, {
    method,
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': nonce,
    },
    body: body ? JSON.stringify(body) : undefined,
    signal,
  });

  const text = await res.text();
  let payload = {};

  if (text) {
    try {
      payload = JSON.parse(text);
    } catch {
      payload = { message: text };
    }
  }

  if (!res.ok) {
    const message =
      payload?.message ||
      payload?.error ||
      sprintf(
        /* translators: %d: HTTP status code */
        __('Request failed (%d)', 'clawpress'),
        res.status
      );
    throw new Error(message);
  }

  return payload;
};

const createRealClient = ({ restBase, nonce, onEvent, onDone, onError }) => {
  const sendMessage = (message, signal) =>
    requestJson({
      url: `${restBase}/chat/message`,
      method: 'POST',
      nonce,
      body: { message },
      signal,
    });

  const getHistory = () =>
    requestJson({
      url: `${restBase}/chat/history`,
      method: 'GET',
      nonce,
    });

  const getStatus = () =>
    requestJson({
      url: `${restBase}/status`,
      method: 'GET',
      nonce,
    });

  const getPanelState = () =>
    requestJson({
      url: `${restBase}/panel/state`,
      method: 'GET',
      nonce,
    });

  const setPanelState = (state) =>
    requestJson({
      url: `${restBase}/panel/state`,
      method: 'POST',
      nonce,
      body: state,
    });

  // Keep a stream-compatible interface for the existing panel flow.
  const stream = (prompt) => {
    const controller = new AbortController();

    (async () => {
      try {
        const response = await sendMessage(prompt, controller.signal);
        const clearHistory =
          response?.meta?.command?.effects &&
          response.meta.command.effects.clear_history === true;

        if (clearHistory) {
          onEvent('history_reset', {});
        }

        if (Array.isArray(response?.meta?.suggestions)) {
          onEvent('suggestions', { items: response.meta.suggestions });
        }

        const reply =
          typeof response?.reply === 'string' ? response.reply.trim() : '';

        const isCommandResponse = Boolean(response?.meta?.command?.name);

        if (reply) {
          onEvent('response_message', {
            text: reply,
            role: isCommandResponse ? 'system' : 'assistant',
          });
        }

        onDone?.({ aborted: false });
      } catch (err) {
        if (err?.name === 'AbortError') {
          onDone?.({ aborted: true });
          return;
        }
        onError?.({ error: err?.message || __('Chat request failed.', 'clawpress') });
        onDone?.({ aborted: false });
      }
    })();

    return { stop: () => controller.abort() };
  };

  const runTool = async () => {
    throw new Error(__('Tool execution is not available in chat mode.', 'clawpress'));
  };

  return { stream, runTool, getHistory, getStatus, getPanelState, setPanelState };
};

export default createRealClient;

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

  const normalizeCard = (rawCard) => {
    if (!rawCard || typeof rawCard !== 'object') {
      return null;
    }

    const type = typeof rawCard.type === 'string' ? rawCard.type.trim() : '';
    if (!type) {
      return null;
    }

    const data =
      rawCard.data && typeof rawCard.data === 'object' && !Array.isArray(rawCard.data)
        ? rawCard.data
        : {};

    return { type, data };
  };

  const normalizeContextUsage = (rawContext) => {
    if (!rawContext || typeof rawContext !== 'object') {
      return null;
    }

    const toPositiveNumber = (value) => {
      const numeric = Number(value);
      if (!Number.isFinite(numeric) || numeric < 0) {
        return null;
      }
      return Math.round(numeric);
    };

    const toNullablePercent = (value) => {
      if (value === null || value === undefined) {
        return null;
      }
      const numeric = Number(value);
      if (!Number.isFinite(numeric)) {
        return null;
      }
      return Math.max(0, Math.min(100, Math.round(numeric)));
    };

    const promptTokens = toPositiveNumber(rawContext.prompt_tokens) ?? 0;
    const completionTokens = toPositiveNumber(rawContext.completion_tokens) ?? 0;
    const totalTokens = toPositiveNumber(rawContext.total_tokens) ?? 0;
    const usedTokens =
      toPositiveNumber(rawContext.used_tokens) ??
      (promptTokens > 0 ? promptTokens : totalTokens);
    const contextWindowTokens =
      toPositiveNumber(rawContext.context_window_tokens) ?? null;
    const percentUsed = toNullablePercent(rawContext.percent_used);
    const percentLeft = toNullablePercent(rawContext.percent_left);
    const windowIsEstimated =
      typeof rawContext.window_is_estimated === 'boolean'
        ? rawContext.window_is_estimated
        : null;

    if (
      promptTokens === 0 &&
      completionTokens === 0 &&
      totalTokens === 0 &&
      usedTokens === 0 &&
      contextWindowTokens === null
    ) {
      return null;
    }

    return {
      promptTokens,
      completionTokens,
      totalTokens,
      usedTokens,
      contextWindowTokens,
      percentUsed,
      percentLeft,
      windowIsEstimated,
    };
  };

  const normalizeToolCall = (rawCall) => {
    if (!rawCall || typeof rawCall !== 'object') {
      return null;
    }

    const normalizeStatus = (value) => {
      const normalized = typeof value === 'string' ? value.trim().toLowerCase() : '';
      if (
        normalized === 'success' ||
        normalized === 'error' ||
        normalized === 'requires_confirmation'
      ) {
        return normalized;
      }
      return 'success';
    };

    const name = typeof rawCall.name === 'string' ? rawCall.name.trim() : '';
    if (!name) {
      return null;
    }

    const ability = typeof rawCall.ability === 'string' ? rawCall.ability.trim() : '';
    const args =
      rawCall.args && typeof rawCall.args === 'object' && !Array.isArray(rawCall.args)
        ? rawCall.args
        : {};
    const status = normalizeStatus(rawCall.status);
    const message =
      typeof rawCall.message === 'string' && rawCall.message.trim()
        ? rawCall.message.trim()
        : '';
    const round = Number.isFinite(Number(rawCall.round))
      ? Math.max(1, Math.round(Number(rawCall.round)))
      : 1;
    const sequence = Number.isFinite(Number(rawCall.sequence))
      ? Math.max(1, Math.round(Number(rawCall.sequence)))
      : 1;
    const requiresConfirmation =
      typeof rawCall.requires_confirmation === 'boolean'
        ? rawCall.requires_confirmation
        : status === 'requires_confirmation';

    return {
      name,
      ability: ability || null,
      args,
      status,
      message: message || null,
      round,
      sequence,
      requiresConfirmation,
    };
  };

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

        const contextUsage = normalizeContextUsage(response?.meta?.context);
        if (contextUsage) {
          onEvent('context_usage', { context: contextUsage });
        }

        const toolCalls = Array.isArray(response?.meta?.tool_calls)
          ? response.meta.tool_calls
              .map((rawCall) => normalizeToolCall(rawCall))
              .filter(Boolean)
          : [];

        toolCalls.forEach((call, index) => {
          onEvent('tool_call', {
            call,
            index: index + 1,
            total: toolCalls.length,
          });
        });

        const responseError =
          response?.meta?.error && typeof response.meta.error === 'object'
            ? response.meta.error
            : null;
        const responseCard =
          response?.meta?.card && typeof response.meta.card === 'object'
            ? normalizeCard(response.meta.card)
            : null;

        if (responseError) {
          const errorMessage =
            typeof responseError.message === 'string' && responseError.message.trim()
              ? responseError.message.trim()
              : __('Chat request failed.', 'clawpress');
          onEvent('error', {
            error: errorMessage,
            type:
              typeof responseError.type === 'string' && responseError.type.trim()
                ? responseError.type.trim()
                : 'provider',
            card: responseCard,
          });
          onDone?.({ aborted: false });
          return;
        }

        const reply =
          typeof response?.reply === 'string' ? response.reply.trim() : '';

        const isCommandResponse = Boolean(response?.meta?.command?.name);
        const card = normalizeCard(response?.meta?.card);

        if (card) {
          onEvent('response_card', {
            card,
            text: reply,
            role: isCommandResponse ? 'system' : 'assistant',
          });
        } else if (reply) {
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
        onError?.({
          error: err?.message || __('Chat request failed.', 'clawpress'),
          type: 'request',
        });
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

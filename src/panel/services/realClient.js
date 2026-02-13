import apiFetch from '@wordpress/api-fetch';

const parseSSE = async (res, onEvent) => {
  const reader = res.body.getReader();
  const decoder = new TextDecoder();
  let buffer = '';
  let done = false;

  while (!done) {
    const { value, done: readerDone } = await reader.read();
    done = readerDone;
    buffer += decoder.decode(value || new Uint8Array(), { stream: !done });

    const parts = buffer.split('\n\n');
    buffer = parts.pop();

    for (const part of parts) {
      const lines = part.split('\n');
      let eventType = 'message';
      let data = '';

      for (const line of lines) {
        if (line.startsWith('event:')) {
          eventType = line.slice(6).trim();
        } else if (line.startsWith('data:')) {
          data += line.slice(5).trim();
        }
      }

      if (!data) continue;

      try {
        const parsed = JSON.parse(data);
        onEvent(eventType, parsed);
      } catch (err) {
        console.warn('JSON parse error in stream:', err, data);
      }
    }
  }
};

const createRealClient = ({ restBase, streamNonce, nonce, onEvent, onDone, onError }) => {
  const stream = (prompt) => {
    const qs = new URLSearchParams({
      prompt,
      _wpnonce: streamNonce,
    });

    const url = `${restBase}/stream?${qs.toString()}`;
    const controller = new AbortController();

    (async () => {
      try {
        const res = await fetch(url, {
          headers: {
            Accept: 'text/event-stream',
          },
          credentials: 'same-origin',
          signal: controller.signal,
        });

        if (!res.ok) {
          const text = await res.text();
          onEvent('error', { error: `Stream error: ${res.status} ${text}` });
          onDone?.({ aborted: false });
          return;
        }

        await parseSSE(res, onEvent);
        onDone?.({ aborted: false });
      } catch (err) {
        if (err?.name === 'AbortError') {
          onDone?.({ aborted: true });
          return;
        }
        console.error('Stream fetch error:', err);
        onError?.({ error: 'Stream error' });
        onDone?.({ aborted: false });
      }
    })();

    return { stop: () => controller.abort() };
  };

  const runTool = (tool, args) =>
    apiFetch({
      path: '/clawpress/v1/run-tool',
      method: 'POST',
      data: { tool, arguments: args },
      headers: { 'X-WP-Nonce': nonce },
    });

  return { stream, runTool };
};

export default createRealClient;

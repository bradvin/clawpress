const tokenize = (text) => text.split('');

export const startMockStream = ({
  prompt,
  mode = 'normal',
  delayMode = 'normal',
  onEvent,
  onDone,
  onError,
}) => {
  const timers = [];
  let stopped = false;
  const startDelay = delayMode === 'slow' ? 3000 : 0;

  const schedule = (fn, delay) => {
    const id = setTimeout(() => {
      if (!stopped) fn();
    }, delay + startDelay);
    timers.push(id);
  };

  const emit = (type, payload) => {
    if (stopped) return;
    onEvent({ type, payload });
  };

  const stop = () => {
    stopped = true;
    timers.forEach(clearTimeout);
    onDone?.({ aborted: true });
  };

  if (delayMode === 'infinite') {
    return { stop };
  }

  if (mode === 'error') {
    schedule(() => onError?.({ error: 'Mock error: something went wrong.' }), 300);
    schedule(() => onDone?.({ aborted: false }), 600);
    return { stop };
  }

  if (mode === 'tool' || mode === 'tool_error') {
    schedule(
      () =>
        emit('tool_call', {
          function: { name: 'update_posts_find_replace', arguments: '{}' },
        }),
      200
    );

    schedule(
      () =>
        emit('tool_plan', {
          function: {
            name: 'update_posts_find_replace',
            arguments: {
              search: mode === 'tool_error' ? 'ERROR: Old Phrase' : 'Old Phrase',
              replace: mode === 'tool_error' ? 'ERROR: New Phrase' : 'New Phrase',
              post_status: mode === 'tool_error' ? 'draft' : 'publish',
              dry_run: true,
            },
          },
        }),
      700
    );
  }

  const longText =
    'Here is a longer mock response to help you test streaming behavior. ' +
    'It should keep streaming long enough for you to press Stop and see the UI react. ' +
    'We can include multiple sentences, line breaks, and a bit of variety.\n\n' +
    'Chunk one: The quick brown fox jumps over the lazy dog. ' +
    'Chunk two: Sphinx of black quartz, judge my vow. ' +
    'Chunk three: Pack my box with five dozen liquor jugs.\n\n' +
    'Final chunk: This should be enough to test canceling a long stream.';

  const responseText =
    mode === 'tool' || mode === 'tool_error'
      ? 'I found a tool that can update posts. Here is the proposed change.'
      : mode === 'long'
        ? longText
        : `Here is a mock response for: "${prompt}"`;

  const tokens = tokenize(responseText);
  let time = 150;
  tokens.forEach((char) => {
    schedule(() => emit('delta', { text: char }), time);
    time += 12;
  });

  schedule(() => onDone?.({ aborted: false }), time + 200);

  return { stop };
};

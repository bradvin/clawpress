import { __, sprintf } from '@wordpress/i18n';

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
    schedule(
      () => onError?.({ error: __('Mock error: something went wrong.', 'clawpress') }),
      300
    );
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
              search: mode === 'tool_error'
                ? sprintf(
                    /* translators: %s: mock text prefixed with ERROR to trigger an error path */
                    __('ERROR: %s', 'clawpress'),
                    __('Old Phrase', 'clawpress')
                  )
                : __('Old Phrase', 'clawpress'),
              replace: mode === 'tool_error'
                ? sprintf(
                    /* translators: %s: mock text prefixed with ERROR to trigger an error path */
                    __('ERROR: %s', 'clawpress'),
                    __('New Phrase', 'clawpress')
                  )
                : __('New Phrase', 'clawpress'),
              post_status: mode === 'tool_error' ? 'draft' : 'publish',
              dry_run: true,
            },
          },
        }),
      700
    );
  }

  const longText =
    __('Here is a longer mock response to help you test streaming behavior. ', 'clawpress') +
    __('It should keep streaming long enough for you to press Stop and see the UI react. ', 'clawpress') +
    __('We can include multiple sentences, line breaks, and a bit of variety.\n\n', 'clawpress') +
    __('Chunk one: The quick brown fox jumps over the lazy dog. ', 'clawpress') +
    __('Chunk two: Sphinx of black quartz, judge my vow. ', 'clawpress') +
    __('Chunk three: Pack my box with five dozen liquor jugs.\n\n', 'clawpress') +
    __('Final chunk: This should be enough to test canceling a long stream.', 'clawpress');

  const responseText =
    mode === 'tool' || mode === 'tool_error'
      ? __('I found a tool that can update posts. Here is the proposed change.', 'clawpress')
      : mode === 'long'
        ? longText
        : sprintf(
            /* translators: %s: user prompt */
            __('Here is a mock response for: "%s"', 'clawpress'),
            prompt
          );

  const tokens = tokenize(responseText);
  let time = 150;
  tokens.forEach((char) => {
    schedule(() => emit('delta', { text: char }), time);
    time += 12;
  });

  schedule(() => onDone?.({ aborted: false }), time + 200);

  return { stop };
};

import { startMockStream } from '../mocks/mockStream';
import { runMockTool } from '../mocks/mockTools';

const createMockClient = ({ mockScenario, mockDelay, onEvent, onDone, onError }) => ({
  stream: (prompt) =>
    startMockStream({
      prompt,
      mode: mockScenario,
      delayMode: mockDelay,
      onEvent: ({ type, payload }) => onEvent(type, payload),
      onDone: () => onDone?.({ aborted: false }),
      onError,
    }),
  runTool: (tool, args) => runMockTool(tool, args, { mockDelay }),
});

export default createMockClient;

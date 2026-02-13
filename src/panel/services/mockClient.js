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
  getHistory: async () => ({ items: [] }),
  getStatus: async () => ({
    mode: 'offline',
    provider: { id: null, configured: false },
    model: { id: null, configured: false },
    onboarding: { completed: false },
    memory: { enabled: false },
    execution_user: { id: null, configured: false },
  }),
  getPanelState: async () => ({
    open: false,
    width: 420,
    last_history_id: '',
  }),
  setPanelState: async (state) => ({
    open: Boolean(state?.open),
    width: Number(state?.width) || 420,
    last_history_id: typeof state?.last_history_id === 'string' ? state.last_history_id : '',
  }),
});

export default createMockClient;

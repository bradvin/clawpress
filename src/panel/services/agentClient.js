import createMockClient from './mockClient';
import createRealClient from './realClient';

const createAgentClient = ({
  mockEnabled,
  mockScenario,
  mockDelay,
  restBase,
  streamNonce,
  nonce,
  onEvent,
  onDone,
  onError,
}) => {
  if (mockEnabled) {
    return createMockClient({ mockScenario, mockDelay, onEvent, onDone, onError });
  }

  return createRealClient({ restBase, streamNonce, nonce, onEvent, onDone, onError });
};

export default createAgentClient;

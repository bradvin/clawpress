export const runMockTool = async (tool, args, { mockDelay = 'normal' } = {}) => {
  if (mockDelay === 'infinite') {
    await new Promise(() => {});
  } else {
    const baseDelay = mockDelay === 'slow' ? 3000 : 300;
    await new Promise((r) => setTimeout(r, baseDelay));
  }

  const searchText = typeof args?.search === 'string' ? args.search : '';
  if (searchText.includes('ERROR')) {
    throw new Error('Mock tool error: execution failed.');
  }

  return {
    step: {
      data: {
        result: {
          dry_run: true,
          total: 2,
          changed: [
            { id: 123, title: 'Hello World', count: 1 },
            { id: 456, title: 'Sample Post', count: 2 },
          ],
          tool,
          args,
        },
      },
    },
  };
};

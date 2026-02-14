export const normalizeCardActions = (card) => {
  if (!Array.isArray(card?.data?.actions)) {
    return [];
  }

  return card.data.actions
    .filter((action) => action && typeof action === 'object')
    .map((action, index) => {
      const label = typeof action.label === 'string' ? action.label.trim() : '';
      const type =
        typeof action.type === 'string' && action.type.trim()
          ? action.type.trim().toLowerCase()
          : 'send_prompt';

      if (!label) {
        return null;
      }

      if ('run_tool' === type) {
        const toolCandidates = [action.tool, action.tool_name, action.name];
        const tool = toolCandidates
          .map((item) => (typeof item === 'string' ? item.trim() : ''))
          .find((item) => item.length > 0);

        if (!tool) {
          return null;
        }

        let args = {};
        if (action.args && typeof action.args === 'object' && !Array.isArray(action.args)) {
          args = action.args;
        } else if (typeof action.arguments === 'string') {
          try {
            const parsedArgs = JSON.parse(action.arguments);
            if (parsedArgs && typeof parsedArgs === 'object' && !Array.isArray(parsedArgs)) {
              args = parsedArgs;
            }
          } catch {
            args = {};
          }
        } else if (
          action.arguments &&
          typeof action.arguments === 'object' &&
          !Array.isArray(action.arguments)
        ) {
          args = action.arguments;
        }

        return {
          id:
            typeof action.id === 'string' && action.id.trim()
              ? action.id.trim()
              : `action-${index}`,
          label,
          type: 'run_tool',
          tool,
          args,
        };
      }

      const promptCandidates = [action.prompt, action.message, action.command];
      const prompt = promptCandidates
        .map((item) => (typeof item === 'string' ? item.trim() : ''))
        .find((item) => item.length > 0);

      if (!prompt) {
        return null;
      }

      return {
        id: typeof action.id === 'string' && action.id.trim() ? action.id.trim() : `action-${index}`,
        label,
        type: 'send_prompt',
        prompt,
      };
    })
    .filter((action) => Boolean(action));
};

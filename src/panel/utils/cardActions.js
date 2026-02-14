export const normalizeCardActions = (card) => {
  if (!Array.isArray(card?.data?.actions)) {
    return [];
  }

  return card.data.actions
    .filter((action) => action && typeof action === 'object')
    .map((action, index) => {
      const label = typeof action.label === 'string' ? action.label.trim() : '';
      const promptCandidates = [action.prompt, action.message, action.command];
      const prompt = promptCandidates
        .map((item) => (typeof item === 'string' ? item.trim() : ''))
        .find((item) => item.length > 0);

      if (!label || !prompt) {
        return null;
      }

      return {
        id: typeof action.id === 'string' && action.id.trim() ? action.id.trim() : `action-${index}`,
        label,
        prompt,
      };
    })
    .filter((action) => Boolean(action));
};

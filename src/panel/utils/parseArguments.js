export const getParsedArguments = (fn) => {
  if (!fn || typeof fn.arguments === 'undefined' || fn.arguments === null) return {};
  if (typeof fn.arguments === 'string') {
    if (fn.arguments.trim() === '') return {};
    try {
      return JSON.parse(fn.arguments);
    } catch {
      return { raw: fn.arguments };
    }
  }
  return fn.arguments;
};

import { useRef } from '@wordpress/element';

const PanelInput = ({
  input,
  onInputChange,
  onSend,
  onStop,
  streaming,
  onHistoryUp,
  onHistoryDown,
}) => {
  const textareaRef = useRef(null);

  const handleKeyDown = (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      onSend();
    }

    if (e.key === 'ArrowUp') {
      const el = textareaRef.current;
      const cursor = el?.selectionStart ?? 0;
      const before = input.slice(0, cursor);
      const isFirstLine = !before.includes('\n');
      const isEmpty = input.trim() === '';

      if ((isEmpty || isFirstLine) && onHistoryUp) {
        e.preventDefault();
        const value = onHistoryUp(input);
        if (value === null || value === undefined) return;
        onInputChange({ target: { value } });
        setTimeout(() => {
          if (!textareaRef.current) return;
          const end = value.length;
          textareaRef.current.setSelectionRange(end, end);
        }, 0);
      }
    }

    if (e.key === 'ArrowDown') {
      const el = textareaRef.current;
      const cursor = el?.selectionStart ?? 0;
      const after = input.slice(cursor);
      const isLastLine = !after.includes('\n');
      const isEmpty = input.trim() === '';

      if ((isEmpty || isLastLine) && onHistoryDown) {
        e.preventDefault();
        const value = onHistoryDown();
        if (value === null || value === undefined) return;
        onInputChange({ target: { value } });
        setTimeout(() => {
          if (!textareaRef.current) return;
          const end = value.length;
          textareaRef.current.setSelectionRange(end, end);
        }, 0);
      }
    }
  };

  return (
    <div className="clawpress-input">
      <textarea
        ref={textareaRef}
        value={input}
        onChange={onInputChange}
        onKeyDown={handleKeyDown}
        placeholder="Ask me anything…"
        disabled={streaming}
      />
      {streaming ? (
        <button className="button" onClick={onStop} type="button">
          Stop
        </button>
      ) : (
        <button className="button button-primary" onClick={onSend} type="button">
          Send
        </button>
      )}
    </div>
  );
};

export default PanelInput;

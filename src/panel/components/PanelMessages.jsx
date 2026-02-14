import { useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import ToolDialog from './ToolDialog';
import PanelCard from './PanelCard';
import { getToolPolicy } from '../utils/toolDialogRenderers';
const PanelMessages = ({
  messages,
  streaming,
  currentStreamText,
  waitingForResponse,
  toolDialogs,
  onRunToolDialog,
  onCancelToolDialog,
  onSendCardAction,
}) => {
  const containerRef = useRef(null);

  useEffect(() => {
    const container = containerRef.current;
    if (!container) return;
    container.scrollTop = container.scrollHeight;
  }, [messages, toolDialogs, streaming, currentStreamText, waitingForResponse]);

  const serialCandidates = toolDialogs
    .filter(
      (dialog) =>
        getToolPolicy(dialog.function?.name || '').concurrency === 'serial' &&
        dialog.status !== 'done' &&
        dialog.status !== 'cancelled'
    )
    .sort((a, b) => a.createdAt - b.createdAt);

  const openId =
    serialCandidates[0]?.id || toolDialogs[toolDialogs.length - 1]?.id || null;

  const latestMessageIndex = messages.length - 1;

  const items = [
    ...messages.map((message, index) => ({
      type: 'message',
      createdAt: message.createdAt ?? 0,
      messageIndex: index,
      data: message,
    })),
    ...toolDialogs.map((dialog) => ({
      type: 'tool',
      createdAt: dialog.createdAt ?? 0,
      data: dialog,
    })),
  ].sort((a, b) => a.createdAt - b.createdAt);

  return (
    <div className="clawpress-messages" ref={containerRef}>
      {items.map((item) =>
        item.type === 'message' ? (() => {
          const isSystem = item.data.role === 'system';
          const isAssistant = item.data.role === 'assistant';
          const hasCard = item.data.card && typeof item.data.card === 'object';
          const isLatestMessageCard =
            hasCard && item.messageIndex === latestMessageIndex;
          const content = item.data.content || '';
          const hasEllipsis = /(\.\.\.|…)\s*$/.test(content);
          const showThinking = isSystem && hasEllipsis;
          const displayContent = showThinking
            ? content.replace(/\s*(\.\.\.|…)\s*$/, '')
            : content;

          return (
            <div
              key={item.data.id || item.data.content}
              className={`clawpress-msg clawpress-${item.data.role}`}
            >
              {isSystem || isAssistant ? (
                <div className="clawpress-msg-label">
                  {isSystem ? __('System', 'clawpress') : __('AGENT', 'clawpress')}
                </div>
              ) : null}
              {hasCard ? (
                <PanelCard
                  card={item.data.card}
                  fallbackText={displayContent}
                  onSendAction={onSendCardAction}
                  isBusy={
                    streaming ||
                    waitingForResponse ||
                    !isLatestMessageCard
                  }
                />
              ) : (
                <div
                  className={`clawpress-msg-content${showThinking ? ' clawpress-thinking' : ''}`}
                >
                  {displayContent}
                </div>
              )}
            </div>
          );
        })() : (
          <ToolDialog
            key={item.data.id}
            toolDialog={item.data}
            isOpen={item.data.id === openId}
            onRunTool={onRunToolDialog}
            onCancel={onCancelToolDialog}
          />
        )
      )}
      {waitingForResponse && !currentStreamText ? (
        <div className="clawpress-msg clawpress-system">
          <div className="clawpress-msg-content clawpress-thinking">
            {__('Thinking', 'clawpress')}
          </div>
        </div>
      ) : null}
      {streaming && currentStreamText ? (
        <div className="clawpress-msg clawpress-assistant">
          <div className="clawpress-msg-label">{__('AGENT', 'clawpress')}</div>
          <div className="clawpress-msg-content">{currentStreamText || '...'}</div>
        </div>
      ) : null}
    </div>
  );
};

export default PanelMessages;

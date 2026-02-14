import { Fragment, useEffect, useRef, useState } from '@wordpress/element';
import PanelHeader from './components/PanelHeader';
import PanelInput from './components/PanelInput';
import PanelMessages from './components/PanelMessages';
import PanelToggle from './components/PanelToggle';
import { getParsedArguments } from './utils/parseArguments';
import createAgentClient from './services/agentClient';
import MockPanel from './components/MockPanel';
import ensureSyntaxHighlight from './syntaxHighlight';
import { getToolPolicy } from './utils/toolDialogRenderers';

const Panel = () => {
  const [open, setOpen] = useState(JSON.parse(localStorage.getItem('clawpress_open') || 'false'));
  const [width, setWidth] = useState(Number(localStorage.getItem('clawpress_width') || CLAWPRESS_PANEL.defaultWidth));
  const [messages, setMessages] = useState([]);
  const [input, setInput] = useState('');
  const [inputHistory, setInputHistory] = useState(() => {
    const raw = localStorage.getItem('clawpress_input_history');
    if (!raw) return [];
    try {
      const parsed = JSON.parse(raw);
      return Array.isArray(parsed) ? parsed.filter((item) => typeof item === 'string') : [];
    } catch {
      return [];
    }
  });
  const [historyIndex, setHistoryIndex] = useState(-1);
  const [historyDraft, setHistoryDraft] = useState('');
  const [streaming, setStreaming] = useState(false);
  const [currentStreamText, setCurrentStreamText] = useState('');
  const [waitingForResponse, setWaitingForResponse] = useState(false);
  const [toolPlan, setToolPlan] = useState(null);
  const [toolDialogs, setToolDialogs] = useState([]);
  const [toolPlanningShown, setToolPlanningShown] = useState(false);
  const [statusSnapshot, setStatusSnapshot] = useState(null);
  const [statusLoading, setStatusLoading] = useState(true);
  const [suggestions, setSuggestions] = useState([]);
  const [panelStateReady, setPanelStateReady] = useState(false);
  const mockEnabled = Boolean(CLAWPRESS_PANEL.mockEnabled);
  const [themeMode, setThemeMode] = useState(
    localStorage.getItem('clawpress_theme') || 'light'
  );
  const [mockScenario, setMockScenario] = useState('normal');
  const [mockDelay, setMockDelay] = useState('normal');
  const [showFloatingToggle, setShowFloatingToggle] = useState(false);
  const streamHandleRef = useRef(null);
  const timelineRef = useRef(0);
  const eventQueueRef = useRef([]);
  const isTypingRef = useRef(false);
  const typingTimerRef = useRef(null);
  const panelStateSyncTimerRef = useRef(null);

  const currentStreamTextRef = useRef(currentStreamText);
  const toolPlanRef = useRef(toolPlan);

  useEffect(() => {
    currentStreamTextRef.current = currentStreamText;
  }, [currentStreamText]);

  useEffect(() => {
    ensureSyntaxHighlight();
  }, []);

  useEffect(() => {
    toolPlanRef.current = toolPlan;
  }, [toolPlan]);

  const isDragging = useRef(false);
  const startX = useRef(0);
  const startW = useRef(width);

  const appendMessage = (role, content) =>
    setMessages((prev) => [
      ...prev,
      {
        id: `msg-${Date.now()}-${Math.random()}`,
        role,
        content,
        createdAt: ++timelineRef.current,
      },
    ]);

  const normalizeHistoryItems = (items) => {
    if (!Array.isArray(items)) {
      return [];
    }

    return items
      .filter((item) => item && typeof item === 'object')
      .map((item, index) => {
        const role =
          item.role === 'user' || item.role === 'assistant' || item.role === 'system'
            ? item.role
            : 'system';
        const content = typeof item.content === 'string' ? item.content : '';
        const createdAt = Number.isFinite(Number(item.createdAt))
          ? Number(item.createdAt)
          : index + 1;
        const id =
          typeof item.id === 'string' && item.id
            ? item.id
            : `history-${createdAt}-${index}`;

        return { id, role, content, createdAt };
      });
  };

  const normalizePanelState = (state) => {
    if (!state || typeof state !== 'object') {
      return {
        open: null,
        width: null,
        lastHistoryId: '',
      };
    }

    const nextOpen = typeof state.open === 'boolean' ? state.open : null;
    const nextWidth = Number.isFinite(Number(state.width)) ? Number(state.width) : null;
    const lastHistoryId =
      typeof state.last_history_id === 'string' ? state.last_history_id : '';

    return {
      open: nextOpen,
      width: nextWidth,
      lastHistoryId,
    };
  };

  const normalizeSuggestions = (items) => {
    if (!Array.isArray(items)) {
      return [];
    }

    const seen = new Set();

    return items
      .map((item) => (typeof item === 'string' ? item.trim() : ''))
      .filter((item) => item.length > 0)
      .filter((item) => {
        if (seen.has(item)) return false;
        seen.add(item);
        return true;
      })
      .slice(0, 8);
  };

  const buildStatusLabel = (status) => {
    if (!status || typeof status !== 'object') {
      return '';
    }

    const providerId = status?.provider?.id;
    const modelId = status?.model?.id;
    if (providerId && modelId) {
      return `${providerId} · ${modelId}`;
    }
    if (providerId) {
      return providerId;
    }
    return '';
  };

  const ephemeralStatusIdRef = useRef(null);
  const setEphemeralStatus = (content) => {
    const id = `status-${Date.now()}-${Math.random()}`;
    ephemeralStatusIdRef.current = id;
    setMessages((prev) => [
      ...prev,
      { id, role: 'system', content, createdAt: ++timelineRef.current },
    ]);
  };
  const clearEphemeralStatus = () => {
    const id = ephemeralStatusIdRef.current;
    if (!id) return;
    setMessages((prev) => prev.filter((m) => m.id !== id));
    ephemeralStatusIdRef.current = null;
  };

  const syncAdminBarState = () => {
    const link = document.querySelector('#wp-admin-bar-clawpress-toggle > a');
    if (!link) return;
    link.setAttribute('aria-expanded', open ? 'true' : 'false');
  };

  useEffect(() => localStorage.setItem('clawpress_open', JSON.stringify(open)), [open]);
  useEffect(() => localStorage.setItem('clawpress_width', String(width)), [width]);
  useEffect(() => {
    document.body.classList.toggle('clawpress-panel-open', open);
    document.body.style.setProperty('--clawpress-panel-width', `${width}px`);
    syncAdminBarState();
  }, [open, width]);
  useEffect(() => {
    localStorage.setItem('clawpress_theme', themeMode);
  }, [themeMode]);
  useEffect(() => {
    localStorage.setItem('clawpress_input_history', JSON.stringify(inputHistory));
  }, [inputHistory]);

  useEffect(() => {
    const kb = (e) => {
      const cmd = e.metaKey || e.ctrlKey;
      if (!cmd || e.altKey || e.key.toLowerCase() !== 'k') return;

      const activeTag = document.activeElement?.tagName?.toLowerCase();
      if (
        activeTag === 'input' ||
        activeTag === 'textarea' ||
        activeTag === 'select' ||
        document.activeElement?.isContentEditable
      ) {
        return;
      }

      e.preventDefault();
      setOpen((o) => !o);
    };
    window.addEventListener('keydown', kb);
    return () => window.removeEventListener('keydown', kb);
  }, []);

  useEffect(() => {
    const onMove = (e) => {
      if (!isDragging.current) return;
      const delta = startX.current - e.clientX;
      const next = Math.max(320, startW.current + delta);
      setWidth(next);
    };
    const onUp = () => {
      if (!isDragging.current) return;
      isDragging.current = false;
      document.body.classList.remove('clawpress-resizing');
    };
    window.addEventListener('mousemove', onMove);
    window.addEventListener('mouseup', onUp);
    return () => {
      window.removeEventListener('mousemove', onMove);
      window.removeEventListener('mouseup', onUp);
    };
  }, []);

  const startDrag = (e) => {
    isDragging.current = true;
    startX.current = e.clientX;
    startW.current = width;
    document.body.classList.add('clawpress-resizing');
  };

  const sendPrompt = (overridePrompt = null) => {
    const prompt =
      typeof overridePrompt === 'string' ? overridePrompt.trim() : input.trim();

    if (!prompt) return;
    setInput('');
    setInputHistory((prev) => {
      const next = [...prev, prompt];
      const limit = CLAWPRESS_PANEL.historyLimit ?? 20;
      return next.length > limit ? next.slice(next.length - limit) : next;
    });
    setHistoryIndex(-1);
    setHistoryDraft('');
    appendMessage('user', prompt);
    setWaitingForResponse(true);
    streamPrompt(prompt);
  };

  const finishStream = () => {
    setStreaming(false);
    streamHandleRef.current = null;
    setWaitingForResponse(false);
    clearEphemeralStatus();
    const text = currentStreamTextRef.current;
    if (text && text.trim()) {
      appendMessage('assistant', text);
      setCurrentStreamText('');
      currentStreamTextRef.current = '';
    } else {
      setCurrentStreamText('');
      currentStreamTextRef.current = '';
    }
    const plan = toolPlanRef.current;
    if (plan) {
      setToolDialogs((prev) => {
        const toolName = plan.function?.name || '';
        const concurrency = getToolConcurrency(toolName);
        const hasEarlierActive =
          concurrency === 'serial' &&
          prev.some(
            (dialog) =>
              dialog.function?.name === toolName &&
              dialog.status !== 'done' &&
              dialog.status !== 'error'
          );

        return [
          ...prev,
          {
            id: `tool-${Date.now()}-${Math.random()}`,
            function: plan.function,
            args: getParsedArguments(plan.function),
            status: hasEarlierActive ? 'blocked' : 'idle',
            result: null,
            error: null,
            diff: null,
            createdAt: ++timelineRef.current,
          },
        ];
      });
    }

    requestStatus(true);
  };

  const processStreamEvent = (eventType, parsed) => {
    clearEphemeralStatus();
    switch (eventType) {
      case 'delta':
        if (parsed.text) {
          const next = `${currentStreamTextRef.current || ''}${parsed.text}`;
          currentStreamTextRef.current = next;
          setCurrentStreamText(next);
        }
        break;
      case 'response_message':
        if (parsed?.text) {
          appendMessage(
            parsed?.role === 'system' ? 'system' : 'assistant',
            parsed.text
          );
        }
        break;
      case 'history_reset':
        setMessages([]);
        setToolDialogs([]);
        timelineRef.current = 0;
        break;
      case 'suggestions': {
        const nextSuggestions = normalizeSuggestions(parsed?.items);
        setSuggestions(nextSuggestions);
        break;
      }
      case 'tool_call':
        if (!toolPlanningShown) {
          setEphemeralStatus('Preparing tool plan...');
          setToolPlanningShown(true);
        }
        break;
      case 'tool_plan':
        setToolPlan(parsed);
        setToolPlanningShown(false);
        break;
      case 'error':
        appendMessage('system', parsed.error || 'Stream error.');
        break;
      case 'done':
        finishStream();
        break;
    }
  };

  const startTypingMessage = (content) => {
    if (!content) return;
    isTypingRef.current = true;
    setCurrentStreamText('');
    currentStreamTextRef.current = '';
    let index = 0;
    const step = () => {
      if (index >= content.length) {
        appendMessage('assistant', content);
        setCurrentStreamText('');
        currentStreamTextRef.current = '';
        isTypingRef.current = false;
        typingTimerRef.current = null;
        processEventQueue();
        return;
      }
      const nextChunk = content.slice(index, index + 2);
      index += 2;
      setCurrentStreamText((prev) => {
        const next = prev + nextChunk;
        currentStreamTextRef.current = next;
        return next;
      });
      typingTimerRef.current = setTimeout(step, 30);
    };
    step();
  };

  const processEventQueue = () => {
    if (isTypingRef.current) return;
    const queue = eventQueueRef.current;
    if (!queue.length) return;

    const { type, payload } = queue.shift();
    if (type === 'assistant_message' && payload?.content) {
      startTypingMessage(payload.content);
      return;
    }

    processStreamEvent(type, payload);
    processEventQueue();
  };

  const handleStreamEvent = (eventType, parsed) => {
    setWaitingForResponse(false);
    clearEphemeralStatus();
    eventQueueRef.current.push({ type: eventType, payload: parsed });
    processEventQueue();
  };

  const buildClient = () =>
    createAgentClient({
      mockEnabled,
      mockScenario,
      mockDelay,
      restBase: CLAWPRESS_PANEL.restBase,
      streamNonce: CLAWPRESS_PANEL.streamNonce,
      nonce: CLAWPRESS_PANEL.nonce,
      onEvent: handleStreamEvent,
      onDone: () => handleStreamEvent('done', {}),
      onError: (payload) => handleStreamEvent('error', payload),
    });

  const requestStatus = async (quiet = false) => {
    if (!quiet) {
      setStatusLoading(true);
    }

    try {
      const status = await buildClient().getStatus?.();
      if (status) {
        setStatusSnapshot(status);
        setSuggestions((prev) => {
          if (prev.length > 0) {
            return prev;
          }
          return normalizeSuggestions(status?.suggestions);
        });
      }
    } catch {
      setStatusSnapshot(null);
    } finally {
      setStatusLoading(false);
    }
  };

  useEffect(() => {
    let mounted = true;

    const initializePanelState = async () => {
      const client = buildClient();

      try {
        const panelStateResponse = await client.getPanelState?.();
        if (!mounted) return;

        const panelState = normalizePanelState(panelStateResponse);
        if (typeof panelState.open === 'boolean') {
          setOpen(panelState.open);
        }
        if (Number.isFinite(panelState.width) && panelState.width > 0) {
          setWidth(panelState.width);
        }
      } catch {
        // Keep localStorage fallback.
      }

      try {
        const statusResponse = await client.getStatus?.();
        if (!mounted) return;

        setStatusSnapshot(statusResponse || null);
        setSuggestions((prev) => {
          if (prev.length > 0) {
            return prev;
          }
          return normalizeSuggestions(statusResponse?.suggestions);
        });
      } catch {
        if (!mounted) return;
        setStatusSnapshot(null);
      } finally {
        if (mounted) {
          setStatusLoading(false);
        }
      }

      try {
        const historyResponse = await client.getHistory?.();
        if (!mounted) return;

        const historyMessages = normalizeHistoryItems(historyResponse?.items || []);
        setMessages(historyMessages);
        timelineRef.current = historyMessages.reduce(
          (max, item) => Math.max(max, Number(item.createdAt) || 0),
          0
        );
      } catch {
        if (!mounted) return;
        appendMessage('system', 'Unable to load chat history.');
      }

      if (mounted) {
        setPanelStateReady(true);
      }
    };

    initializePanelState();

    return () => {
      mounted = false;
    };
  }, []);

  useEffect(() => {
    if (!open) return;
    requestStatus(true);

    const intervalId = setInterval(() => {
      requestStatus(true);
    }, 15000);

    return () => clearInterval(intervalId);
  }, [open]);

  useEffect(() => {
    if (!panelStateReady) return;

    const lastMessage = messages[messages.length - 1];
    const payload = {
      open,
      width,
      last_history_id: typeof lastMessage?.id === 'string' ? lastMessage.id : '',
    };

    if (panelStateSyncTimerRef.current) {
      clearTimeout(panelStateSyncTimerRef.current);
    }

    panelStateSyncTimerRef.current = setTimeout(() => {
      buildClient().setPanelState?.(payload).catch(() => {});
    }, 350);

    return () => {
      if (panelStateSyncTimerRef.current) {
        clearTimeout(panelStateSyncTimerRef.current);
      }
    };
  }, [panelStateReady, open, width, messages]);

  const streamPrompt = async (prompt) => {
    setStreaming(true);
    setCurrentStreamText('');
    currentStreamTextRef.current = '';
    setToolPlan(null);

    const client = buildClient();

    streamHandleRef.current = client.stream(prompt);
  };

  const stopStream = () => {
    streamHandleRef.current?.stop?.();
    appendMessage('system', 'Stream stopped.');
  };

  const runTool = async (tool, args) => {
    try {
      const res = await buildClient().runTool(tool, args);

      return res;
    } catch (e) {
      throw e;
    }
  };

  const getToolConcurrency = (toolName) => getToolPolicy(toolName).concurrency || 'parallel';

  const updateToolDialog = (id, updater) => {
    setToolDialogs((prev) =>
      prev.map((dialog) => (dialog.id === id ? { ...dialog, ...updater } : dialog))
    );
  };


  const executeToolDialog = async (dialog) => {
    const toolName = dialog.function?.name || '';
    const args = dialog.args || getParsedArguments(dialog.function);

    updateToolDialog(dialog.id, { status: 'running', error: null });

    try {
      const res = await runTool(toolName, args);
      const result = res.step?.data?.result ?? null;
      const diff = Array.isArray(result?.changed) ? result.changed : null;
      updateToolDialog(dialog.id, {
        status: 'done',
        result,
        diff,
      });
    } catch (err) {
      updateToolDialog(dialog.id, {
        status: 'error',
        error: err?.message || 'Tool execution failed.',
      });
    }
  };

  const requestRunToolDialog = (dialogId, overrideArgs) => {
    const dialog = toolDialogs.find((d) => d.id === dialogId);
    if (!dialog) return;

    const toolName = dialog.function?.name || '';
    const policy = getToolConcurrency(toolName);

    if (policy === 'serial') {
      const sameTool = toolDialogs
        .filter((d) => d.function?.name === toolName)
        .sort((a, b) => a.createdAt - b.createdAt);

      const hasRunning = sameTool.some((d) => d.status === 'running');
      const hasEarlierActive = sameTool.some(
        (d) =>
          d.id !== dialog.id &&
          d.createdAt < dialog.createdAt &&
          (d.status === 'idle' ||
            d.status === 'running' ||
            d.status === 'error' ||
            d.status === 'blocked')
      );

      if (hasRunning || hasEarlierActive) {
        updateToolDialog(dialog.id, { status: 'blocked', args: overrideArgs ?? dialog.args });
        return;
      }
    }

    if (overrideArgs) {
      updateToolDialog(dialog.id, { args: overrideArgs });
    }
    const nextDialog = overrideArgs ? { ...dialog, args: overrideArgs } : dialog;
    executeToolDialog(nextDialog);
  };

  const cancelToolDialog = (dialogId) => {
    updateToolDialog(dialogId, { status: 'cancelled' });
  };


  const runMockScenario = () => {
    if (!mockEnabled) return;
    setInput('');
    appendMessage('user', `Mock: ${mockScenario}`);
    streamPrompt(`Mock: ${mockScenario}`);
  };

  useEffect(() => {
    setToolDialogs((prev) => {
      let changed = false;
      const next = prev.map((dialog) => {
        const toolName = dialog.function?.name || '';
        const concurrency = getToolConcurrency(toolName);
        if (concurrency !== 'serial') return dialog;
        if (
          dialog.status === 'running' ||
          dialog.status === 'error' ||
          dialog.status === 'done' ||
          dialog.status === 'cancelled'
        ) {
          return dialog;
        }

        const earlierActive = prev.some(
          (other) =>
            other.function?.name === toolName &&
            other.createdAt < dialog.createdAt &&
            other.status !== 'done' &&
            other.status !== 'cancelled'
        );

        const desired = earlierActive ? 'blocked' : 'idle';
        if (dialog.status !== desired) {
          changed = true;
          return { ...dialog, status: desired };
        }

        return dialog;
      });

      return changed ? next : prev;
    });
  }, [toolDialogs]);

  useEffect(() => {
    const link = document.querySelector('#wp-admin-bar-clawpress-toggle > a');
    if (!link) {
      setShowFloatingToggle(true);
      return;
    }

    setShowFloatingToggle(false);
    const onClick = (e) => {
      e.preventDefault();
      setOpen((o) => !o);
    };

    link.addEventListener('click', onClick);
    return () => link.removeEventListener('click', onClick);
  }, []);

  return (
    <Fragment>
      {showFloatingToggle ? <PanelToggle onToggle={() => setOpen((o) => !o)} /> : null}
      <div className="clawpress-panel" data-theme={themeMode} style={{ width: `${width}px` }}>
        <PanelHeader
          onClose={() => setOpen(false)}
          onToggleTheme={() => setThemeMode((m) => (m === 'light' ? 'dark' : 'light'))}
          statusMode={statusSnapshot?.mode || 'offline'}
          statusLabel={buildStatusLabel(statusSnapshot)}
          statusLoading={statusLoading}
        />
        <div className="clawpress-drag-handle" onMouseDown={startDrag} />
        <PanelMessages
          messages={messages}
          streaming={streaming}
          currentStreamText={currentStreamText}
          waitingForResponse={waitingForResponse}
          toolDialogs={toolDialogs}
          onRunToolDialog={requestRunToolDialog}
          onCancelToolDialog={cancelToolDialog}
        />
        <PanelInput
          input={input}
          onInputChange={(e) => setInput(e.target.value)}
          onSend={sendPrompt}
          suggestions={suggestions}
          onSendSuggestion={(suggestion) => sendPrompt(suggestion)}
          onStop={stopStream}
          streaming={streaming}
          onHistoryUp={(currentValue) => {
            if (inputHistory.length === 0) return null;
            if (historyIndex === -1) {
              setHistoryDraft(currentValue);
              const nextIndex = inputHistory.length - 1;
              setHistoryIndex(nextIndex);
              return inputHistory[nextIndex];
            }
            const nextIndex = Math.max(0, historyIndex - 1);
            setHistoryIndex(nextIndex);
            return inputHistory[nextIndex];
          }}
          onHistoryDown={() => {
            if (historyIndex === -1) return null;
            const nextIndex = historyIndex + 1;
            if (nextIndex >= inputHistory.length) {
              setHistoryIndex(-1);
              const draft = historyDraft;
              setHistoryDraft('');
              return draft;
            }
            setHistoryIndex(nextIndex);
            return inputHistory[nextIndex];
          }}
        />
      </div>
      <MockPanel
        mockEnabled={mockEnabled}
        mockScenario={mockScenario}
        mockDelay={mockDelay}
        onSelectScenario={setMockScenario}
        onSelectDelay={setMockDelay}
        onRunScenario={runMockScenario}
        themeMode={themeMode}
      />
    </Fragment>
  );
};

export default Panel;

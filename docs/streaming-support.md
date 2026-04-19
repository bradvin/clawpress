# Streaming Support Notes

This document captures the simplified streaming shape now that ClawPress uses the WordPress streaming client package.

## Current State

ClawPress now streams the main chat panel through:

- `includes/helpers/class-agent-loop-helper.php`
- `includes/transports/class-agent-event-sink.php`
- `includes/rest/class-chat-controller.php`
- `src/panel/services/realClient.js`

`transport_mode=streaming` now uses a single `Agent_Event_Sink` that can:

- emit live SSE callback events,
- persist non-delta runtime events for polling/resume flows,
- keep high-frequency `agent.llm.delta` events out of the event log by default.

## Why This Is Low-Risk

The execution control plane remains decoupled from delivery details:

- Run/session state machine lives in helpers/stores.
- Runner logic (claim, pause, retry, complete) is delivery-mode agnostic.
- Event persistence and polling APIs remain valid as fallback.

As a result, streaming support did not require redesigning retries, leases, resumability, or DB schemas.

## Current Design

1. `Chat_Controller` exposes `/chat/stream` and forwards live runtime events as SSE frames.
2. `Agent_Loop_Helper` enables provider streaming when `wp_ai_client_stream_prompt()` is available.
3. `Agent_Event_Sink` handles both immediate callback delivery and persisted run events.
4. Polling remains the fallback path for `in_progress` continuations and background slices.

## Components Expected To Stay Unchanged

These should not need structural changes:

- `includes/class-agent-runner.php`
- `includes/helpers/class-agent-run-helper.php`
- `includes/helpers/class-agent-session-helper.php`
- `includes/stores/class-agent-run-store.php`
- `includes/stores/class-agent-session-store.php`
- `includes/stores/class-agent-event-store.php`
- `includes/rest/class-agent-run-controller.php` (except optional stream bootstrap helpers)

## Suggested Event Contract for Streaming

Recommended event types for live transport:

- `agent.run.started`
- `agent.llm.delta`
- `agent.llm.response`
- `agent.tool_call`
- `agent.confirmation.required`
- `agent.slice.paused`
- `agent.run.finished`
- `agent.run.error`

The final event should include enough metadata for the client to reconcile against run status polling.

## Backward Compatibility Expectations

- Existing polling clients continue to work without changes.
- `transport_mode=polling` behavior remains unchanged.
- If streaming channel fails, runtime can degrade to persisted polling events.

## Testing Guidance for Future Streaming Work

Streaming-sensitive tests should cover:

- event-sink selection for `transport_mode=streaming`;
- chunk/delta emission ordering and finalization;
- fallback behavior when streaming channel disconnects;
- parity of final `TurnResult` fields between polling and streaming modes;
- runner/background behavior unaffected by streaming delivery.

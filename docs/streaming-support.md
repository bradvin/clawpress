# Streaming Support Plan

This document explains how ClawPress will adopt true streaming once the WP AI Client exposes stable streaming APIs.

## Current State

ClawPress already has the runtime layering needed for streaming with minimal core changes:

- Transport contract: `includes/transports/interface-agent-transport.php`
- Polling transport: `includes/transports/class-polling-transport.php`
- Null transport: `includes/transports/class-null-transport.php`
- Loop runtime integration point: `includes/helpers/class-agent-loop-helper.php`

Today, `transport_mode=streaming` is accepted but intentionally routed through polling transport in `Agent_Loop_Helper::create_transport()`. This means there is no true live token streaming yet.

## Why This Is Low-Risk

The execution control plane is already decoupled from delivery transport:

- Run/session state machine lives in helpers/stores.
- Runner logic (claim, pause, retry, complete) is transport-agnostic.
- Event persistence and polling APIs remain valid as fallback.

As a result, adding streaming should not require redesigning retries, leases, resumability, or DB schemas.

## Minimal Changes Needed When WP AI Client Adds Streaming

1. Add `Streaming_Transport` implementation.
   - New file: `includes/transports/class-streaming-transport.php`
   - Implement `Agent_Transport` (`emit()`, `close()`).
   - Emit live deltas to the connected client channel (SSE/WebSocket).
   - Optionally mirror selected events to `Agent_Event_Helper` for observability and fallback polling.

2. Switch transport selection in loop runtime.
   - Update `Agent_Loop_Helper::create_transport()` in `includes/helpers/class-agent-loop-helper.php`.
   - Return `Streaming_Transport` for `transport_mode=streaming`.
   - Keep polling as default/fallback.

3. Integrate streaming model-call path.
   - Update model invocation in `includes/helpers/class-agent-loop-helper.php`.
   - Consume WP AI Client chunk/delta callbacks.
   - Emit incremental `agent.llm.delta`/`agent.llm.response` style events through transport.
   - Preserve existing normalized `TurnResult` semantics at stream end.

4. Add/extend delivery adapter endpoint.
   - Add SSE/WebSocket endpoint in REST/controller layer for clients to subscribe to stream events.
   - Keep `/agent/runs/{run_id}/events` polling endpoint as backup path.

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

When implementing true streaming, add tests for:

- transport selection (`streaming` -> `Streaming_Transport`);
- chunk/delta emission ordering and finalization;
- fallback behavior when streaming channel disconnects;
- parity of final `TurnResult` fields between polling and streaming modes;
- runner/background behavior unaffected by streaming transport.


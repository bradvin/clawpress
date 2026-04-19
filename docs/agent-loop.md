# Agent Loop Runtime

This document describes how the ClawPress agent loop executes work across chat, background runner, and spawn flows.

## Scope

The runtime spans:

- `Agent_Loop_Helper`: turn/slice execution engine.
- `Agent_Runner`: Action Scheduler worker that claims and executes run slices.
- `Agent_Run_Controller`: REST API for creating, spawning, enqueueing, and polling runs.
- `Agent_Run_Helper` + `Agent_Session_Helper`: lifecycle/state helpers.
- `Agent_Run_Store` + `Agent_Session_Store` + `Agent_Event_Store`: persistence.
- `Agent_Event_Sink`: runtime event delivery to live callbacks and/or persisted event logs.

Shared runtime utilities:

- `Agent_Runner::enqueue_run_slice_action()` is the single enqueue path used by chat, run-controller, and runner retry/continuation logic.
- `Provider_Helper::resolve_provider_and_model()` is the default provider/model resolver for both chat and loop runtime.
- `Agent_Loop_Helper::classify_provider_error_type()` is reused by chat and loop runtime error handling.

## Core Model

### Session

Session rows (`clawpress_agent_sessions`) represent a logical execution stream and lease ownership.

Important fields:

- `status`: `idle`, `running`, `paused`, `error`.
- `lease_owner`, `lease_token`, `lease_expires_at_gmt`: session-level lease lock.
- `last_run_status`, `consecutive_failures`: health/telemetry.

### Run

Run rows (`clawpress_agent_runs`) represent one queued/claimed execution attempt.

Important fields:

- `status`: `queued`, `running`, `paused`, terminal states (`done`, `error`, `timeout`, `requires_confirmation`, etc.).
- `lock_token`, `lock_expires_at_gmt`: run-level lock.
- `attempt`, `max_attempts`: retry accounting.
- `retry_count`: retry/backoff accounting separate from continuation slices.
- `next_retry_at_gmt`: deferred scheduling.
- `resume_cursor_json`: continuation state between slices.
- `meta_json`: request payload and runtime metadata.

### Event

Event rows (`clawpress_agent_events`) are append-only progress records for polling clients.

## Execution Paths

## 1) Chat Path (`Chat_Helper` -> `Agent_Loop_Helper::run_slice`)

1. Resolve provider/model.
2. Create and claim chat session/run records up front (`run_id`, `session_id`).
3. Build runtime request payload.
4. Execute a bounded slice through `Agent_Loop_Helper::run_slice`.
5. Persist first-slice outcomes onto the same run/session state.
6. Emit run-linked events from the first slice (no pre-run event gap).
7. If slice completes: return assistant text/card/context/tool-call trace.
8. If slice returns `in_progress`: persist `resume_cursor`, enqueue background continuation, and return `run_id` + `session_id` + `status=in_progress` for polling.

If provider is unavailable, it returns deterministic offline mode without background run creation.

Execution identity:

- Chat, run-creation, and spawn adapters resolve execution user consistently.
- Preferred execution user is configured `agent_user_id`; fallback is requesting user.

## 2) Background Path (`Agent_Run_Controller` + `Agent_Runner`)

1. `POST /clawpress/v1/agent/runs` creates session/run and enqueues slice action.
2. Runner claims runnable run (`queued`/`paused`, retry time reached).
   - Runnable scan also includes stale `running` rows whose run lock lease has expired.
3. Runner claims session lease.
4. Runner executes one slice via `Agent_Loop_Helper::run_slice`.
5. Runner either:
   - completes run (`done`, `requires_confirmation`, `error`, etc.), or
   - pauses run with `resume_cursor` and `next_retry_at_gmt`, then re-enqueues.

## 3) Spawn Path (`POST /agent/spawn`)

1. Create a new `spawned_agent` session.
2. Seed first run metadata (`message`, slice budgets, retry limits).
3. Enqueue first slice.
4. Return `session_id` + `run_id` immediately.

## Locking and Claim Rules

### Run claim

`Agent_Run_Helper::claim_run` supports:

- fresh claim from `queued` (attempt stays at current value; first claim remains `1`),
- retry claim from `paused` (attempt increments),
- stale reclaim from expired `running` lease (attempt increments).

Heartbeat scan intentionally includes expired `running` leases so stale claims are actually discoverable in production.

### Session claim

`Agent_Session_Helper::claim_session` supports:

- `idle` and `paused` sessions,
- stale reclaim of expired `running` lease.

This prevents deadlock when a run pauses and later needs to resume under a paused session.

## Retry and Failure Semantics

Session failure counter behavior (`update_run_completion`):

- reset to `0` for `success`, `done`, `requires_confirmation`,
- unchanged for `paused`,
- incremented for error-like outcomes.

This keeps time-slicing and confirmation waits from being treated as failures.

Retry model notes:

- `attempt` tracks claim lifecycle visibility.
- `retry_count` tracks error-driven retries/backoff only.
- continuation pauses (`in_progress`) do not increment `retry_count`.
- paused progress releases run lock metadata (`lock_token`, `claimed_by`, lease timestamps) because lock scope is one slice.
- runner enforces trigger policy `max_wall_time_seconds` and marks run `timeout` when exceeded.
- when `allow_background_followups` is false, the runner does not enqueue additional slices after `in_progress` or retryable error outcomes.

Policy violation modes:

- `deny`: tool call is rejected with policy error payload.
- `degrade`: tool call returns a successful degraded no-op payload so the model can continue.
- `fail`: tool call returns an error payload tagged as fail mode (`*_fail` policy code).

## Manual Re-enqueue Safety

`POST /agent/runs/{run_id}/enqueue` is intentionally guarded:

- only terminal/retryable statuses can be re-enqueued,
- active statuses (`queued`, `running`, `paused`) return HTTP `409`,
- DB update is compare-and-swap on `id + status` to avoid racey resets.

This prevents accidental mutation of active runs and stale-state rewrites.

## Idempotency

`idempotency_key` is supported on run creation:

- `POST /agent/runs` with the same `session_id + idempotency_key` returns the existing run instead of inserting a duplicate.
- duplicate create requests return HTTP `200` with `deduplicated=true`.
- unknown explicit `session_id` now returns HTTP `404` to prevent orphan run creation.
- run table enforces unique `(session_id, idempotency_key)` for non-null keys to harden concurrent create races.
- helper fallback re-reads by idempotency key when insert returns no ID, covering duplicate-key race windows.

## Agent Loop Callback Compatibility

`Agent_Loop_Helper` supports custom `online_reply_generator` callables with varying arity.

Arity resolver supports:

- array callables (`[object, 'method']`),
- closures/string callables,
- invokable objects (`__invoke`).

Invokable object reflection uses `ReflectionMethod(__invoke)` and catches both `ReflectionException` and `TypeError`.

## REST Endpoints

`Agent_Run_Controller` registers:

- `POST /clawpress/v1/agent/runs`
- `POST /clawpress/v1/agent/spawn`
- `POST /clawpress/v1/agent/runs/{run_id}/enqueue`
- `GET /clawpress/v1/agent/runs/{run_id}`
- `GET /clawpress/v1/agent/runs/{run_id}/events?after={event_id}&limit={n}`

## Eventing

- `Agent_Event_Sink` can append runtime events via `Agent_Event_Helper`.
- Runner emits operational events (slice started/paused/completed).
- Polling endpoint returns incremental cursor (`next_cursor`) for client-side streaming.

## Test Harness Coverage

The runtime harness uses `tests/Support/AgentRuntimeWpdb.php` and covers:

- run/session claim and completion lifecycle,
- paused-session reclaim behavior,
- attempt accounting correctness,
- enqueue safety rules,
- paused and requires-confirmation failure accounting,
- runner progression from queued run to completion,
- runner missing-session and unclaimable-session negative flows,
- controller create/enqueue/events behavior,
- controller idempotency and unknown-session negative flows,
- spawn endpoint flow,
- chat `in_progress` handoff with run metadata,
- loop invocation with invokable callback arity,
- loop streaming-mode transport fallback and slice `in_progress` payload semantics.

Primary tests:

- `tests/Unit/AgentRunHelperTest.php`
- `tests/Unit/AgentRunnerTest.php`
- `tests/Unit/AgentRunControllerTest.php`
- `tests/Unit/AgentLoopHelperTest.php`

## Operational Notes

- `Agent_Runner` is wired in plugin bootstrap and listens to `clawpress_run_scheduled_tasks` and `clawpress_agent_run_slice`.
- Action Scheduler async actions are preferred; delayed slices use single scheduled actions.
- Chat and REST controllers delegate run-slice scheduling to `Agent_Runner::enqueue_run_slice_action()` to avoid duplicate enqueue behavior.
- Keep helper/store boundaries intact: controllers and runner call helpers; helpers call stores.

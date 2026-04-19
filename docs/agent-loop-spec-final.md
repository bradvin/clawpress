## Agent Loop Spec (Final)

## Summary
Refactor the current chat-bound agent execution into a reusable **Agent Loop runtime** that can be called from:

1. synchronous chat requests,
2. heartbeat/background jobs,
3. future spawned-agent APIs.

The loop must work reliably with **non-streaming providers now** and support **streaming later** without rewriting core execution logic.

---

## Current State

### What exists
- Chat execution path currently drives model/tool loop:
  - `includes/rest/class-chat-controller.php` -> `Chat_Helper::generate_ai_reply()`
  - core loop logic currently in `includes/helpers/class-chat-helper.php`
- Heartbeat scheduler exists:
  - `includes/class-heartbeat.php`
  - schedules `clawpress_heartbeat_tick` every 15 minutes via Action Scheduler
  - tick triggers: `do_action( 'clawpress_run_scheduled_tasks' )`

### What is missing
- No production-grade consumer attached to `clawpress_run_scheduled_tasks` for autonomous runs.
- No fully independent runtime loop surface shared by chat/background/spawn adapters.
- No completed run coordinator across triggers with robust claim/lock/retry lifecycle for all paths.

Result: execution is still effectively request-bound in practice.

---

## Constraints

### Non-streaming first
- Current LLM integration uses WP AI Client, which does not yet provide streaming support.
- Long run-to-completion turns inside a single HTTP request are unreliable.

### Non-negotiable design rule
The agent loop must be deterministic and resumable independent of delivery transport.

- Streaming is a transport optimization.
- Streaming must not require control-plane rewrites.

---

## Final Goal
Build one reusable loop runtime that provides:

- identical core behavior across chat, heartbeat, and spawned agents,
- safe asynchronous/background execution via time-sliced runs,
- durable state and event persistence for polling-based progress now,
- a direct upgrade path to streaming transport later.

---

## Final Architecture

### 1) Explicit layers
Implement clear layers:

1. **Loop Engine**: pure execution logic; no DB, no HTTP, no scheduler coupling.
2. **Stores**: DB-backed persistence for sessions/runs/events and locking metadata.
   - session store: `ClawPress\Stores\Agent_Session_Store`
   - run store: `ClawPress\Stores\Agent_Run_Store`
   - event store: `ClawPress\Stores\Agent_Event_Store` for append-only run/session events
3. **Helpers**: orchestration-facing APIs over stores.
   - session helper: `ClawPress\Helpers\Agent_Session_Helper`
   - run helper: `ClawPress\Helpers\Agent_Run_Helper`
   - event helper: `ClawPress\Helpers\Agent_Event_Helper`
   - rule: loop/runner/transport/controllers call helpers; helpers call stores; stores own DB logic.
4. **Runner**: Action Scheduler tick executor that claims work and runs slices.
5. **Transport**: delivery channel for progress/events (`polling` now, `streaming` later).

### 2) Loop Engine responsibilities
Extract loop responsibilities from `Chat_Helper` into `includes/helpers/class-agent-loop-helper.php` (name flexible):

- provider/model resolution,
- context assembly and prompt preparation,
- model call orchestration,
- tool-call loop with bounded limits,
- confirmation batching behavior,
- usage/context metadata collection,
- normalized result payloads,
- event emission hooks.

`Chat_Helper` becomes a thin adapter.

### 3) Step-based and slice-based execution
Support both:

- `run_turn(...)`: full turn execution for synchronous contexts when budget permits.
- `run_slice(...)`: bounded execution chunk (for runner/background).

A slice should do bounded work (for example, one model call or a limited tool batch), then return resumable state.

### 4) Transport abstraction
Define internal transport interface:

- `emit( AgentEvent $event ): void`
- `close(): void`

Implementations:

- **PollingTransport** (default now): append events via `ClawPress\Helpers\Agent_Event_Helper` backed by `ClawPress\Stores\Agent_Event_Store`.
- **StreamingTransport** (future): emits deltas live; may still persist events for observability.

The loop engine must not branch by transport-specific behavior.

---

## Contracts

### TurnRequest
Required/common fields:

- `session_id`
- `trigger` (`chat`, `heartbeat`, `spawned_agent`, ...)
- `message` (optional for heartbeat-driven turns)
- `requesting_user_id`
- `execution_user_id`
- policy knobs (`allow_tools`, `require_confirmation`, limits/timeouts)

Additions for resumability and slicing:

- `run_id` (unique per attempt)
- `attempt` (int)
- `slice_budget_ms` (hard per-tick budget)
- `max_steps_per_slice` (hard cap per slice)
- `transport_mode` (`polling` | `streaming`)
- `resume_cursor` (opaque engine state token; optional)

### TurnResult
Core fields:

- `assistant_text`
- `tool_calls` trace
- optional `card`
- usage/context metadata

Status values:

- `success`
- `requires_confirmation`
- `error`
- `timeout`
- `in_progress` (slice paused due to budget)

Next-action hints:

- `continue_now`
- `continue_later`
- `stop`

Additions for resumability and UI polling:

- `resume_cursor` (when `status=in_progress`)
- `events_cursor` (optional incremental cursor)

---

## Persistence Model

### agent_sessions (long-lived)
Backed by: `ClawPress\Stores\Agent_Session_Store`
Accessed via: `ClawPress\Helpers\Agent_Session_Helper`

- `session_id`
- `status` (`idle|running|paused|error|dead`)
- policy profile
- schedule fields (`last_run_at`, `next_run_at`)
- lock/lease metadata (or lock table)

### agent_runs (per attempt)
Backed by: `ClawPress\Stores\Agent_Run_Store`
Accessed via: `ClawPress\Helpers\Agent_Run_Helper`

- `run_id`, `session_id`
- `trigger`
- `status` (`queued|running|waiting_llm|waiting_tools|paused|done|error`)
- `attempt`, retry/backoff fields
- `resume_cursor`
- usage totals and error classification

### agent_events (append-only)
Backed by: `ClawPress\Stores\Agent_Event_Store`
Accessed via: `ClawPress\Helpers\Agent_Event_Helper`

- monotonic `event_id`
- `run_id`, `session_id`
- `type`
- JSON `payload`
- `created_at`

UI polls event stream incrementally via cursor.

---

## Runner (Action Scheduler)

Runner algorithm per tick:

1. Find and claim runnable sessions/runs.
2. Acquire lock/lease.
3. Load or create run state.
4. Execute one bounded slice.
5. Persist updated run/session state and emitted events.
6. If needed, enqueue follow-up tick immediately or with backoff.
7. Release lock/lease.

### Lock semantics
- Lock scope is one slice execution, not entire multi-slice lifecycle.
- Lease renewed per tick.
- Stale lease recovery requeues safely.
- Idempotency checks prevent duplicate progress on repeated ticks.

---

## Adapter Behavior

### Chat adapter
Keep synchronous for small turns, but remain thin:

1. persist inbound user message,
2. call loop runtime,
3. persist outputs,
4. return response.

If request budget is exceeded, return `run_id` + `in_progress`, and client switches to polling.

### Heartbeat/background adapter
Consume `clawpress_run_scheduled_tasks` and execute slices through runner.

### Spawn adapter
Spawn endpoint should:

1. create session,
2. seed initial context/message,
3. enqueue first run,
4. return session/run identifiers.

No loop internals in spawn endpoint.

---

## Policy and Safety

### Policy by trigger
- `chat`: interactive confirmation behavior.
- `heartbeat` / `spawned`: destructive tools denied or queued by default.
- optional per-session policy profiles.

### Guardrails
- max wall time per run/slice,
- max tool calls per run,
- bounded retries with exponential backoff,
- dead-letter terminal state after N failures,
- idempotency keys for run attempts.

---

## Observability
Log structured run data (reuse/extend action log + run/event records):

- `run_id`, `session_id`, trigger,
- tool trace + statuses,
- provider/model and usage,
- error type + retry count,
- final outcome.

Polling endpoint must expose incremental run events.

---

## API Implications
Minimum endpoints:

- `POST /agent/runs` -> create run and return `run_id`
- `POST /agent/runs/{run_id}/enqueue` (optional)
- `GET /agent/runs/{run_id}` -> status summary
- `GET /agent/runs/{run_id}/events?after={event_id}` -> incremental events

---

## Implementation Phases

### Phase 1: Internal extraction (no behavior regression)
- Extract Loop Engine from `Chat_Helper`.
- Add transport interface with polling implementation.
- Emit structured events.

### Phase 2: Persistence + lock manager
- Extend/finalize `Agent_Session_Helper` + `Agent_Session_Store` for full session requirements.
- Extend/finalize `Agent_Run_Helper` + `Agent_Run_Store` for full run requirements.
- Use `Agent_Event_Helper` + `Agent_Event_Store` for append-only run/session events.
- Finalize lock/lease handling across stores.
- Ensure stale recovery and idempotency.

### Phase 3: Time-sliced runner
- Implement Action Scheduler slice executor.
- Enforce time/step budgets.
- Re-enqueue until completion.

### Phase 4: Spawn support + hardening
- Add spawn adapter/endpoint.
- Add retry/backoff/dead-letter policies.
- Add trigger-based policy profiles.

### Phase 5: Streaming transport (future)
- Add `StreamingTransport` once provider supports deltas.
- Keep polling as fallback.
- No loop/state-machine rewrite required.

---

## Acceptance Criteria

- Chat uses shared loop runtime (no duplicated loop logic).
- Runner supports multi-slice execution with `in_progress` and `resume_cursor`.
- Background runs complete safely without long-lived HTTP requests.
- Per-session concurrency safety: no duplicate concurrent execution.
- Event persistence supports incremental UI polling.
- Destructive tool behavior is explicitly policy-controlled by trigger.
- Transport mode is pluggable (`polling` now, `streaming` later) without core loop rewrite.

---

## Why this is worth doing

- One agent brain across all transports/triggers.
- Clean async/autonomous execution path.
- Safe path to spawned parallel sessions.
- Lower long-term maintenance cost than duplicating chat logic in background adapters.

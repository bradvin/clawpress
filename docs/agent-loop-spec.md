## Summary
Refactor current chat-bound agent execution into a reusable **Agent Loop Helper** (runtime service) that can be called from:

1. current synchronous chat requests,
2. heartbeat/background jobs,
3. future agent spawning APIs.

This should make agent execution transport-agnostic and enable true async/background runs without duplicating core loop logic.

---

## Current State (as of now)

### What exists
- Chat execution path currently drives model/tool loop:
  - `includes/rest/class-chat-controller.php` → `Chat_Helper::generate_ai_reply()`
  - Core loop currently in `includes/helpers/class-chat-helper.php`
- Heartbeat scheduler exists:
  - `includes/class-heartbeat.php`
  - Schedules `clawpress_heartbeat_tick` every 15 min using Action Scheduler
  - Tick triggers: `do_action( 'clawpress_run_scheduled_tasks' )`

### What is missing
- No consumer/handler currently attached to `clawpress_run_scheduled_tasks` in plugin code.
- No independent agent thread/session runtime (outside chat request path).
- No spawn manager / spawn endpoint that creates and runs separate agent threads.
- No async run coordinator (claim/lock/retry/failure lifecycle) for autonomous runs.

Result: loop logic is effectively request-bound to chat right now.

---

## Goal
Create a reusable **Agent Loop Helper** so one core loop can be used by multiple entry points (chat, heartbeat, spawning) with consistent behavior and policy.

---

## Proposed Architecture

### 1) Extract a core runtime service
Create e.g. `includes/helpers/class-agent-loop-helper.php` (name flexible) and move loop responsibilities out of `Chat_Helper`:

- provider/model resolution
- context assembly + prompt prep
- model call
- tool-call loop (`MAX_TOOL_ROUNDS`, `MAX_TOOL_CALLS_PER_ROUND`)
- confirmation batching behavior
- context/token usage collection
- normalized result payload

`Chat_Helper` should become an adapter that invokes this service.

---

### 2) Define canonical request/response contracts
Introduce internal DTO-like arrays/classes:

#### `TurnRequest`
- `thread_id` (or session id)
- `trigger` (`chat`, `heartbeat`, `spawned_agent`, etc.)
- `message` (optional for heartbeat-driven turns)
- `requesting_user_id`
- `execution_user_id`
- policy knobs: `allow_tools`, `require_confirmation`, limits/timeouts

#### `TurnResult`
- `assistant_text`
- `tool_calls` trace
- `card` payload (optional)
- `status` (`success`, `requires_confirmation`, `error`, `timeout`)
- usage/context metadata
- optional `next_action` hint (`continue`, `stop`, `reschedule`)

This contract is key to reusability across adapters.

---

### 3) Separate state persistence from execution
Add explicit agent-thread/session state (rather than implicitly relying on chat history only).

Minimum state needed:
- thread/session identity
- lifecycle status
- `last_run_at`, `next_run_at`
- lock/lease metadata (owner + expiry)
- failure/retry counters
- trigger metadata

Storage can be CPT-based or custom table (table likely better for concurrency/locking).

---

### 4) Add background runner adapter (heartbeat path)
Implement a consumer for `clawpress_run_scheduled_tasks` that:

1. finds/claims runnable agent threads
2. acquires lock/lease
3. builds `TurnRequest`
4. calls Agent Loop Helper
5. persists run outputs/logs/state
6. schedules follow-up if required
7. releases lock

This enables async operation without rewriting loop logic.

---

### 5) Keep chat path synchronous but thin
`Chat_Controller` flow should be:
1. persist inbound user message
2. call Agent Loop Helper synchronously
3. persist assistant response/meta
4. return response

No duplicated loop logic in chat layer.

---

### 6) Add spawn entry point later as another adapter
Future spawn endpoint should:
- create a new thread/session record,
- seed initial context/message,
- enqueue first run via Action Scheduler,
- return spawned thread id.

Spawn endpoint should not contain loop internals.

---

## Policy & Safety Considerations

### Confirmation/destructive tools by trigger
Define policy by trigger source:
- `chat`: current confirmation behavior acceptable
- `heartbeat` / `spawned`: likely deny or queue destructive calls by default
- optionally allow policy profiles per agent/thread

### Runtime guardrails
- max wall time per run
- max tool calls per run
- bounded retries + exponential backoff
- dead-letter/failure terminal state after N failures
- idempotency key per run attempt

### Concurrency controls
- at-most-one active run per thread/session (lock/lease)
- stale lock recovery
- avoid duplicate processing by concurrent scheduler invocations

---

## Observability / Debuggability
Add structured run logging (reuse/extend action log):
- `run_id`, `thread_id`, trigger source
- tool trace + per-call status
- provider/model + token/context usage
- error classification + retry count
- final run outcome

Without this, async failures will be hard to diagnose.

---

## Suggested Implementation Phases

### Phase 1: Internal refactor (no behavior change)
- Introduce Agent Loop Helper
- Move loop logic from `Chat_Helper` into helper
- Keep current chat behavior identical

### Phase 2: Background execution wiring
- Implement `clawpress_run_scheduled_tasks` consumer
- Add minimal thread/run state + locking
- Run one background thread safely

### Phase 3: Spawn support
- Add spawn API/service
- Create separate threads and schedule independent runs

### Phase 4: Hardening
- retries/backoff/dead-letter
- policy profiles by trigger
- richer observability and admin inspection UI

---

## Acceptance Criteria
- Chat uses Agent Loop Helper (no duplicated loop logic in chat layer).
- Heartbeat can run at least one agent thread asynchronously.
- Background runs are lock-safe (no duplicate concurrent turn execution per thread).
- Destructive tool behavior is explicitly policy-controlled per trigger.
- Run status/errors are inspectable via logs.

---

## Why this is worth doing
This makes ClawPress extensible:
- one “agent brain,” many transports/triggers,
- clean path to autonomous agents,
- clean path to true spawned parallel threads,
- less tech debt than cloning chat logic into heartbeat/spawn flows.

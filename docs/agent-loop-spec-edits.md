## Issue #12 Spec Update: Non-Streaming Now, Streaming Later (Transport-Agnostic + Time-Sliced Runner)

### Background / Constraint (NEW)

* Current LLM integration uses **WP AI Client**, which **does not support streaming yet**.
* Therefore, long-running “run-to-completion” turns inside a single web request are **not reliable** (timeouts / proxy buffering / request aborts).
* The agent loop must support:

  1. **non-streaming execution** with **polling-based progress**, and
  2. an **upgrade path** to streaming **without rewriting** core loop logic.

### Non-Negotiable Design Rule (NEW)

> The **agent loop logic** must be deterministic and resumable **independent of delivery transport**.
> Streaming is an optimization layer (transport), not a control-plane rewrite.

---

## Updated Goal

Keep the original goal (“one core loop used by chat + heartbeat + spawning”). ([GitHub][1])
**Add**: the loop must support **time-sliced execution** and **event persistence** so it can run safely under Action Scheduler with non-streaming providers.

---

## Architecture Changes / Additions

### 0) Introduce explicit layers (NEW)

1. **Loop Engine** (pure logic; no DB; no HTTP; no Action Scheduler assumptions)
2. **Session Store** (DB-backed, already implied by issue)
3. **Runner** (Action Scheduler tick executor; uses store + lock)
4. **Transport** (how progress is delivered: polling now, streaming later)

This clarifies “transport-agnostic” in a concrete way.

---

### 1) Agent Loop Helper becomes a “Loop Engine” (UPDATED)

Current proposal: `class-agent-loop-helper.php` extracted from `Chat_Helper`. ([GitHub][1])
Adjust it to:

#### A) Provide **step-based execution** (NEW)

Instead of “run loop until done”, expose:

* `run_turn(TurnRequest $req, AgentSession $session, LoopOptions $opts, AgentTransport $transport): TurnResult`
* `run_slice(RunSliceRequest $req): RunSliceResult`

  * Executes **bounded work** (one LLM call OR a limited batch of tool calls), then returns a resumable state marker.

**Why:** enables safe background execution even when LLM calls/tool runs are slow.

#### B) Make the engine emit events (NEW)

The engine must emit structured events as it progresses (even non-streaming):

* `agent_start`, `turn_start`, `message_start`, `message_end`
* `tool_execution_start`, `tool_execution_update`, `tool_execution_end`
* `turn_end`, `agent_end`
* optionally `message_update` (no-op for non-streaming today, real deltas later)

These events go to the **Transport** layer.

---

### 2) Expand TurnRequest / TurnResult contracts (UPDATED)

Existing contracts are good. ([GitHub][1])
Add the fields needed for slicing + resumability:

#### `TurnRequest` additions

* `run_id` (unique per attempt)
* `attempt` (int)
* `slice_budget_ms` (hard per-tick time budget; e.g., 2000–5000ms)
* `max_steps_per_slice` (hard cap; e.g., 1 LLM call or N tool calls)
* `transport_mode` (`polling` | `streaming`)
* `resume_cursor` (opaque engine cursor/state token; optional)

#### `TurnResult` additions

* `status` expands to include:

  * `success`, `requires_confirmation`, `error`, `timeout`,
  * **`in_progress`** (paused due to slice budget)
* `next_action` expands to:

  * `continue_now` (enqueue next tick ASAP)
  * `continue_later` (backoff/retry scheduling)
  * `stop`
* `resume_cursor` (present when `status=in_progress`)
* `events_cursor` (cursor for UI polling; optional)

---

### 3) Formalize Store Models: Thread vs Run vs Event (NEW)

Issue already calls for thread/session state + locking. ([GitHub][1])
Make it explicit:

#### A) `agent_threads` (long-lived)

* `thread_id`
* `status` (idle|running|paused|error|dead)
* `policy_profile` (by trigger)
* scheduling: `last_run_at`, `next_run_at`
* lock fields (or separate lock table; see below)

#### B) `agent_runs` (per attempt)

* `run_id`, `thread_id`
* `trigger` (chat|heartbeat|spawned_agent|...)
* `status` (queued|running|waiting_llm|waiting_tools|paused|done|error)
* `attempt`, `retry_at`, `error_code`, `error_message`
* `resume_cursor` (opaque engine cursor)
* usage totals (tokens/cost if available)

#### C) `agent_events` (append-only log for polling + debugging)

* `event_id` (monotonic)
* `run_id`, `thread_id`
* `type`
* `payload` (json)
* `created_at`

**Polling UI reads events**: `GET /runs/{run_id}/events?after={event_id}`

---

### 4) Transport abstraction (NEW)

Add an internal interface:

* `AgentTransport::emit(AgentEvent $event): void`
* `AgentTransport::close(): void`

Implementations:

1. **PollingTransport** (default today): writes events to `agent_events` table
2. **StreamingTransport** (future): emits SSE/websocket updates *and optionally also persists events* (debug mode)

**Core loop engine must never care which transport is in use.**

---

### 5) Runner: Action Scheduler ticks become first-class (UPDATED)

Issue already proposes a heartbeat consumer that claims runnable threads and runs the loop helper. ([GitHub][1])
Adjust so that the heartbeat worker executes **run slices**:

#### Runner algorithm (per tick)

1. Claim runnable `agent_threads`
2. Acquire lock/lease (existing requirement)
3. Load or create `agent_run` in `running` state
4. Execute **one slice**:

   * time budget enforcement
   * either: perform the next LLM call OR next tool batch
5. Persist:

   * updated `resume_cursor`
   * `agent_run.status` + `next_action`
   * emitted events (via PollingTransport)
6. If `next_action=continue_now`, enqueue another AS action immediately
7. Release lock/lease

---

## Lock/Lease Semantics (UPDATED)

Issue already calls for lock/lease + stale recovery. ([GitHub][1])
Clarify:

* Lock covers a **single slice execution**, not “the entire multi-slice run”.
* Lease must be renewed each tick; stale lease recovery should requeue the run safely.
* Store must support idempotency:

  * if a tick repeats (duplicate AS run), it should detect already-advanced `resume_cursor`/status and no-op.

---

## UI / API Implications (NEW)

Because WP AI Client is non-streaming today, “real time” must be achieved via polling:

### Endpoints (internal or REST)

* `POST /agent/runs` (chat/spawn) -> returns `run_id`
* `POST /agent/runs/{run_id}/enqueue` (optional) -> enqueue tick
* `GET /agent/runs/{run_id}` -> status + summary
* `GET /agent/runs/{run_id}/events?after=...` -> incremental event feed

Chat can remain synchronous for small turns, but must have a fallback:

* if request budget exceeded, return `{ run_id, status: in_progress }` and client switches to polling.

---

## Streaming Upgrade Path (NEW)

When WP AI Client supports streaming:

* Implement `StreamingTransport` that emits `message_update` events live.
* Update the LLM adapter to emit deltas to the transport.
* **No change** to:

  * Run/session store schemas
  * Lock/lease mechanism
  * Tool execution logic
  * State machine
  * Runner (still valid; streaming can be used for UI only)

Optional: allow a “streaming-only” immediate path for chat if hosting supports it, but do not remove the slice runner.

---

## Updated Implementation Phases

### Phase 1: Extract Loop Engine + Events (behavior-preserving)

* Extract core loop out of `Chat_Helper` into Loop Engine
* Introduce `AgentTransport` and implement `PollingTransport`
* Emit events, even if chat endpoint doesn’t use them yet

### Phase 2: Session Store + Run/Event tables + Lock manager

* Implement DB-backed thread/run/event storage
* Implement lock/lease with stale recovery + idempotency keys
* Wire minimal admin debugging (at least inspect by run_id)

### Phase 3: Runner (Action Scheduler) with slice execution

* Implement slice/tick runner
* Enqueue follow-up ticks until run completes
* Ensure time budget enforcement + retry/backoff

### Phase 4: Spawn adapter + hardening

* Spawn endpoint creates thread + run and enqueues tick
* Dead-letter state after N failures
* Policy profiles by trigger (chat vs heartbeat vs spawned)

### Phase 5: Streaming transport (future)

* Add `StreamingTransport` + LLM delta emission when WP AI Client supports it
* Keep polling mode as config fallback

---

## Acceptance Criteria (UPDATED)

Keep existing acceptance criteria, and add:

* Engine supports **time-sliced** execution (`status=in_progress` + `resume_cursor`).
* Background runner can complete multi-step runs without a long-lived HTTP request.
* Event log exists and UI can poll incremental progress (minimum viable observability).
* Transport is configurable: `polling` now; `streaming` later, without loop rewrite.
* Lock/lease is safe across multiple slices and resilient to duplicate scheduler invocations.

---

### Notes / Definitions

* “Slice” = bounded unit of work (one LLM call OR bounded tool batch).
* “Transport” = how progress events are delivered (persisted polling vs streaming).

[1]: https://github.com/bradvin/clawpress/issues/12 "Refactor to reusable Agent Loop Helper for chat + heartbeat + future spawning · Issue #12 · bradvin/clawpress · GitHub"

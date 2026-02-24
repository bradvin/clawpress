# Agent Loop Findings And Gap-Closure Plan

Date: 2026-02-24  
Scope: Implementation audit of the current agent loop against `docs/agent-loop-spec-final.md`.

## Executive Summary

The current implementation is strong on architecture direction: shared loop runtime exists, slice execution exists, persistence helpers/stores are in place, polling events exist, and chat/background/spawn adapters are wired.

Main gaps are in operational hardening and parity:

1. Stale `running` runs are not surfaced by the heartbeat scanner.
2. Idempotency key dedupe is race-prone under concurrency.
3. `attempt` semantics mix continuation and retry.
4. Run lock metadata is not cleared on pause.
5. Some policy guardrail fields are defined but not enforced.
6. Chat first-slice observability is not fully parity with background/spawn.
7. Execution user identity differs between adapters.

## Findings

## 1) [P1] Stale `running` runs are not recoverable by heartbeat scanning

Spec impact:
- Violates stale lease recovery expectations in the runner lifecycle.

Current behavior:
- Claim logic supports stale reclaim in `Agent_Run_Helper::claim_run()`.
- Runnable selection only queries `status IN ('queued','paused')`.

Evidence:
- `includes/helpers/class-agent-run-helper.php` (stale reclaim logic)
- `includes/stores/class-agent-run-store.php` (`get_runnable_runs()` query)

Risk:
- If a worker crashes while a run is `running`, that run can remain orphaned indefinitely unless manually intervened.

## 2) [P1] Idempotency is check-then-insert without DB uniqueness

Spec impact:
- Weakens idempotent attempt guarantees under concurrent requests.

Current behavior:
- `create_run()` checks existing run by `(session_id, idempotency_key)` before insert.
- Schema does not enforce unique key for that pair.

Evidence:
- `includes/helpers/class-agent-run-helper.php`
- `includes/stores/class-agent-run-store.php`

Risk:
- Parallel requests can still create duplicate runs with the same idempotency key.

## 3) [P2] `attempt` semantics conflate retry with normal continuation

Spec impact:
- Retry/backoff accounting is not cleanly separated from healthy multi-slice progression.

Current behavior:
- Any claim from `paused` increments `attempt`.
- `paused` is used for both budget continuation and retry backoff.

Evidence:
- `includes/helpers/class-agent-run-helper.php`
- `includes/class-agent-runner.php`

Risk:
- Long but healthy runs can consume retry budget and receive inflated backoff after first real error.

## 4) [P2] Run lock metadata persists across pause

Spec impact:
- Conflicts with lock scope being one slice execution.

Current behavior:
- Pause updates run status but do not clear `lock_token`/lease metadata.

Evidence:
- `includes/stores/class-agent-run-store.php` (`update_progress()`)

Risk:
- Operational ambiguity and harder debugging; lock visibility no longer matches actual ownership boundaries.

## 5) [P2] Policy fields exist but are not enforced at runtime

Spec impact:
- Guardrail completeness is partial.

Current behavior:
- `max_wall_time_seconds`, `allow_background_followups`, and `on_policy_violation` are resolved in policy but mostly not executed as runtime decisions.

Evidence:
- `includes/helpers/class-policy-helper.php`
- `includes/helpers/class-abilities-helper.php`

Risk:
- Configuration surface suggests guarantees that are not actually applied.

## 6) [P3] Chat in-progress handoff loses first-slice event visibility

Spec impact:
- Reduces run/event parity across adapters.

Current behavior:
- Chat performs first slice before creating run/session rows.
- Without run/session IDs, transport is `Null_Transport`, so first-slice runtime events are dropped.

Evidence:
- `includes/helpers/class-chat-helper.php`
- `includes/helpers/class-agent-loop-helper.php`

Risk:
- Partial observability for chat-triggered long tasks.

## 7) [P3] Execution user defaults are inconsistent by adapter

Spec impact:
- Inconsistent permission posture between chat and run/spawn endpoints.

Current behavior:
- Chat uses configured agent execution user.
- `/agent/runs` and `/agent/spawn` default execution user to requesting user.

Evidence:
- `includes/helpers/class-chat-helper.php`
- `includes/rest/class-agent-run-controller.php`

Risk:
- Surprising behavior differences and possible privilege drift.

## Simplification And Deprecation Opportunities

1. Consolidate duplicate run-slice enqueue code in `Chat_Helper` and `Agent_Run_Controller` into one helper/runner service.
2. Consolidate duplicated provider/model resolution and error-classification logic shared by chat and loop helpers.
3. Remove unused constants in `Agent_Loop_Helper` (`DEFAULT_MAX_TOOL_ROUNDS`, `DEFAULT_MAX_TOOL_CALLS_PER_ROUND`) or wire them as true defaults.
4. Decide and document policy-surface truth:
   - Either fully implement `allow_background_followups` and `on_policy_violation`.
   - Or explicitly deprecate them until semantics are finalized.

## Step-By-Step Plan To Close All Gaps

## Phase 1: Correctness And Recoverability (P1)

1. Expand runnable-run selection to include stale `running` rows.
   - Update `Agent_Run_Store::get_runnable_runs()` query to include expired `running` leases.
   - Keep `claim_run()` CAS protections as final authority.

2. Add DB-level idempotency guarantee.
   - Add unique index on `(session_id, idempotency_key)` when key is present.
   - Keep nullable keys for non-idempotent runs.
   - Update `create_run()` to handle duplicate-key insert by fetching existing run and returning it.

3. Add tests for both fixes.
   - Runner test: stale `running` run is picked and reclaimed automatically.
   - Helper/controller test: simulated concurrent insert path returns one deduplicated run.

4. Update docs.
   - Update `docs/agent-loop.md` idempotency and stale-recovery sections.

## Phase 2: Retry Model And Lock Semantics (P2)

5. Split continuation from retry accounting.
   - Introduce explicit pause reason (`slice_budget`, `retry_backoff`, `session_not_claimable`).
   - Track retry counter separately from slice continuation count (schema field or structured `meta` with strict conventions).
   - Increment retry counter only on error-driven retry paths.

6. Clear run lock metadata on pause.
   - In `update_progress()`, clear `lock_token`, `claimed_by`, and lock timestamps.
   - Preserve CAS update guard by matching previous lock token in `WHERE`.

7. Add tests.
   - Continuation slices do not increase retry counter.
   - Error retries do increase retry counter and exponential backoff is based on retry counter.
   - Paused runs have lock metadata cleared.

## Phase 3: Policy Hardening And Surface Cleanup (P2)

8. Enforce `max_wall_time_seconds`.
   - Add run-level elapsed wall-time enforcement across slices.
   - Terminate with classified error/timeout once budget is exceeded.

9. Resolve policy field ambiguity.
   - Recommended: implement `on_policy_violation` modes (`deny`, `degrade`, `fail`) in `Abilities_Helper`.
   - If not implementing now, explicitly deprecate `degrade`/`fail` options and reduce config surface to enforced behavior.

10. Decide fate of `allow_background_followups`.
   - Implement as scheduler re-enqueue gate when false.
   - Or deprecate and remove from policy outputs until real behavior exists.

11. Add tests.
   - Wall-time budget enforcement path.
   - Policy mode behavior assertions.
   - Background follow-up gating behavior (or deprecation assertions).

## Phase 4: Adapter Parity And Identity Consistency (P3)

12. Create run/session before first chat slice for online path.
   - Start chat online request with run/session IDs so first-slice events are persisted.
   - If chat finishes in first slice, mark run terminal immediately.
   - Keep offline path unchanged.

13. Unify execution-user resolution.
   - Add shared resolver used by `Chat_Helper` and `Agent_Run_Controller`.
   - Default: configured agent user when available, fallback to requester.
   - Document trigger-specific overrides if needed.

14. Add tests.
   - Chat first slice emits events with valid run/session linkage.
   - Execution user is consistent across chat, run, and spawn creation.

## Phase 5: Simplification Pass

15. Refactor duplicate enqueue logic into one reusable path.
16. Refactor shared provider/model resolver + timeout classifier into one utility.
17. Remove or wire unused loop constants.
18. Refresh docs for final behavior and migration notes.

## Validation Checklist Before Merge

1. Targeted tests for all modified modules pass.
2. Full PHPUnit suite passes.
3. Lint checks pass (`npm run lint:php`).
4. Manual smoke:
   - Chat short run
   - Chat long run to `in_progress`
   - Spawn run
   - Stale lock recovery
   - Idempotent duplicate request
5. Verify event polling cursor progression for first-slice chat and resumed slices.

## Suggested Delivery Order

1. Phase 1 (critical reliability)
2. Phase 2 (state model integrity)
3. Phase 4 (adapter parity)
4. Phase 3 (policy hardening/deprecation decisions)
5. Phase 5 (cleanup/refactor)

This order minimizes production risk first, then addresses consistency and maintainability.

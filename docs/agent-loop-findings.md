# Agent Loop Findings And Gap-Closure Plan

Date: 2026-02-26  
Scope: Current outstanding gaps after implementing fixes against `docs/agent-loop-spec-final.md`.

## Executive Summary

Most originally identified gaps are now resolved in code. The remaining core issue is run-attempt semantics: continuation and retry still share the same `attempt` counter, which makes observability and retry diagnostics ambiguous.

## Outstanding Findings

## 1) [P2] `attempt` semantics still conflate continuation and retry

Spec impact:
- Retry/backoff accounting is not cleanly separated from healthy multi-slice progression.

Current behavior:
- `claim_run()` increments `attempt` for any `paused` run claim.
- `paused` is used for both normal continuation (`slice_budget`, `chat_request_budget`, `session_not_claimable`) and error retry/backoff (`retry_backoff`).

Evidence:
- `includes/helpers/class-agent-run-helper.php` (`claim_run()` pause-claim increment path)
- `includes/class-agent-runner.php` (pause reasons and retry-count handling)

Risk:
- `attempt` no longer strictly means "retry attempt".
- Operational dashboards and diagnostics can misread healthy long runs as repeated retries.

## Previously Reported Gaps Now Closed

The following items from the original findings were resolved and removed from active-gap tracking:
- stale `running` reclaimability in runnable-run selection
- idempotency race-prone check-then-insert path
- lock metadata persistence on pause
- policy runtime enforcement gaps (`max_wall_time_seconds`, `allow_background_followups`, policy violation mode handling)
- chat first-slice event visibility parity
- execution user consistency across adapters and resumed slices

## Step-By-Step Plan To Close Remaining Gap

1. Introduce distinct counters/semantics
- Keep `attempt` for true retry attempts only, or add a dedicated `retry_attempt` field.
- Track continuation slices separately (`slice_count` or `continuation_count` in run metadata/schema).

2. Update claim logic
- In `Agent_Run_Helper::claim_run()`, increment retry attempt only when prior pause reason is retry-driven (`retry_backoff`), not for normal continuation reasons.

3. Normalize pause reason contract
- Enforce a strict enum for pause reasons in one place (`slice_budget`, `chat_request_budget`, `session_not_claimable`, `retry_backoff`).
- Ensure all pause paths set one of those canonical reasons.

4. Align backoff and telemetry
- Ensure backoff decisions depend only on retry-specific counters.
- Expose both counters in run status summary for observability.

5. Add tests
- continuation pause/resume does not increment retry counter
- retry pause/resume does increment retry counter
- mixed continuation + retry sequence preserves correct values for both counters

6. Update docs
- Clarify retry vs continuation semantics in `docs/agent-loop.md` and API response docs.

## Validation Checklist Before Merge

1. Targeted tests for `Agent_Run_Helper` and `Agent_Runner` pass.
2. Full PHPUnit suite passes.
3. Lint checks pass (`npm run lint:php` and JS/CSS as applicable).
4. Manual smoke for long-run continuation + forced retry path confirms counter behavior is intuitive.


# Spec 9: Background Jobs, Observability, and Site Health

## Goal

Extend Action Scheduler usage beyond heartbeat to retention, maintenance, and diagnostics, and expose system health visibility.

## Source Requirements

- Plugin spec: 6.7, 12, 13, 15.12
- Agent spec: Level 6 and architecture acceptance criteria for retry-safe jobs

## In Scope

- Scheduled jobs for memory retention and workspace cleanup.
- Job idempotency and lock guards.
- Site Health integration and diagnostics export foundation.

## Out of Scope

- Fully autonomous write automation by default.
- Multi-site scheduler partitioning.

## Implementation Tasks

1. Scheduler expansion
- Extend `inc/heartbeat.php` with registered recurring jobs in action group `clawpress`.
- Keep jobs present via `action_scheduler_ensure_recurring_actions`.

2. Job handlers
- Add callbacks for memory compaction/purge and stale workspace cleanup.
- Add retry-safe guards with transients/options locks where needed.

3. Observability
- Emit structured logs for job lifecycle events and failures.
- Add admin diagnostics endpoint/export (redacted).

4. Site Health
- Add Site Health tests for provider status, scheduler status, and schema version.

## Acceptance Criteria

- Recurring jobs remain scheduled after activation and normal admin usage.
- Job callbacks are idempotent and safe when retried.
- Site Health reports actionable ClawPress subsystem status.

## Test Plan

- Unit: lock/idempotency helpers.
- Integration: schedule creation and ensure-recurring behavior.
- Manual: forced job retries and health check verification.

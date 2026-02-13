# Spec 10: Multi-Channel Adapters and Orchestration (Advanced)

## Goal

Add optional non-admin channels and role-based orchestration while keeping a single core planner/executor pipeline.

## Source Requirements

- Plugin spec: future-facing goals in section 14 and open questions
- Agent spec: Levels 5 and 7

## In Scope

- Feature-flagged channel adapter interface.
- Common message envelope.
- Optional orchestrator roles (Planner, Researcher, Executor, Reviewer).

## Out of Scope

- Enabling external channels by default in MVP.
- Multi-model optimization work.

## Implementation Tasks

1. Adapter interfaces
- Define channel adapter contract and message envelope mapping.
- Gate adapters behind explicit admin feature flags.

2. Orchestrator baseline
- Extend `inc/agent.php` with staged role execution and bounded budgets.
- Persist orchestration trace IDs into action logs.

3. Safety controls
- Ensure reviewer role can block unsafe plans.
- Preserve existing capability and confirmation checks in orchestrated flows.

4. Admin visibility
- Add minimal debug view for orchestration traces and channel events.

## Acceptance Criteria

- At least one non-admin channel can reuse core pipeline without code fork.
- Orchestrated runs remain bounded by max-tool/time/token budget settings.
- Safety checks cannot be bypassed by channel or orchestrator path.

## Test Plan

- Unit: envelope normalization and orchestration budget guards.
- Integration: adapter ingress to common chat pipeline.
- Manual: unsafe plan rejection by reviewer stage.

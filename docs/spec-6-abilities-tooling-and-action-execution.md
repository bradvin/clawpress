# Spec 6: Abilities API Tools and Action Execution

## Goal

Implement tool registration/execution through WordPress Abilities API with execution-user context and explicit safety classes.

## Source Requirements

- Plugin spec: 6.5, 8.1, 10.3, 15.7, 15.11
- Agent spec: Level 3 requirements and request lifecycle step 8

## In Scope

- Ability registration and allowlist.
- Tool executor abstraction in server-side PHP.
- Confirmation gating for destructive actions.

## Out of Scope

- User-defined arbitrary executable tools.
- Multi-agent orchestration.

## Implementation Tasks

1. Abilities module
- Add `inc/abilities.php` and register abilities on `wp_abilities_api_init`.
- Define standard ability shape: schema, callbacks, safety class.

2. Agent executor
- Add execution pipeline in `inc/agent.php` for tool plans.
- Enforce allowlisted `clawpress/*` ability IDs only.

3. Execution-user context
- Introduce explicit context switch helper for tool execution.
- Evaluate `permission_callback` under execution-user context.
- Always restore original context after action.

4. Confirmation flow
- Add policy checks in `inc/security.php` for `destructive` class abilities.

5. Action ledger
- Write immutable action records to `clawpress_action_logs` with requesting actor + execution actor.

## Acceptance Criteria

- Tools are discoverable through a ClawPress tools endpoint/listing.
- Mutating abilities fail without permission callback and confirmation when required.
- Action logs record actor attribution and outcomes for every tool call.

## Test Plan

- Unit: ability registration and allowlist enforcement.
- Integration: tool execution with and without confirmation.
- Manual: permission differences between requesting user and agent user.

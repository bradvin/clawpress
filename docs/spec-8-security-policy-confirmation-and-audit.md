# Spec 8: Security Policy, Confirmation Gates, and Audit

## Goal

Centralize policy enforcement for capability checks, confirmation requirements, sanitization, and immutable audit logging.

## Source Requirements

- Plugin spec: section 8, section 10 safety clauses, section 12 logging
- Agent spec: security requirements across Levels 0-4 and acceptance section 5

## In Scope

- Policy gate service for chat and tool actions.
- Confirmation token/prompt handling for destructive actions.
- Structured audit logging standard.

## Out of Scope

- Third-party SIEM integrations.
- Full enterprise compliance export formats.

## Implementation Tasks

1. Security module
- Add `inc/security.php` with reusable policy checks.
- Enforce nonce, capability, rate-limit (if added), and confirmation gates.

2. REST hardening
- Ensure all routes in `inc/rest-api.php` define `permission_callback` and strict args.
- Standardize error envelope for policy failures.

3. Output safety
- Ensure server-side escaping and JS-side markdown rendering sanitization.

4. Audit model
- Standardize log payload: request ID, thread ID, requesting actor, execution actor, action, result, timestamp.
- Make action log writes append-only in normal operations.

## Acceptance Criteria

- All mutating paths enforce capability + confirmation policy.
- Denied operations return consistent machine-readable errors.
- Audit data is sufficient to reconstruct who requested vs who executed an action.

## Test Plan

- Unit: policy branches and confirmation checks.
- Integration: deny/allow transitions per capability.
- Manual: audit trail inspection for mixed read/write actions.

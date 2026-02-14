# Spec 3: Onboarding

## Goal

Implement chat-led onboarding state machine which guides the user through the initial setup process.

## Source Requirements

- Plugin spec: 5.2, 6.3, 9, 15.2, 15.8, 15.13
- Agent spec: Level 0 onboarding constraints and section 3 request lifecycle step 4

## In Scope

- Onboarding state machine storage and progression.
- Provider and model selection. If providers not setup then include link to `wp-admin/options-general.php?page=wp-ai-client`
- Execution user selection.
- Provision `SOUL.md`, `AGENTS.md`, `USER.md`, `HEARTBEAT.md` from template files.

## Out of Scope

- Rich conversational onboarding copy generation by LLM.
- Multi-site onboarding variations.

## Implementation Tasks

1. Onboarding module
- Add `includes/onboarding.php` with state transitions and resumable progress.
- Persist onboarding state to settings.

2. REST contract
- Add `GET /onboarding` and `POST /onboarding` handlers in `includes/rest/class-onboarding-controller.php`.
- Validate transitions server-side; reject invalid jumps.

3. Execution user flow
- Add helpers to list eligible users and validate selected user.
- Store `clawpress_execution_user_id` option.
- Return setup-required status when mutating operations are attempted without this value.

4. Template bootstrap
- Implement idempotent bootstrap from `docs/templates/*` into `clawpress_agent_file` posts.
- Set author to execution user for agent-created records.
- Mark `SOUL.md` protected using post meta.
- Track template version via `clawpress_onboarding_templates_version`.

5. UI integration
- Add `OnboardingFlow` component to guide first-run steps and resume.

## Acceptance Criteria

- Fresh install enters onboarding and can resume after reload.
- Onboarding cannot complete until settings are valid.
- Existing agent-file records are not overwritten if they already exist.

## Test Plan

- Unit: transition guards, template create-if-missing logic.
- Integration: onboarding REST progression and idempotent re-run.
- Manual: skip provider setup and still finish offline path.

# Spec 1: Foundation Chat Panel and Status API

## Goal

Upgrade the existing floating chat panel implementation so it becomes the stable MVP entrypoint across `wp-admin`, with a first-class status API contract and consistent panel behavior.

## Source Requirements

- Plugin spec: sections 4.1, 5.1, 6.1, 6.2, 7 (`GET /status`), 15.1-15.3
- Agent spec: Level 0 preconditions and architecture section 3.1 steps 1-5

## Current Implementation Snapshot

- Floating panel is already registered globally via `includes/class-panel.php`.
- Panel UI is already mounted from `src/panel/index.jsx` and implemented in `src/panel/Panel.jsx`.
- Admin bar toggle exists (`#wp-admin-bar-clawpress-toggle`) with fallback floating toggle.
- Chat REST controller currently exposes `/chat/message` and `/chat/history` in `includes/rest/class-chat-controller.php`.
- Panel client currently expects `/stream` and `/run-tool` in `src/panel/services/realClient.js`, which are not yet registered in REST controllers.

## In Scope

- Changes to the existing panel shell and interaction model (not a new shell).
- `GET /clawpress/v1/status` endpoint with deterministic mode/provider/onboarding summary.
- API alignment between panel client and currently registered REST routes.
- Persistent panel state for open/closed, width, and last-used thread/history pointer.
- Capability-gated panel visibility on all admin pages.

## Out of Scope

- Full tool runtime and execution policy implementation.
- Multi-channel adapters.
- Background jobs and memory retention work (handled by later specs).

## Implementation Tasks

1. PHP bootstrap
- Keep `includes/class-panel.php` as the single panel bootstrap module.
- Ensure panel assets are only enqueued for authorized users and all admin screens except explicit exclusions (if any).
- Keep `includes/class-admin-page.php` as settings/control center without duplicating panel rendering logic.

2. REST endpoint
- Add `GET /status` through existing controller architecture (`includes/class-rest-api.php` + route controller class).
- Add strict args and `permission_callback`.
- Return stable envelope keys: `mode`, `provider`, `model`, `onboarding`, `memory`, `agent_user`.

3. Existing panel API alignment
- Decide one MVP transport path and align both sides:
- Option A: Panel uses `/chat/message` + `/chat/history` now.
- Option B: Implement `/stream` + `/run-tool` now.
- Remove dead-path assumptions so panel can always send and receive messages in MVP.

4. Panel UX/state hardening (existing `src/panel/*`)
- Add status badge and mode indicator in panel header (`online/offline`).
- Load and render persisted history on open via `/chat/history`.
- Keep local `localStorage` fallback but persist canonical panel state in `user_meta` through REST where appropriate.
- Standardize keyboard shortcut to plugin spec default (`Cmd/Ctrl + K`) or expose configurable shortcut setting.

5. Security and request consistency
- Ensure all panel-origin requests include WP REST nonce and receive consistent error envelope.
- Ensure panel is hidden when capability check fails, and REST endpoints deny access with clear status.
- Keep response rendering escaped/sanitized for system/error messages shown in panel.

## Acceptance Criteria

- Authorized users can open the existing floating panel from any eligible admin screen.
- Panel can send at least one message/response cycle through aligned chat endpoint(s) without transport mismatch.
- Header visibly reflects status from `/status` and updates when provider state changes.
- User panel state persists across reloads (open/closed, width, last context).
- Unauthorized users do not receive panel UI or successful REST responses.

## Test Plan

- Unit
- Status payload builder and capability gate helpers.
- Panel state serialization/deserialization (REST + local fallback).
- Integration
- REST `GET /status` success + permission denial.
- Panel-client transport path test for selected MVP endpoint strategy.
- Manual
- Toggle panel from admin bar and fallback button.
- Verify message send/reply and history restore after page reload.
- Verify keyboard shortcut behavior and collision checks on common admin screens.

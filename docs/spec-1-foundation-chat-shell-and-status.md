# Spec 1: Foundation Chat Shell and Status API

## Goal

Deliver the baseline in-admin ClawPress surface available on all allowed `wp-admin` screens, with a working status API and global chat launcher shell.

## Source Requirements

- Plugin spec: sections 4.1, 5.1, 6.1, 6.2, 7 (`GET /status`), 15.1-15.3
- Agent spec: Level 0 preconditions and architecture section 3.1 steps 1-5

## In Scope

- Global launcher + drawer mount outside the current single-page ClawPress menu.
- `GET /clawpress/v1/status` endpoint with deterministic site/provider/onboarding summary.
- Per-user UI state persistence for launcher open/closed and last thread pointer.
- Capability-gated chat visibility on all admin pages.

## Out of Scope

- Full message send pipeline.
- Tool execution.
- Provider calls.

## Implementation Tasks

1. PHP bootstrap
- Update `inc/admin-page.php` or add `inc/chat-ui.php` to enqueue global chat assets on admin pages for authorized users.
- Keep existing admin menu page intact as settings/control center.

2. REST endpoint
- Extend `inc/rest-api.php` with `GET /status`.
- Add strict route arg schema and `permission_callback`.
- Return a stable JSON envelope: `mode`, `provider`, `onboarding`, `memory`, `execution_user`.

3. JS UI shell
- Add components: `ChatLauncher`, `ChatWindow`, `StatusBadge` under `src/js/admin/components/`.
- Add state store/hook for open/close and bootstrap status fetch.
- Add keyboard shortcut (`Cmd/Ctrl + K`) and user setting fallback.

4. Settings persistence
- Add `user_meta` keys for chat UI state.
- Create REST hooks for reading/writing minimal UI state if needed.

5. Wiring
- Ensure entrypoint (`src/js/admin/index.js`) supports both current admin page app and global launcher mount point.

## Acceptance Criteria

- Authorized users see launcher across admin screens.
- Launcher opens drawer without navigation.
- Status badge reflects offline/online readiness from `/status`.
- Unauthorized users never receive launcher markup or REST access.

## Test Plan

- Unit: status response builder, capability gate helpers.
- Integration: REST `GET /status` success and permission denial.
- Manual: launcher behavior on Posts, Pages, Plugins, Settings screens.

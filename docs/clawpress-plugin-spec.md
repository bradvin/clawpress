# ClawPress Plugin Specification

Version: 0.1 (Draft)
Owner: ClawPress
Status: Proposed
Minimum WordPress: 6.9

## 1) Product Vision

ClawPress is an in-admin AI agent for WordPress that can reason and take actions safely inside a site. It is inspired by OpenClaw, but built around WordPress-first architecture, permissions, and UX.

Core promise: "The AI for WordPress that actually does things" while remaining secure by default.

### 1.1 Product Philosophy & Purpose

ClawPress is meant to be helpful. It should feel like a helpful assistant, not a tool. It should be easy to setup, use and understand. It should be secure by default and easy to audit. It should be locked down to start with, and allow you to unlock more permissions, tools, and capabilities as needed. It should be easy to extend and customize.

## 2) Goals and Non-Goals

### Goals

1. Runs fully inside WordPress admin and is accessible from any admin page.
2. Provides persistent memory across sessions, users, and tasks (with privacy controls).
3. Is secure by default with explicit capability checks, auditability, and safe action confirmation.
4. Autonomous proactive agent that is useful from day 1.
5. Delivers simple chat-led onboarding that works even when no LLM provider is configured.
6. Uses modern WordPress best practices for architecture, APIs, and UI.
7. Uses `wordpress/php-ai-client` as the core LLM abstraction layer.
8. Uses the WordPress Abilities API for all ClawPress tool registration and execution authorization.

### Non-Goals

ClawPress will not ever be:

1. A frontend visitor chat widget / chatbot.
2. A website generation tool as it's primary focus.


## 3) Primary Users

1. Site Admin: installs plugin, configures provider, manages permissions, reviews logs.
2. Editor/Manager (optional): can use approved read/write actions based on granted capabilities.
3. Developer: extends commands/tools and integrates custom workflows.

## 4) High-Level Feature Set

### 4.1 Runs Inside Your WP

- Floating chat launcher visible on all wp-admin screens.
- Chat drawer that opens without navigating away from current admin context.
- Context awareness from current admin screen (post edit, plugin list, settings, etc.).

### 4.2 Persistent Memory

- Stores conversation history by thread.
- Stores durable memory facts/summaries tied to user/site/project scopes.
- Supports retrieval into prompts with recency + relevance strategy.
- Supports retention settings, export, and deletion.

### 4.3 Agent Files (Editable Workspace)

- Uses `agent-file` CPT as primary store for editable agent files (`SOUL.md`, playbooks, snippets).
- Supports user-created files that the agent can reference in prompts and tool execution.
- All file lookups must run through built-in `file_read` tool behavior: resolve `agent-file` CPT first, then fallback scan in agent workspace under uploads.

### 4.4 Secure by Default

- Least privilege capability model for every endpoint and action.
- Tool authorization and execution policy enforced via WordPress Abilities API.
- Offline mode as default until provider is configured.
- Agent actions execute as a selected WordPress agent user (recommended low-privilege dedicated account).
- Confirmation step for destructive actions.
- Full audit trail for agent actions (requesting actor + execution actor).
- Input/output sanitization and escaped rendering across admin UI.

### 4.5 Simple Onboarding

- Chat-first onboarding wizard (inside chat) starts on first launch.
- Detects provider setup state and shows online/offline status.
- Offers built-in offline commands so plugin remains useful pre-LLM.
- Bootstraps core agent context files from packaged templates into `agent-file` CPT.
- Clear completion criteria and health check at the end.

## 5) User Experience Requirements

### 5.1 Chat Availability

1. Chat launcher appears on every admin page where user has permission to access ClawPress.
2. Launcher state persists per user (open/closed, last thread, size).
3. Keyboard shortcut opens chat (configurable, default `Cmd/Ctrl + K`).

### 5.2 First-Run Onboarding (in chat)

1. Detect current setup:
   - LLM configured? yes/no
   - required capabilities present?
   - memory enabled?
   - agent user configured? yes/no
   - `SOUL.md` configured? yes/no
   - core onboarding files provisioned? yes/no
2. If no LLM configured, show Offline status and guide user through:
   - provider selection
   - API key storage
   - test connection
3. Include execution-user selection/setup step:
   - choose existing WP user or create/select dedicated low-privilege service user
   - persist selected agent user per site
   - selected agent user becomes the author for agent-created files
   - agent file access is isolated to that agent user's allowed files/workspace
4. Provision onboarding files into `agent-file` CPT from `docs/templates/`:
   - `SOUL.md`
   - `AGENTS.md`
   - `USER.md`
   - `HEARTBEAT.md`
5. Provisioning rules:
   - create if missing (idempotent)
   - do not overwrite existing file content unless user explicitly chooses reset
   - mark `SOUL.md` as protected
   - set file author to configured agent user
6. If user skips provider setup, onboarding continues with offline command tutorial.
7. Onboarding is resumable and persists progress.

### 5.3 Offline Mode

When no LLM is configured, chat must still accept deterministic built-in commands.

Minimum command set for MVP:

- `/help` -> list available commands.
- `/status` -> show plugin, provider, memory, and permissions status.
- `/onboarding start|resume|reset` -> manage onboarding.
- `/memory list` -> list saved memory entries (if enabled).
- `/memory clear` -> clear memory (with confirmation).
- `/site info` -> show site name, URL, WP version, plugin version.
- `/tools list` -> show available agent tools/actions and whether enabled.

Command responses must be produced locally without external LLM calls.

## 6) Technical Architecture

### 6.1 Plugin Modules (PHP)

- `inc/admin-page.php`: admin shell and script enqueue.
- `inc/rest-api.php`: routes for chat, onboarding, memory, settings, status.
- `inc/heartbeat.php`: heartbeat lifecycle wiring and Action Scheduler registrations.
- New modules proposed:
  - `inc/abilities.php` (Abilities API tool registration and ability lookup helpers)
  - `inc/ai-client.php` (php-ai-client integration)
  - `inc/agent.php` (planner/executor orchestration)
  - `inc/soul.php` (`SOUL.md` resolution/validation and prompt assembly helper)
  - `inc/files.php` (`agent-file` CPT handlers + resolver + file tools)
  - `inc/workspace.php` (single workspace location resolver)
  - `inc/commands.php` (offline command parser/handlers)
  - `inc/memory.php` (memory persistence and retrieval)
  - `inc/security.php` (policy checks, confirmations, audit logger)
  - `inc/onboarding.php` (state machine, template file bootstrap, and step content)

### 6.2 JavaScript Admin App

- Add global chat UI mounted via admin enqueue on all admin screens.
- Use WordPress packages (`@wordpress/components`, `@wordpress/data`, `@wordpress/api-fetch`, `@wordpress/i18n`).
- Components:
  - `ChatLauncher`
  - `ChatWindow`
  - `MessageList`
  - `Composer`
  - `StatusBadge` (online/offline)
  - `OnboardingFlow`

### 6.3 Data Storage

Use Option A (hybrid) for v1:

- Custom tables for high-volume operational data:
  1. `{$wpdb->prefix}clawpress_threads`
  2. `{$wpdb->prefix}clawpress_messages`
  3. `{$wpdb->prefix}clawpress_action_logs`
- Custom post types for editor-friendly, queryable entities:
  - `clawpress_memory`
  - `clawpress_skill`
  - `agent-file`
- Filesystem workspace for agent file operations, with secure randomized paths.
- `wp_options` via `register_setting()` for plugin settings and retention defaults.
- `user_meta` for per-user workspace mapping and per-user onboarding/chat UI state.
- Additional options for agent behavior:
  - `clawpress_active_soul_file` (active `SOUL.md` file reference)
  - `clawpress_agent_user_id` (selected WP user for agent action execution)
  - `clawpress_onboarding_templates_version` (tracks template bootstrap version applied)

#### 6.3.1 Memory Storage (`clawpress_memory` CPT)

Memory is stored in a private CPT to enable visual admin workflows, built-in WP querying, revisions, and capability control.

- CPT config: `public=false`, `show_ui=true`, `show_in_rest=false` (v1), custom capabilities.
- Scope model (hybrid default):
  - Global/shared memory
  - User-private memory
- Suggested post meta:
  - `clawpress_scope` (`global|user`)
  - `clawpress_owner_user_id` (nullable for global)
  - `clawpress_visibility`
  - `clawpress_importance`
  - `clawpress_expires_at` (UTC)
  - `clawpress_last_used_at` (UTC)
  - `clawpress_tags` (array/string)

#### 6.3.2 Skill Storage (`clawpress_skill` CPT)

Skills are stored in a private CPT so admins can manage and lock them down visually.

- CPT config: `public=false`, `show_ui=true`, custom capabilities.
- Suggested post meta:
  - `clawpress_skill_handler` (registered handler slug/class)
  - `clawpress_ability_id` (registered ability identifier, e.g. `clawpress/file_read`)
  - `clawpress_required_capability`
  - `clawpress_enabled` (`0|1`)
  - `clawpress_safety_level`
  - `clawpress_config_json`
- Runtime uses allowlisted handlers; CPT stores configuration/policy, not arbitrary executable code.

#### 6.3.3 Agent File Storage (`agent-file` CPT)

Agent files are stored as a private CPT to provide editable file-like content with built-in WordPress revisions, access controls, and auditability.

- CPT config: `public=false`, `show_ui=true`, custom capabilities.
- Canonical file identity:
  - logical file path (e.g., `SOUL.md`, `playbooks/editorial.md`)
  - unique normalized slug/path key
- Suggested post meta:
  - `clawpress_file_path` (normalized logical path)
  - `clawpress_file_type` (`policy|prompt|playbook|note|other`)
  - `clawpress_file_protected` (`0|1`)
  - `clawpress_file_owner_user_id`
  - `clawpress_file_scope` (`global|user`)
  - `clawpress_file_checksum`
  - `clawpress_file_last_used_at` (UTC)

Resolver order for built-in file tools:

1. All file lookups are executed through built-in `file_read` tool semantics.
2. Look up `agent-file` CPT by normalized path.
3. If not found, run fallback scan in workspace filesystem under resolved uploads workspace root.

Authoring and isolation rules:

- Files created by the agent are authored by the configured agent user.
- Agent reads/writes are restricted to that agent user's file/workspace scope.
- Agent must not access files/workspaces owned by other users.

Onboarding bootstrap files (created in `agent-file` CPT):

1. `SOUL.md` (protected, required)
2. `AGENTS.md` (required)
3. `USER.md` (required)
4. `HEARTBEAT.md` (required for heartbeat polling behavior)

### 6.4 AI Provider Integration (`wordpress/php-ai-client`)

Required dependency:

```bash
composer require wordpress/php-ai-client
```

Integration rules:

1. All model calls pass through a single adapter service.
2. Use provider/model selection from validated settings.
3. Build prompts from server-side policy + resolved `SOUL.md` from file resolver + retrieved memory/context.
4. Log model metadata and token/cost metadata (if available) in action logs.
5. Fail gracefully to Offline mode when provider config is missing/invalid.

### 6.5 Tool System Integration (WordPress Abilities API)

All ClawPress tools must be registered as abilities and executed through the Abilities API.

Integration rules:

1. Register abilities on `wp_abilities_api_init` using `wp_register_ability()`.
2. All tool invocations resolve to ability IDs (no direct handler execution by user-supplied slug).
3. Every mutating ability must define `permission_callback`.
4. All abilities must define `input_schema` and `output_schema`.
5. Ability checks/evaluation run in execution-user context for tool actions.
6. Restrict execution to allowlisted ClawPress ability IDs (namespace `clawpress/*`).

### 6.6 Workspace File Storage

Default workspace location is under uploads:

- Base path: `wp-content/uploads/clawpress/`
- Workspace root per user/session: `ws/{site_hash}/{user_hash}/{workspace_token}/`
- `workspace_token` must be high-entropy random bytes (non-guessable).
- Store workspace mapping in `user_meta`; never expose absolute paths in client responses.
- Single-site first in v1; multisite support deferred to later phase.
- Use a single workspace location resolver (`inc/workspace.php`) as the only way to derive workspace paths.
- File tools and file routes must not construct workspace paths directly.
- Resolver API must be stable so path strategy can evolve later (for multi-agent layouts).

Retention:

- Configurable TTL (default policy) with Action Scheduler purge jobs for expired files and stale workspace directories.

### 6.7 Background Task Scheduling (Action Scheduler)

ClawPress uses Action Scheduler for background jobs instead of WP-Cron.

Integration rules:

1. Initialize Action Scheduler before scheduling jobs and wait for `action_scheduler_init`.
2. Schedule recurring jobs with `as_schedule_recurring_action()` under action group `clawpress`.
3. Queue immediate background jobs with `as_enqueue_async_action()` when needed.
4. Keep recurring jobs present via `action_scheduler_ensure_recurring_actions`.
5. Ensure every job callback is idempotent and safe to retry.


## 7) REST API Contract (MVP)

Namespace: `clawpress/v1`

Required routes:

1. `GET /status`
   - Returns online/offline, provider state, memory state, onboarding completion.
2. `POST /chat/message`
   - Accepts message and thread id.
   - Routes to offline command engine or online LLM agent engine.
3. `GET /chat/threads`
4. `POST /chat/threads`
5. `GET /memory`
6. `DELETE /memory`
7. `GET /onboarding`
8. `POST /onboarding`
   - Handles state progression and template file bootstrap into `agent-file` CPT.
9. `GET /tools`
10. `POST /settings/provider`
11. `POST /settings/agent` (manage editable `SOUL.md` and execution-user settings)
12. `GET /files`
13. `POST /files`
14. `GET /files/(?P<path>...)`
15. `DELETE /files/(?P<path>...)`

Endpoint requirements:

- `permission_callback` required on all routes.
- Strict arg schemas with sanitize + validate callbacks.
- `WP_REST_Response` with consistent error envelope.

## 8) Security Requirements

1. Capability gates:
   - `manage_options` for provider settings and destructive global operations.
   - configurable lower role capability for non-destructive chat usage.
   - all ClawPress tools must enforce Abilities API permission callbacks.
2. CSRF protection:
   - use WP REST nonce for all authenticated requests.
3. Output safety:
   - escape all rendered text in PHP templates.
   - sanitize markdown/chat rendering in JS.
4. Secret handling:
   - never expose API keys in localized script objects or REST responses.
   - store encrypted at rest where environment permits.
5. Action safety:
   - require explicit confirmation for destructive actions.
   - execute mutating actions as configured agent user, not requesting chat user.
   - keep immutable action log with requesting actor, execution actor, timestamp, ability/tool, args hash, outcome.
6. Prompt safety:
   - apply system constraints and tool-use policy server-side (not client-only).
   - load `SOUL.md` via server-side file resolver; clients cannot override it directly.
7. Workspace file security:
   - treat workspace files as private by default.
   - add `index.php` and server deny rules where possible.
   - do not rely on obscurity alone; enforce capability checks for any file read/download route.
   - sanitize file names and enforce path traversal protections.
   - enforce execution-user workspace isolation for all file operations.
8. Agent-file security:
   - enforce custom capabilities for create/read/update/delete on `agent-file` CPT.
   - mark protected files (including `SOUL.md`) and require elevated capability + confirmation for edits.
   - canonicalize file paths and block traversal/symlink escapes before filesystem fallback.


## 9) Onboarding State Machine

States:

1. `welcome`
2. `permissions`
3. `agent_user_setup`
4. `agent_files_setup`
5. `provider_setup`
6. `connection_test`
7. `memory_preferences`
8. `offline_commands_tutorial`
9. `ready`

Rules:

- If provider not configured, `provider_setup` is required before online status.
- `agent_user_setup` is required before mutating actions are allowed.
- `agent_files_setup` creates required onboarding files in `agent-file` CPT using templates.
- Onboarding cannot reach `ready` unless required onboarding files exist.
- `offline_commands_tutorial` is always available and can complete onboarding even offline.
- State persisted per site with per-user completion metadata.

## 10) Agent Behavior (MVP)

1. In Offline mode:
   - command parser executes deterministic handlers only.
2. In Online mode:
   - intent classification decides between command/tool/LLM response.
   - LLM suggestions that trigger site-changing actions require confirmation.
3. Tool execution:
   - use allowlisted internal tools only.
   - register and execute tools through Abilities API only.
   - pass minimal context and capability-limited execution.
   - execute as configured agent user and evaluate action capability in that context.
   - resolve files via `agent-file` CPT first; filesystem workspace only as fallback.

## 11) WordPress Best Practices Checklist

1. `declare( strict_types=1 )` and namespaced modules.
2. Use `register_activation_hook()` for table creation/migrations.
3. Use `dbDelta()` with versioned schema migration option.
4. Use `register_setting()` and sanitize callbacks for admin settings.
5. Use nonces + capability checks in REST and admin actions.
6. Use i18n functions for user-facing strings.
7. Follow WPCS; lint PHP/JS/CSS in CI.
8. Do not edit generated build asset files manually.
9. Register and enforce ClawPress tools through Abilities API only.
10. Use Action Scheduler for background work; do not rely on ad-hoc WP-Cron hooks.

## 12) Observability and Supportability

1. Add Site Health section for ClawPress:
   - provider connectivity
   - background task status
   - DB schema version
2. Structured logs for:
   - chat requests
   - command invocations
   - tool/ability executions
   - requesting actor vs execution actor attribution
   - file resolution source (`agent-file|workspace`) where relevant
   - Action Scheduler job lifecycle events
   - provider failures/timeouts
3. Admin diagnostics export (redacted).

## 13) Performance Targets (MVP)

1. Chat open interaction latency < 100ms on typical admin screens.
2. Offline command response target < 500ms.
3. Online first-token feedback < 2s when provider supports it.
4. Memory retrieval query p95 < 200ms for up to 50k memory rows.

## 14) Phased Delivery Plan

### Phase 1: Foundation

- Chat shell on all admin pages.
- Status API + provider settings.
- Offline command engine.
- Chat-first onboarding v1.

### Phase 2: Online AI

- `php-ai-client` integration.
- Basic agent routing with confirmation gate.
- Persistent chat threads/messages.

### Phase 3: Memory + Safety Hardening

- Durable memory retrieval + retention.
- Action logging and diagnostics.
- Site Health integration.

## 15) MVP Acceptance Criteria

1. User can open chat from any admin page.
2. Fresh install shows onboarding in chat.
3. With no provider configured, status is Offline and offline commands work.
4. User can configure provider and send a successful online prompt.
5. Chat history persists across page reloads.
6. Memory can be viewed and cleared from command or settings UX.
7. Every mutating action is permission-checked and logged with requesting + execution actor IDs.
8. Mutating actions are blocked until onboarding sets agent user.
9. Agent identity policy is sourced from editable protected `SOUL.md` file.
10. Agent can create and reference user files; built-in file tools resolve `agent-file` CPT first and workspace second.
11. All ClawPress tools are registered and authorized via Abilities API.
12. Background jobs run through Action Scheduler and are observable/retry-safe.
13. Onboarding creates `SOUL.md`, `AGENTS.md`, `USER.md`, and `HEARTBEAT.md` in `agent-file` CPT.

## 16) Decision Log and Open Questions

Resolved for v1:
1. Storage model: Option A hybrid (custom tables + CPT + filesystem workspace).
2. Memory scope default: hybrid (global + user-private).
3. Skills management: `clawpress_skill` CPT + meta.
4. Workspace location: uploads subdirectory with non-guessable randomized folder names.
5. Retention baseline: configurable TTL with Action Scheduler purge jobs.
6. Multisite: single-site first.
7. Agent action model: execute actions as selected WP agent user (recommended low-privilege dedicated account).
8. Identity model: `SOUL.md` is an editable protected file in `agent-file` CPT and is injected server-side through resolver.
9. File model: built-in file tools resolve `agent-file` CPT first, with workspace filesystem fallback.
10. Tool model: all ClawPress tools are abilities (Abilities API).
11. Background scheduling model: Action Scheduler (not WP-Cron).
12. Onboarding file model: required context files are provisioned from `docs/templates/` into `agent-file` CPT.

Still open:
1. Which capabilities should map to non-admin chat users in v1?
2. Which provider(s) are first-class in onboarding presets?
3. Is streaming response required in MVP or phase 2?

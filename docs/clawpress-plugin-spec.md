# ClawPress Plugin Specification

Version: 0.1 (Draft)
Owner: ClawPress
Status: Proposed
Minimum WordPress: 6.9

## 1) Product Vision

ClawPress is an in-admin AI agent for WordPress that can reason and take actions safely inside a site. It is inspired by OpenClaw, but built around a WordPress-first architecture, permissions, and UX.

Core promise: "The AI for WordPress that actually does things" while remaining secure by default.

### 1.1 Product Philosophy & Purpose

ClawPress is meant to be helpful. It should feel like a helpful assistant, not a tool. It should be easy to setup, use and understand. It should be secure by default and easy to audit. It should be locked down to start with, and allow you to unlock more permissions, tools, and capabilities as needed. It should be easy to extend and customize.

## 2) Goals and Non-Goals

### Goals

1. Runs fully inside WordPress admin and is accessible from any admin page.
2. Provides persistent memory across sessions, users, and tasks (with privacy controls).
3. Is secure by default with explicit capability checks, auditability, and safe action confirmation.
4. Autonomous proactive agent that is useful from day 1.
5. Delivers simple chat-led setup that works even when no LLM provider is configured.
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

### 4.5 Simple Setup

- Chat-first setup wizard (inside chat) starts on first launch.
- Detects provider setup state and shows online/offline status.
- Offers built-in offline commands so plugin remains useful pre-LLM.
- Bootstraps core agent context files from packaged templates into `agent-file` CPT.
- Clear completion criteria and health check at the end.

## 5) User Experience Requirements

### 5.1 Chat Availability

1. Chat launcher appears on every admin page where user has permission to access ClawPress.
2. Launcher state persists per user (open/closed, last thread, size).
3. Keyboard shortcut opens chat (configurable, default `Cmd/Ctrl + K`).

### 5.2 First-Run Setup (in chat)

1. Detect current setup:
   - LLM configured? yes/no
   - required capabilities present?
   - memory enabled?
   - agent user configured? yes/no
   - `SOUL.md` configured? yes/no
   - core setup files provisioned? yes/no
2. If no LLM configured, show Offline status and guide user through:
   - provider selection
   - API key storage
   - test connection
3. Include execution-user selection/setup step:
   - choose existing WP user or create/select dedicated low-privilege service user
   - persist selected agent user per site
   - selected agent user becomes the author for agent-created files
   - agent file access is isolated to that agent user's allowed files/workspace
4. Provision setup files into `agent-file` CPT from `docs/templates/`:
   - `SOUL.md`
   - `AGENTS.md`
   - `USER.md`
   - `HEARTBEAT.md`
5. Provisioning rules:
   - create if missing (idempotent)
   - do not overwrite existing file content unless user explicitly chooses reset
   - mark `SOUL.md` as protected
   - set file author to configured agent user
6. If user skips provider setup, setup continues with offline command tutorial.
7. Setup is resumable and persists progress.

### 5.3 Offline Mode

When no LLM is configured, chat must still accept deterministic built-in commands.

Minimum command set for MVP:

- `/help` -> list available commands.
- `/status` -> show plugin, provider, memory, and permissions status.
- `/setup start|resume|reset` -> manage setup.
- `/memory list` -> list saved memory entries (if enabled).
- `/memory clear` -> clear memory (with confirmation).
- `/site info` -> show site name, URL, WP version, plugin version.
- `/tools list` -> show available agent tools/actions and whether enabled.

Command responses must be produced locally without external LLM calls.

## 6) Technical Architecture

### 6.1 Plugin Modules (PHP)

- `inc/admin-page.php`: admin shell and script enqueue.
- `inc/rest-api.php`: routes for chat, setup, memory, settings, status.
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
  - `inc/setup.php` (state machine, template file bootstrap, and step content)

### 6.2 JavaScript Admin App

- Add global chat UI mounted via admin enqueue on all admin screens.
- Use WordPress packages (`@wordpress/components`, `@wordpress/data`, `@wordpress/api-fetch`, `@wordpress/i18n`).
- Components:
  - `ChatLauncher`
  - `ChatWindow`
  - `MessageList`
  - `Composer`
  - `StatusBadge` (online/offline)
  - `SetupFlow`

### 6.3 Data Storage

Use Option A (hybrid) for v1:

- Custom table for action_log:
  - `{$wpdb->prefix}clawpress_action_logs`
- Custom post types for editor-friendly, queryable entities:
  - `clawpress_agent_file`
  - `clawpress_agent_mem`
  - `clawpress_agent_skill`
- Filesystem workspace for agent file operations, with secure randomized paths.

#### 6.3.1 Agent File Storage (`clawpress_agent_file` CPT)

Agent files are stored as a private CPT to provide editable file-like content with built-in WordPress revisions, access controls, and auditability.

- CPT config: `public=false`, `show_ui=true`, custom capabilities.
- Canonical file identity:
  - logical file path (e.g., `SOUL.md`, `playbooks/editorial.md`)
  - unique normalized slug/path key

#### 6.3.2 Memory Storage (`clawpress_agent_mem` CPT)

Memory is stored in a private CPT to enable visual admin workflows, built-in WP querying, revisions, and capability control.

#### 6.3.3 Skill Storage (`clawpress_agent_skill` CPT)

Skills design is still to be determined.

Resolver order for built-in file tools:

1. All file lookups are executed through built-in `file_read` tool semantics.
2. Look up `clawpress_agent_file` CPT by normalized path.
3. If not found, run fallback scan in workspace filesystem under resolved uploads workspace root.

Authoring and isolation rules:

- Files created by the agent are authored by the configured agent user.
- Agent reads/writes are restricted to that agent user's file/workspace scope.
- Agent must not access files/workspaces owned by other users.

Setup bootstrap files (created in `agent-file` CPT):

1. `SOUL.md` (required)
2. `AGENTS.md` (required)
3. `USER.md` (required)
4. `HEARTBEAT.md` (required for heartbeat polling behavior)
5. `BOOTSTRAP.md` (required for bootstrap behavior, then deleted)

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
- Workspace root per user/session: `/{user_id}/{workspace_token}/`
- `workspace_token` must be high-entropy random bytes (non-guessable).
- Store workspace mapping in `user_meta`; never expose absolute paths in client responses.
- Single-site first in v1; multisite support deferred to later phase.
- Use a single workspace location resolver (`class-workspace-helper.php`) as the only way to derive workspace paths.
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
   - Returns online/offline, provider state, memory state, setup completion.
2. `POST /chat/message`
   - Accepts message and thread id.
   - Routes to offline command engine or online LLM agent engine.
3. `GET /chat/threads`
4. `POST /chat/threads`
5. `GET /memory`
6. `DELETE /memory`
7. `GET /setup`
8. `POST /setup`
   - Handles state progression and template file bootstrap into `agent-file` CPT.
9. `GET /tools`
10. `POST /settings/provider`
11. `POST /settings/agent` (manage editable `SOUL.md` and execution-user settings)

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

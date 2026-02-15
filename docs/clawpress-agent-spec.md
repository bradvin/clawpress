# ClawPress Agent Specification

Version: 0.1 (Draft)
Owner: ClawPress
Status: Proposed
Minimum WordPress: 6.9
Depends on: `docs/clawpress-plugin-spec.md`
Reference Guide: https://gist.github.com/dabit3/bc60d3bea0b02927995cd9bf53c3db32

## 1) Purpose

This document defines how ClawPress should implement an agent using the same step-by-step progression from the reference guide, adapted for WordPress architecture, permissions, and admin UX.

Primary objective: ship a practical, secure, in-admin WordPress agent that is useful from day 1 and grows in capability by levels.

Execution model requirement: all agent actions that read/write WordPress state must execute as a selected WordPress agent user (chosen during setup), not as the requesting chat user. This agent user should default to a dedicated low-permission account.

File model requirement: all file lookups run through built-in `file_read` tool semantics: resolve `agent-file` CPT first, then fallback scan in the resolved workspace under uploads.

## 2) ClawPress Agent Build Ladder (Adapted)

The implementation must follow these levels in order. Each level is deployable on its own and adds one new class of capability.

### Level 0: Deterministic Assistant (Offline-First)

Goal: useful chat experience without any external model.

WordPress implementation:

1. Route `POST /clawpress/v1/chat/message` to `inc/commands.php` when provider is missing/disabled.
2. Support MVP commands from plugin spec:
   - `/help`
   - `/status`
   - `/setup start|resume|reset`
   - `/memory list`
   - `/memory clear` (with confirmation)
   - `/site info`
   - `/tools list`
3. Return deterministic responses generated locally (no API calls).
4. Show clear `Offline` status in `StatusBadge`.
5. Setup must include an execution-user selection step:
   - choose existing user or create/select dedicated service user
   - recommend lower-privilege role by default
   - block mutating agent actions until selection is completed

Security requirements:

1. Capability checks on every command path.
2. REST nonce required for request.
3. Sanitize user input and escape response rendering.
4. Do not allow action execution without a configured agent user.

Definition of done:

1. Fresh install works with no provider keys.
2. All Level 0 commands return in <500ms on typical admin host.

### Level 1: Persistent Conversation + Memory

Goal: preserve state across turns, sessions, and users (with scope controls).

WordPress implementation:

1. Persist threads/messages in custom tables:
   - `{$wpdb->prefix}clawpress_threads`
   - `{$wpdb->prefix}clawpress_messages`
2. Persist long-term memory in `clawpress_memory` CPT with scope meta:
   - `global`
   - `user`
3. Add retrieval pipeline in `inc/memory.php`:
   - fetch by scope + recency + relevance
   - inject small context window into agent input
4. Support memory retention policies and purge jobs.

Security requirements:

1. User-private memory cannot be read by other users.
2. `DELETE /memory` requires explicit confirmation and sufficient capability.

Definition of done:

1. Conversations survive reloads.
2. Agent can reference prior interactions from same thread/user scope.

### Level 2: Agent Identity, `SOUL.md`, and Behavioral Contract

Goal: give the agent stable identity, boundaries, and response style.

WordPress implementation:

1. Add `inc/agent.php` identity layer with a system policy composed from:
   - plugin mission
   - capability constraints
   - tool-use rules
   - safety/confirmation rules
2. Define the agent identity contract as `SOUL.md` content:
   - Role: WordPress in-admin assistant
   - Principles: secure-by-default, least privilege, reversible changes
   - Boundaries: no destructive action without confirmation
   - Voice: concise, operational, auditable
3. Store `SOUL.md` as an `agent-file` CPT record (logical path `SOUL.md`) so it remains editable with built-in WordPress revisions and capability controls.
4. Store only active `SOUL.md` reference metadata in options (e.g., active file ID/path), not the canonical file body.

Security requirements:

1. Identity and safety policy are injected server-side only.
2. Client cannot override system policy.
3. Only authorized admins can edit protected identity files (including `SOUL.md`).

Definition of done:

1. Agent responses are behaviorally consistent across sessions.
2. Tool-use boundaries hold regardless of user prompt phrasing.

### Level 3: Tools and Real Actions

Goal: move from "advice" to safe execution.

WordPress implementation:

1. Register all ClawPress tools as Abilities API abilities using `wp_register_ability()` on `wp_abilities_api_init`.
2. Keep an allowlist of supported ClawPress ability IDs and execute only from that list.
3. Each tool ability must declare:
   - namespaced ability ID (`clawpress/...`)
   - label + description
   - category
   - `input_schema` + `output_schema`
   - `execute_callback`
   - `permission_callback`
   - safety class (`read`, `write`, `destructive`) in meta/config
4. Include built-in file tool abilities: `clawpress/file_list`, `clawpress/file_read`, `clawpress/file_write`, `clawpress/file_move`, `clawpress/file_delete`, `clawpress/file_search`.
5. File tools must resolve files in this order:
    - primary: `agent-file` CPT by logical path/slug
    - fallback: filesystem workspace path
6. New files created by agent file tools should default to `agent-file` CPT unless caller explicitly targets workspace.
7. Workspace path must be obtained from a single workspace location resolver only; direct path construction is not allowed.
8. Agent-created files must be authored by the configured agent user.
9. File reads/writes must be isolated to the agent user's file/workspace scope.
10. Execute tool calls server-side only; never from JS directly.
11. Execute all tools under the configured agent user context for the thread/site.
12. Evaluate permissions through each ability's `permission_callback` in execution-user context, while still validating that the requesting user is allowed to use ClawPress.
13. Log each action in `{$wpdb->prefix}clawpress_action_logs`:
   - requesting_actor
   - execution_actor
   - ability
   - file_source (`agent-file|workspace`) when applicable
   - args hash
   - result
   - timestamp
14. For `destructive` tools, require explicit user confirmation step.

Security requirements:

1. Ability registration occurs only on `wp_abilities_api_init`.
2. `permission_callback` is required for each mutating tool ability.
3. Schema validation follows Abilities API `input_schema`/`output_schema` and server-side sanitization.
4. Immutable action log records.
5. Execution-user context switch must be explicit and reset after each action.
6. File resolver must canonicalize logical path and block traversal/symlink escapes.
7. File resolver and workspace resolver must enforce execution-user isolation boundaries.

Definition of done:

1. Agent can complete at least 3 real admin tasks via tools.
2. All write/destructive tool calls are auditable and permission-checked using execution-user capabilities.

### Level 4: Unified Context + Interface Layer

Goal: create a stable context interface so the agent can reason over WordPress state predictably.

WordPress implementation:

1. Build context providers in `inc/context.php` (or equivalent):
   - current admin screen context
   - user/session context
   - plugin/site health context
   - memory context
2. Standardize prompt assembly pipeline in `inc/ai-client.php`:
   - identity policy loaded from resolved `SOUL.md`
   - retrieved memory
   - current context providers
   - latest user message
3. Support user file references in prompts (e.g., `@/playbooks/editorial.md`) via the same resolver order (CPT first, filesystem fallback).
4. Add adapter boundaries so future external connectors can be plugged in without changing planner logic.

Security requirements:

1. Context providers must redact secrets.
2. Do not include raw credentials/API keys in model inputs.

Definition of done:

1. Prompt assembly is deterministic and testable.
2. New context providers can be added without changing chat route contract.

### Level 5: Multi-Channel Access (WordPress-Centric)

Goal: support multiple interaction surfaces while keeping one agent core.

WordPress implementation:

1. Keep `wp-admin` chat as primary channel.
2. Expose optional channel adapters behind feature flags:
   - REST webhook endpoints
   - future Slack/Discord/email adapters
3. All channels map into a common message envelope:
   - actor
   - channel
   - thread_id
   - content
   - metadata
4. Reuse same planner/tool/memory pipeline across channels.

Security requirements:

1. Channel-specific auth verification required before message acceptance.
2. Maintain per-channel audit trail.

Definition of done:

1. At least one non-admin channel can send/receive safely without forking core logic.

### Level 6: Scheduled Jobs and Proactive Behavior

Goal: agent does useful work without waiting for a prompt.

WordPress implementation:

1. Register Action Scheduler actions in `inc/heartbeat.php`:
   - memory compaction/summarization
   - workspace cleanup
   - health checks
   - configurable proactive digests
2. Use Action Scheduler APIs (`as_schedule_recurring_action()`, `as_enqueue_async_action()`) in the `clawpress` action group.
3. Schedule recurring actions on activation and keep them present via `action_scheduler_ensure_recurring_actions`.
4. Use idempotent jobs with lock/transient guards.
5. Create notification surface in admin chat/activity panel.
6. Allow admins to configure schedule, scope, and opt-in behavior.

Security requirements:

1. Action Scheduler-triggered actions still run through ability permission checks and policy checks.
2. Proactive write actions require explicit opt-in and confirmation policy.

Definition of done:

1. Agent can deliver scheduled summaries/alerts with no manual prompt.
2. Jobs are observable and retry-safe.

### Level 7: Multi-Agent Orchestration

Goal: decompose complex tasks into specialized agent roles.

WordPress implementation:

1. Add orchestrator in `inc/agent.php` with role-based workers:
   - `Planner`: breaks down task
   - `Researcher`: gathers context/memory/tool facts
   - `Executor`: runs approved tools
   - `Reviewer`: validates result + safety
2. Start with single-model role prompts; upgrade to separate models later.
3. Persist orchestration trace IDs in action logs for debugging.
4. Add execution budget controls:
   - max tool calls
   - timeout ceiling
   - token/cost ceiling (when provider exposes usage)

Security requirements:

1. Orchestrator cannot bypass tool capability/confirmation checks.
2. Reviewer can block unsafe plan execution.

Definition of done:

1. Complex tasks show improved reliability vs single-pass execution.
2. Orchestration trace is visible to admins for audit/debug.

## 3) Integrated ClawPress Architecture

### 3.1 Request Lifecycle

1. Message enters `POST /chat/message`.
2. Policy gate checks capability + nonce + rate limit.
3. Resolve agent user for site/thread context.
4. If agent user is missing, allow read-only chat/status/setup and return setup-required for mutating requests.
5. Router selects mode:
   - Offline command engine (Level 0)
   - Online agent pipeline (Levels 1+)
6. Online path assembles resolved `SOUL.md` identity + memory + context + user input.
7. Agent decides:
   - plain response
   - tool plan
   - orchestration flow (Level 7)
8. Action executor runs allowlisted tools with safety checks in execution-user context.
9. Persist messages, memory updates, and action logs (requesting + execution actor IDs).
10. Return response envelope to UI.

### 3.2 Module Map (WordPress)

Required modules:

- `inc/rest-api.php`: route registration and schemas
- `inc/commands.php`: offline deterministic commands
- `inc/ai-client.php`: provider/model abstraction (`wordpress/php-ai-client`)
- `inc/agent.php`: planner/router/orchestrator
- `inc/files.php`: file tools + CPT/workspace resolver logic
- `inc/workspace.php`: single workspace location resolver used by all file operations
- `inc/memory.php`: retrieval, summarization, retention
- `inc/security.php`: policy checks, confirmations, audit writer
- `inc/setup.php`: setup state machine
- `inc/heartbeat.php`: Action Scheduler registration and heartbeat wiring

### 3.3 Data Map

1. Threads/messages tables: conversational transcript.
2. `clawpress_memory` CPT: durable memory facts.
3. `clawpress_skill` CPT: tool/skill policy configuration.
4. `agent-file` CPT: editable agent files (`SOUL.md`, playbooks, prompt snippets) with revisions.
5. Action logs table: immutable execution ledger.
6. User meta/options: UI state, workspace mapping, global settings.
7. Options: active `SOUL.md` reference and selected execution-user configuration.
8. Action Scheduler queue storage: scheduled and historical background actions (group: `clawpress`).

## 4) Phase Alignment to Existing Plugin Roadmap

1. Phase 1 (Foundation): Level 0 + setup + status.
2. Phase 2 (Online AI): Levels 1-3 with provider integration and tools.
3. Phase 3 (Safety + Memory hardening): Level 4 + Level 6 basics + diagnostics.
4. Phase 4 (Advanced): Level 5 channel adapters + Level 7 orchestration.

## 5) Acceptance Criteria by Capability Layer

1. Layered rollout is possible behind feature flags.
2. Offline mode remains fully functional if provider fails.
3. All write/destructive actions require both capability and policy checks.
4. Every action is traceable in logs with requesting actor, execution actor, and outcome.
5. Memory is scoped, queryable, and deletable per policy.
6. Scheduled jobs are observable and safe to retry through Action Scheduler.
7. Orchestrated tasks are bounded by explicit execution budgets.
8. Mutating actions are blocked until setup sets an agent user.
9. Agent file lookups always resolve via built-in `file_read` semantics (CPT first, workspace fallback).
10. Workspace paths are obtained only through a single resolver interface.
11. Agent-created files are authored by agent user and isolated from other users' file/workspace scopes.

## 6) Implementation Notes

1. Keep single-site support as default for v1; multisite later.
2. Prefer narrow, testable classes over large monolith agent classes.
3. Add unit tests for:
   - command parsing
   - policy gate behavior
   - tool schema validation
   - memory scope filtering
   - file resolver precedence (`agent-file` before workspace fallback)
   - ability registration and permission callback behavior
4. Add integration tests for REST contracts and confirmation flows.

## 7) Out of Scope (v1)

1. Frontend visitor chat widget.
2. Arbitrary executable user-defined code tools.
3. Full autonomous background mutation without explicit admin opt-in.

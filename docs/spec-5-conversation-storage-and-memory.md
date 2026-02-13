# Spec 5: Conversation Storage and Durable Memory

## Goal

Add persistent thread/message history and scoped long-term memory retrieval.

## Source Requirements

- Plugin spec: 4.2, 6.3, 6.3.1, 7 (`/chat/threads`, `/memory`), 15.5-15.6
- Agent spec: Level 1 and Level 4 memory context requirements

## In Scope

- DB tables for threads/messages/action logs baseline migrations.
- Thread CRUD API (`GET/POST /chat/threads`).
- Memory CRUD API (`GET /memory`, `DELETE /memory`).
- Retrieval strategy for prompt context injection.

## Out of Scope

- Semantic/vector search infra.
- Cross-site memory federation.

## Implementation Tasks

1. Schema and migrations
- Add activation-time migrations for `clawpress_threads` and `clawpress_messages`.
- Prepare `clawpress_action_logs` baseline schema for later specs.

2. Memory module
- Add `inc/memory.php` for `clawpress_memory` CRUD helpers and query filters.
- Enforce scope model: global + user-private.

3. REST endpoints
- Implement `/chat/threads`, `/memory`, and `DELETE /memory` with explicit confirmation requirement.
- Validate pagination, scope, and ownership parameters.

4. Prompt retrieval
- Add memory retrieval pipeline (`scope + recency + relevance`) and small context window assembly.

5. Retention hooks
- Add retention metadata fields and purge callable for scheduler integration.

## Acceptance Criteria

- Conversations persist and reload correctly.
- User-private memory is not visible to other users.
- Memory clear operation requires confirmation and appropriate capability.

## Test Plan

- Unit: scope filtering and retrieval ordering.
- Integration: thread persistence and memory delete permission checks.
- Manual: multi-user visibility verification.

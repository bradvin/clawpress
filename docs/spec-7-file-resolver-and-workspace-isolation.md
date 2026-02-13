# Spec 7: File Resolver and Workspace Isolation

## Goal

Implement secure file operations with mandatory resolver order (`agent-file` CPT first, workspace fallback) and execution-user isolation.

## Source Requirements

- Plugin spec: 4.3, 6.3.3, 6.6, 8.7-8.8, 15.10
- Agent spec: file model requirement and Level 3 file tool constraints

## In Scope

- File resolver abstraction.
- Workspace resolver abstraction.
- Built-in file abilities (`list/read/write/move/delete/search`).

## Out of Scope

- Arbitrary absolute filesystem access.
- Public file serving endpoints.

## Implementation Tasks

1. Files module
- Add `inc/files.php` with normalized path resolver and CPT-first lookup.
- Persist canonical file path metadata for `clawpress_agent_file` items.

2. Workspace module
- Add `inc/workspace.php` as the only workspace path builder.
- Store opaque workspace mapping in `user_meta`.
- Prevent direct path construction in routes/tool handlers.

3. Security hardening
- Canonicalize all logical paths.
- Block traversal and symlink escape attempts.
- Enforce execution-user ownership/scope boundaries.

4. Tool integration
- Bind file abilities to resolver APIs.
- Default agent-created files to `agent-file` CPT unless explicit workspace target is provided.

## Acceptance Criteria

- File read resolves from `agent-file` before workspace fallback.
- Cross-user workspace or file access is blocked.
- Path traversal attempts are rejected safely and logged.

## Test Plan

- Unit: path canonicalization and resolver precedence.
- Integration: end-to-end file tool behavior with CPT/workspace scenarios.
- Manual: destructive file operation confirmation path.

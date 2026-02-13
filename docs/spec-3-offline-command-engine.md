# Spec 3: Offline Command Engine (Level 0)

## Goal

Provide deterministic offline chat commands with no external model dependency.

## Source Requirements

- Plugin spec: 5.3, 7 (`POST /chat/message`), 10.1, 13.2
- Agent spec: Level 0 definition and security requirements

## In Scope

- Command parser and dispatcher in PHP.
- Offline command handlers:
  - `/help`
  - `/status`
  - `/onboarding start|resume|reset`
  - `/memory list`
  - `/memory clear` (with confirmation)
  - `/site info`
  - `/tools list`
- Deterministic response formatting.

## Out of Scope

- Any external provider/model calls.
- Tool execution planner.

## Implementation Tasks

1. Command module
- Add `inc/commands.php` with parser, command registry, and handler map.
- Normalize command and argument tokenization.

2. Chat route integration
- Add `POST /chat/message` route in `inc/rest-api.php`.
- Route to offline engine when provider is missing/disabled.

3. Confirmation mechanism
- Add server-enforced confirmation flow for `/memory clear`.
- Include explicit challenge token or command confirmation semantics.

4. Security checks
- Enforce nonce + capability checks on every request.
- Sanitize inbound message content and escape outbound render text.

5. UI behavior
- Update chat UI to surface command suggestions and offline label.

## Acceptance Criteria

- Offline commands work on fresh install with no provider configured.
- Responses are deterministic and returned under target latency on local dev.
- Mutating command paths are blocked if execution-user prerequisites are missing.

## Test Plan

- Unit: parser edge cases and command dispatch.
- Integration: command responses through `POST /chat/message`.
- Manual: invalid command handling and confirmation flow behavior.

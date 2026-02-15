# Spec 3: Offline Command Engine (Level 0)

## Goal

Provide deterministic chat commands with no external model dependency. Commands will work in offline mode and in online mode. They will return deterministic responses and will not be sent to a LLM.

## Source Requirements

- Plugin spec: 5.3, 7 (`POST /chat/message`), 10.1, 13.2
- Agent spec: Level 0 definition and security requirements

## In Scope

- Command parser and dispatcher in PHP.
- Offline command handlers:
  - `/help`
  - `/status`
  - `/setup start|resume|reset`
  - `/memory list`
  - `/memory clear` (with confirmation)
  - `/site info`
  - `/tools list`
- Deterministic response formatting.
- Commands are routed through a command parser and dispatcher and are never sent to a LLM.

## Out of Scope

- Any external provider/model calls.
- Tool execution planner.

## Implementation Tasks

1. Command module
- Add `includes/commands/class-commangs.php` with parser, command registry, and handler map.
- Normalize command and argument tokenization.

1.2. Command handlers
- Create separate command handlers files in `includes/commands/handlers/` for each command.
- Each command must know how to parse and validate its arguments.
- Each command must know how to execute its logic.
- Each command must know how to format its response.
- Each command must know how to handle confirmation.
- Each command must know how to handle errors.

1.3. Command registry
- Add `includes/commands/class-command-registry.php` with command registration and lookup.
- Register commands in `includes/commands/class-commands.php`.
- When commands are registered, they must specifiy if they are destructive or not.

2. Chat route integration
- Routes through the usual chat flow.
- When commands are detected, they are dispatched to the command parser and dispatcher.
- When commands are not detected, then the usual chat flow is used.
- When commands return errors, then return command help text.
- If a command is not found, then return the help text for the `/help` command.
- The default command is the help command.

3. Confirmation mechanism
- Add server-enforced confirmation flow for destructive commands like `/memory clear`.

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

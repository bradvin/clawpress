# Spec 4: Provider Settings and Online Agent Routing

## Goal

Enable online mode using `wordpress/php-ai-client` with a single server-side adapter and safe fallback to offline mode.

## Source Requirements

- Plugin spec: 6.4, 7 (`POST /settings/provider`), 10.2, 14 phase 2
- Agent spec: Level 4 prompt assembly prerequisites

## In Scope

- Provider settings persistence and validation.
- AI adapter module (`inc/ai-client.php`).
- Chat route intent split: command vs online model path.

## Out of Scope

- Advanced multi-provider UI presets.
- Streaming transport optimizations.

## Implementation Tasks

1. Provider settings API
- Add `POST /settings/provider` in `inc/rest-api.php`.
- Validate provider, model, and credentials fields.
- Store secrets safely and never return keys in responses.

2. AI adapter
- Add `inc/ai-client.php` with one call surface for all model invocations.
- Inject server policy, resolved identity file, memory, and user message.

3. Online routing
- Extend `POST /chat/message` logic to choose offline/online mode.
- Fail closed to offline mode when provider validation fails.

4. Metadata logging
- Persist model request metadata (provider/model/token usage if present) via audit/log layer hooks.

## Acceptance Criteria

- Valid provider setup allows successful online prompt response.
- Invalid/missing settings produce safe offline fallback.
- No API credentials leak via localized script objects or REST payloads.

## Test Plan

- Unit: provider settings validation and adapter error mapping.
- Integration: mode switch behavior in chat endpoint.
- Manual: provider disconnect and recovery flow.

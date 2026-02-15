# Spec 6: Abilities API Tools and Action Execution

## Goal

Implement tool registration/execution through WordPress Abilities API with execution-user context and explicit safety classes.

## In Scope

- Ability registration and allowlist.
- Tool executor abstraction in server-side PHP.
- Confirmation gating for destructive actions.

## Out of Scope

- User-defined arbitrary executable tools.
- Multi-agent orchestration.

## Implementation Tasks

1. Abilities module
- Add `includes/class-abilities.php` and register abilities on `wp_abilities_api_init`.
- Define standard ability shape: schema, callbacks, safety class.

2. Abilities helper class
- Add an abilities helper class in `includes/helpers/class-abilities-helper.php`.
- Wire up context helper class to load clawpress abilities from abilities helper class.
- Available tools passed to the LLM are clawpress abilities.

3. Built-in abilities
- Add built-in abilities for common clawpress actions.
- Add all clawpress abilities under 'includes/abilities/'
- All abilities should be registered in the 'clawpress' namespace.
- Each ability should have a schema and callback.
- Each ability should be in its own file.
- Abilities should use helper classes always.

4. Confirmation flow
- Add policy checks in `includes/class-security.php` for `destructive` class abilities.
- Add confirmation flow for destructive actions.

5. Action ledger
- Write immutable action records to `clawpress_action_logs` with requesting actor + execution actor.

## Built-in Abilities

1. `file_write`
- Write to the agent-file CPT for all *.md files.
- Write to the workspace for all other files, like images.

2. `file_read`
- Read an agent-file CPT or workspace file.
- First try agent-file CPT, then workspace file.

3. `file_delete`
- Delete a file from the agent-file CPT or workspace.
- Confirmation required for destructive actions.

4. `file_list`
- List all available agent-file CPTs and workspace files.

5. `memory_short_term_add`
- Add a short-term memory using the memory helper.

6. `memory_short_term_update`
- Update a short-term memory using the memory helper.

7. `memory_short_term_delete`
- Delete a short-term memory using the memory helper.

8. `memory_long_term_add`
- Add a long-term memory using the memory helper.

9. `memory_long_term_update`
- Update a long-term memory using the memory helper.

10. `memory_long_term_delete`
- Delete a long-term memory using the memory helper.

## Acceptance Criteria

- Abilities are passed to the LLM as tools.
- Mutating abilities fail without permission callback and confirmation when required.
- Action logs record actor attribution and outcomes for every tool call.

## Test Plan

- Unit: ability registration and allowlist enforcement.
- Integration: tool execution with and without confirmation.
- Manual: permission differences between requesting user and agent user.

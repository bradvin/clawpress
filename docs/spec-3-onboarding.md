# Spec 3: Onboarding

## Goal

Implement chat-led onboarding state machine which guides the user through the initial setup process. It shold provide a nice UX and explain everything that is happening.

## Source Requirements

- Plugin spec: 5.2, 6.3, 9, 15.2, 15.8, 15.13
- Agent spec: Level 0 onboarding constraints and section 3 request lifecycle step 4

## In Scope

- Onboarding state machine storage and progression.
- Provider and model selection. If providers not setup then include link to `wp-admin/options-general.php?page=wp-ai-client`. Onboarding is blocked.
- Agent user selection. (suggest creating a dedicated user the agent will use. Suggest giving it contributor permissions to start.)
- Provision `SOUL.md`, `AGENTS.md`, `USER.md`, `HEARTBEAT.md` from template files.

## Out of Scope

- Rich conversational onboarding copy generation by LLM.
- Multi-site onboarding variations.

## Implementation Tasks

1. Onboarding module
- Add `includes/onboarding.php` with state transitions and resumable progress.
- Persist onboarding state to settings.

2. Onboarding card which is a wizard that guides the user through the onboarding process.

3. Agent user flow
- Add helpers to list eligible users and validate selected user.
- Store `clawpress_agent_user_id` option.
- Return setup-required status when mutating operations are attempted without this value.

4. Template bootstrap
- Implement idempotent bootstrap from `docs/templates/*` into `clawpress_agent_file` posts.
- Set author to agent user for agent-created records.
- Mark `SOUL.md` protected using post meta.
- Track template version via `clawpress_onboarding_templates_version`.

5. UI integration
- Add `OnboardingFlow` component to guide first-run steps and resume.

## UI

The following steps need to be included in the wizard:

1. Provider selection.

- If providers not setup then include link to `wp-admin/options-general.php?page=wp-ai-client`. Onboarding is blocked. Check if the user is on that page, and if so show a refresh button in the wizard to get the providers. The provider helper should be used under the hood.
- If providers are setup then show a dropdown to select a provider (if there are multiple). The provider helper should be used under the hood.
- If there is only 1 provider setup, then show a button "Use <provider>", which sends command `/settings provider <provider>` and takes the user to the next step.

2. Model selection.
- Show a dropdown to select a model (if there are multiple). The model helper should be used under the hood.
- If there is only 1 model setup, then show a button "Use <model>", which sends command `/settings model <model>` and takes the user to the next step.

3. Once provider and model are selected, show a "test connection" button which issues a `/test` command to the chat panel.
- If the connection is successful, update status to "online" and take the user to the next step.
- If the connection fails, show an error message and a "Retry" button which will retry the connection.

4. Agent user selection.
- Explain that the agent will use a specific user to run tools and access files. And suggest that a new user with Contributor access be created.
- Show a button "Create agent user" which will send command `/create-agent-user` and if successful sends command `/settings agent-user <new-user-id>` and takes the user to the next step.
- Otherwise, have another button for "Use current user" which will send command `/settings agent-user <current-user-id>` and if successful takes the user to the next step.

5. Workspace Setup.
- Explain that a secure workspace is needed for clawpress to function and save files.
- Show the workspace path, using the workspace helper.
- Show a button "Create workspace" which will call the `/create-workspace` command.
- Once created, take the user to the next step.

6. Agent File bootstrap.
- Explain that files are needed for clawpress to function.
- Show a button "Create agent files" which will call the `/create-agent-files` command.
- Once created, take the user to the next step.

Each step should check for a successful completion and if so, take the user to the next step. If not, show an error message and a "Retry" button which will retry the step if applicable.

## Acceptance Criteria

- Fresh install enters onboarding and can resume after reload.
- Onboarding cannot complete until settings are valid.
- Existing agent-file records are not overwritten if they already exist.

## Test Plan

- Unit: transition guards, template create-if-missing logic.
- Integration: onboarding REST progression and idempotent re-run.
- Manual: skip provider setup and still finish offline path.

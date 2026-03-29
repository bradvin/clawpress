# ClawPress

## The AI Agent for WordPress that actually does things.

![ClawPress Logo](img/clawpress-logo-500x500.png)

[Preview in WordPress Playground](https://playground.wordpress.net/?wp=beta&blueprint-url=https://raw.githubusercontent.com/bradvin/clawpress/refs/heads/main/blueprint.json)

## Quick Start

```bash
make bootstrap
npm run build
```

## Common dev commands

```bash
make test
make lint
make lint-changed
npm run plugin:check
```

## Key Features

### Admin Assistant MVP

Current MVP features implemented in this plugin:

- Floating chat panel available across all admin screens to chat with your AI assistant.
- Admin bar toggle (`🦞`) to open / close the chat panel.
- Offline mode which still allows slash commands to be used.
- Setup Wizard built into chat to guide users through plugin setup process.
- Admin settings page to set which provider, model, and other settings to use.
- Admin abilities settings page to enable which abilities can be used by the agent.
- Registers WP Abilities to and loads them as tools into the AI client.
- Creates an action log database table to track actions taken by the AI assistant.
- Context & system prompt built up using chat history, tools (abilities), agent files & memory.
- Card UI system to display information in a card format.
  - Included cards : Welcome card, Setup Wizard card, User Permissions card.
- Shows context usage with toolip.

Current Agent Features:

- Access to agent files (AGENTS.md, SOUL.md, BOOTSTRAP.md) (stored in `clawpress-agent-file` custom post type).
- Persistent agent memory (stored as `clawpress-agent-mem` custom post type). (Short term memory, long term memory)
- Access to a secure workspace file-system located at `/wp-content/uploads/<agent-user-id>/<random-hash>`
- Has an assigned WordPress user. (this user will be used to perform heartbeat tasks)
- Agent abilities:
  - `file_list` (read from agent-files first then workspace)
  - `file_read`
  - `file_write`
  - `file_delete`
  - `web_fetch` (read-only remote fetch via the WordPress HTTP API, validated with `wp_http_validate_url()`, logged to the action log table)
  - `memory_long_term_add`
  - `memory_long_term_update`
  - `memory_long_term_delete`
  - `memory_short_term_add`
  - `memory_short_term_update`
  - `memory_short_term_delete`

### Slash Commands

- `/help` - shows availalble commands.
- `/clear` - Clears chat history.
- `/status` - Shows plugin status.
- `/tools` - Shows registered abilities (tools).
- `/site info` - Shows site information.
- `/memory list|clear` - Shows agent memory.
- `/setup` - Runs the setup wizard.
- `/test` - Runs a test to ensure the AI client is working.
- `/reset` - Resets the chat history and shows Welcome card.

## Technical Details

- Uses modern WordPress patterns for admin pages and REST API.
- Uses WordPress core AI client APIs on WordPress 7.0+. ([docs](https://github.com/WordPress/wp-ai-client))
- Uses `@woocommerce/action-scheduler` for background processing. ([docs](https://github.com/woocommerce/action-scheduler))
- Uses Composer autoloading for native ClawPress PHP classes.
- Uses `@wordpress/dataviews` for admin `DataViews` tables and `DataForm` settings forms.
- Uses `wp-scripts` for build tooling. ([docs](https://developer.wordpress.org/block-editor/packages/packages-scripts/))
- Uses `phpunit` for unit testing.
- Uses `wp-coding-standards` and `phpcodesniffer` for code quality.
- Built from Brian Coords [woodev-extension-starter](https://github.com/bacoords/woodev-extension-starter)

### REST API

Custom endpoints with permission callbacks and parameter validation.

- Namespace: `clawpress/v1`
- Endpoints:
  - `/settings` (GET/POST)
  - `/status` (GET)
  - `/panel/state` (GET/POST)
  - `/chat/message` (POST)
  - `/chat/history` (GET)
- Location: `includes/class-rest-api.php`

## Dependencies

### Runtime
- WordPress core AI client APIs (WordPress 7.0+)
- `woocommerce/action-scheduler` - background task scheduling
- `@wordpress/dataviews` - `DataViews` tables and `DataForm` form components
- `@wordpress/icons` - Icon library

### Development (npm)
- `@wordpress/scripts` - Build tooling
- `@wordpress/env` - Local disposable WordPress environment for plugin checks

### Development (Composer)
- `wp-coding-standards/wpcs` - WordPress Coding Standards for PHP_CodeSniffer
- `phpunit/phpunit` - PHPUnit test runner

## Patterns Used

| Pattern | Purpose |
|---------|---------|
| Namespaced PHP classes | Isolated features, no conflicts |
| Config-driven UI | Modify fields/actions without touching components |
| Custom hooks | Encapsulated data logic |
| REST API with validation | Clear frontend/backend contract |
| Asset file pattern | Auto-managed dependencies |
| wp-scripts build tooling | Zero-config builds |
| WordPress admin menu page | Proper admin page integration |

## Requirements

- PHP 8.1+
- WordPress 7.0+
- Composer 2+
- Node.js 20+
- npm 10+

## WordPress 7.0 Decision

ClawPress now targets WordPress 7.0+ intentionally.

This was a deliberate compatibility decision:

- WordPress 7.0 provides the AI client APIs in core.
- Bundling a separate runtime copy of `wordpress/wp-ai-client` caused conflicts with the core-provided classes.
- ClawPress now relies on the core AI client at runtime instead of shipping its own runtime copy.
- On sites running below WordPress 7.0, ClawPress does not bootstrap and shows an admin notice explaining the minimum required version.

## TODO

### Approved

- Add more agent abilities for reading general WordPress content (posts, pages, etc.)
- Add abilities to read memory files (long and short term).
- Add abilities for bulk file_reads and bulk memory_reads.
- Add ability to send emails to administrator using built-in WP mail functions.
- Chat threads - have multiple conversation threads at once, per user.
- Implement a working heartbeat task. Start small and test how it works. Create a built in nightly healthcheck that checks site health and emails admin. Add wizard for setting up useful nightly site health email report.
- Agent skills! Create abilities to read, create, update, execute a skill. Consider limitations and security.

### Backlog

- Add current site context, when doing direct chat messages. Consider an ability to pull this info, including all active plugins.
- Improve context usage info / limit / tooltip. (right now its not useful and not accurate. Also need to decide on a suitable max context size - maybe 200K)
- Define actual scope of agent user. (should the user only be used for heartbeat tasks?)
- Fix boostrap agent file setup and writing (doesnt always run)
- /reset command should clear everything (like uninstall + activation). All history will be wiped and Welcome card should show. Even after page refreshes.
- Consider how browser search and use will work within WordPress.
- Mulit agent support. (can configure multiple agents)
- Multi-user support. (each user has their own agents)
- Channels for agent interaction.
- Streaming responses.
- Add ability to read DB, or generate a query to read from the DB. Might need an ability to get DB schema, which can then be used to generate a query. Need to consider destructive queries, or make all queries require confirmation just to be safe.

## Decision Log

1. Storage model: (custom table + CPT + filesystem workspace).
2. Memory management: `clawpress_agent_mem` CPT (long term and short term).
3. Skills management: `clawpress_agent_skill` CPT.
4. Workspace location: uploads subdirectory with non-guessable randomized folder names.
5. Retention baseline: configurable TTL with Action Scheduler purge jobs.
6. Agent action model: execute actions as selected WP agent user (recommended low-privilege dedicated account).
7. System prompt built up from small system prompt + chat history + tools + agent files + memory.
8. File model: built-in file tools resolve `clawpress_agent_file` CPT first, with workspace filesystem fallback.
9. Tool model: all ClawPress tools are abilities (Abilities API).
10. Background scheduling model: Action Scheduler (not WP-Cron).
11. Cards are used to display complex UI in chat panel.
12. Commands can be used offline.
13. Cards can have actions, which run commands, or send messages.
14. Runtime AI integration depends on WordPress 7.0+ core AI APIs instead of bundling `wordpress/wp-ai-client`.
15. On unsupported WordPress versions, ClawPress shows an admin notice and does not bootstrap.

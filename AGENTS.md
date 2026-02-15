# AGENTS.md - AI Assistant Context

This is a WordPress plugin ClawPress. When working with this codebase, follow these patterns and conventions.

## Project Overview

The plugin includes:
- React-based admin UI using DataViews
- REST API with namespaced endpoints
- Floating wp-admin panel UI (`src/panel`)
- wp-scripts build system

## Architecture Patterns

### PHP Structure

- **Main file** (`clawpress.php`): Only defines constants and requires modules
- **Feature modules** (`includes/class-*.php`): Each feature isolated in its own namespaced class file
- **Strict types**: All PHP files use `declare( strict_types=1 )`
- **Namespace convention**: `ClawPress\FeatureName`
- **Coding standards**: WordPress Coding Standards (WPCS) - use tabs, spaces inside parentheses
- **Linting config**: `phpcs.xml.dist` - run `npm run lint:php` to check

### JavaScript Structure

- **Entry point**: `src/js/admin/index.js` - mounts React app on plugin admin page
- **Panel entry point**: `src/panel/index.jsx` - mounts floating admin panel
- **Components**: `src/js/admin/components/` - React components
- **Hooks**: `src/js/admin/hooks/` - Custom hooks (data fetching, state)
- **Config**: `src/js/admin/config/` - Field definitions, actions, settings

### REST API Pattern

Located in `includes/class-rest-api.php`:
- Namespace: `clawpress/v1`
- Always include `permission_callback`
- Use `sanitize_callback` and `validate_callback` for args
- Return `WP_REST_Response` with appropriate status codes

**Note**: The DataViews demo uses `@wordpress/core-data` which handles REST calls for post types internally. Custom endpoints are only needed for operations not covered by WordPress core (custom business logic, aggregations, etc.).

### DataViews Pattern

The admin UI uses `@wordpress/dataviews` with the WordPress Data Layer:
- **fields**: Define columns in `config/itemConfig.js`
- **actions**: Define row actions (view, edit, trash) in same file
- **useItems hook**: Uses `useEntityRecords` from `@wordpress/core-data`

To switch post types, change `'page'` to your CPT slug in `useItems.js`.

## Key Files to Modify

When adding features:

| Task | Files to Edit |
|------|---------------|
| Add custom post type | `includes/class-post-types.php` |
| Add REST endpoint | `includes/class-rest-api.php` |
| Add admin component | `src/js/admin/components/`, import in `App.js` |
| Update floating panel UI | `src/panel/` and `includes/class-panel.php` |
| Add PHP heartbeat/scheduler hook | `includes/class-heartbeat.php` |

## Helper Class Reference

Use helpers in `includes/helpers/` as the primary integration surface for shared logic.

| Helper | File | Responsibility | Usage Rule |
|------|------|------|------|
| `Agent_File_Helper` | `includes/helpers/class-agent-file-helper.php` | Bootstrap and resolve agent files from `clawpress_agent_file` | Resolve logical files through this helper; do not bypass with ad-hoc lookups. |
| `Chat_Helper` | `includes/helpers/class-chat-helper.php` | Online/offline reply orchestration and AI-call integration | Route model replies through this helper from chat flows. |
| `Chat_History_Helper` | `includes/helpers/class-chat-history-helper.php` | Per-user chat transcript persistence | Use for chat history reads/writes and clears. |
| `Context_Helper` | `includes/helpers/class-context-helper.php` | Builds system prompt + message arrays from bootstrap/memory/skills/history | Use this as the central context builder for LLM calls. |
| `Memory_Helper` | `includes/helpers/class-memory-helper.php` | Memory persistence/retrieval in `clawpress_agent_mem` (`memory.md` + `memory-ddmmyyyy.md`) | All memory save/read/clear operations must use this helper only. |
| `Model_Helper` | `includes/helpers/class-model-helper.php` | Provider-specific model option lists and defaults | Use for model option resolution in UI/commands. |
| `Panel_Helper` | `includes/helpers/class-panel-helper.php` | Floating panel state normalization and defaults | Use for panel state storage and hydration. |
| `Provider_Helper` | `includes/helpers/class-provider-helper.php` | Provider availability/configuration/model resolution | Use for provider detection and configured-provider fallback logic. |
| `Settings_Helper` | `includes/helpers/class-settings-helper.php` | Typed plugin settings read/write and sanitization | Use for plugin setting access instead of direct option mutation. |
| `Status_Helper` | `includes/helpers/class-status-helper.php` | Canonical status payload for REST/UI | Use for `/status` payload generation. |
| `User_Helper` | `includes/helpers/class-user-helper.php` | Agent user creation and validation | Use for agent-user setup and verification. |
| `Workspace_Helper` | `includes/helpers/class-workspace-helper.php` | Agent workspace path/hash creation and structure | Use for workspace creation/path resolution. |

### Helper Guardrails

- Do not duplicate helper logic in controllers/commands.
- Do not add new direct memory storage access in commands/controllers/options.
- Memory data model is:
  - Daily memory files: `memory-ddmmyyyy.md`
  - Long-term memory file: `memory.md`
- Context assembly must read memory via `Memory_Helper` and not from legacy options.

## Data Flow

The demo uses the WordPress Data Layer (`@wordpress/core-data`):

```
WordPress REST API (built-in /wp/v2/pages)
    ↓
useEntityRecords (src/js/admin/hooks/useItems.js)
    ↓
DataViews component (src/js/admin/components/App.js)
```

The Data Layer handles authentication, caching, and optimistic updates automatically. To use a custom post type, change `'page'` to your CPT slug in `useItems.js`.

For custom endpoints (settings, business logic), use `includes/class-rest-api.php` with `wp_localize_script` in `includes/class-admin-page.php`. These endpoints can then be called from your React components using `apiFetch`.

## Common Tasks

### Changing the post type

```javascript
// src/js/admin/hooks/useItems.js
// Change 'page' to your custom post type slug
useEntityRecords( 'postType', 'your_cpt_slug', { ... } );
```

### Adding a new DataViews field

```javascript
// src/js/admin/config/itemConfig.js
{
    id: 'new_field',
    label: 'New Field',
    type: 'text',
    enableSorting: true,
    getValue: ({ item }) => item.new_field,
}
```

### Adding a REST endpoint

```php
// includes/class-rest-api.php
register_rest_route(
	'clawpress/v1',
	'/new-endpoint',
	array(
		'methods'             => 'GET',
		'callback'            => __NAMESPACE__ . '\handle_new_endpoint',
		'permission_callback' => function () {
			return current_user_can( 'manage_options' );
		},
	)
);
```


## Dependencies

Scripts rely on WordPress packages extracted at build time:
- `@wordpress/element` - React wrapper
- `@wordpress/components` - UI components
- `@wordpress/core-data` - Data layer (REST API abstraction)
- `@wordpress/dataviews` - Table/grid views
- `@wordpress/icons` - Icon library
- `@wordpress/dom-ready` - Admin app bootstrap
- `@wordpress/element` - React root rendering

## Internationalization (Required)

All user-facing strings must be translatable.

- **Never ship hardcoded UI text** in PHP or JS.
- **Always use text domain**: `clawpress`.
- **PHP strings**: use `__()`, `_x()`, `esc_html__()`, `esc_attr__()`, `sprintf()` with translator comments when placeholders are used.
- **JS strings**: import from `@wordpress/i18n` and use `__`, `_x`, `_n`, `sprintf` as needed.
- **Keep command identifiers/keys untranslated** (e.g., `/help`, option keys, slugs), but translate labels/messages/descriptions shown to users.
- **When adding new script entry points/handles**, ensure script translations are loaded via `wp_set_script_translations( $handle, 'clawpress', CLAWPRESS_DIR . 'languages' )`.

## Important Notes

- Asset files (`build/scripts/*.asset.php`) are auto-generated - never edit manually
- The `build/`, `node_modules/`, and `vendor/` directories are gitignored
- Always run `composer install && npm install && npm run build` after cloning
- After adding, renaming, or removing PHP class files in the plugin, run `composer dump-autoload` before testing.
- PHP must follow WordPress Coding Standards - run `npm run lint:php` before committing
- PHP namespaces must match directory structure for autoloading compatibility
- The Data Layer (`@wordpress/core-data`) handles REST auth automatically
- Admin page uses `add_menu_page()` and mounts the app into a dedicated root element
- Avoiding adding manual inline style overrides to components unless specifically requested.

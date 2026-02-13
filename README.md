# ClawPress

The AI for WordPress that actually does things

[Preview in WordPress Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/bacoords/clawpress/refs/heads/main/blueprint.json)

## Quick Start

This template is designed for AI-assisted development. To create a new plugin:

1. Copy this directory and rename it to your plugin name
2. Ask your AI agent to rename the plugin by performing find and replace on these patterns:
   - `clawpress` → `your-plugin-name`
   - `ClawPress` → `YourPluginName`
   - `clawpress` → `your_plugin_name`
   - `CLAWPRESS` → `YOUR_PLUGIN_NAME`
   - `clawpress` → `your-tool-name`
   - `clawpress/v1` → `your-plugin/v1`
3. Install dependencies and build:

```bash
composer install
npm install
npm run build
```

## Key Features

### Foundation Chat MVP (Spec 1)

Current `wp-admin` MVP features implemented in this plugin:

- Floating panel available across admin screens for authorized users (`manage_options`).
- Admin bar toggle (`🦞`) plus fallback floating toggle when admin bar link is unavailable.
- Chat transport aligned to REST endpoints:
  - `POST /wp-json/clawpress/v1/chat/message`
  - `GET /wp-json/clawpress/v1/chat/history`
- Status contract endpoint:
  - `GET /wp-json/clawpress/v1/status`
  - Envelope keys: `mode`, `provider`, `model`, `onboarding`, `memory`, `execution_user`
- Panel state persistence endpoint (per-user):
  - `GET /wp-json/clawpress/v1/panel/state`
  - `POST /wp-json/clawpress/v1/panel/state`
  - Stores `open`, `width`, `last_history_id`
- Header status indicator with online/offline badge and provider/model label.
- Keyboard shortcut standardized to `Cmd/Ctrl + K`.
- Requests include WP REST nonce (`X-WP-Nonce`) for authenticated REST calls.

Out of scope for Spec 1 and intentionally not complete yet:

- Full tool runtime/execution policy for `/run-tool`.
- Multi-channel adapters.
- Background jobs and memory retention implementation.

### WordPress Admin Page

Registers a top-level WordPress admin page and mounts a React app.

- Location: `includes/class-admin-page.php`
- Menu: **Dashboard → ClawPress**
- Uses `add_menu_page()` and mounts React into `#clawpress-admin-root`
- Scripts/styles auto-enqueued via asset file pattern

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

**Note**: The DataViews demo uses `@wordpress/core-data` which leverages the built-in WordPress REST API. Custom endpoints are only needed for operations not covered by core.

### DataViews Admin UI

Pre-built table/grid interface using `@wordpress/dataviews` with WordPress Data Layer.

- **App.js**: Main component with view state management
- **useItems.js**: Custom hook using `useEntityRecords` from `@wordpress/core-data`
- **itemConfig.js**: Field definitions and action handlers (view/edit/trash)

The demo displays WordPress pages. Replace `'page'` with your custom post type slug to work with your own data.

### Floating AI Panel

ClawPress includes a floating wp-admin panel UI for chat-style interactions.

- Source: `src/panel/`
- Build output: `build/panel/`
- Runtime loader: `includes/class-panel.php`
- Header status indicator reads from `GET /clawpress/v1/status`
- Panel state sync reads/writes `GET/POST /clawpress/v1/panel/state`
- Shortcut: `Cmd/Ctrl + K`

## Customization Guide

### Adding a Custom Post Type

Register custom post types in `includes/class-post-types.php`. After registering, update `src/js/admin/hooks/useItems.js` to use your post type slug instead of `'page'`.

### Adding a New Admin Feature

1. Create `src/js/admin/components/NewFeature.js`
2. Import and use in `App.js`
3. Add any new REST endpoints in `includes/class-rest-api.php`

### Changing the Post Type

The demo uses WordPress pages. To use a custom post type:

1. Edit `src/js/admin/hooks/useItems.js`:
```javascript
// Change 'page' to your post type slug
useEntityRecords( 'postType', 'your_post_type', { ... } );
```

2. Update field definitions in `src/js/admin/config/itemConfig.js` to match your post type's fields.

### Adding Fields to DataViews

Edit `src/js/admin/config/itemConfig.js`:

```javascript
export const fields = [
    {
        id: 'your_field',
        label: 'Your Field',
        type: 'text',
        enableSorting: true,
        getValue: ({ item }) => item.your_field,
    },
    // ...
];
```

### Adding a New PHP Feature

1. Create `includes/class-your-feature.php` with namespace and class
2. Run `composer dump-autoload` after adding/renaming/removing PHP class files

## Dependencies

### Runtime
- `@wordpress/dataviews` - Table/grid UI components
- `@wordpress/icons` - Icon library

### Development (npm)
- `@wordpress/scripts` - Build tooling

### Development (Composer)
- `wp-coding-standards/wpcs` - WordPress Coding Standards for PHP_CodeSniffer

## Patterns Used

| Pattern | Purpose |
|---------|---------|
| Namespaced PHP modules | Isolated features, no conflicts |
| Config-driven UI | Modify fields/actions without touching components |
| Custom hooks | Encapsulated data logic |
| REST API with validation | Clear frontend/backend contract |
| Asset file pattern | Auto-managed dependencies |
| wp-scripts build tooling | Zero-config builds |
| WordPress admin menu page | Proper admin page integration |

## Requirements

- PHP 8.1+
- WordPress 6.9+
- Node.js 18+

## Current Status Summary

- Spec 1 in-scope MVP work is implemented in code.
- Unit-level coverage includes routes, status/controller permission checks, and panel-state round trip.
- Remaining work is mostly from later specs (tool runtime depth, richer onboarding/memory workflows, and broader integration/manual verification).

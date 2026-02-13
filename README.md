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

### WordPress Admin Page

Registers a top-level WordPress admin page and mounts a React app.

- Location: `includes/class-admin-page.php`
- Menu: **Dashboard → ClawPress**
- Uses `add_menu_page()` and mounts React into `#clawpress-admin-root`
- Scripts/styles auto-enqueued via asset file pattern

### REST API

Template for custom endpoints with proper permission callbacks and parameter validation.

- Namespace: `clawpress/v1`
- Example endpoints: `/settings` (GET/POST)
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
2. Add `require_once` in main plugin file

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

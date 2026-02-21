# WordPress stub contracts for boot paths

ClawPress now enforces explicit WordPress API contracts for critical boot paths.

## Plugin boot contract (`ClawPress\Plugin::assert_boot_contract`)

Required functions:
- `add_action`

## Plugin activation contract (`ClawPress\Plugin::assert_activation_contract`)

Required functions:
- `get_current_user_id`
- `metadata_exists`

## Admin boot contract (`ClawPress\AdminPage\Admin_Page::assert_boot_contract`)

Required functions:
- `add_action`
- `add_menu_page`
- `add_submenu_page`
- `remove_submenu_page`
- `wp_enqueue_script`
- `wp_enqueue_style`
- `wp_localize_script`
- `rest_url`
- `esc_url_raw`
- `wp_create_nonce`

## Test runtime expectation

Unit tests rely on `tests/Support/WordPressStubs.php` to satisfy these contracts in non-WordPress runtime.
If a required API is missing, boot now fails fast with an actionable `RuntimeException` message.

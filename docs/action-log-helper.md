# Action Log Helper

`Action_Log_Helper` is the public integration surface for writing and reading action/event logs.

- Class: `ClawPress\Helpers\Action_Log_Helper`
- DB store behind it: `ClawPress\Stores\Action_Log_Store`

Use the helper from controllers/services. Do not call the store directly unless you are working on persistence internals.

## Common Usage

```php
use ClawPress\Helpers\Action_Log_Helper;

$log_helper = Action_Log_Helper::get_instance();

$log_helper->log_event(
	'tool.execute',
	[
		'event_type'         => 'tool_call',
		'status'             => 'success',
		'message'            => 'Workspace created.',
		'requesting_user_id' => get_current_user_id(),
		'execution_user_id'  => 123,
		'context'            => [ 'ability' => 'create_workspace' ],
	]
);
```

## API

### `get_instance(): Action_Log_Helper`
Singleton accessor.

### `log_event( string $action_name, array $args = [] ): bool`
Writes one log row.

Supported `$args` keys:

- `event_type` (string, default: `event`)
- `status` (string, allowed: `debug`, `info`, `success`, `warning`, `error`; invalid values become `info`)
- `message` (string)
- `requesting_user_id` (int; defaults to current user when available)
- `execution_user_id` (int)
- `context` (array; JSON-encoded for storage)

### `get_recent_logs( array $args = [] ): array`
Reads recent rows and returns normalized records.

Supported filters:

- `event_type` (string)
- `status` (string)
- `requesting_user_id` (int)
- `execution_user_id` (int)
- `limit` (int, default `50`, max `500`)
- `offset` (int, default `0`)

Returned `context` is decoded back to an array.

## Notes

- Input is sanitized by the helper before persistence.
- Status and event/action identifiers are normalized to predictable values.
- The helper is safe to use across admin, REST, and background execution paths.
- Schema/table management for action logs is owned by `Action_Log_Store` and called from plugin activation.

# Agent Session Helper

`Agent_Session_Helper` manages session-level lifecycle state for the agent runtime.

- Class: `ClawPress\Helpers\Agent_Session_Helper`
- DB store behind it: `ClawPress\Stores\Agent_Session_Store`

Use this helper for session creation and run-completion rollups. It encapsulates defaults and delegates persistence to the store.

## Common Usage

```php
use ClawPress\Helpers\Agent_Session_Helper;

$session_helper = Agent_Session_Helper::get_instance();

$session_id = $session_helper->create_session(
	[
		'trigger_type'       => 'chat',
		'requesting_user_id' => get_current_user_id(),
		'execution_user_id'  => 123,
		'policy_profile'     => 'default',
	]
);
```

## API

### `get_instance(): Agent_Session_Helper`
Singleton accessor.

### `create_session( array $args = [] ): int`
Creates one session row and returns the session ID (or `0` on failure).

Supported `$args` keys:

- `uuid` (string; auto-generated when omitted)
- `status` (string; default `active`)
- `trigger_type` (string; default `chat`)
- `requesting_user_id` (int|null)
- `execution_user_id` (int|null)
- `policy_profile` (string|null)
- `next_run_at_gmt` (string|null)

### `apply_run_completion( int $session_id, string $run_status, ?string $next_run_at_gmt = null ): bool`
Updates session rollup fields after a run completes:

- `last_run_at_gmt`
- `last_run_status`
- `next_run_at_gmt`
- `updated_at_gmt`
- `consecutive_failures`

Failure counter behavior:

- resets to `0` when `$run_status === 'success'`
- increments by `1` for all other statuses

## Notes

- This helper is intended to be called by `Agent_Run_Helper` after run completion.
- Session persistence details are isolated in `includes/stores/class-agent-session-store.php`.
- Schema/table management for sessions is owned by `Agent_Session_Store` and called from plugin activation.

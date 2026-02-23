# Agent Run Helper

`Agent_Run_Helper` manages run lifecycle and lock semantics for background agent execution.

- Class: `ClawPress\Helpers\Agent_Run_Helper`
- DB store behind it: `ClawPress\Stores\Agent_Run_Store`

Use this helper from workers/executors. It handles claim rules, stale lock reclaim, completion, and session rollup orchestration.

## Common Usage

```php
use ClawPress\Helpers\Agent_Run_Helper;

$run_helper = Agent_Run_Helper::get_instance();

$run_id = $run_helper->create_run( $session_id );
$claim  = $run_helper->claim_run( $run_id, 'worker-a', 120 );

if ( ! empty( $claim['claimed'] ) ) {
	$run_helper->complete_run(
		$run_id,
		(string) $claim['lock_token'],
		'success',
		[
			'meta'            => [ 'tools' => 3 ],
			'next_run_at_gmt' => null,
		]
	);
}
```

## API

### `get_instance(): Agent_Run_Helper`
Singleton accessor.

### `create_run( int $session_id ): int`
Creates a queued run row and returns the run ID (or `0` on failure).

### `claim_run( int $run_id, string $worker_id, int $lease_ttl_seconds = 120 ): array`
Attempts to claim a queued run (or reclaim a stale running lock).

Success payload includes:

- `claimed` (`true`)
- `run_id`
- `lock_token`
- `attempt`
- `reclaimed` (`true` when stale lock was reclaimed)

Failure payload includes `claimed => false` and a `reason`:

- `run_not_found`
- `not_claimable`
- `claim_collision`

### `complete_run( int $run_id, string $lock_token, string $status, array $args = [] ): bool`
Completes a claimed run and updates session rollup state in one transaction.

Allowed terminal statuses:

- `success`
- `failed`
- `cancelled`
- `canceled`

Supported `$args` keys:

- `error_code` (string|null)
- `error_message` (string|null)
- `meta` (array; JSON-encoded)
- `next_run_at_gmt` (string|null; passed to session helper)

### `get_run( int $run_id ): array`
Returns a run row as an associative array, or `[]` when not found.

## Notes

- `complete_run()` enforces lock-token ownership before writing completion state.
- Completion rolls back if run or session update fails, preventing partial state.
- For new behavior, put workflow logic in the helper and keep SQL-only logic in the store.
- Schema/table management for runs is owned by `Agent_Run_Store` and called from plugin activation.

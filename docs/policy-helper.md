# ClawPress Policy Helper

Version: 0.1  
Owner: ClawPress  
Status: Draft

## Purpose

`Policy_Helper` provides a single runtime policy contract for tool execution. It translates trigger context (`chat`, `heartbeat`, `spawned_agent`) plus optional overrides into a normalized policy array that downstream helpers can enforce consistently.

Implementation: `includes/helpers/class-policy-helper.php`

## What It Owns

`Policy_Helper` is responsible for:

1. Defining default policy fields.
2. Defining trigger-specific policy overrides.
3. Applying override precedence.
4. Normalizing output types (bool/int/enum-like strings).

`Policy_Helper` is not responsible for:

1. Executing tool calls.
2. Performing permission checks.
3. Enforcing all policy fields itself.

Enforcement is done by consumers such as `Agent_Loop_Helper`, `Abilities_Helper`, and `Agent_Runner`.

## Runtime Policy Contract

Current resolved fields:

1. `trigger_type` (`chat|heartbeat|spawned_agent`)
2. `policy_profile` (normalized profile key, default `default`)
3. `allow_tools` (bool)
4. `allow_destructive_tools` (bool)
5. `require_confirmation_for_destructive` (bool)
6. `allow_file_delete` (bool)
7. `max_tool_rounds` (int >= 1)
8. `max_tool_calls_per_round` (int >= 1)
9. `max_wall_time_seconds` (int >= 1)
10. `allow_network` (bool)
11. `allow_background_followups` (bool)
12. `on_policy_violation` (`deny|degrade|fail`)

## Resolution Algorithm

`Policy_Helper::resolve_runtime_policy( $trigger_type, $session_metadata, $profile_overrides )` resolves policy in this order:

1. Start with `BASE_POLICY`.
2. Merge trigger profile from `TRIGGER_POLICY_OVERRIDES[normalized_trigger]`.
3. Merge `session_metadata['policy_overrides']` if present.
4. Merge explicit `$profile_overrides` (highest precedence).
5. Normalize and clamp values before return.

Precedence summary (lowest to highest):

1. Base defaults
2. Trigger defaults
3. Session metadata overrides
4. Direct method overrides

Unknown or empty trigger types are normalized to `chat`.

## Current Trigger Profiles

### `chat`

Uses base defaults (least restrictive profile), including `allow_network = true`.

### `heartbeat`

More restrictive than chat:

1. `allow_destructive_tools = false`
2. `allow_file_delete = false`
3. `allow_network = true`
4. `max_tool_rounds = 2`
5. `max_tool_calls_per_round = 3`
6. `max_wall_time_seconds = 45`
7. `allow_background_followups = false`

### `spawned_agent`

Restrictive but less constrained than heartbeat:

1. `allow_destructive_tools = false`
2. `allow_file_delete = false`
3. `allow_network = true`
4. `max_tool_rounds = 3`
5. `max_tool_calls_per_round = 4`
6. `max_wall_time_seconds = 90`

## Where It Is Used

## 1) Loop policy resolution

`Agent_Loop_Helper::generate_online_reply()` resolves policy once per turn/slice and then uses it to bound loop execution:

1. `max_tool_rounds` controls tool-call rounds.
2. `max_tool_calls_per_round` caps calls per round.
3. The resolved policy is passed into each `Abilities_Helper::execute_tool_call()` invocation as `runtime_policy`.

Implementation: `includes/helpers/class-agent-loop-helper.php`

## 2) Tool execution policy enforcement

`Abilities_Helper::execute_tool_call()` enforces policy gates in this order:

1. `allow_tools`
2. `allow_network` (for network-capable abilities such as `web_fetch`)
3. `allow_destructive_tools` (for destructive abilities)
4. `allow_file_delete` (for `file_delete`)
5. `require_confirmation_for_destructive` (confirmation workflow gate)

On policy violation, it returns a structured payload with:

1. `success: false`
2. error code/message
3. `policy` block (`trigger_type`, `policy_profile`, `on_violation`, `decision`)

Implementation: `includes/helpers/class-abilities-helper.php`

## 3) Runner-level policy enforcement

`Agent_Runner::process_claimed_run()` enforces run-lifecycle guardrails:

1. `max_wall_time_seconds` is enforced before and after slice execution.
2. `allow_background_followups` gates re-enqueue behavior for both continuation (`in_progress`) and retryable error outcomes.

Implementation: `includes/class-agent-runner.php`

## Practical Usage Patterns

## Let helper resolve from trigger + overrides

Use this when the caller has context but does not need to pre-resolve policy:

```php
$result = Abilities_Helper::get_instance()->execute_tool_call(
	'file_delete',
	[ 'path' => 'notes.md' ],
	[
		'trigger_type' => 'heartbeat',
		'session_metadata' => [
			'policy_profile' => 'default',
		],
		'policy_overrides' => [
			'allow_file_delete' => false,
		],
	]
);
```

## Pre-resolve once and pass `runtime_policy`

Use this in loops so every call uses the same resolved contract:

```php
$policy = Policy_Helper::get_instance()->resolve_runtime_policy(
	'spawned_agent',
	[ 'policy_profile' => 'trusted-runner' ],
	[ 'allow_destructive_tools' => true ]
);

$result = Abilities_Helper::get_instance()->execute_tool_call(
	'file_delete',
	[ 'path' => 'notes.md' ],
	[
		'runtime_policy' => $policy,
	]
);
```

## Extension Guide

## Add a new trigger profile

1. Add key to `TRIGGER_POLICY_OVERRIDES` in `class-policy-helper.php`.
2. Keep values partial: only overrides that differ from base.
3. Add unit coverage in `tests/Unit/PolicyHelperTest.php`.
4. Add enforcement coverage in `tests/Unit/AbilitiesHelperTest.php` (if behavior affects tool gates).

## Add a new policy field

1. Add default in `BASE_POLICY`.
2. Add optional per-trigger overrides.
3. Add normalization in `resolve_runtime_policy()`.
4. Enforce in the correct consumer (`Chat_Helper`, `Abilities_Helper`, command handlers, or scheduler jobs).
5. Add tests for:
   - default behavior
   - override behavior
   - enforcement behavior

## Policy violation handling

`on_policy_violation` is normalized in `Policy_Helper` and enforced in `Abilities_Helper`:

1. `deny`: returns policy error payload.
2. `degrade`: returns successful degraded no-op payload.
3. `fail`: returns policy error payload with `*_fail` code suffix.

## Suggested Future Improvements

1. Add a policy filter hook (for example, `clawpress_runtime_policy_resolved`) if third-party plugins need policy customization without patching core code.
2. If future network-capable tools need different restrictions than `web_fetch`, split `allow_network` into capability-specific fields without overloading destructive-tool policy.

## Test Coverage Today

Current coverage includes:

1. Base and trigger policy contracts (`PolicyHelperTest`).
2. Deterministic override application (`PolicyHelperTest`).
3. Destructive/file-delete gates under policy (`AbilitiesHelperTest`).

Recommended additional coverage:

1. Chat loop behavior under non-default `max_tool_rounds` and `max_tool_calls_per_round`.
2. Any new enforcement path added for future policy fields beyond the current tool and network gates.

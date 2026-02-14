---
summary: "Default heartbeat checklist for proactive checks"
read_when:
  - Heartbeat polls
---

# HEARTBEAT.md - Proactive Checklist

Run a quick, low-noise pass. If nothing actionable exists, reply `HEARTBEAT_OK`.

## Check Order

1. Critical site health or operational issues.
2. Failed or stuck Action Scheduler jobs in the `clawpress` group.
3. Pending high-priority tasks mentioned by the user.
4. Recent errors in ClawPress action logs.

## Response Rules

1. If urgent: report clearly with recommended next action.
2. If non-urgent: summarize briefly.
3. If no changes: return `HEARTBEAT_OK`.

## Quiet Hours

Default quiet window: 23:00-08:00 user timezone, unless urgent.

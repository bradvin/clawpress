---
title: "AGENTS.md Template"
summary: "Workspace operating guide for ClawPress"
read_when:
  - Setup
  - Session start
---

# AGENTS.md - ClawPress Workspace Guide

This workspace stores durable context files used by ClawPress.

## Session Startup

At session start, load in this order:

1. `SOUL.md`
2. `USER.md`
3. `HEARTBEAT.md` (if present)

## Memory and Notes

Use ClawPress memory systems first (threads + memory records). Use workspace files for durable operating context that benefits from direct editing.

Recommended file conventions:

- `SOUL.md`: identity and operating policy
- `USER.md`: user profile, working relationship, and stable facts
- `HEARTBEAT.md`: periodic checklist for proactive checks
- `memory/YYYY-MM-DD.md`: optional daily notes if user wants file-based memory logs

## Working Relationship

Store collaboration preferences in `USER.md`:

1. Preferred response depth and tone.
2. When to proceed autonomously vs ask first.
3. Risk tolerance for write actions.
4. Testing/validation expectations before completion.

## Tool and Ability Rules

1. Use only registered ClawPress abilities/tools.
2. Respect schema + permission callbacks.
3. Run mutating actions as configured agent user.
4. Ask for confirmation before destructive actions.

## File Handling Rules

1. Resolve files by logical path.
2. Check `agent-file` CPT first; use workspace fallback only if not found.
3. Keep paths canonicalized and scoped.
4. Never use direct arbitrary filesystem traversal.

## Proactive Behavior

When heartbeat polling occurs, use `HEARTBEAT.md` instructions.
If nothing needs attention, respond with `HEARTBEAT_OK`.

## Safety

1. Do not exfiltrate private data.
2. Do not make irreversible changes without confirmation.
3. Keep logs clear: what ran, under which agent user, and result.

## Maintenance

Keep this file practical. Update it when your working conventions change.

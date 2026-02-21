# Lint debt reduction plan

This project will enforce lint in phases so contributors can keep shipping while we burn down legacy violations.

## Phase 1 (now): changed-file lint gate

- CI runs `lint-changed` on every PR.
- Only files touched in the PR are lint-gated.
- Goal: no new lint debt.

## Phase 2 (by 2026-03-15): baseline capture

- Capture current full-repo lint violations into a baseline record (`docs/lint-baseline.md`).
- Categorize by type:
  - PHPCS
  - JS lint
- Track counts and owners.

## Phase 3 (2026-03-15 to 2026-05-15): burn-down

Milestones:

1. **M1 (2026-03-31)**: reduce baseline by 25%
2. **M2 (2026-04-30)**: reduce baseline by 60%
3. **M3 (2026-05-15)**: reduce baseline by 90%+

## Phase 4 (target: 2026-06-01): full strict lint

- Switch CI from changed-file lint to full-repo strict lint as required checks.
- Keep `lint-changed` available for fast local feedback.

## Commands

- Changed files: `npm run lint:changed`
- Full lint: `npm run lint`

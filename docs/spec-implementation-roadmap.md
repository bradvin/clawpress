# ClawPress Implementation Spec Roadmap

This roadmap breaks `docs/clawpress-plugin-spec.md` and `docs/clawpress-agent-spec.md` into implementation-sized specs.

## Order of Execution

1. `docs/spec-1-foundation-chat-shell-and-status.md`
2. `docs/spec-2-offline-command-engine.md`
3. `docs/spec-3-setup.md`
4. `docs/spec-4-provider-settings-and-online-agent-routing.md`
5. `docs/spec-5-conversation-storage-and-memory.md`
6. `docs/spec-6-abilities-tooling-and-action-execution.md`
7. `docs/spec-7-file-resolver-and-workspace-isolation.md`
8. `docs/spec-8-security-policy-confirmation-and-audit.md`
9. `docs/spec-9-background-jobs-observability-and-health.md`
10. `docs/spec-10-multi-channel-and-orchestration.md`

## Milestone Mapping

- Phase 1 (Foundation): Specs 1-3
- Phase 2 (Online AI): Specs 4-6
- Phase 3 (Memory + Safety Hardening): Specs 7-9
- Phase 4 (Advanced): Spec 10

## Notes

- Each spec is independently reviewable and testable.
- All implementation must follow WPCS, strict types, and namespaced modules.
- New modules should be loaded from `clawpress.php` only after the feature spec is approved.

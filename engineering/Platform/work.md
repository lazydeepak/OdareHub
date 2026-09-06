# Platform — Work

## Current Focus

Local deployment and update channel baseline.

## In Progress

- [x] Document current deployment readiness entrypoints and owner boundaries. See `docs/architecture/deployment-update-foundation-audit.md`.
- [ ] Run local deployment readiness on a clean working tree.
- [x] Inspect existing release package generation and history flow. See `docs/architecture/deployment-update-foundation-audit.md`.
- [x] Define local update channel manifest schema. See `docs/architecture/local-release-channel-contract.md`.
- [x] Generate a local channel manifest from an existing release package.
- [x] Preview an update from the local channel without applying it.
- [x] Prepare a staged local apply plan without mutating the live installation.
- [ ] Define safe V1 apply behavior, post-update verification, and recovery checklist.

## Deferred Until Deployment Baseline

- [x] Classify current apps, modules, plugins, platform engines, and Studio tools by ownership type before Hospitality implementation. See `docs/architecture/current-ownership-inventory.md`.
- [x] Identify legacy `suite` and `bundle` wording that represents old lifecycle vocabulary, not current first-class Suite architecture. See `docs/architecture/current-ownership-inventory.md`.
- [ ] Decide whether `Procurement` should be treated as a Shared App or Domain App.
- [ ] Define the first runtime-compatible convention for App Extensions.

## Next

- [ ] Define a versioned local channel manifest with release SHA-256, compatibility, migration warnings, and explicit apply eligibility.
- [x] Define a complete database backup/recovery provider contract and configuration-preservation rules before any local apply implementation. See `docs/architecture/backup-provider-implementation-plan.md`.
- [x] Implement read-only MySQL/MariaDB provider preflight plus recovery-point metadata validation tests. See `platform/Recovery/tests/probe_recovery_provider_preflight.php`.
- [x] Implement database dump and filesystem archive creation with disposable integration fixtures and checksum verification. See `platform/Recovery/tests/probe_recovery_backup_providers.php`.
- [x] Implement isolated restore rehearsal and only then evaluate update-apply eligibility. See `platform/Recovery/tests/probe_restore_rehearsal_service.php`.
- [x] Produce a controlled package/channel compatibility preview without applying a release. See `platform/Updates/tests/probe_local_release_channel_preview.php`.
- [ ] Define post-apply verification and a rehearsed code/database/configuration restore acceptance test before controlled local apply.
- [ ] Resume app ownership classification after the local deployment/update channel baseline is usable.
- [ ] Prepare the first Hospitality App scope after deployment baseline and ownership audit pass.
- [ ] Keep Studio as a future system doctor/upgrade workbench; do not depend on Studio to generate Hospitality yet.

## Blocked

- Full local deployment readiness is blocked by `scripts/architecture/check_localization_migration_guardrail.sh`: tracked legacy locale files remain under `apps/Studio/Tools/LabelDesigner/lang/` (`en.php`, `ja.php`, `ne.php`).

## Completed

- 2026-08-19 — Implemented Platform-owned read-only recovery provider preflight and recovery-point metadata validation (`platform/Recovery/`). Probe: `13/13` pass. No dump, archive, restore point, storage directory, DB, update, or runtime mutation.
- 2026-08-19 — Implemented disposable-fixture recovery backup providers (`MySqlDumpProvider`, `FilesystemArchiveProvider`). Probe creates only temporary dump/archive fixtures, verifies checksums, removes temporary credential files, and does not create production recovery storage.
- 2026-08-19 — Implemented isolated restore rehearsal service with disposable payload verification, safe ZIP extraction, injected database rehearsal, and explicit non-mutating failure states. No production recovery storage, real database restore, update apply, or installer behavior.
- 2026-08-19 — Implemented read-only local release channel preview service with disposable channel fixtures, package checksum/size binding, compatibility checks, recovery-evidence apply gate, and no update-channel storage creation.
- 2026-08-19 — Implemented Platform recovery-point orchestrator and governed CLI wrapper with explicit target-directory create/verify, metadata validation, payload verification, and registry documentation. CLI does not create production recovery storage implicitly.
- 2026-08-19 — Added explicit CLI restore rehearsal mode using a separate rehearsal database and isolated filesystem directory. It refuses to rehearse into the source database and remains separate from update apply/installer behavior.
- 2026-08-19 — Compacted restore rehearsal CLI output and wrote `rehearsal.json` evidence beside the recovery point, including final state, rehearsal database, filesystem entry count, and entries checksum.
- 2026-08-19 — Added local release channel builder and CLI preview/build modes. Generated app-lane packages exclude storage/packages and can be previewed against verified recovery evidence without applying.
- 2026-08-19 — Added local apply preparation staging. It requires an apply-eligible channel preview plus verified/rehearsed recovery evidence, extracts only into an explicit empty staging directory, rejects blocked payload paths, writes `apply-plan.json`, and performs no live mutation.
- 2026-08-19 — Rebuilt the local release channel and prepared a staged apply plan against rehearsed recovery evidence. Live installation mutation remained false. Full deployment readiness was attempted but remains blocked by the existing Label Designer legacy-locale migration guardrail.
- 2026-08-19 — Defined the V1 local recovery provider implementation plan in `docs/architecture/backup-provider-implementation-plan.md`: MySQL/MariaDB dump, filesystem ZIP archive, non-secret recovery metadata, CLI-first boundary, verification, rehearsal, retention, and phased tests. No runtime code changed.
- 2026-08-19 — Defined the V1 backup/restore/recovery contract in `docs/architecture/backup-restore-recovery-contract.md`: full database plus filesystem recovery point, preservation rules, restore verification, explicit final states, and update/installer gate. No runtime code changed.
- 2026-08-19 — Defined V1 local release package and filesystem-backed channel contract in `docs/architecture/local-release-channel-contract.md`: monolith `app` lane, vendor-included payload, immutable SHA-256 metadata, preservation/exclusion rules, read-only preview, backup/restore gates, and future private-update mapping. No runtime code changed.
- 2026-08-19 — Completed factual deployment/update foundation audit in `docs/architecture/deployment-update-foundation-audit.md`. Readiness, packaging, lifecycle apply, export/restore, version/history, environment, checksum/signature, database backup, installer, and private-update gaps are documented. No runtime code changed.
- 2026-08-19 — Completed Phase 1 read-only ownership inventory in `docs/architecture/current-ownership-inventory.md`: six top-level apps, 32 app modules, five root plugins, root Platform engines, Studio tools, package/release/upgrade machinery, report ownership, legacy vocabulary, and unresolved decisions. No runtime code changed.
- 2026-08-19 — Consolidated runtime-discovery, lifecycle, naming, deployment, Studio, report, and Hospitality constraints in `docs/architecture/susankhya-productization-roadmap.md`. The roadmap is documentation-only and does not authorize runtime refactors, installer/update implementation, or Hospitality implementation.
- 2026-08-19 — Recorded local deployment and update channel planning baseline in `docs/architecture/local-deployment-update-channel-plan.md`.
- 2026-08-17 — Recorded app ownership classification and Hospitality readiness planning baseline in `docs/architecture/app-ownership-classification-and-hospitality-readiness.md`.
- 2026-07-03 — Engineering Workspace execution lifecycle strengthened into a platform contract. Implementation, review, planning, and analysis must resolve and load the workspace contract before repository inspection; unresolved implementation-capable work is blocked, including platform scope.

## Evidence

- `php platform/Engineering/tests/probe_agent_bootstrap.php` — 93 passed, 0 failed.
- `php platform/Engineering/tests/probe_agent_execution_gate.php` — 98 passed, 0 failed.
- `php apps/Studio/Tools/EngineeringWorkspaces/tests/probe_agent_context_integration.php` — 19 passed, 0 failed.
- `git diff --check` — clean.

# Platform — Work

## Current Focus

Local deployment and update channel baseline.

Current reconciliation: OH-AUTO1-055 corrected the previously open Core ACL
deny-precedence and plugin route-rollback defects. OH-AUTO1-068 has now
requalified bounded disposable database testing; installed-database access,
production verification, release authority, installed identity, and runtime
acceptance remain blocked or human-gated.

OH-AUTO1-057 is complete as a documentation-only proposal for REL-001 and
REL-002. Signing, identity persistence, database, release apply, and deployment
implementation remain unauthorized.

OH-AUTO1-058 is complete as a bounded REL-004 package-path correction. The
work was limited to disposable ZIP fixtures and package-builder policy; no
package publication, installation, database, or deployment operation occurred.

OH-AUTO1-059 is complete as a read-only REL-003 migration-compatibility
contract proposal. OH-AUTO1-060 reconciles its executor feasibility gap.
Migration execution, SQL, schema mutation, and a second migration system
remain unauthorized.

## In Progress

- [x] OH-AUTO1-018 — Critical business workflow and data-integrity audit. Corrected the Procurement order/receipt method-boundary defect and added a read-only regression probe. Broader DB-backed behavioral probes remain not run because they mutate the installed database. See `docs/architecture/business-workflow-data-integrity-audit-2026-10-14.md`.
- [x] OH-AUTO1-019 — Operational diagnostics and observability audit. Corrected raw exception propagation from operator read surfaces, retained server-side diagnostics, and added a read-only redaction probe. Core/global logging, realtime error handling, and live acceptance remain escalated. See `docs/architecture/operational-diagnostics-observability-audit-2026-10-14.md`.
- [x] OH-AUTO1-021 — Consolidated production-readiness reconciliation. Current baseline is dirty/uncommitted and production readiness remains blocked by ACL, lifecycle, isolated-DB, release-authority, and human-gated deployment evidence. See `docs/architecture/consolidated-production-readiness-reconciliation-2026-10-14.md`.
- [x] OH-AUTO1-022 — Added the non-executing disposable DB safety harness and known parent/child fixture contract. Docker CLI exists but the daemon is unavailable; no DB provisioning or SQL execution was attempted.
- [x] OH-AUTO1-023 — Hardened the disposable DB validator so synthetic proof is always mock-only and blocked; added 14 negative safety assertions and reproduced SEC-AUTH-001 with a Core-free in-memory ACL probe. See `docs/architecture/security-acl-deny-precedence-remediation-spec-2026-10-14.md`.
- [x] OH-AUTO1-024 — Reproduced plugin partial-route retention with an in-memory Router fixture, inspected `PluginManager::loadActivePlugins()`, and documented the minimal Core-owned rollback contract. Probe passes `16/16`; no plugin activation, DB, lifecycle mutation, or Core change. See `docs/architecture/plugin-route-registration-atomicity-audit-2026-10-14.md`.
- [x] OH-AUTO1-025 — Established the Git/file baseline: main at `42e0ccd8`, upstream parity `0/0`, 36 tracked modifications, 36 untracked files, no Core diff; focused probes, PHP lint, architecture gates, deletion gates `28/28`, deployment readiness, and diff hygiene pass. Integration remains blocked by ownership reconciliation and missing behavioral verification. See `docs/architecture/integration-baseline-verification-2026-10-14.md`.
- [x] OH-AUTO1-026 — Classified the live working tree into nine proposed integration groups plus an explicit runtime-artifact exclusion. The machine-checkable manifest covered 73/73 paths at completion; the subsequent OH-AUTO1-027 plan is now separately classified, making the live set 74/74 with 72 candidate records and 2 excluded logs. No unknown provenance, Core changes, staging, or implementation changes. See `docs/architecture/oh-auto1-governed-integration-manifest-2026-10-14.md`.
- [x] OH-AUTO1-027 — Prepared the dependency-ordered 11-commit proposal covering 72 candidate paths, with paired tests, exact file lists, review owners, focused verification commands, rollback notes, and explicit exclusion of both runtime logs. No staging or commit performed. See `docs/architecture/oh-auto1-integration-commit-plan-2026-10-14.md`.
- [x] OH-AUTO1-028 — Discovered and safely verified a disposable Homebrew MySQL 9.6.0 instance using a fresh datadir, unique Unix socket, `--no-defaults`, and `--skip-networking`; exact repository fixture commit/rollback/FK checks passed, harness remained blocked, and cleanup preserved ServBay PID 1181/port 3306. See `docs/architecture/disposable-database-environment-discovery-2026-10-14.md`.
- [x] OH-AUTO1-017 — Lifecycle execution-boundary hardening. Added the read-only SBAIO negative probe, verified the prior route correction, reproduced plugin partial-route retention for Core escalation, and resolved the aggregate timeout as a slow 28-gate deletion-family scan. See `docs/architecture/lifecycle-execution-boundary-hardening-2026-10-14.md`.
- [x] OH-AUTO1-053 — Diagnosed and corrected redundant Studio deletion-family gate traversal. The complete architecture suite passed 49/49; deletion-family certification remains 28/28 and dropped from approximately 320 seconds to approximately 43 seconds without removing assertions. See `docs/architecture/oh-auto1-053-aggregate-gate-reliability-2026-10-15.md`.
- [x] OH-AUTO1-054 — Separated non-mutating deployment-readiness verification from explicit CSS publication. Default readiness now uses strict `--verify`; publication remains explicit via `--apply`. Fixture probe 10/10 and full readiness pass. See `docs/architecture/oh-auto1-054-non-mutating-deployment-readiness-2026-10-15.md`.
- [x] OH-AUTO1-055 — Applied authorized Core ACL deny-precedence and plugin route-registration rollback corrections. In-memory regressions pass 12/12 and 15/15; architecture gates pass 49/49 with the scoped Core exception. See `docs/architecture/oh-auto1-055-core-security-lifecycle-remediation-2026-10-15.md`.
- [x] OH-AUTO1-057 — Release authority and installed identity V1 design proposal. Pending architecture/release-authority decisions; no runtime implementation. See `docs/architecture/oh-auto1-057-release-authority-installed-identity-design-2026-10-15.md`.
- [x] OH-AUTO1-058 — Resolved vendor development metadata conflict at the release-builder boundary without weakening strict package inspection. REL-004 fixture 15/15; existing builder 13/13, preview 30/30, apply preparation 7/7, and readiness evidence 10/10. See `docs/architecture/oh-auto1-058-release-package-protected-path-compatibility-2026-10-15.md`.
- [x] OH-AUTO1-059 — Release migration compatibility, target-schema identity, ordering, failure, and downgrade V1 contract proposal. Pending architecture/release-authority decisions; no migration execution or schema mutation. See `docs/architecture/oh-auto1-059-release-migration-compatibility-ordering-contract-2026-10-15.md`.
- [x] OH-AUTO1-060 — Reconciled REL-003 execution feasibility: owner-batched runner limitation, exact planned-step contract, and failed-row evidence preservation. Pure fixture 18/18; existing migration probes unchanged. No runtime implementation or database execution. See the OH-AUTO1-059 contract and Platform decision log.
- [x] OH-AUTO1-061 — Produced and inspected an actual-source disposable package; identified the broken Composer autoload boundary caused by filtered development targets.
- [x] OH-AUTO1-062 — Regenerated production Composer autoload metadata in runner-owned staging with `--no-dev --no-scripts --no-plugins`, verified zero missing production targets, and added a focused portability probe. Full development-package pruning remains policy-gated because Composer install/metadata rewriting is not authorized.
- [x] OH-AUTO1-063 — Protected release identity-derived ZIP/sidecar/channel artifacts from overwrite and normalized build-ID collisions using temporary finalization and fail-closed collision checks. Immutability probe 13/13.
- [x] OH-AUTO1-064 — Implemented pure offline Ed25519 release-statement canonicalization and verification with caller-supplied trust fixtures. Deterministic verifier probe 24/24; production release authorization remains disabled.
- [x] OH-AUTO1-065 — Added read-only exact ZIP/sidecar artifact binding over stable byte snapshots, strict package inspection, and Ed25519 verification. Artifact probe 18/18; readiness spoof reproduced without changing eligibility behavior.
- [x] OH-AUTO1-066 — Removed caller-controlled readiness authenticity from eligibility; readiness now fails closed with `eligible=false` until independently verified authority is integrated. Focused readiness, signature, artifact, preview, and apply regressions pass.
- [x] OH-AUTO1-067 — Added read-only release-authenticity diagnostics to local channel preview using exact ZIP/sidecar verification and explicit in-memory signature/trust inputs. Valid, unsigned, invalid, unknown, revoked, mutation, missing-input, readiness, compatibility, and apply-blocking cases pass. See `docs/architecture/oh-auto1-067-read-only-release-authenticity-preview-2026-10-15.md`.
- [x] OH-AUTO1-068 — Requalified bounded disposable MySQL testing. Corrected the runner to stage and execute a complete isolated source root, reproduced configuration precedence safely, passed isolation negatives 11/11, fixture commit/rollback/foreign-key smoke, and staged Manufacturing transaction 21/21. Installed ServBay remained untouched. See `docs/architecture/oh-auto1-068-database-isolation-requalification-2026-10-15.md`.
- [x] OH-AUTO1-016 — Read-only application/module lifecycle enforcement audit. Corrected the SBAIO legacy fallback loader outside Core; escalated module-surface filtering, universal module route guards, and plugin partial-route rollback for Core/platform owner review. See `docs/architecture/lifecycle-enforcement-audit-2026-10-14.md`.
- [x] OH-AUTO1-010 — CLI installed-state evidence integration.
- [x] OH-AUTO1-011 — Migration ordering and schema-target compatibility preflight.
- [x] OH-AUTO1-012 — Local readiness evidence collection and authority boundary.
- [x] OH-AUTO1-013 — Authoritative installed build identity investigation.
- [x] OH-AUTO1-014 — Consolidated release baseline and regression audit.
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

- Database-backed production verification remains blocked because OH-AUTO1-056 is explicitly deferred.
- Release authority/attestation, installed build identity, migration semantics, and live runtime acceptance remain unresolved or human-gated.
- Full production dependency pruning remains unresolved pending an approved Composer installation/metadata materialization policy; autoload portability itself is corrected and verified.

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
- 2026-08-19 — Consolidated runtime-discovery, lifecycle, naming, deployment, Studio, report, and Hospitality constraints in `docs/architecture/odarehub-productization-roadmap.md`. The roadmap is documentation-only and does not authorize runtime refactors, installer/update implementation, or Hospitality implementation.
- 2026-08-19 — Recorded local deployment and update channel planning baseline in `docs/architecture/local-deployment-update-channel-plan.md`.
- 2026-08-17 — Recorded app ownership classification and Hospitality readiness planning baseline in `docs/architecture/app-ownership-classification-and-hospitality-readiness.md`.
- 2026-07-03 — Engineering Workspace execution lifecycle strengthened into a platform contract. Implementation, review, planning, and analysis must resolve and load the workspace contract before repository inspection; unresolved implementation-capable work is blocked, including platform scope.

## Evidence

- `php platform/Engineering/tests/probe_agent_bootstrap.php` — 93 passed, 0 failed.
- `php platform/Engineering/tests/probe_agent_execution_gate.php` — 98 passed, 0 failed.
- `php apps/Studio/Tools/EngineeringWorkspaces/tests/probe_agent_context_integration.php` — 19 passed, 0 failed.
- `git diff --check` — clean.

## OH-AUTO1-015 — 2026-10-14

- [x] Audited authentication/session lifecycle, login/2FA/logout, session-version invalidation,
  CSRF, privileged route guards, app/module/plugin route ownership, and operator self-workspace
  mutation confinement.
- [x] Audited organization and business schemas for tenant/company/branch ownership fields.
- [x] Confirmed no generic end-user tenant boundary exists for Hospitality, Manufacturing, or
  Parties; organization tables are platform-governance data rather than user tenant scopes.
- [x] Reproduced the ACL deny re-addition path with a pure in-memory disposable probe; no database,
  HTTP, browser, schema, or runtime mutation was used.
- [x] Recorded the finding and remediation direction in
  `docs/architecture/security-authorization-tenant-isolation-audit-2026-10-14.md`.
- [ ] Core/security owner must decide and implement the deny-precedence correction before ACL
  deny overrides can be treated as reliable for all grant sources.
- [ ] Define an explicit tenant/organization contract before claiming cross-organization isolation.
- [ ] Live authenticated HTTP acceptance remains environment-bound and unverified.

## OH-AUTO1-001 — 2026-10-14

- [x] Verified the worker assignment was not represented by an existing repository task or active
  duplicate before proceeding.
- [x] Repaired `scripts/architecture/check_localization_resource_diagnostics.sh` path
  normalization so repository-relative `./apps/...` locale paths are evaluated correctly.
- [x] Extended the diagnostic boundary and owner-key handling to support nested Studio tool
  locale paths under `apps/Studio/Tools/**/Resources/lang/`.
- [x] Removed the per-file diagnostic result pipeline subshell so boundary errors and warnings
  are counted in the final result.
- [x] Focused diagnostic: `RESULT: PASS (32 warning(s))`; warnings are existing locale parity /
  value-quality findings, not boundary failures.
- [x] Full readiness: `DEPLOYMENT READINESS: PASS`; architecture gates and deletion family gates
  passed.
- [ ] Transport binding/delivery remains unverified by repository evidence; this is external to
   the codebase and requires planner/worker-session infrastructure confirmation.

## OH-AUTO1-006 — 2026-10-14

- [x] Added `ReleasePackageInspectionService` as the shared read-only V1 app-lane ZIP inspector for
  builder self-validation, channel preview, and apply preparation.
- [x] Enforced contractual runtime roots and files: `app/`, `apps/`, `platform/`, `plugins/`,
  `public/`, `resources/`, `vendor/`, `bin/`, `composer.json`, `composer.lock`, and internal
  `release_metadata.json`.
- [x] Enforced valid ZIP structure, readable entries, duplicate/normalized-path collision checks,
  traversal/absolute path rejection, non-regular entry rejection, and protected installation path
  rejection.
- [x] Enforced Composer JSON/lock structure and requirement presence, internal/external metadata
  identity consistency, and package filename consistency.
- [x] Updated the builder to emit internal metadata and reject incomplete generated packages rather
  than weakening preview validation.
- [x] Added disposable positive and negative package fixtures covering missing roots/files, metadata
  mismatches, corrupt ZIPs, duplicate/colliding/traversal entries, unsafe entry types, protected
  paths, checksum, and size failures.
- [x] Update/recovery probes, PHP lint, architecture gates, deletion gates `28/28`, deployment
  readiness, and diff hygiene passed.
- [ ] Commit/push remain deferred pending human authorization.

### Remaining contract limitations

- V1 package checksums establish byte integrity, not signing or authorization.
- Composer lock validation checks JSON structure, content-hash format when present, and declared
  requirement presence; Composer itself is not executed during preview or apply preparation.

## OH-AUTO1-007 — 2026-10-14

- [x] Added `InstalledSystemCompatibilityPreflightService` as the single read-only compatibility
  path used by release preview.
- [x] Required structured installed evidence for product/platform identity, versions, app/module
  inventories, schema baseline, and migration state.
- [x] Enforced declared product/platform/app/module version ranges, required component presence,
  dependency presence and lifecycle status, PHP prerequisites, schema minimums, unresolved
  pending/failed migrations, contradictory identity, and unsupported downgrade direction.
- [x] Integrated compatibility results into release preview and apply preparation without enabling
  activation, migration, or any runtime mutation.
- [x] Added dedicated compatibility fixtures plus preview/apply negative coverage for missing
  evidence, identity/version/dependency/status/schema/migration/downgrade/metadata failures.
- [x] Update/recovery probes, PHP lint, architecture gates, deletion gates `28/28`, deployment
  readiness, and diff hygiene passed.
- [ ] Commit/push remain deferred pending human authorization.

### Remaining compatibility limitations

- The V1 metadata example permits empty app/module requirement arrays; no app/module claim is
  inferred when the release does not declare one.
- The repository has no authoritative generic migration-order or schema-target compatibility
  contract. Pending/failed migration state is blocked, while unsupported target semantics remain
  explicitly unverified rather than synthesized.
- The CLI currently supplies only product version context and therefore fails closed until a
  complete installed-state evidence provider is authorized and connected.

## OH-AUTO1-003 — 2026-10-14

- [x] Replaced overlapping locale glob discovery with one sorted, unique traversal of
  `apps/**` and `plugins/**` `Resources/lang/*.php` files.
- [x] Preserved nested Studio ownership keys and made temporary parity storage identifiers
  reversible and collision-safe for slash-containing owner paths.
- [x] Moved parity warning evaluation into the parent shell process and included parity records
  in the final warning count.
- [x] Added deterministic warning accounting plus per-category and per-owner totals.
- [x] Added isolated temporary-fixture regression coverage for nested ownership, naming,
  dangerous-value heuristics, parity warnings, and unique discovery.
- [x] Focused diagnostic: 118 unique locale files; 82 emitted warnings and 82 recorded warnings
  (63 parity, 16 dangerous-value heuristic, 3 naming); `RESULT: PASS`.
- [x] Architecture gates: PASS; deletion family gates: 28/28; deployment readiness: PASS.
- [x] `git diff --check`: clean.
- [ ] Commit/push remain deferred pending human authorization.

## OH-AUTO1-005 — 2026-10-14

- [x] Replaced caller-supplied recovery boolean evidence with an explicit recovery-point
  directory input in release preview and apply preparation.
- [x] Added authoritative recovery-point verification through `RecoveryPointService`, including
  metadata validation, database/filesystem payload existence, SHA-256, and byte-size checks.
- [x] Bound recovery evidence to installed product/release/build identity where supplied.
- [x] Required structured `susankhya.rehearsal.v1` evidence bound to the recovery point, with
  restored final state and successful database/filesystem rehearsal results.
- [x] Added isolated negative coverage for fabricated/missing/corrupt/mismatched recovery
  evidence, payload checksum/availability failures, rehearsal absence, and apply-preparation
  bypass attempts; valid fixture evidence remains apply-eligible.
- [x] Focused update/recovery probes, PHP lint, architecture gates, deployment readiness, and
  diff hygiene passed.
- [ ] Commit/push remain deferred pending human authorization.

### Remaining contract limitation

The existing rehearsal evidence format is a structured local record without a cryptographic
signature or external attestation. The verifier therefore fails closed on missing, malformed,
mismatched, or unsuccessful evidence but does not claim signer-level authenticity. A future
release-safety slice must define and authorize that stronger provenance mechanism before treating
local rehearsal records as independently authenticated evidence.

## OH-AUTO1-008 — 2026-10-14

- [x] Added `ReleaseReadinessEvidenceService` to validate explicit readiness check identities,
  statuses, evidence payloads, required checks, and exact release-artifact binding.
- [x] Corrected `LocalReleaseChannelBuilderService` so it no longer declares readiness passed with
  empty evidence. It records package-contract evidence, marks architecture/deployment checks
  unknown, and sets overall readiness to blocked.
- [x] Integrated readiness validation into release preview. Empty, malformed, unknown, failed,
  contradictory, mismatched, and caller-injected success claims fail closed.
- [x] Kept readiness separate from package integrity, installed compatibility, and authoritative
  recovery evidence; no readiness claim bypasses those gates.
- [x] Added deterministic readiness fixtures and preview/apply bypass coverage.
- [x] Update/recovery probes, PHP lint, architecture gates, deletion gates `28/28`, deployment
  readiness, and diff hygiene passed.
- [ ] Commit/push remain deferred pending human authorization.

### Remaining readiness limitations

- V1 has no signature or external attestation mechanism. Structurally valid readiness evidence is
  therefore reported but cannot make preview/apply eligible without an authorized verifier.
- The builder does not execute architecture or deployment readiness itself; it records those checks
  as `unknown` and blocks overall readiness.

## OH-AUTO1-009 — 2026-10-14

- [x] Added `probe_offline_release_recovery_integration.php` using disposable source, channel,
  package, installed-state, and recovery-point fixtures.
- [x] Verified the actual builder → package inspection → preview → compatibility → recovery →
  apply-preparation chain preserves release version, build ID, checksum, and size identity.
- [x] Positive control confirms structural package, compatibility, and recovery checks pass together;
  readiness remains blocked because V1 has no authentic attestation provider.
- [x] Added negative integration cases for missing readiness, incompatible schema, recovery identity
  mismatch, corrupted bytes, channel mismatch, missing dependency, missing package file, fabricated
  readiness, fabricated rehearsal, cross-release recovery reuse, wrong installed release, and apply
  bypass attempts.
- [x] Integration probe: `23/23`; focused update/recovery probes, PHP lint, architecture gates,
  deletion gates `28/28`, deployment readiness, and diff hygiene passed.
- [ ] Commit/push remain deferred pending human authorization.

### Cross-service result

- Structural release validity, installed compatibility, recovery verification, readiness evidence, and
  apply preparation remain separate results. No service interaction converts blocked or unauthenticated
  evidence into eligibility.

### Remaining blockers

- V1 still lacks an authorized readiness attestation provider and migration compatibility contract.
- CLI installed-state evidence remains incomplete and fails closed; live installation/deployment
  acceptance remains human-gated.

## OH-AUTO1-010 — 2026-10-14

- [x] Added `InstalledSystemEvidenceProvider` as the single read-only installed-state collector for
  application registry, module registry, product identity settings, migration ledger, and schema
  snapshots.
- [x] Connected `scripts/system/recovery_point.php` preview and prepare-apply commands to the
  provider; both now pass the same collected evidence into compatibility preflight.
- [x] Removed CLI placeholder evidence (`product_version => APP_VERSION`). Missing explicit product,
  platform build, registry, schema, migration, or database evidence fails closed with diagnostics.
- [x] Added injectable offline readers and regression coverage for valid evidence, missing registries,
  unavailable database, disabled/missing components, dependency mismatch, contradictory lifecycle,
  missing schema ledger, pending/failed migrations, unsupported platform version, incomplete version,
  and CLI placeholder-injection prevention.
- [x] Provider probe: `22/22`; all update/recovery probes, PHP lint, architecture gates, deletion
  gates `28/28`, deployment readiness, and diff hygiene passed.
- [ ] Commit/push remain deferred pending human authorization.

### Authoritative source map

- Product identity: `core_settings` (`product_id` or `platform.product_id`); absence is blocked.
- Platform/application registry: `AppRegistryService` backed by `core_apps`.
- Module registry/lifecycle: `installed_plugins` rows with explicit status/version/dependency JSON.
- Schema baseline: explicit `schema_version`/`version` in `core_schema_snapshots.snapshot_json`.
- Migration state: `core_app_migrations`, compared with declared installed migration files for pending
  detection; no file presence is treated as a successful migration.
- Runtime prerequisites: existing compatibility preflight checks the actual CLI PHP runtime/extensions.

### Current CLI limitation

The live CLI now reaches the provider but the current installed registry has no explicit platform
`build_id`; it returns structured evidence-unavailable output and exits nonzero. No checksum is
reinterpreted as a build identity.

### Consolidated uncommitted OH-AUTO changes

- OH-AUTO1-001/003: localization diagnostic gate and disposable diagnostic regression fixture.
- OH-AUTO1-005: recovery-point verification, recovery CLI integration, and recovery/update probes.
- OH-AUTO1-006: package inspection, builder/package fixture coverage, and package preview/apply checks.
- OH-AUTO1-007: installed compatibility preflight and compatibility fixtures.
- OH-AUTO1-008: readiness evidence validator, builder/preview/apply readiness changes, and readiness probe.
- OH-AUTO1-009: offline release/recovery integration probe.
- OH-AUTO1-010: installed evidence provider, CLI wiring, and provider probe.
- Shared compliance/work records: `AGENT-COMPLIANCE-CHECKLIST.md` and this workspace `work.md`.

### Unrelated preserved working-tree changes

- `storage/logs/app-lifecycle-dedupe.json`
- `storage/logs/app-lifecycle.log`

## OH-AUTO1-011 — 2026-10-14

- [x] Reconciled the existing migration authority: `AppMigrationService` orders SQL files by
  filename per application, records owner/version/name/checksum/status in `core_app_migrations`,
  and writes schema snapshots to `core_schema_snapshots`.
- [x] Added `MigrationCompatibilityPreflightService` and integrated it into the existing installed
  compatibility preflight. Pending/failed migrations, malformed evidence, and target mismatches are
  hard compatibility failures; unverified ordering/ownership/checksum and unavailable target-schema
  proof remain explicit apply-eligibility blockers.
- [x] Hardened `InstalledSystemEvidenceProvider` to verify migration identity, owner, recorded
  checksum against the declared file, per-application runner order, schema snapshot checksum, and
  pending/failed state. It does not infer migration success from file presence.
- [x] Added `probe_migration_compatibility.php` covering matching history, missing/failed migrations,
  duplicate identity, invalid order, missing owner, checksum mismatch, unknown schema, downgrade,
  target mismatch, malformed evidence, cross-release reuse, and apply-preparation bypass.
- [x] Migration compatibility probe: `16/16`; installed evidence: `22/22`; compatibility `16/16`;
  preview `30/30`; apply preparation `7/7`; offline integration `23/23`; recovery probes,
  architecture gates, deletion gates `28/28`, deployment readiness, PHP lint, and diff hygiene passed.
- [ ] Commit/push remain deferred pending human authorization.

### Migration authority and unresolved contracts

- Authoritative order exists only per application: lexicographic SQL filename order in
  `AppMigrationService`; no cross-application dependency/order graph exists.
- Ownership is the migration row `app_key` and must match an installed `core_apps` owner.
- Migration checksums are authoritative only where the ledger records SHA-256 and the declared file
  is available; absent/mismatched checksums block collection or eligibility.
- `core_schema_snapshots` supplies a baseline only when its JSON version and checksum are present and
  valid.
- No generic release target-schema/result contract exists. Metadata `schema.target` is therefore
  blocked unless independently verified evidence supplies the exact target; current V1 metadata with
  `target: null` remains apply-ineligible.
- No migration dependency graph or downgrade contract exists. No such semantics were invented;
  component dependency checks remain governed by the existing compatibility contract.

## OH-AUTO1-012 — 2026-10-14

- [x] Confirmed the existing trust model: local checksums and local command output establish
  integrity/observation only; no authorized signer, attestation provider, key registry, entitlement
  authority, or release authorization contract exists.
- [x] Added `ReleaseReadinessEvidenceCollectorService`, which binds observations to the exact channel
  package, package checksum/size, release identity, repository revision, and working-tree state before
  executing fixed noninteractive architecture and deployment readiness commands.
- [x] Added strict execution evidence validation: fixed command identity, executed flag, exit code,
  output digest, source revision, working-tree digest, and exact artifact binding. Required checks are
  never caller-selectable or skippable by the collector.
- [x] Added governed CLI command `collect-readiness`. It reports local observations only and never
  persists readiness, grants eligibility, mutates release metadata, or claims attestation.
- [x] Added collector regression coverage for successful command execution, failed/skipped checks,
  fabricated execution, artifact mutation, revision mismatch, dirty working tree, missing channel,
  and unavailable attestation authority.
- [x] Collector probe: `19/19`; readiness evidence: `10/10`; all update/recovery probes, PHP lint,
  architecture gates, deletion gates `28/28`, deployment readiness, and diff hygiene passed.
- [x] Real collector execution observed the repository revision and ran the existing readiness command
  chain, but correctly returned `blocked` because the working tree is dirty and no attestation exists.
- [ ] Commit/push remain deferred pending human authorization.

### Readiness trust model and limitations

- `ReleasePackageInspectionService` and SHA-256 metadata provide package integrity, not authenticity.
- `run_architecture_gates.sh` and `check_deployment_readiness.sh` provide local execution results;
  they do not establish independent authority.
- `ReleaseReadinessEvidenceService` validates schema, execution provenance fields, and artifact binding;
  `eligible` remains false unless an authorized verifier is explicitly present.
- No signing, key generation, cryptographic attestation, entitlement, or authorization policy was
  introduced.
- The existing local channel package currently fails collector package inspection because an older
  stored package contains protected `vendor/ratchet/rfc6455/phpunit.xml.dist`; no package was rebuilt
  or altered by this assignment.

## OH-AUTO1-013 — 2026-10-14

- [x] Traced release identity from `LocalReleaseChannelBuilderService` through channel metadata,
  installed evidence, compatibility preflight, recovery metadata, and recovery verification.
- [x] Confirmed release `build_id` and installed `build_id` are separate concepts. Release construction
  may receive a source revision, but that does not prove what is installed.
- [x] Confirmed the current `core_apps` schema has no persisted `build_id` column. `AppLocalDiscoveryService`
  does not write one, and `InstalledSystemEvidenceProvider::fromRuntime()` therefore cannot collect an
  authoritative installed build identity from the current installation.
- [x] Corrected recovery-point creation so it requires complete installed evidence and takes product,
  version, and build identity from that evidence. It no longer uses repository `git_head()` as an
  installed build identity or falls back to a placeholder.
- [x] Added `probe_installed_build_identity.php` covering explicit structured identity, missing/blank
  identity, separate product identity, repository-revision/package-checksum non-substitution, schema
  absence, discovery non-persistence, and recovery CLI fail-closed behavior.
- [x] Identity probe: `13/13`; installed evidence: `22/22`; installed compatibility: `16/16`.
- [x] Recovery, update, offline integration, architecture, deletion, deployment-readiness, lint, and
  diff-hygiene checks remain passing.
- [ ] No database column, migration, installation write, or competing identity source was introduced.
- [ ] Commit/push remain deferred pending human authorization.

### Authoritative identity gap

- Product identity is read from `core_settings`; platform version and lifecycle are read from `core_apps`.
- No current authoritative source persists the immutable installed platform build identity.
- `core_apps.checksum` is an app-manifest checksum and is not reinterpreted as a platform build ID.
- Repository HEAD is release-build input only and is not accepted as installed identity.
- Recovery metadata requires `installation.build_id`, so recovery creation now blocks when installed identity
  cannot be collected rather than recording a false value.
- The smallest compatible persistence decision is still pending: define one authoritative installation/
  upgrade-owned build identity field and its write lifecycle, then connect the provider and recovery
  creation to that field in a separately authorized schema/install change.

## OH-AUTO1-014 — 2026-10-14

- [x] Captured repository baseline: `main`, HEAD `42e0ccd8c0f9b695dad2b4869f856e579183ded`,
  upstream aligned `0/0`, no Core changes, 14 tracked modifications, 14 untracked automation
  files, and preserved lifecycle-log changes.
- [x] Audited the complete OH-AUTO1-001 through OH-AUTO1-013 inventory across localization,
  package/build, preview/apply preparation, readiness, installed compatibility, migration,
  recovery, build identity, CLI, tests, and governance records.
- [x] Traced builder → package inspection → readiness → installed evidence → compatibility/migration
  → recovery → preview → apply preparation. No bypass or accidental apply path was found.
- [x] Confirmed failed, unknown, unauthenticated, missing, and contradictory evidence remains blocked.
- [x] Confirmed no Core/database/schema/migration execution, installation, restore, deployment, apply,
  commit, or push occurred.
- [x] Consolidated audit recorded in `docs/architecture/consolidated-release-baseline-audit-2026-10-14.md`.
- [x] Full focused regression, PHP lint, architecture gates, deletion gates `28/28`, deployment
  readiness, localization fixture, and diff hygiene passed.
- [x] No additional code correction was required beyond the accepted OH-AUTO1-013 recovery identity
  correction already present in the working tree.
- [ ] Commit/push remain deferred pending human authorization.

### Consolidated audit findings

- No verified engineering defect remains in the audited chain.
- Missing architectural contracts remain: attestation authority, installed build persistence,
  target-schema proof, cross-application migration ordering, and vendor development-file package policy.
- Human-gated acceptance remains outstanding for clean release build, live installation/deployment,
  database migration/restore rehearsal, and real apply/rollback.
- Deferred functionality remains intentionally absent: signing, build persistence, generic migration
  dependency/downgrade semantics, post-apply verification, and production apply.

## Cloud deployment preparation — 2026-09-06

Completed preparation: isolated cloud.odarehub.com workflow, release build/activation scripts, Nginx/PHP-FPM templates and runbook under `.github/cloud/`. Deployment remains disabled; no server, DB, Core, or unrelated changes. Workflow/ShellCheck, 1,738 PHP syntax checks, production payload and checksums, and adapted local server config syntax passed. Existing readiness is blocked: nine gate failures observed; remaining nested final-group checks stopped after all 49 top-level groups were reached. See `.github/cloud/SETUP.md`; no readiness bypass was added.

## OH-AUTO1-042 — Database isolation breach and test bootstrap hardening (2026-10-14)

Status: Source correction completed; database-backed execution remains suspended.

Root cause: `app_db_config()` gives `APP_ROOT/storage/db_config.php` precedence
over database environment variables. A preliminary Hospitality baseline used
the repository APP_ROOT and therefore selected the installed configuration.
Temporary fixture cleanup was reported, but no independent before/after state
exists; restoration is not certified. See
`docs/architecture/oh-auto1-042-database-isolation-incident-2026-10-14.md`.

Added the non-Core, no-connection `DisposableDbTestConfigurationGuard` and
wired it into the shared business-integrity runner plus Procurement,
Manufacturing, and Hospitality concurrency probes. The guard validates the
isolated APP_ROOT/config path, exact endpoint identity, test-only user, and
post-authorization config fingerprint before any probe DB layer loads. Static
regression: 14/14. No Core changes, database connections, SQL, migration,
deployment, commit, or push occurred in OH-AUTO1-042.

## OH-AUTO1-043 - Database test entry-point isolation enforcement (2026-10-14)

Status: Static enforcement complete; database-backed execution remains
suspended.

All 25 DB-capable Procurement, Manufacturing, and Hospitality entry points
were enumerated. Legacy direct-bootstrap probes now require the shared
configuration guard and disposable execution authorizer before autoloading.
The effective `APP_ROOT` must be a complete isolated source root outside the
repository. Missing evidence, repository-root execution, identity mismatch,
or teardown configuration drift fails closed. Static entry-point regression:
63/63; authorizer safety: 9/9; harness safety: 14/14; Core lock: PASS.

## OH-AUTO1-045 - Isolated test execution boundary reconciliation (2026-10-14)

Status: Ready for a separately authorized disposable requalification decision;
database execution remains suspended.

The prior 24-entry-point count omitted the already-guarded
`probe_reservation_create_concurrency.php` from OH-AUTO1-041. The actual
authority list is 25 paths. The source snapshot was audited and reduced from
5,341 to 5,271 files by excluding vendor test/documentation/example/tooling
trees while retaining declared dependencies and runtime resources. Staged
Composer autoload loaded successfully without absolute repository references;
application bootstrap was not run. Runner regression: 17/17; config/entry-point
guard: 63/63; authorizer: 9/9; harness: 14/14; no DB or SQL.

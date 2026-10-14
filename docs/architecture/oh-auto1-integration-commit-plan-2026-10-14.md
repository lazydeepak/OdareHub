# OH-AUTO1 Governed Integration Commit Plan — 2026-10-14

## Status

This document is a proposed, read-only integration sequence. It does not
authorize staging, committing, pushing, deployment, activation, migration,
database access, or removal of any working-tree file.

The plan is based on the approved manifest
`docs/architecture/oh-auto1-governed-integration-manifest-2026-10-14.md`.
Runtime logs and generated test caches remain excluded from every proposed
commit.

## Baseline and boundary rules

- Base: `main` at `42e0ccd8c0f9b695dad2b4869f856e579183ded`.
- Upstream: `origin/main`, parity `0/0`.
- No Core files are included.
- No schema, migration, deployment, install, apply, or live activation files
  are included.
- Each implementation commit carries its directly coupled regression probes.
- Documentation and workspace records are separated from runtime corrections
  unless their coupling is necessary to explain the correction.
- The two runtime logs are preserved and excluded:
  `storage/logs/app-lifecycle.log` and
  `storage/logs/app-lifecycle-dedupe.json`.
- Generated `vendor/bin/.phpunit.result.cache` is also excluded from source
  integration.

## Proposed dependency-ordered commits

### C01 — Localization diagnostics gate

**Message:** `test(architecture): add localization resource diagnostic fixture`

**Paths (2):**

- `scripts/architecture/check_localization_resource_diagnostics.sh`
- `scripts/architecture/tests/test_localization_resource_diagnostics.sh`

**Provenance:** OH-AUTO1-001/003. **Owner:** architecture/tooling.
**Depends on:** none. **Risk:** low. **Review:** Platform architecture.

**Verification:**

```text
bash -n scripts/architecture/check_localization_resource_diagnostics.sh
bash scripts/architecture/tests/test_localization_resource_diagnostics.sh
```

### C02 — Release package, recovery, and apply-preparation chain

**Message:** `feat(platform): add fail-closed local release and recovery preparation`

**Paths (10):**

- `platform/Recovery/RecoveryPointService.php`
- `platform/Updates/LocalReleaseApplyPreparationService.php`
- `platform/Updates/LocalReleaseChannelBuilderService.php`
- `platform/Updates/LocalReleaseChannelPreviewService.php`
- `platform/Updates/ReleasePackageInspectionService.php`
- `platform/Updates/tests/probe_local_release_apply_preparation.php`
- `platform/Updates/tests/probe_local_release_channel_builder.php`
- `platform/Updates/tests/probe_local_release_channel_preview.php`
- `platform/Updates/tests/probe_offline_release_recovery_integration.php`
- `scripts/system/recovery_point.php`

**Provenance:** OH-AUTO1-005/006/009/010/013. **Owner:** Platform.
**Depends on:** C01 only for shared gate execution. **Risk:** high.
**Review:** Platform release/recovery owner.

**Closure:** package inspection is included with all callers; recovery CLI and
the directly coupled release probes are included. Unchanged recovery provider
probes remain baseline dependencies and are not duplicated in this commit.

**Verification:**

```text
php platform/Updates/tests/probe_local_release_channel_builder.php
php platform/Updates/tests/probe_local_release_channel_preview.php
php platform/Updates/tests/probe_local_release_apply_preparation.php
php platform/Updates/tests/probe_offline_release_recovery_integration.php
php -l platform/Recovery/RecoveryPointService.php
php scripts/system/recovery_point.php --help
```

No command in this verification list applies a release or mutates an installed
database.

### C03 — Installed compatibility and migration evidence

**Message:** `feat(platform): add installed-system compatibility evidence`

**Paths (7):**

- `platform/Updates/InstalledSystemCompatibilityPreflightService.php`
- `platform/Updates/InstalledSystemEvidenceProvider.php`
- `platform/Updates/MigrationCompatibilityPreflightService.php`
- `platform/Updates/tests/probe_installed_build_identity.php`
- `platform/Updates/tests/probe_installed_system_compatibility.php`
- `platform/Updates/tests/probe_installed_system_evidence_provider.php`
- `platform/Updates/tests/probe_migration_compatibility.php`

**Provenance:** OH-AUTO1-007/010/011/013. **Owner:** Platform.
**Depends on:** C02 contracts. **Risk:** high. **Review:** Platform update owner.

**Verification:**

```text
php platform/Updates/tests/probe_installed_build_identity.php
php platform/Updates/tests/probe_installed_system_compatibility.php
php platform/Updates/tests/probe_installed_system_evidence_provider.php
php platform/Updates/tests/probe_migration_compatibility.php
```

This commit does not resolve release attestation, authoritative build identity,
or migration target semantics.

### C04 — Readiness evidence collection

**Message:** `feat(platform): add release readiness evidence collection`

**Paths (4):**

- `platform/Updates/ReleaseReadinessEvidenceCollectorService.php`
- `platform/Updates/ReleaseReadinessEvidenceService.php`
- `platform/Updates/tests/probe_release_readiness_evidence.php`
- `platform/Updates/tests/probe_release_readiness_evidence_collector.php`

**Provenance:** OH-AUTO1-008/012. **Owner:** Platform/release governance.
**Depends on:** C02 and C03. **Risk:** high. **Review:** release authority.

**Verification:**

```text
php platform/Updates/tests/probe_release_readiness_evidence.php
php platform/Updates/tests/probe_release_readiness_evidence_collector.php
```

Eligibility remains blocked until an authorized attestation policy exists.

### C05 — SBAIO lifecycle fallback correction

**Message:** `fix(sbaio): avoid inactive legacy module route fallback`

**Paths (2):**

- `apps/SBAIO/routes.php`
- `apps/SBAIO/Tests/probe_lifecycle_execution_boundary.php`

**Provenance:** OH-AUTO1-016/017. **Owner:** SBAIO.
**Depends on:** none. **Risk:** medium. **Review:** SBAIO owner plus lifecycle reviewer.

**Verification:**

```text
php apps/SBAIO/Tests/probe_lifecycle_execution_boundary.php
```

This is independent of the unresolved Core plugin route rollback defect.

### C06 — Lifecycle evidence and Core escalation record

**Message:** `test(lifecycle): document plugin route registration atomicity gap`

**Paths (4):**

- `scripts/testing/probe_plugin_route_registration_atomicity.php`
- `docs/architecture/lifecycle-enforcement-audit-2026-10-14.md`
- `docs/architecture/lifecycle-execution-boundary-hardening-2026-10-14.md`
- `docs/architecture/plugin-route-registration-atomicity-audit-2026-10-14.md`

**Provenance:** OH-AUTO1-017/024. **Owner:** Platform/Core escalation record.
**Depends on:** C05 only for assignment chronology; no runtime dependency.
**Risk:** high. **Review:** Core/lifecycle owner.

**Verification:**

```text
php scripts/testing/probe_plugin_route_registration_atomicity.php
```

This commit records a reproducible defect and does not patch Core.

### C07 — Procurement receipt-line correction

**Message:** `fix(procurement): validate receipt line ownership at receipt creation`

**Paths (3):**

- `apps/Procurement/Services/ProcurementOverviewService.php`
- `apps/Procurement/Tests/probe_order_receipt_method_boundaries.php`
- `apps/Procurement/Tests/probe_procurement_concurrency.php`

**Provenance:** OH-AUTO1-018. **Owner:** Procurement.
**Depends on:** none for the narrow correction; C08 is required for transaction
claims. **Risk:** medium. **Review:** Procurement owner.

**Verification:**

```text
php apps/Procurement/Tests/probe_order_receipt_method_boundaries.php
```

### C08 — Procurement transaction-contract evidence

**Message:** `test(procurement): record DB-free transaction contract gaps`

**Paths (3):**

- `apps/Procurement/Tests/probe_transaction_contract_audit.php`
- `docs/architecture/business-workflow-data-integrity-audit-2026-10-14.md`
- `engineering/Procurement/work.md`

**Provenance:** OH-AUTO1-018/020. **Owner:** Procurement/Platform.
**Depends on:** C07 and later C09 for actual DB execution. **Risk:** medium.
**Review:** Procurement and test-infrastructure owners.

**Verification:**

```text
php apps/Procurement/Tests/probe_transaction_contract_audit.php
```

The probe is static and must not be described as atomicity proof.

### C09 — Disposable DB and security evidence

**Message:** `test(security): add fail-closed disposable DB and ACL probes`

**Paths (12):**

- `scripts/testing/README.md`
- `scripts/testing/disposable_db_fixture.sql`
- `scripts/testing/disposable_db_harness.php`
- `scripts/testing/DisposableDbExecutionAuthorizer.php`
- `scripts/testing/DisposableDbTestConfigurationGuard.php`
- `scripts/testing/probe_disposable_db_execution_authorizer.php`
- `scripts/testing/probe_disposable_db_harness_safety.php`
- `scripts/testing/probe_disposable_db_test_configuration_guard.php`
- `scripts/security/probe_acl_deny_precedence.php`
- `docs/architecture/disposable-database-test-infrastructure-2026-10-14.md`
- `docs/architecture/security-acl-deny-precedence-remediation-spec-2026-10-14.md`
- `docs/architecture/security-authorization-tenant-isolation-audit-2026-10-14.md`

**Provenance:** OH-AUTO1-015/022/023. **Owner:** test infrastructure/security.
**Depends on:** none for mock safety; real DB execution depends on external
environment attestation. **Risk:** high. **Review:** security and environment
owners.

**Verification:**

```text
php scripts/testing/probe_disposable_db_harness_safety.php
php scripts/testing/probe_disposable_db_test_configuration_guard.php
php scripts/security/probe_acl_deny_precedence.php
```

No SQL execution is authorized by this commit. `SEC-AUTH-001` remains open.

### C10 — Operator diagnostic redaction and handshake safety

**Message:** `fix(shell): secure realtime authentication and redact operator diagnostics`

**Paths (24):**

- `apps/Manufacturing/Services/OperatorLayerAdapters/AssemblyAdapter.php`
- `apps/Manufacturing/Services/OperatorLayerAdapters/CoverageAdapter.php`
- `apps/Manufacturing/Services/OperatorLayerAdapters/DispatchAdapter.php`
- `apps/Manufacturing/Services/OperatorLayerAdapters/MachinesAdapter.php`
- `apps/Manufacturing/Services/OperatorLayerAdapters/MaterialsAdapter.php`
- `apps/Manufacturing/Services/OperatorLayerAdapters/QcAdapter.php`
- `apps/Shell/Composers/OperatorSurfaceComposer.php`
- `apps/Shell/Resources/lang/en.php`
- `apps/Shell/Resources/lang/ja.php`
- `apps/Shell/Resources/lang/ne.php`
- `apps/Shell/Views/operator/fulfillment.php`
- `apps/Shell/Views/operator/materials.php`
- `apps/Shell/Views/operator/messages.php`
- `apps/Shell/Views/operator/notifications.php`
- `apps/Shell/Views/operator/processing.php`
- `apps/Shell/Views/operator/production.php`
- `apps/Shell/Views/operator/tasks.php`
- `apps/Shell/Tests/probe_operator_diagnostic_redaction.php`
- `apps/Shell/Services/OperatorRealtimeCacheManager.php`
- `apps/Shell/Services/OperatorRealtimeKpiProvider.php`
- `apps/Shell/Services/OperatorRealtimeService.php`
- `apps/Shell/Views/operator/preferences.php`
- `public/assets/operator-realtime.js`
- `engineering/Shell/work.md`

**Provenance:** OH-AUTO1-019/038/039/040. **Owner:** Shell/Manufacturing.
**Depends on:** none. **Risk:** medium. **Review:** Shell and Manufacturing.

**Verification:**

```text
php apps/Shell/Tests/probe_operator_diagnostic_redaction.php
```

Live browser/operator acceptance remains a separate gate.

The probe includes the OH-AUTO1-040 handshake findings: the current bootstrap
does not use Ratchet `OriginCheck`, while the client no longer logs the
credential-bearing URL. No origin allowlist or credential transport migration
is included until deployment authority is decided.

### C11 — Consolidated audit and governance records

**Message:** `docs(architecture): record OH-AUTO production-readiness evidence`

**Paths (15):**

- `AGENT-COMPLIANCE-CHECKLIST.md`
- `AGENTS.md`
- `engineering/Platform/decisions.md`
- `engineering/Platform/work.md`
- `docs/architecture/consolidated-production-readiness-reconciliation-2026-10-14.md`
- `docs/architecture/consolidated-release-baseline-audit-2026-10-14.md`
- `docs/architecture/operational-diagnostics-observability-audit-2026-10-14.md`
- `docs/architecture/transactional-business-verification-audit-2026-10-14.md`
- `docs/architecture/integration-baseline-verification-2026-10-14.md`
- `docs/architecture/oh-auto1-governed-integration-manifest-2026-10-14.md`
- `docs/architecture/oh-auto1-integration-commit-plan-2026-10-14.md`
- `docs/architecture/disposable-database-environment-discovery-2026-10-14.md`
- `docs/architecture/core-security-lifecycle-remediation-decision-2026-10-14.md`
- `docs/architecture/production-release-gate-reconciliation-2026-10-14.md`
- `docs/architecture/oh-auto1-042-database-isolation-incident-2026-10-14.md`

**Provenance:** OH-AUTO1-001–043. **Owner:** Platform/architecture governance.
**Depends on:** C01–C12 evidence. **Risk:** medium (record integrity).
**Review:** Planner and architecture authority.

**Verification:**

```text
git diff --check
```

This commit should remain separate from runtime implementation commits and does
not convert evidence into a production-readiness claim.

### C12 — Manufacturing upstream supply correction

**Message:** `fix(manufacturing): recover concurrent upstream target creation`

**Paths (4):**

- `apps/Manufacturing/Services/UpstreamSupplyGenerationService.php`
- `apps/Manufacturing/Tests/probe_upstream_supply_transaction.php`
- `apps/Manufacturing/Tests/probe_upstream_supply_concurrency.php`
- `engineering/Manufacturing/work.md`

**Provenance:** OH-AUTO1-030/032/033. **Owner:** Manufacturing.
**Depends on:** C09 disposable-instance authorization. **Risk:** high.
**Review:** Manufacturing and test-infrastructure owners.

**Verification:**

```text
php apps/Manufacturing/Tests/probe_upstream_supply_transaction.php
php apps/Manufacturing/Tests/probe_upstream_supply_concurrency.php
```

The correction is limited to duplicate-key recovery after rollback and does not
add a new lock or idempotency framework. Nested caller-owned transaction use is
explicitly unsupported because no transaction-depth/savepoint contract exists.
Runtime generation no longer invokes schema-ensure DDL; migration ownership
and that runtime boundary are recorded in C15.

### C13 — Hospitality reservation integrity and consolidated orchestration

**Message:** `fix(hospitality): serialize assigned-room reservation creation`

**Paths (4):**

- `apps/Hospitality/Services/ReservationsService.php`
- `apps/Hospitality/Tests/probe_reservation_create_concurrency.php`
- `engineering/Hospitality/work.md`
- `scripts/testing/run_business_integrity_regression.sh`

**Provenance:** OH-AUTO1-041 and existing OH-AUTO1-033. **Owner:** Hospitality
and test infrastructure/Platform governance. **Depends on:** C09 disposable
instance authorization and the Hospitality foundation. **Risk:** high.
**Review:** Hospitality owner, planner, and test-infrastructure owners.

**Verification:**

```text
php apps/Hospitality/Tests/probe_reservation_create_concurrency.php
bash scripts/testing/run_business_integrity_regression.sh
```

The Hospitality correction is verified against the existing overlap predicate
using an independently attested disposable instance; no installed database is
accessed. The runner provisions separate disposable instances for its existing
live probes, preserves the installed service, and owns no business assertions
or installed DB access.

### C14 — Database test entry-point isolation enforcement

**Message:** `test(infrastructure): gate every business DB probe before bootstrap`

**Paths (29):**

- `scripts/testing/DisposableDbTestEntryPointGuard.php`
- `apps/Procurement/Tests/probe_behavioral_cancellation.php`
- `apps/Procurement/Tests/probe_behavioral_request_cancel.php`
- `apps/Procurement/Tests/probe_behavioral_supplier_update.php`
- `apps/Hospitality/Tests/probe_reservation_transition_concurrency.php`
- `apps/Hospitality/Tests/probe_slice1_registration.php`
- `apps/Hospitality/Tests/probe_slice2_migrations.php`
- `apps/Hospitality/Tests/probe_slice3_modules.php`
- `apps/Hospitality/Tests/probe_slice4_routes_navigation.php`
- `apps/Hospitality/Tests/probe_slice5_rooms_guests_crud.php`
- `apps/Hospitality/Tests/probe_slice6_reservations_lifecycle.php`
- `apps/Hospitality/Tests/probe_slice7_frontdesk_folio.php`
- `apps/Hospitality/Tests/probe_slice8_housekeeping_board.php`
- `apps/Hospitality/Tests/probe_frontdesk_void_readiness.php`
- `apps/Hospitality/Tests/probe_install_admin_lifecycle.php`
- `apps/Hospitality/Tests/probe_operator_slice2_contribution.php`
- `apps/Hospitality/Tests/probe_operator_slice4_housekeeping_action.php`
- `apps/Hospitality/Tests/probe_operator_slice5_frontdesk_checkin.php`
- `apps/Hospitality/Tests/probe_operator_slice6_frontdesk_checkout.php`
- `apps/Hospitality/Tests/probe_operator_slice7_add_charge.php`
- `apps/Hospitality/Tests/probe_operator_slice8_cancel.php`
- `apps/Hospitality/Tests/probe_operator_slice9_no_show.php`
- `apps/Hospitality/Tests/run_all_hospitality_probes.php`
- `docs/architecture/oh-auto1-043-database-test-entrypoint-isolation-2026-10-14.md`
- `scripts/testing/IsolatedSourceRootTestRunner.php`
- `scripts/testing/prepare_isolated_source_root.php`
- `scripts/testing/cleanup_isolated_source_root.php`
- `scripts/testing/probe_isolated_source_root_runner.php`
- `docs/architecture/oh-auto1-045-isolated-test-execution-boundary-reconciliation-2026-10-14.md`

**Provenance:** OH-AUTO1-043. **Owner:** test infrastructure with Hospitality
and Procurement entry-point owners. **Depends on:** C09. **Risk:** high.

Every DB-capable entry point now requires the shared configuration guard and
execution authorizer before autoload/bootstrap. Direct invocation without
governed evidence fails closed. Legacy probes remain behaviorally unchanged
but cannot execute until a complete isolated source-root runner is authorized.

**Verification:**

```text
php scripts/testing/probe_disposable_db_test_configuration_guard.php
php -l <all modified DB probe files>
```

### C15 — Manufacturing runtime DDL safety

**Message:** `fix(manufacturing): keep upstream generation migration-owned`

**Paths (3):**

- `apps/Manufacturing/Services/DemandEngineService.php`
- `apps/Manufacturing/Tests/probe_upstream_supply_runtime_ddl_boundary.php`
- `docs/architecture/oh-auto1-047-manufacturing-runtime-ddl-safety-2026-10-14.md`

**Provenance:** OH-AUTO1-047. **Owner:** Manufacturing. **Depends on:**
Manufacturing migration ownership and the existing C12 transaction correction.
**Risk:** medium.

The paired `UpstreamSupplyGenerationService.php` change remains in C12 because
that service is already grouped with the upstream target/link correction.
No migration or schema file is included.

### C16 — Remaining Manufacturing runtime DDL boundary

**Message:** `fix(manufacturing): keep stage transitions migration-owned`

**Paths (4):**

- `apps/Manufacturing/Services/StageTransitionService.php`
- `apps/Manufacturing/Services/DemandExecutionService.php`
- `apps/Manufacturing/Tests/probe_remaining_runtime_ddl_boundaries.php`
- `docs/architecture/oh-auto1-048-remaining-manufacturing-runtime-ddl-2026-10-14.md`

**Provenance:** OH-AUTO1-048. **Owner:** Manufacturing. **Depends on:**
Manufacturing migration ownership. **Risk:** medium.

The correction removes runtime schema preparation from stage computation,
release, and override paths. Processing-generator and DispatchOps DDL remain
unchanged because their tables lack repository migration ownership.

### C17 — Hospitality reservation modification integrity

**Message:** `test(hospitality): verify reservation modification boundaries`

**Paths (2):**

- `apps/Hospitality/Tests/probe_reservation_modification_integrity.php`
- `docs/architecture/oh-auto1-049-hospitality-reservation-modification-integrity-2026-10-14.md`

**Provenance:** OH-AUTO1-049. **Owner:** Hospitality. **Risk:** low.

The static probe confirms that implemented reservation mutations converge on
the existing create/transition services, with no room/date reassignment or
reactivation API. No runtime correction was necessary.

### C18 — Hospitality reservation transition authorization

**Message:** `test(hospitality): verify reservation transition authorization`

**Paths (1):**

- `apps/Hospitality/Tests/probe_reservation_transition_authorization.php`

**Provenance:** OH-AUTO1-050. **Owner:** Hospitality. **Risk:** low.

The static probe verifies manage permission, CSRF, authenticated operator
self-workspace binding, guarded dispatch, and preserved CAS state checks. No
runtime correction was necessary.

### C19 — Procurement write authorization hardening

**Message:** `fix(procurement): recheck app access at write controllers`

**Paths (2):**

- `apps/Procurement/Controllers/ProcurementDashboardController.php`
- `apps/Procurement/Tests/probe_write_authorization_integrity.php`

**Provenance:** OH-AUTO1-051. **Owner:** Procurement. **Risk:** medium.

The selected purchase-order and receipt write controllers now re-check
`procurement` app access before CSRF and service dispatch. Existing identifier
ownership and workflow validation are preserved; no undocumented permission
mapping was introduced.

## Count and closure summary

| Commit | Files |
|---|---:|
| C01 | 2 |
| C02 | 10 |
| C03 | 7 |
| C04 | 4 |
| C05 | 2 |
| C06 | 4 |
| C07 | 3 |
| C08 | 3 |
| C09 | 12 |
| C10 | 24 |
| C11 | 14 |
| C12 | 4 |
| C13 | 4 |
| C14 | 29 |
| C15 | 3 |
| C16 | 4 |
| C17 | 2 |
| C18 | 1 |
| C19 | 2 |
| **Total candidate paths** | **135** |

The count deliberately excludes the two runtime logs. The six unchanged
recovery probes listed in the manifest are baseline dependencies and are not
included in any proposed commit.

## Integration risks and rollback

- **C02–C04:** release and recovery behavior is fail-closed but not production
  authorization. Revert the relevant commit as a whole if its paired probes or
  package contract fail review; do not partially revert a service without its
  directly coupled tests.
- **C05–C06:** C06 documents a Core defect; rollback evidence must not be
  mistaken for a correction. Revert C05 independently if SBAIO route behavior
  regresses.
- **C07–C08:** C07 is a narrow validation correction; C08 remains evidence-only.
  Revert C07 as one implementation/test unit if contract expectations change.
- **C09:** never run its fixture against an installed database. Any environment
  evidence must be externally attested before a later DB assignment.
- **C10:** keep adapters, composer, locale files, views, realtime client/service,
  and probe together to avoid raw-key/rendering or handshake-contract mismatch.
- **C11:** documentation rollback is independent of runtime rollback, but the
  evidence record must not be deleted to make readiness appear cleaner.
- **All commits:** runtime logs remain untouched and outside commit selection.

## Remaining approval requirements

1. Planner approval of these exact commit boundaries.
2. Core/security decision for `SEC-AUTH-001`.
3. Core/lifecycle decision for `LIFECYCLE-003`.
4. Independently attested disposable DB environment before any future live
   regression rerun; OH-AUTO1-028 through OH-AUTO1-033 evidence is complete.
5. Release authority decisions for attestation, installed identity, migration
   semantics, and protected package paths.
6. Human authorization for live HTTP, deployment, restore, rollback, and browser
   acceptance.

## Next actionable assignment

OH-AUTO1-034 — Post-transaction integration baseline reconciliation, limited to
read-only manifest/commit-plan updates and focused verification. No staging,
commit, push, deployment, activation, migration, or installed-database access
is authorized.

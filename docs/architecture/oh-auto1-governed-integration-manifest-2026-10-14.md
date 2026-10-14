# OH-AUTO1 Governed Integration Manifest — 2026-10-14

## Status and scope

This is a proposed integration manifest, not authorization to stage, commit,
push, deploy, activate, migrate, or discard any working-tree content.

Current checkout: `main` at `42e0ccd8c0f9b695dad2b4869f856e579183ded`, tracking
`origin/main` at parity `0/0`. Current Git status reports **70 tracked
modifications and generated/test files: 138 paths total**. This is **135
candidate source paths, 2 preserved runtime logs, and 1 generated PHPUnit cache**.
No paths are staged, deleted, or renamed, and no `app/Core/` changes exist.

Ownership classification is based on path ownership, OH-AUTO records, source
references, and the already-run focused probes. Confidence describes provenance
classification, not production readiness. Runtime logs are intentionally
excluded from source integration.

## Integration groups and dependency order

### G1 — Localization diagnostics

**Order:** 1. **Owner:** architecture/tooling. **Confidence:** high.

| Path | Role | Evidence / disposition |
|---|---|---|
| `scripts/architecture/check_localization_resource_diagnostics.sh` | Diagnostic gate | Bash syntax pass; current localization gate pass |
| `scripts/architecture/tests/test_localization_resource_diagnostics.sh` | Fixture regression | Fixture regression pass |

The gate and its fixture test are complete as a pair. No runtime application
dependency was introduced.

### G2 — Release package, recovery, and CLI safety

**Order:** 2. **Owner:** Platform. **Depends on:** G1 only for shared gate
execution. **Confidence:** high.

| Path | Role | Evidence / disposition |
|---|---|---|
| `platform/Recovery/RecoveryPointService.php` | Recovery implementation | Recovery/release probes pass; PHP lint |
| `platform/Updates/LocalReleaseChannelBuilderService.php` | Release builder | 13/13; offline integration 23/23 |
| `platform/Updates/LocalReleaseChannelPreviewService.php` | Release preview | 30/30; offline integration 23/23 |
| `platform/Updates/LocalReleaseApplyPreparationService.php` | Apply preparation | 7/7; offline integration 23/23 |
| `platform/Updates/ReleasePackageInspectionService.php` | Package inspection | Release probes pass; PHP lint |
| `scripts/system/recovery_point.php` | Recovery/release CLI | Help smoke; release/recovery probes pass |

This group is source-integrable only as reviewed Platform commits. It does not
authorize actual apply, installation, migration, restore, or deployment.

### G3 — Installed-system compatibility and migration evidence

**Order:** 3. **Owner:** Platform. **Depends on:** G2 package/recovery contracts.
**Confidence:** high.

| Path | Role | Evidence / disposition |
|---|---|---|
| `platform/Updates/InstalledSystemCompatibilityPreflightService.php` | Compatibility preflight | 16/16; PHP lint |
| `platform/Updates/InstalledSystemEvidenceProvider.php` | Installed evidence | 22/22; PHP lint |
| `platform/Updates/MigrationCompatibilityPreflightService.php` | Migration compatibility | 16/16; PHP lint |
| `platform/Updates/tests/probe_installed_build_identity.php` | Build identity probe | 13/13 |
| `platform/Updates/tests/probe_installed_system_compatibility.php` | Compatibility probe | 16/16 |
| `platform/Updates/tests/probe_installed_system_evidence_provider.php` | Evidence probe | 22/22 |
| `platform/Updates/tests/probe_migration_compatibility.php` | Migration probe | 16/16 |

The group is not a release-authorization contract. Attestation, authoritative
installed identity, migration target semantics, and protected-path policy remain
open decisions.

### G4 — Readiness evidence collection

**Order:** 4. **Owner:** Platform/release governance. **Depends on:** G2 and G3.
**Confidence:** high.

| Path | Role | Evidence / disposition |
|---|---|---|
| `platform/Updates/ReleaseReadinessEvidenceService.php` | Readiness validation | 10/10; PHP lint |
| `platform/Updates/ReleaseReadinessEvidenceCollectorService.php` | Evidence collection | 19/19; PHP lint |
| `platform/Updates/tests/probe_release_readiness_evidence.php` | Readiness probe | 10/10 |
| `platform/Updates/tests/probe_release_readiness_evidence_collector.php` | Collector probe | 19/19 |

This group remains blocked from eligibility claims until release authority and
attestation policy are decided.

### G5 — Lifecycle and route enforcement

**Order:** 5. **Owner:** SBAIO/Platform with Core dependency for unresolved
loader rollback. **Depends on:** no executable group; Core decisions remain a
release blocker. **Confidence:** high for provenance.

| Path | Role | Evidence / disposition |
|---|---|---|
| `apps/SBAIO/routes.php` | Non-Core lifecycle fallback correction | Lifecycle probe 13/13; PHP lint |
| `apps/SBAIO/Tests/probe_lifecycle_execution_boundary.php` | Lifecycle regression probe | 13/13; PHP lint |
| `scripts/testing/probe_plugin_route_registration_atomicity.php` | Core defect reproduction | 16/16; no activation |
| `docs/architecture/lifecycle-enforcement-audit-2026-10-14.md` | Lifecycle audit | Historical/current findings recorded |
| `docs/architecture/lifecycle-execution-boundary-hardening-2026-10-14.md` | Lifecycle audit | Reviewed; no runtime mutation |
| `docs/architecture/plugin-route-registration-atomicity-audit-2026-10-14.md` | Route rollback specification | 16/16 reproduction |

The SBAIO correction can be separately reviewed. The route-atomicity probe and
documents are evidence only; `LIFECYCLE-003` still requires Core-owner approval.

### G6 — Procurement workflow correction

**Order:** 6. **Owner:** Procurement. **Depends on:** G8 for DB-backed behavior
before any transaction claim. **Confidence:** high for provenance.

| Path | Role | Evidence / disposition |
|---|---|---|
| `apps/Procurement/Services/ProcurementOverviewService.php` | Receipt-line ownership correction; transaction audit target | Boundary 9/9; static audit 30/30; PHP lint |
| `apps/Procurement/Tests/probe_order_receipt_method_boundaries.php` | Correction regression probe | 9/9; PHP lint |
| `apps/Procurement/Tests/probe_transaction_contract_audit.php` | DB-free transaction/ownership audit | 32/32; source classification only |
| `apps/Procurement/Controllers/ProcurementDashboardController.php` | Direct-controller app-access hardening for selected writes | `Auth::requireAppAccess('procurement')` added to three methods |
| `apps/Procurement/Tests/probe_write_authorization_integrity.php` | Write authorization and ownership regression probe | 40/40 static |
| `apps/Procurement/Tests/probe_procurement_concurrency.php` | Disposable concurrency regression | 10/10; independently attested disposable DB |
| `docs/architecture/business-workflow-data-integrity-audit-2026-10-14.md` | Procurement audit | Correction and remaining gaps recorded |

The method correction and concurrency probe are reviewable as Procurement-owned
units. Receipt over-quantity policy and broader downstream status-path behavior
remain unverified.

### G7 — Operator diagnostic redaction

**Order:** 7. **Owner:** Shell/Manufacturing. **Depends on:** no executable
group. **Confidence:** high.

| Path | Role | Evidence / disposition |
|---|---|---|
| `apps/Manufacturing/Services/OperatorLayerAdapters/AssemblyAdapter.php` | Stable diagnostic key | Redaction probe 51/51; PHP lint |
| `apps/Manufacturing/Services/OperatorLayerAdapters/CoverageAdapter.php` | Stable diagnostic key | Redaction probe 51/51; PHP lint |
| `apps/Manufacturing/Services/OperatorLayerAdapters/DispatchAdapter.php` | Stable diagnostic key | Redaction probe 51/51; PHP lint |
| `apps/Manufacturing/Services/OperatorLayerAdapters/MachinesAdapter.php` | Stable diagnostic key | Redaction probe 51/51; PHP lint |
| `apps/Manufacturing/Services/OperatorLayerAdapters/MaterialsAdapter.php` | Stable diagnostic key | Redaction probe 51/51; PHP lint |
| `apps/Manufacturing/Services/OperatorLayerAdapters/QcAdapter.php` | Stable diagnostic key | Redaction probe 51/51; PHP lint |
| `apps/Shell/Composers/OperatorSurfaceComposer.php` | Stable diagnostic key/rendering | Redaction probe 51/51; PHP lint |
| `apps/Shell/Resources/lang/en.php` | Diagnostic localization | Redaction probe 51/51; PHP lint |
| `apps/Shell/Resources/lang/ja.php` | Diagnostic localization | Redaction probe 51/51; PHP lint |
| `apps/Shell/Resources/lang/ne.php` | Diagnostic localization | Redaction probe 51/51; PHP lint |
| `apps/Shell/Views/operator/fulfillment.php` | Operator rendering | Redaction probe 51/51; PHP lint |
| `apps/Shell/Views/operator/materials.php` | Operator rendering | Redaction probe 51/51; PHP lint |
| `apps/Shell/Views/operator/messages.php` | Operator rendering | Redaction probe 51/51; PHP lint |
| `apps/Shell/Views/operator/notifications.php` | Operator rendering | Redaction probe 51/51; PHP lint |
| `apps/Shell/Views/operator/processing.php` | Operator rendering | Redaction probe 51/51; PHP lint |
| `apps/Shell/Views/operator/production.php` | Operator rendering | Redaction probe 51/51; PHP lint |
| `apps/Shell/Views/operator/tasks.php` | Operator rendering | Redaction probe 51/51; PHP lint |
| `apps/Shell/Tests/probe_operator_diagnostic_redaction.php` | Redaction/authentication/handshake probe | 89/89; PHP lint |
| `apps/Shell/Services/OperatorRealtimeCacheManager.php` | Cache diagnostic redaction | OH-AUTO1-038/039; PHP lint |
| `apps/Shell/Services/OperatorRealtimeKpiProvider.php` | Realtime failure containment | OH-AUTO1-038/039; PHP lint |
| `apps/Shell/Services/OperatorRealtimeService.php` | Session-authenticated realtime boundary | OH-AUTO1-039/040; PHP lint |
| `apps/Shell/Views/operator/preferences.php` | WebSocket test error redaction | OH-AUTO1-038/039/040; PHP lint |
| `public/assets/operator-realtime.js` | Credential-bearing URL console redaction | OH-AUTO1-040; JS/source probe |
| `engineering/Shell/work.md` | Workstream evidence | Documentation record |

The stable-key correction is narrowly reviewable. Live operator/browser
acceptance remains outside this manifest’s evidence.

### G8 — Disposable database harness and security probes

**Order:** 8. **Owner:** test infrastructure/security. **Depends on:** no
repository implementation group; requires independently attested DB
infrastructure before execution. **Confidence:** high for provenance.

| Path | Role | Evidence / disposition |
|---|---|---|
| `scripts/testing/README.md` | Harness safety contract | Reviewed |
| `scripts/testing/disposable_db_fixture.sql` | Disposable fixture | Destructive-DDL safety review; not executed |
| `scripts/testing/disposable_db_harness.php` | Fail-closed harness | Mock proof remains blocked |
| `scripts/testing/DisposableDbExecutionAuthorizer.php` | Live disposable-instance authorizer | 24/24 per isolated instance |
| `scripts/testing/DisposableDbTestConfigurationGuard.php` | Pre-connection config identity guard | Pure static identity validation; no DB/SQL |
| `scripts/testing/DisposableDbTestEntryPointGuard.php` | Common pre-bootstrap entry-point guard | Static boundary; authorizer required before bootstrap |
| `scripts/testing/probe_disposable_db_execution_authorizer.php` | Authorizer regression probe | 9/9; no DB |
| `scripts/testing/probe_disposable_db_harness_safety.php` | Harness safety probe | 14/14; no DB |
| `scripts/testing/probe_disposable_db_test_configuration_guard.php` | Config precedence regression | 14/14; no DB/SQL |
| `scripts/security/probe_acl_deny_precedence.php` | In-memory ACL reproduction | 10/10; no DB |
| `docs/architecture/disposable-database-test-infrastructure-2026-10-14.md` | DB infrastructure audit | Real DB unavailable |
| `docs/architecture/disposable-database-environment-discovery-2026-10-14.md` | Environment discovery report | Disposable fixture verification passed |
| `docs/architecture/security-acl-deny-precedence-remediation-spec-2026-10-14.md` | Core remediation specification | 10/10 reproduction |
| `docs/architecture/security-authorization-tenant-isolation-audit-2026-10-14.md` | Security audit | Core blocker remains |

No installed-system SQL was executed. Disposable SQL is permitted only after
independent instance authorization; `SEC-AUTH-001` still requires Core/security
authorization and the harness cannot authorize itself.

### G10 — Manufacturing upstream supply correction

**Order:** 10. **Owner:** Manufacturing. **Depends on:** G8 disposable DB
authorization for behavioral claims. **Confidence:** high for provenance.

| Path | Role | Evidence / disposition |
|---|---|---|
| `apps/Manufacturing/Services/UpstreamSupplyGenerationService.php` | Target/link transaction and duplicate-key recovery | 21/21 rollback; 11/11 concurrency; PHP lint |
| `apps/Manufacturing/Services/StageTransitionService.php` | Migration-owned stage readiness runtime-DDL correction | 20/20 static boundary; PHP lint |
| `apps/Manufacturing/Services/DemandExecutionService.php` | Migration-owned demand-read runtime-DDL correction | 20/20 static boundary; PHP lint |
| `apps/Manufacturing/Tests/probe_upstream_supply_transaction.php` | Transaction/rollback regression | 21/21 |
| `apps/Manufacturing/Tests/probe_upstream_supply_concurrency.php` | Concurrent target/link regression | 11/11 |
| `apps/Manufacturing/Tests/probe_upstream_supply_runtime_ddl_boundary.php` | OH-AUTO1-047 runtime-DDL boundary | 14/14 static |
| `apps/Manufacturing/Tests/probe_remaining_runtime_ddl_boundaries.php` | OH-AUTO1-048 inventory/boundary regression | 20/20 static |
| `docs/architecture/oh-auto1-047-manufacturing-runtime-ddl-safety-2026-10-14.md` | OH-AUTO1-047 evidence | Static/mock correction |
| `docs/architecture/oh-auto1-048-remaining-manufacturing-runtime-ddl-2026-10-14.md` | OH-AUTO1-048 evidence | Static/mock correction |
| `engineering/Manufacturing/work.md` | Manufacturing evidence record | OH-AUTO1-030/032/033 recorded |

The correction uses existing composite uniqueness only. Nested caller-owned
transaction composability remains unsupported because no transaction-depth/
savepoint contract exists. Runtime generation no longer performs schema-ensure
DDL; required tables are migration-owned.

Processing-generator and DispatchOps schema helpers remain compatibility
blockers because their runtime-created tables have no repository migration
ownership; they were not removed speculatively.

### G11 — Hospitality reservation integrity and business-integrity orchestration

**Order:** 11. **Owner:** Hospitality and test infrastructure/Platform governance.
**Depends on:** G8 disposable-instance authorization and the existing
Hospitality foundation. **Confidence:** high for provenance.

| Path | Role | Evidence / disposition |
|---|---|---|
| `apps/Hospitality/Services/ReservationsService.php` | Room-row locking correction for concurrent assigned reservations | Failing race reproduced before correction; five post-correction trials admitted one active row each; PHP lint |
| `apps/Hospitality/Tests/probe_reservation_modification_integrity.php` | Reservation modification boundary regression | 27/27 static |
| `apps/Hospitality/Tests/probe_reservation_transition_authorization.php` | Reservation transition authorization regression | 62/62 static |
| `apps/Hospitality/Tests/probe_reservation_create_concurrency.php` | Disposable concurrency regression | 3/3; authorizer required; cleanup verified |
| `engineering/Hospitality/work.md` | Hospitality evidence record | OH-AUTO1-041 recorded; disposable resource cleanup verified |
| `docs/architecture/oh-auto1-049-hospitality-reservation-modification-integrity-2026-10-14.md` | OH-AUTO1-049 evidence | Static/mock investigation; no bypass found |
| `scripts/testing/run_business_integrity_regression.sh` | Governed multi-instance regression runner | Existing suites pass; separate disposable instances; cleanup verified |

The Hospitality correction preserves the existing overlap predicate and schema,
locking only the assigned room row during check-and-insert. The runner
orchestrates existing probes and does not replace their assertions, share
mutable fixture state, or authorize installed-database access.

Execution note: a preliminary local baseline attempt selected the installed
config before config precedence was detected; its temporary fixtures were
cleaned up. The authorized correction and concurrency evidence below used only
the independently attested disposable instance.

### G9 — Architecture, governance, and audit documentation

**Order:** 9, after technical group review. **Owner:** Platform/architecture
governance. **Confidence:** high for provenance; integration necessity varies.

| Path | Role | Evidence / disposition |
|---|---|---|
| `AGENT-COMPLIANCE-CHECKLIST.md` | Cross-session compliance record | Diff hygiene pass; retain only by governance decision |
| `AGENTS.md` | Root agent session record | OH-AUTO1-042 incident and hardening record |
| `engineering/Platform/decisions.md` | Platform decisions | Documentation only; review append-only history |
| `engineering/Platform/work.md` | OH-AUTO assignment record | Documentation only; current status updated |
| `engineering/Procurement/work.md` | Procurement work record | Documentation only |
| `docs/architecture/consolidated-production-readiness-reconciliation-2026-10-14.md` | Blocker register | Production status remains blocked |
| `docs/architecture/consolidated-release-baseline-audit-2026-10-14.md` | Release audit | Historical evidence; no authorization |
| `docs/architecture/operational-diagnostics-observability-audit-2026-10-14.md` | Diagnostics audit | Historical recommendations retained |
| `docs/architecture/transactional-business-verification-audit-2026-10-14.md` | Transaction audit | Isolated Procurement/Manufacturing behavior recorded; nested composition remains unsupported |
| `docs/architecture/integration-baseline-verification-2026-10-14.md` | OH-AUTO1-025 baseline | Snapshot inventory; not a clean baseline claim |
| `docs/architecture/oh-auto1-governed-integration-manifest-2026-10-14.md` | OH-AUTO1-026 manifest | This proposed manifest; not source authorization |
| `docs/architecture/oh-auto1-integration-commit-plan-2026-10-14.md` | OH-AUTO1-027 commit plan | Proposed boundaries; not execution authorization |
| `docs/architecture/core-security-lifecycle-remediation-decision-2026-10-14.md` | OH-AUTO1-035 decision package | Core correction proposal; no Core mutation |
| `docs/architecture/production-release-gate-reconciliation-2026-10-14.md` | OH-AUTO1-036 gate matrix | Current release verdict; no release authorization |

These records should not be bundled blindly with runtime implementation. Their
final commit boundary and retention policy require planner approval.

### X — Excluded runtime-generated artifacts

These files are classified confidently as runtime artifacts but excluded from
future source integration until retention/provenance is explicitly decided:

| Path | Reason |
|---|---|
| `storage/logs/app-lifecycle-dedupe.json` | Runtime-generated log/state artifact; preserve, do not source-commit by default |
| `storage/logs/app-lifecycle.log` | Runtime-generated log; preserve, do not source-commit by default |
| `vendor/bin/.phpunit.result.cache` | Generated PHPUnit result cache; exclude from source integration and do not treat as a source change |

## Unchanged repository dependencies

These existing tracked files are not part of the 120-path working-tree delta,
but they are required dependencies for the claimed recovery verification. They
remain unchanged and are listed here to prevent “missing test” confusion:

| Path | Role | Evidence |
|---|---|---|
| `platform/Recovery/tests/probe_recovery_point_service.php` | Recovery service regression | 9/9 in prior audit |
| `platform/Recovery/tests/probe_recovery_provider_preflight.php` | Provider preflight regression | 13/13 in prior audit |
| `platform/Recovery/tests/probe_recovery_backup_providers.php` | Backup-provider regression | 19/19 in prior audit |
| `platform/Recovery/tests/probe_mysql_restore_provider.php` | MySQL restore regression | 7/7 in prior audit |
| `platform/Recovery/tests/probe_restore_rehearsal_service.php` | Restore rehearsal regression | 10/10 in prior audit |
| `platform/Recovery/tests/probe_recovery_point_cli.php` | Recovery CLI regression | 9/9 in prior audit |

They are deliberately not duplicated in the changed-path inventory below.

## Machine-checkable complete inventory

The following block is the authoritative path set for this manifest. Each line
is `PATH|GROUP|PROVENANCE|INTEGRATION_DISPOSITION`. It must match the live Git
union of tracked modifications and untracked files exactly.

<!-- INVENTORY_START -->
AGENT-COMPLIANCE-CHECKLIST.md|G9|OH-AUTO1-001..041|reviewed-governance-record
AGENTS.md|G9|OH-AUTO1-042|reviewed-governance-record
apps/Hospitality/Services/ReservationsService.php|G11|OH-AUTO1-041|candidate-source
apps/Hospitality/Tests/probe_reservation_modification_integrity.php|G11|OH-AUTO1-049|candidate-test
apps/Hospitality/Tests/probe_reservation_transition_authorization.php|G11|OH-AUTO1-050|candidate-test
apps/Hospitality/Tests/probe_reservation_create_concurrency.php|G11|OH-AUTO1-041|candidate-test
apps/Hospitality/Tests/probe_reservation_transition_concurrency.php|G11|OH-AUTO1-043|candidate-test
apps/Hospitality/Tests/probe_frontdesk_void_readiness.php|G11|OH-AUTO1-043|candidate-test
apps/Hospitality/Tests/probe_install_admin_lifecycle.php|G11|OH-AUTO1-043|candidate-test
apps/Hospitality/Tests/probe_operator_slice2_contribution.php|G11|OH-AUTO1-043|candidate-test
apps/Hospitality/Tests/probe_operator_slice4_housekeeping_action.php|G11|OH-AUTO1-043|candidate-test
apps/Hospitality/Tests/probe_operator_slice5_frontdesk_checkin.php|G11|OH-AUTO1-043|candidate-test
apps/Hospitality/Tests/probe_operator_slice6_frontdesk_checkout.php|G11|OH-AUTO1-043|candidate-test
apps/Hospitality/Tests/probe_operator_slice7_add_charge.php|G11|OH-AUTO1-043|candidate-test
apps/Hospitality/Tests/probe_operator_slice8_cancel.php|G11|OH-AUTO1-043|candidate-test
apps/Hospitality/Tests/probe_operator_slice9_no_show.php|G11|OH-AUTO1-043|candidate-test
apps/Hospitality/Tests/probe_slice1_registration.php|G11|OH-AUTO1-043|candidate-test
apps/Hospitality/Tests/probe_slice2_migrations.php|G11|OH-AUTO1-043|candidate-test
apps/Hospitality/Tests/probe_slice3_modules.php|G11|OH-AUTO1-043|candidate-test
apps/Hospitality/Tests/probe_slice4_routes_navigation.php|G11|OH-AUTO1-043|candidate-test
apps/Hospitality/Tests/probe_slice5_rooms_guests_crud.php|G11|OH-AUTO1-043|candidate-test
apps/Hospitality/Tests/probe_slice6_reservations_lifecycle.php|G11|OH-AUTO1-043|candidate-test
apps/Hospitality/Tests/probe_slice7_frontdesk_folio.php|G11|OH-AUTO1-043|candidate-test
apps/Hospitality/Tests/probe_slice8_housekeeping_board.php|G11|OH-AUTO1-043|candidate-test
apps/Hospitality/Tests/run_all_hospitality_probes.php|G11|OH-AUTO1-043|candidate-tooling
apps/Manufacturing/Services/OperatorLayerAdapters/AssemblyAdapter.php|G7|OH-AUTO1-019|candidate-source
apps/Manufacturing/Services/OperatorLayerAdapters/CoverageAdapter.php|G7|OH-AUTO1-019|candidate-source
apps/Manufacturing/Services/OperatorLayerAdapters/DispatchAdapter.php|G7|OH-AUTO1-019|candidate-source
apps/Manufacturing/Services/OperatorLayerAdapters/MachinesAdapter.php|G7|OH-AUTO1-019|candidate-source
apps/Manufacturing/Services/OperatorLayerAdapters/MaterialsAdapter.php|G7|OH-AUTO1-019|candidate-source
apps/Manufacturing/Services/OperatorLayerAdapters/QcAdapter.php|G7|OH-AUTO1-019|candidate-source
apps/Manufacturing/Services/UpstreamSupplyGenerationService.php|G10|OH-AUTO1-030,032|candidate-source
apps/Manufacturing/Services/DemandEngineService.php|G10|OH-AUTO1-047|candidate-source
apps/Manufacturing/Tests/probe_upstream_supply_concurrency.php|G10|OH-AUTO1-032|candidate-test
apps/Manufacturing/Tests/probe_upstream_supply_transaction.php|G10|OH-AUTO1-030,032|candidate-test
apps/Manufacturing/Tests/probe_upstream_supply_runtime_ddl_boundary.php|G10|OH-AUTO1-047|candidate-test
apps/Manufacturing/Tests/probe_remaining_runtime_ddl_boundaries.php|G10|OH-AUTO1-048|candidate-test
apps/Manufacturing/Services/StageTransitionService.php|G10|OH-AUTO1-048|candidate-source
apps/Manufacturing/Services/DemandExecutionService.php|G10|OH-AUTO1-048|candidate-source
apps/Procurement/Services/ProcurementOverviewService.php|G6|OH-AUTO1-018,020,029,031|candidate-source
apps/Procurement/Controllers/ProcurementDashboardController.php|G6|OH-AUTO1-051|candidate-source
apps/Procurement/Tests/probe_write_authorization_integrity.php|G6|OH-AUTO1-051|candidate-test
apps/Procurement/Tests/probe_order_receipt_method_boundaries.php|G6|OH-AUTO1-018|candidate-test
apps/Procurement/Tests/probe_procurement_concurrency.php|G6|OH-AUTO1-031,033|candidate-test
apps/Procurement/Tests/probe_behavioral_cancellation.php|G6|OH-AUTO1-043|candidate-test
apps/Procurement/Tests/probe_behavioral_request_cancel.php|G6|OH-AUTO1-043|candidate-test
apps/Procurement/Tests/probe_behavioral_supplier_update.php|G6|OH-AUTO1-043|candidate-test
apps/Procurement/Tests/probe_transaction_contract_audit.php|G6|OH-AUTO1-020,033|candidate-test
apps/SBAIO/Tests/probe_lifecycle_execution_boundary.php|G5|OH-AUTO1-016,017|candidate-test
apps/SBAIO/routes.php|G5|OH-AUTO1-016|candidate-source
apps/Shell/Composers/OperatorSurfaceComposer.php|G7|OH-AUTO1-019|candidate-source
apps/Shell/Resources/lang/en.php|G7|OH-AUTO1-019|candidate-source
apps/Shell/Resources/lang/ja.php|G7|OH-AUTO1-019|candidate-source
apps/Shell/Resources/lang/ne.php|G7|OH-AUTO1-019|candidate-source
apps/Shell/Tests/probe_operator_diagnostic_redaction.php|G7|OH-AUTO1-019,038,039,040|candidate-test
apps/Shell/Services/OperatorRealtimeCacheManager.php|G7|OH-AUTO1-038,039|candidate-source
apps/Shell/Services/OperatorRealtimeKpiProvider.php|G7|OH-AUTO1-038,039|candidate-source
apps/Shell/Services/OperatorRealtimeService.php|G7|OH-AUTO1-039,040|candidate-source
apps/Shell/Views/operator/fulfillment.php|G7|OH-AUTO1-019|candidate-source
apps/Shell/Views/operator/materials.php|G7|OH-AUTO1-019|candidate-source
apps/Shell/Views/operator/messages.php|G7|OH-AUTO1-019|candidate-source
apps/Shell/Views/operator/notifications.php|G7|OH-AUTO1-019|candidate-source
apps/Shell/Views/operator/processing.php|G7|OH-AUTO1-019|candidate-source
apps/Shell/Views/operator/production.php|G7|OH-AUTO1-019|candidate-source
apps/Shell/Views/operator/tasks.php|G7|OH-AUTO1-019|candidate-source
apps/Shell/Views/operator/preferences.php|G7|OH-AUTO1-038,039,040|candidate-source
public/assets/operator-realtime.js|G7|OH-AUTO1-040|candidate-source
docs/architecture/business-workflow-data-integrity-audit-2026-10-14.md|G6|OH-AUTO1-018|candidate-documentation
docs/architecture/consolidated-production-readiness-reconciliation-2026-10-14.md|G9|OH-AUTO1-021|candidate-documentation
docs/architecture/consolidated-release-baseline-audit-2026-10-14.md|G9|OH-AUTO1-014|candidate-documentation
docs/architecture/disposable-database-environment-discovery-2026-10-14.md|G8|OH-AUTO1-028|candidate-documentation
docs/architecture/disposable-database-test-infrastructure-2026-10-14.md|G8|OH-AUTO1-022|candidate-documentation
docs/architecture/integration-baseline-verification-2026-10-14.md|G9|OH-AUTO1-025|candidate-documentation
docs/architecture/lifecycle-enforcement-audit-2026-10-14.md|G5|OH-AUTO1-016|candidate-documentation
docs/architecture/lifecycle-execution-boundary-hardening-2026-10-14.md|G5|OH-AUTO1-017|candidate-documentation
docs/architecture/oh-auto1-governed-integration-manifest-2026-10-14.md|G9|OH-AUTO1-026|proposed-manifest
docs/architecture/oh-auto1-integration-commit-plan-2026-10-14.md|G9|OH-AUTO1-027|proposed-commit-plan
docs/architecture/core-security-lifecycle-remediation-decision-2026-10-14.md|G9|OH-AUTO1-035|candidate-documentation
docs/architecture/production-release-gate-reconciliation-2026-10-14.md|G9|OH-AUTO1-036|candidate-documentation
docs/architecture/oh-auto1-042-database-isolation-incident-2026-10-14.md|G9|OH-AUTO1-042|candidate-documentation
docs/architecture/oh-auto1-043-database-test-entrypoint-isolation-2026-10-14.md|G9|OH-AUTO1-043|candidate-documentation
docs/architecture/oh-auto1-045-isolated-test-execution-boundary-reconciliation-2026-10-14.md|G9|OH-AUTO1-045|candidate-documentation
docs/architecture/oh-auto1-047-manufacturing-runtime-ddl-safety-2026-10-14.md|G10|OH-AUTO1-047|candidate-documentation
docs/architecture/oh-auto1-048-remaining-manufacturing-runtime-ddl-2026-10-14.md|G10|OH-AUTO1-048|candidate-documentation
docs/architecture/oh-auto1-049-hospitality-reservation-modification-integrity-2026-10-14.md|G11|OH-AUTO1-049|candidate-documentation
docs/architecture/operational-diagnostics-observability-audit-2026-10-14.md|G9|OH-AUTO1-019|candidate-documentation
docs/architecture/plugin-route-registration-atomicity-audit-2026-10-14.md|G5|OH-AUTO1-024|candidate-documentation
docs/architecture/security-acl-deny-precedence-remediation-spec-2026-10-14.md|G8|OH-AUTO1-023|candidate-documentation
docs/architecture/security-authorization-tenant-isolation-audit-2026-10-14.md|G8|OH-AUTO1-015|candidate-documentation
docs/architecture/transactional-business-verification-audit-2026-10-14.md|G9|OH-AUTO1-020,029..033|candidate-documentation
engineering/Manufacturing/work.md|G10|OH-AUTO1-019,020,030,032,033,047,048|workspace-record
engineering/Hospitality/work.md|G11|OH-AUTO1-041,049|workspace-record
engineering/Platform/decisions.md|G9|OH-AUTO1-001..026|workspace-record
engineering/Platform/work.md|G9|OH-AUTO1-001..026|workspace-record
engineering/Procurement/work.md|G6|OH-AUTO1-018,020,029,031,033|workspace-record
engineering/Shell/work.md|G7|OH-AUTO1-019|workspace-record
platform/Recovery/RecoveryPointService.php|G2|OH-AUTO1-005,010,013|candidate-source
platform/Updates/InstalledSystemCompatibilityPreflightService.php|G3|OH-AUTO1-007|candidate-source
platform/Updates/InstalledSystemEvidenceProvider.php|G3|OH-AUTO1-010|candidate-source
platform/Updates/LocalReleaseApplyPreparationService.php|G2|OH-AUTO1-006..014|candidate-source
platform/Updates/LocalReleaseChannelBuilderService.php|G2|OH-AUTO1-006..014|candidate-source
platform/Updates/LocalReleaseChannelPreviewService.php|G2|OH-AUTO1-006..014|candidate-source
platform/Updates/MigrationCompatibilityPreflightService.php|G3|OH-AUTO1-011|candidate-source
platform/Updates/ReleasePackageInspectionService.php|G2|OH-AUTO1-006|candidate-source
platform/Updates/ReleaseReadinessEvidenceCollectorService.php|G4|OH-AUTO1-012|candidate-source
platform/Updates/ReleaseReadinessEvidenceService.php|G4|OH-AUTO1-008|candidate-source
platform/Updates/tests/probe_installed_build_identity.php|G3|OH-AUTO1-013|candidate-test
platform/Updates/tests/probe_installed_system_compatibility.php|G3|OH-AUTO1-007|candidate-test
platform/Updates/tests/probe_installed_system_evidence_provider.php|G3|OH-AUTO1-010|candidate-test
platform/Updates/tests/probe_local_release_apply_preparation.php|G2|OH-AUTO1-006..014|candidate-test
platform/Updates/tests/probe_local_release_channel_builder.php|G2|OH-AUTO1-006..014|candidate-test
platform/Updates/tests/probe_local_release_channel_preview.php|G2|OH-AUTO1-006..014|candidate-test
platform/Updates/tests/probe_migration_compatibility.php|G3|OH-AUTO1-011|candidate-test
platform/Updates/tests/probe_offline_release_recovery_integration.php|G2|OH-AUTO1-009|candidate-test
platform/Updates/tests/probe_release_readiness_evidence.php|G4|OH-AUTO1-008|candidate-test
platform/Updates/tests/probe_release_readiness_evidence_collector.php|G4|OH-AUTO1-012|candidate-test
scripts/architecture/check_localization_resource_diagnostics.sh|G1|OH-AUTO1-001,003|candidate-tooling
scripts/architecture/tests/test_localization_resource_diagnostics.sh|G1|OH-AUTO1-003|candidate-test
scripts/security/probe_acl_deny_precedence.php|G8|OH-AUTO1-023|candidate-test
scripts/system/recovery_point.php|G2|OH-AUTO1-005,010,013|candidate-tooling
scripts/testing/README.md|G8|OH-AUTO1-022,023|candidate-documentation
scripts/testing/DisposableDbExecutionAuthorizer.php|G8|OH-AUTO1-028..033|candidate-tooling
scripts/testing/DisposableDbTestConfigurationGuard.php|G8|OH-AUTO1-042|candidate-tooling
scripts/testing/DisposableDbTestEntryPointGuard.php|G8|OH-AUTO1-043|candidate-tooling
scripts/testing/IsolatedSourceRootTestRunner.php|G8|OH-AUTO1-044|candidate-tooling
scripts/testing/prepare_isolated_source_root.php|G8|OH-AUTO1-044|candidate-tooling
scripts/testing/cleanup_isolated_source_root.php|G8|OH-AUTO1-044|candidate-tooling
scripts/testing/probe_isolated_source_root_runner.php|G8|OH-AUTO1-044|candidate-test
scripts/testing/disposable_db_fixture.sql|G8|OH-AUTO1-022|candidate-fixture
scripts/testing/disposable_db_harness.php|G8|OH-AUTO1-022,023|candidate-tooling
scripts/testing/probe_disposable_db_execution_authorizer.php|G8|OH-AUTO1-028..033|candidate-test
scripts/testing/probe_disposable_db_harness_safety.php|G8|OH-AUTO1-023|candidate-test
scripts/testing/probe_disposable_db_test_configuration_guard.php|G8|OH-AUTO1-042|candidate-test
scripts/testing/probe_plugin_route_registration_atomicity.php|G5|OH-AUTO1-024|candidate-test
scripts/testing/run_business_integrity_regression.sh|G11|OH-AUTO1-033,041|candidate-tooling
storage/logs/app-lifecycle-dedupe.json|X|pre-existing-runtime|excluded-runtime-artifact
storage/logs/app-lifecycle.log|X|pre-existing-runtime|excluded-runtime-artifact
vendor/bin/.phpunit.result.cache|X|generated-test-artifact|excluded-generated-artifact
<!-- INVENTORY_END -->

## Reconciliation result

- **138/138 live paths are classified**: 135 OH-AUTO candidate records and 3
  preserved/generated artifacts excluded from source integration.
- **0 paths have unknown provenance** based on current evidence. This does not
  mean all candidate paths are approved for one commit.
- **3 paths are explicitly excluded** from source integration: the lifecycle log,
  dedupe JSON runtime artifact, and generated PHPUnit cache.
- **135 paths remain candidate integration content**, subject to group-level
  review, commit-boundary approval, and the blockers below.
- No missing implementation/test dependency was found for the release, lifecycle,
  procurement, Manufacturing, Hospitality, operator-diagnostic, localization,
  harness, database-isolation, or consolidated-regression groups.

## Group readiness disposition

| Group | Governed source-review state | Reason |
|---|---|---|
| G1 | Reviewable | Implementation and fixture regression are paired and pass. |
| G2 | Reviewable, production-blocked | Offline release/recovery chain passes; live apply and recovery remain human-gated. |
| G3 | Reviewable, production-blocked | Compatibility probes pass; migration target and installed identity contracts remain open. |
| G4 | Reviewable, production-blocked | Evidence collection passes; no attestation authority exists. |
| G5 | Split review required | SBAIO correction is app-owned; route rollback evidence remains Core-blocked. |
| G6 | Reviewable, behavior-verified with limits | Procurement transaction/concurrency probes pass; over-quantity and broader downstream status policy remain open. |
| G7 | Reviewable, acceptance-blocked | Redaction evidence passes; live operator acceptance is absent. |
| G8 | Reviewable, execution-bounded | Disposable-instance authorizer and behavioral harness pass; installed DB access and Core ACL correction remain prohibited. |
| G9 | Governance review only | Retention, historical routing, and commit boundaries require planner approval. |
| G10 | Reviewable, behavior-verified | Manufacturing rollback and concurrency probes pass; nested caller-owned transaction composition remains unsupported. |
| G11 | Reviewable, behavior-verified with limits | Hospitality assigned-room concurrency is serialized by a room-row lock; all 25 DB-capable business entry points now reject direct bootstrap without governed isolation evidence. Database retesting remains suspended pending review. |
| X | Excluded | Runtime-generated artifacts are preserved, not source integration candidates. |

## Blockers and unresolved ownership decisions

1. `SEC-AUTH-001` remains a Core/security defect; no application workaround is
   authorized.
2. `LIFECYCLE-003` remains a Core route rollback defect; the probe is evidence,
   not a correction.
3. Procurement and Manufacturing transaction claims are backed by completed
   isolated probes; nested Manufacturing caller-owned transaction composition
   remains explicitly unsupported.
4. Release attestation, installed build identity, migration semantics, and
   protected-path policy remain undecided.
5. Governance/workspace records are attributable but their retention and commit
   boundaries require planner approval.
6. No candidate group is production-ready solely from these offline probes.

## Suggested governed commit boundaries

1. G1 localization diagnostic gate plus fixture regression.
2. G2 recovery/release implementation plus directly coupled probes.
3. G3 installed compatibility/migration implementation plus probes.
4. G4 readiness services plus probes.
5. G5 SBAIO correction/probe, while keeping Core route-atomicity evidence as a
   separate documentation/probe review until Core ownership is decided.
6. G6 Procurement correction and focused boundary probe; transaction audit can
   be a separate evidence commit if desired.
7. G7 operator redaction implementation, localization, views, and probe.
8. G8 fail-closed harness/security probes and contracts, with no DB execution.
9. G9 governance records and audit documents, reviewed separately from runtime
   source. X remains uncommitted unless retention authority explicitly requires
   otherwise.
10. G10 Manufacturing correction and directly coupled transaction/concurrency
    probes plus owner record.
11. G11 Hospitality reservation correction and evidence, with the existing
    consolidated regression runner reviewed as orchestration/tooling only.

These are proposed review boundaries only. They do not authorize staging or
commitment.

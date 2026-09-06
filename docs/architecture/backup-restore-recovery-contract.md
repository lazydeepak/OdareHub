# Backup Restore Recovery Contract

Status: V1 design contract. No backup file, database dump, restore point, restore operation, update apply, installer, hosted service, or runtime behavior is created by this document.

Date: 2026-08-19

## 1. Purpose

Define the full database and filesystem recovery point required before any Susankhya OS local update apply, repair upgrade, Windows installer upgrade, or private online update can be authorized.

## 2. Scope

This contract protects one compatible Susankhya OS modular-monolith installation: runtime code, vendor dependencies, database, configuration, customer files, customer-generated state, and necessary recovery evidence.

It does not redefine app/module ownership or replace app/module export/import features. It is a product-update recovery contract, not a generic data-export feature.

## 3. Terms

| Term | Meaning |
| --- | --- |
| Recovery Point | Verified, immutable record plus backup payload sufficient to restore one compatible installation. |
| Database Backup | Complete database backup or provider-supported recovery point, not a selected-table export. |
| Filesystem Backup | Backup of code, configuration, customer files, and required local runtime state. |
| Restore Rehearsal | Controlled proof that a recovery point restores to a working compatible state. |
| Preservation Inventory | Explicit list of target-owned paths/configuration that apply or restore must retain. |
| Restore Record | Non-secret audit evidence of backup identity, actor, request, result, and final state. |

Existing `suite` export/restore terminology is compatibility-only and does not define this contract's product scope.

## 4. Current Evidence

| Existing mechanism | Current value | Why it is insufficient alone |
| --- | --- | --- |
| `SuiteExportService` | Exports metadata, packages, and rows from declared required module tables. | Its “full snapshot” is a suite/module export, not whole database/filesystem recovery. |
| `SuiteRestoreService` | Previews uploaded JSON/ZIP exports, restores package entries and selected table rows. | No full database restore, no pre-restore recovery point, and `rollback_attempted` is currently false. |
| Manufacturing/SBAIO restore routes | Provide preview and commit UI over `SuiteRestoreService`. | Domain import-back UI is not product-update recovery. |
| `RestoreHistoryService` / `ReleaseHistoryService` | Persist preview/history records. | History does not prove that payloads can restore an installation. |
| `AppMigrationService` | Records migration checksums and schema snapshots. | Migration bookkeeping is not a database backup or down-migration guarantee. |
| `CoreSetupService` | Checks database connectivity and writable paths. | It cannot recover database/filesystem state. |

No `mysqldump`, `mysqlpump`, database snapshot provider, or equivalent complete database backup/restore implementation is currently present in the audited repository surface.

## 5. Recovery Point Identity

Every recovery point must have an immutable, non-secret identity record containing:

```text
schema_version
recovery_point_id
created_at
created_by
reason                  (update_apply | repair | installer_upgrade | manual)
installed_product_version
installed_build_id
release/package/channel identity, when update-related
app and module manifest checksums
schema and migration state
database connection identity without secrets
database backup format/provider identity and checksum
filesystem backup format, scope, and checksum
preservation inventory fingerprint
restore-rehearsal status and evidence reference
```

Database identity may record host label, port, database name, engine/provider, and schema state. It must never record database passwords, connection DSNs containing secrets, private keys, access tokens, or raw `storage/db_config.php` content.

## 6. Database Backup Requirement

Before any update/apply mutation, V1 requires one of:

1. a complete, verified database backup created by an approved database backup provider; or
2. a provider-supported recovery point with equivalent documented restoration guarantees.

The backup must cover all required schemas/tables for the target installation, preserve transactional consistency, carry a format/provider version and checksum, and be independently restorable on a controlled target.

Selected-table export, schema snapshot rows, migration checksums, and application-level history records do not satisfy this requirement.

## 7. Filesystem Backup Requirement

The recovery point must capture the filesystem state needed to restore one compatible installation, including:

- installed release-owned code and `vendor/` identity;
- `storage/db_config.php` or a documented, non-secret configuration-preservation reference;
- customer files/uploads and customer-generated documents;
- required app/generated runtime state under `storage/`;
- installed/customer package state under `packages/`; and
- release/restore/update evidence and prior recovery artifacts needed by the preservation inventory.

The backup mechanism may exclude only paths explicitly declared reproducible in Section 10. It must record root scope, format, checksum, size, and completion status.

## 8. Configuration Preservation Requirement

`storage/db_config.php` and environment-provided `ERP_DB_*` configuration are target-owned secrets/configuration. Update and restore must preserve them by default.

The recovery contract must define, before implementation:

- which configuration paths are retained, copied, or supplied externally;
- a non-secret fingerprint/identity method for comparison;
- handling when a required config file is missing or differs from the expected installation; and
- confirmation that recovery logs, release metadata, previews, and support reports never expose credentials.

## 9. Customer Data Preservation Requirement

Preserve by default:

- uploads and customer files;
- customer-generated documents, labels, reports, exports, and imports where they are retained as operational records;
- app-generated customer/runtime data under `storage/`;
- Studio-generated state and audit evidence when it is required by a live generated app or governed history contract;
- backups, restore points, release/restore previews, and history; and
- installed/customer package state under `packages/`.

Any new customer-data root must be declared by its owning app/module before update support is enabled. An update package's omission of a path is never permission to delete it.

## 10. Exclusions And Reproducible Assets

The filesystem backup may exclude caches, temporary staging files, and derived CSS/assets only when all conditions hold:

- the source of truth is included or independently preserved;
- regeneration is deterministic and covered by the verified readiness/recovery procedure;
- exclusion cannot remove customer data, configuration, audit evidence, or installation state; and
- the recovery record lists the excluded path and regeneration step.

Logs may be retained separately and should not block functional restore, but error/audit retention must follow the storage retention policy. Local temp/cache cleanup is never a substitute for recovery evidence.

## 11. Restore Eligibility Rules

Restore may start only when:

1. the recovery-point identity, checksums, and backup payload availability are verified;
2. the target installation and requested package/schema state are identified;
3. the actor is authorized and explicitly confirms the restore scope;
4. required configuration and preservation inventory are available without exposing secrets;
5. database provider/format compatibility is confirmed; and
6. no prior unresolved `failed_recovery_required` state blocks the operation.

The implementation must fail closed. An incomplete export archive or a history row alone is never restore eligibility.

## 12. Restore Procedure Contract

The later procedure must use a bounded order:

```text
authorize and load recovery point
  -> validate identity/checksums/compatibility
  -> protect current target state when required
  -> restore filesystem/configuration/customer state
  -> restore complete database recovery point
  -> restore or regenerate declared reproducible assets
  -> reconcile app/module registry and schema state
  -> run verification and smoke checks
  -> write final restore record
```

Database and filesystem restoration must converge on the same compatible release/schema state. A restore must not report success after only copying files, restoring selected tables, or re-running migrations.

## 13. Verification After Backup

Before a recovery point becomes eligible for update apply, verify:

- all payloads exist, are readable, and match recorded checksums/sizes;
- database backup/provider metadata proves completion and consistent scope;
- filesystem scope contains configuration preservation evidence and required customer state;
- recovery identity binds installed release, app/module checksums, migration/schema state, actor, and timestamp;
- secrets are absent from metadata and logs; and
- a restore rehearsal record is current for the same provider/format family.

## 14. Verification After Restore

The restore is successful only when all pass:

- database connection succeeds and the expected database/schema state is present;
- required configuration is present without secret leakage;
- application bootstrap starts;
- app registry and module registry load with expected states;
- migrations are compatible with the restored release, not merely runnable;
- required writable paths exist;
- deployment readiness and selected authenticated smoke checks pass; and
- recovered customer files/state and preservation inventory match the recovery record.

## 15. Failure State And Recovery Records

Every restore/update-recovery attempt must end in exactly one state:

| Final state | Meaning |
| --- | --- |
| `restored` | Full verification passed and the target is compatible. |
| `failed_without_mutation` | Validation failed before any target mutation. |
| `failed_recovery_required` | Mutation occurred or target state is uncertain; operator recovery is required. |
| `recovery_in_progress` | A bounded recovery operation is active; no competing apply/restore may run. |

Records must include non-secret error/step evidence, actor, timestamps, recovery-point ID, target identity, and final state. A failed restore must block update apply until the state is resolved through the approved recovery procedure.

## 16. Relationship To Existing Export/Restore Services

`SuiteExportService` and `SuiteRestoreService` may continue to serve domain/module export/import-back use cases. They are not full product backup/rollback services because they work with selected module/package/table payloads and do not establish complete database/filesystem recovery.

`RestoreHistoryService` and domain restore controllers may provide implementation evidence or later UI integration, but they cannot define product recovery authority. Their legacy Suite naming remains unchanged in this contract slice.

## 17. Relationship To Update Apply

The local release channel contract requires verified backup/recovery evidence before apply eligibility. Therefore, **V1 update apply is blocked** until this contract is implemented and a recovery point is successfully rehearsed.

Future update apply must bind its release/channel/package checksum, compatibility preview, operator approval, pre-apply recovery point, post-apply verification, and failure state to one auditable operation. Existing `UpgradeAssistantService` behavior is not automatically approved as that operation.

## 18. Relationship To Installer

A future Windows installer may perform fresh installation without an existing recovery point only when no customer installation exists. For repair or upgrade of an existing installation, it must call/enforce this contract before any file/schema mutation.

The installer must not invent an alternate backup format, configuration-preservation rule, or restore status model.

## 19. Security And Retention Requirements

- Backup payloads contain sensitive customer and operational data; protect them with least-privilege filesystem/provider access and transport controls.
- Do not place secrets in release packages, channel metadata, backup identity records, preview output, logs, or support diagnostics.
- Encrypt backup storage/transport where the selected provider and deployment risk require it; key-management design is a later provider decision.
- Retention, expiration, cleanup, legal/customer obligations, and secure deletion policy must be explicitly approved before production use.
- Cleanup must never delete a recovery point referenced by an unresolved update/restore record, a required audit history, or a live installation's supported recovery window.

## 20. Non-Goals

This contract does not authorize:

- implementation of database dumps, provider snapshots, filesystem backup, restore, rollback, or retention cleanup;
- creation of backup files, restore points, storage directories, dumps, or recovery records;
- local update apply, installer, Windows bootstrap, `update.susankhya.com`, signing, entitlement, or cloud synchronization;
- modification of SuiteExport/SuiteRestore, restore routes, migrations, storage contents, or runtime code;
- Hospitality implementation, Suite runtime, shared-app extraction, or large app/module refactors.

## Related Documents

- `docs/architecture/local-release-channel-contract.md`
- `docs/architecture/deployment-update-foundation-audit.md`
- `docs/architecture/local-deployment-update-channel-plan.md`
- `docs/architecture/susankhya-productization-roadmap.md`
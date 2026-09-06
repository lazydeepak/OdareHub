# Batch 7 Storage Runtime Retention Policy Diagnostic

Status: executed as read-only diagnostic and documentation only.

No storage files were deleted, moved, archived, or mutated. Runtime behavior and
Core behavior were not changed.

## Architecture Basis

- Storage is runtime/customer artifact territory unless a retention policy says
  otherwise.
- Studio generated apps depend on `storage/appstudio` registry, generated data,
  snapshots, applies, audit records, packages, and publish decisions.
- Public delivery output and generated apps cannot be cleaned safely without
  storage provenance.
- Temporary-looking storage directories are not deletion candidates until the
  owning workflow exposes TTL, completion state, and active-job checks.

## Diagnostic Script

- script: `scripts/architecture/check_storage_runtime_retention_policy.sh`
- mode: read-only diagnostic
- current result: pass

Local diagnostic evidence:

- 4 storage files are tracked by git:
  `storage/.gitkeep`, `storage/README.md`,
  `storage/appstudio/applies/.gitkeep`, and
  `storage/db_config.php.example`.
- `storage/appstudio/generated_data` currently contains 2 files.
- `storage/appstudio/snapshots` currently contains 14 files.
- `storage/appstudio/applies` currently contains 19 files.
- `storage/appstudio/audit` currently contains 24 files.
- `storage/appstudio/packages` currently contains 2 files.
- `storage/appstudio/publish_decisions` currently contains 1 file.
- `storage/tmp` currently contains 8 files.
- `storage/restore_previews` currently contains 3 files.
- `storage/environment_snapshots` currently contains 2 files.

## Direct Answers

1. Which storage folders are runtime/customer state?
   - `storage/db.sqlite`, `storage/db_config.php`, `storage/app_files`,
     `storage/branding`, and `storage/logs`.
   - Studio runtime registries:
     `storage/appstudio/apps_registry.json`,
     `storage/appstudio/registry.json`, and
     `storage/appstudio/schema_registry.json`.
   - These are not cleanup candidates in migration batches.

2. Which are generated-app evidence?
   - `storage/appstudio/generated_data`
   - `storage/appstudio/snapshots`
   - `storage/appstudio/applies`
   - `storage/appstudio/audit`
   - `storage/appstudio/publish_decisions`
   - `storage/appstudio/packages`
   - `storage/appstudio/history.json`
   - `storage/appstudio/apply_log.json`
   - `storage/appstudio/apply_log.ndjson`

3. Which are temporary but unsafe to delete without TTL/policy?
   - `storage/tmp`
   - `storage/tmp_plugins`
   - `storage/restore_tmp`
   - `storage/import_uploads`
   - `storage/import_previews`
   - `storage/restore_uploads`
   - `storage/environment_clone_uploads`
   - `storage/environment_clone_previews`

4. Which are package/export/restore artifacts?
   - `storage/appstudio/packages`
   - `storage/exports`
   - `storage/plugin_exports`
   - `storage/releases`
   - `storage/release_previews`
   - `storage/restore_previews`
   - `storage/restore_uploads`
   - `storage/environment_snapshots`

5. Which are tracked fixtures versus local operational byproducts?
   - Tracked fixtures are limited to `storage/.gitkeep`,
     `storage/README.md`, `storage/appstudio/applies/.gitkeep`, and
     `storage/db_config.php.example`.
   - Current operational artifacts under `storage/appstudio`, `storage/tmp`,
     `storage/branding`, logs, exports, snapshots, restore uploads/previews,
     releases, and local DB/config files are untracked runtime state.

6. What must be checked before deleting generated app storage?
   - `storage/appstudio/apps_registry.json` status.
   - Generated app code, manifests, routes, and navigation.
   - `storage/appstudio/generated_data`, snapshots, applies, audit records,
     publish decisions, and packages.
   - Public assets under `public/assets/apps/...`.
   - Docs/scripts references.
   - Backup/export availability and rollback/support window.

7. What retention policy should govern snapshots, audit logs, packages, tmp
   files, and restore previews?
   - Snapshots, applies, audit logs, publish decisions, and history remain
     retained through the rollback/support window and until generated app
     archive evidence is exported.
   - Packages, plugin exports, release artifacts, and environment snapshots are
     retained by version/export ledger; zip and signature pairs must remain
     together.
   - Tmp/import/restore staging files may be deleted only by TTL after the
     owning workflow records completion/cancellation and active jobs are clear.
   - Runtime/customer state is never deleted by migration cleanup.

## Classification

| Storage area | Classification | Owner | Cleanup decision |
|---|---|---|---|
| `storage/appstudio/apps_registry.json` | RUNTIME_STATE | Studio | retain; generated app lifecycle truth |
| `storage/appstudio/generated_data` | GENERATED_DATA | Studio + generated app providers | retain for lifetime of generated app |
| `storage/appstudio/snapshots` | SNAPSHOT_EVIDENCE | Studio | retain through rollback/support window |
| `storage/appstudio/applies` | AUDIT_EVIDENCE | Studio | retain as apply evidence |
| `storage/appstudio/audit` | AUDIT_EVIDENCE | Studio governance | retain as governance evidence |
| `storage/appstudio/packages` | PACKAGE_ARTIFACT | Studio package/export lifecycle | retain zip/signature pairs together |
| `storage/appstudio/publish_decisions` | AUDIT_EVIDENCE | Studio governance | retain as generated/public publish provenance |
| `storage/appstudio/registry.json` | RUNTIME_STATE | Studio compatibility | retain until registry split is resolved |
| `storage/appstudio/schema_registry.json` | RUNTIME_STATE | Studio schema governance | retain while schema governance is enabled |
| `storage/tools` | TOOL_STATE | System tools/developer tooling | retain tracked fixtures; classify outputs separately |
| `storage/tmp` | TEMP_ARTIFACT | Shell/data exchange/runtime jobs | keep until TTL and active-job detection exist |
| `storage/environment_snapshots` | RELEASE_PREVIEW | Platform/environment portability | retain until export retention policy exists |
| `storage/release_previews` | RELEASE_PREVIEW | Release packaging | retain until release workflow TTL exists |
| `storage/releases` | PACKAGE_ARTIFACT | Release packaging | retain by release artifact policy |
| `storage/restore_previews` | RESTORE_PREVIEW | Restore/import lifecycle | retain until restore workflow TTL exists |
| `storage/restore_uploads` | RESTORE_PREVIEW | Restore/import lifecycle | retain while restore previews may reference uploads |
| `storage/restore_tmp` | TEMP_ARTIFACT | Restore/import lifecycle | clear only through restore-service TTL/job cleanup |
| `storage/exports` | PACKAGE_ARTIFACT | Package/export lifecycle | retain until export artifact policy exists |
| `storage/plugin_exports` | PACKAGE_ARTIFACT | Package/plugin lifecycle | retain until plugin export policy exists |
| `storage/tmp_plugins` | TEMP_ARTIFACT | Package/plugin lifecycle | clear only through PackageManager-safe TTL |
| `storage/app_files` | RUNTIME_STATE | Runtime uploads | retain; app-owner cleanup only |
| `storage/branding` | RUNTIME_STATE | Platform/Organization branding | retain active/referenced branding assets |
| `storage/logs` | AUDIT_EVIDENCE | Runtime diagnostics | rotate/expire only by log policy |
| `storage/db.sqlite` | RUNTIME_STATE | Local runtime database | never clean in migration batches |
| `storage/db_config.php` | RUNTIME_STATE | Environment configuration | never commit real credentials or delete blindly |
| `storage/db_config.php.example` | TEST_FIXTURE | Setup fixture | retain in git |
| `storage/architecture_policy.php` | RUNTIME_STATE | Architecture policy compatibility | retain until policy source owner is clarified |
| `storage/schema_snapshots` | SNAPSHOT_EVIDENCE | Schema tooling | retain placeholder/latest evidence until policy exists |

## Runtime Coupling Summary

- Generated app providers read `storage/appstudio/generated_data/...` at
  runtime.
- Studio services write and read `storage/appstudio` applies, snapshots, audit
  records, publish decisions, package artifacts, and registries.
- Restore, import, release, environment snapshot, package export, branding, and
  logging services all have explicit `storage/...` readers/writers.
- Most current storage artifacts are untracked operational state. Git cleanup
  and repository decluttering must not imply runtime deletion.

## Future Safe Work

1. Create a cleanup decision matrix before any archive/delete batch.
2. Define TTL and active-job checks for tmp/import/restore staging areas.
3. Define generated app retirement policy that archives app code, registry
   state, generated data, snapshots, applies, audit, packages, and public
   assets as one governed unit.
4. Define package/export/release retention ledgers before deleting zip/json
   artifacts.
5. Keep runtime/customer storage outside migration cleanup unless an owning
   service provides a safe cleanup operation.

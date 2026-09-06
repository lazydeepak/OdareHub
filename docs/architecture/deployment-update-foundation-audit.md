# Deployment Update Foundation Audit

Status: factual audit. No installer, update channel, safe apply workflow, backup implementation, restore implementation, hosted service, or runtime change is authorized by this document.

Date: 2026-08-19

## 1. Purpose

Record the current deployment, release, package, upgrade, export, restore, recovery, and environment machinery before productization work begins. This audit distinguishes evidence that exists from the V1 local deployment/update flow planned in `local-deployment-update-channel-plan.md`.

## 2. Current Deployment Shape

Susankhya is deployed as one PHP modular monolith: repository code, `vendor/`, `public/`, `app/`, `apps/`, `platform/`, plugins, database configuration, and writable storage coexist in one installation.

| Concern | Current evidence | State |
| --- | --- | --- |
| Web deployment | `README.md` directs deployment under `public_html` with web root `public_html/public`. | Documented manual deployment. |
| Database configuration | `storage/db_config.php.example`; `App\\Core\\DB`; `README.md`. | File or `ERP_DB_*` environment variables supported. |
| Runtime dependencies | `composer.json`, `composer.lock`, `vendor/autoload.php`. | Lockfile exists; deployment still expects Composer externally or a synchronized `vendor/`. |
| Runtime asset preparation | `scripts/assets/publish_registered_css.php`, `compile_first_boot_css.php`, theme compilation/recovery. | Implemented derived-asset delivery only. |
| App discovery | `AppLocalDiscoveryService`, `AppRegistryService`, `core_apps`. | Implemented local app lifecycle discovery. |
| Product installer | No installer executable or Windows bootstrap found. | Missing. |
| Local update channel | `docs/architecture/local-deployment-update-channel-plan.md` only. | Planned, not implemented. |

## 3. Existing Readiness Checks

| Entry point | What it does | What it does not prove |
| --- | --- | --- |
| `scripts/system/check_deployment_readiness.sh` | Runs System Tool inventory, backfill aging, PHP syntax checks, publishes registered and first-boot CSS with `--apply`, runs architecture gates, runs `git diff --check`, and checks that app delivery assets do not leave tracked drift. | It does not install a release, check a channel, verify a release signature, back up a database, restore a database, or test an update rollback. |
| `scripts/system/check_deployment_readiness_portable.php` | Delegates to Bash when available; otherwise runs a portable PHP/git subset and reports Bash-only diagnostics as skipped. | A portable-local pass is not equivalent to executing all Bash gates. |
| `CoreSetupService::preflight()` | Checks PHP >= 8.1; `mysqli`, `json`, and `zip`; database connectivity; and selected writable paths. | It does not verify an installer prerequisite set, database backup capability, Composer installation, `gd`, filesystem rollback capacity, or update-channel compatibility. |

The readiness orchestrator intentionally writes reproducible CSS delivery output. It is a governed validation/preparation tool, not an installer or updater.

## 4. Existing Release Packaging

| Service | Current behavior | Boundary |
| --- | --- | --- |
| `ReleasePackagingService` | Creates a ZIP in `storage/releases` after a `DeploymentReadinessService` preview reports ready. It adds core payload, selected app groups, and optional module packages plus `release_metadata.json`. | Release packaging exists; it is not installer packaging or channel distribution. |
| `ReleaseHistoryService` | Stores preview/generated/failed release records in `core_release_runs` and JSON preview payloads under `storage/release_previews`. | History and preview evidence exist. |
| `AppPackageService` / `AppInstallService` | Accept, extract, validate, register, and install a top-level ZIP with `manifest.json` and `package_type: bundle`. | App package transport/install exists; it is not an immutable product release protocol. |
| `PackageManager` | Reads ZIP `manifest.json` or `plugin.json`, dispatches bundle/module handling, and uses legacy plugin paths as fallback. | Lifecycle/package compatibility machinery, not a channel client. |

An uploaded app ZIP receives a SHA-256 value in `AppPackageService`, and `AppInstallService` uses it for duplicate-version protection. App local discovery hashes its manifest. Migration and schema snapshots also record checksums. However, `ReleasePackagingService` does not emit a general release-package SHA-256 or signature verification contract, and no current release consumer verifies one.

Studio has separate signed package behavior under `apps/Studio/Services/GuiStudioService.php`; that applies to Studio-generated artifacts and must not be represented as product-release signing.

## 5. Existing Upgrade / Update Preview Machinery

| Service | Current behavior | Productization assessment |
| --- | --- | --- |
| `DeploymentReadinessService` | Evaluates core, selected legacy “suites,” and modules for environment, dependencies, schema gaps, failed hooks, migrations, and warnings. | Useful local readiness preview; no channel/package comparison. |
| `UpgradeAssistantService` | Offers core, suite, and module preview/apply paths, records setup state, calls Core repair, app install/repair/configure, or module update. | Existing lifecycle apply, not a safe product update protocol. |
| `SuiteSetupService` | Discovers business app manifests as “suites,” configures profiles, and verifies modules. | Active compatibility naming; no first-class Suite loader. |
| `VersionCatalogService` | Reads core and Manufacturing/SBAIO versioning files plus DB/manifest state. | Local catalog only; not a remote feed or universal app catalog. |

The existing apply paths operate against local files, database state, and installed artifacts. They do not retrieve a release from a local/online channel, bind an approved release manifest to a file checksum/signature, create a pre-apply filesystem/database recovery point, or prove post-failure rollback.

## 6. Existing Backup / Restore / Recovery Machinery

| Service | What exists | Limit before product-safe recovery |
| --- | --- | --- |
| `SuiteExportService` | Exports metadata, package-only, data-only, package+data, or “full snapshot” ZIP/JSON artifacts to `storage/exports`; data is rows from declared required module tables. | “Full snapshot” is suite/module export terminology, not a whole-installation/database backup guarantee. |
| `SuiteRestoreService` | Parses JSON/ZIP exports, previews conflicts, can restore embedded packages and selected table data using `overwrite` or `merge`. | Package actions occur before data restore; no pre-restore filesystem/database backup is created; `rollback_attempted` is returned as `false`; no verified rollback workflow exists. |
| `RestoreHistoryService` | Stores preview/restored/failed rows in `core_restore_runs` and preview JSON under `storage/restore_previews`. | History is not a restore guarantee. |
| `AppMigrationService` | Records additive SQL migration checksums and schema snapshots in database tables. | Migration bookkeeping is not database backup/restore. |
| `CoreSetupService` / setup state | Provides recovery/repair-oriented lifecycle steps and preflight state. | No tested full system restore contract. |

No `mysqldump`, `mysqlpump`, database snapshot provider, or equivalent full database backup/restore implementation was found in the inspected code/scripts. Existing export data covers selected declared module tables and may warn on unreadable tables; it is not a complete database or configuration recovery point.

## 7. Existing Version / Catalog / History Machinery

| Capability | Evidence | State |
| --- | --- | --- |
| Core version catalog | `app/versioning/core.php` through `VersionCatalogService::coreInfo()` | Local static catalog. |
| Business app version catalogs | `apps/Manufacturing/versioning.php`, `apps/SBAIO/versioning.php` through `suiteInfo()` | Local static, legacy suite naming, limited to these two apps. |
| Module version metadata | module `plugin.json`, `installed_plugins`, module catalog sections | Present, legacy plugin/module lifecycle. |
| Release history | `ReleaseHistoryService`, `core_release_runs`, `storage/release_previews` | Present. |
| Restore history | `RestoreHistoryService`, `core_restore_runs`, `storage/restore_previews` | Present. |
| Setup/update execution history | `SetupStateService` consumers such as `UpgradeAssistantService` | Present. |
| Online release catalog | No endpoint/client/channel source found. | Missing. |

`suite`, `included_suites`, `suite_key`, and `Suite*Service` are compatibility-only names for current app grouping. They are not evidence of a first-class Suite registry or update model.

## 8. Existing Runtime And Environment Assumptions

| Assumption | Evidence | Audit observation |
| --- | --- | --- |
| PHP and extensions | `CoreSetupService::preflight()` checks PHP 8.1+, `mysqli`, `json`, `zip`. | README also lists `gd`; preflight does not currently validate it. |
| Composer/vendor | `composer.lock` and checked-in `vendor/autoload.php` exist; README says synchronize `vendor/` where Composer cannot run. | Installer-ready dependency acquisition/verification is not implemented. |
| Database config | `storage/db_config.php` or `ERP_DB_HOST`, `ERP_DB_NAME`, `ERP_DB_USER`, `ERP_DB_PASS`, optional port/charset/timezone. | Config preservation/merge policy during update is not defined. |
| Writable paths | `CoreSetupService` checks `storage`, `storage/logs`, `packages`, selected package staging paths, and public asset paths. | `AppPackageService` uses `packages/uploaded` and `packages/temp`, while preflight checks `packages/uploads` and `packages/extracted`; the coverage mismatch needs resolution before installer acceptance. |
| Runtime-generated assets | Asset tools and in-process first-boot/theme recovery write under public asset delivery paths. | The installation user/web process needs controlled write permissions; rollback handling for generated assets is not defined. |
| Application migration | `AppMigrationService` runs additive SQL and records migration/schema checksums. | No generic down-migration or database restore guarantee. |

`storage/README.md` requires storage to remain writable for logs, imports, restores, snapshots, and release packaging. It correctly prohibits committing real `storage/db_config.php` credentials, but it does not define product-update config preservation or backup retention.

## 9. Existing Gaps Before Installer

- No supported Windows installer/bootstrap executable or prerequisite detector.
- No locked install manifest covering PHP, web server, database, Composer/vendor, PHP extensions, writable paths, permissions, and initial configuration.
- No dependency acquisition/verification plan for `composer.lock` and `vendor/`.
- No defined preservation/merge rules for `storage/db_config.php`, environment variables, writable storage, logs, uploads, or generated assets.
- No verified full database backup/restore step before migration or file replacement.
- No installer-level acceptance test for fresh install, repair, upgrade, failed upgrade, restore, and uninstall boundaries.

## 10. Existing Gaps Before Private Online Updates

- No `update.susankhya.com` implementation, release-feed client, channel resolver, entitlement check, or license-to-release authorization path.
- No product-release signing key, public-key verification, release checksum verification, or signature/revocation policy.
- No remote metadata schema, HTTPS/pinning policy, rollout cohort policy, or audit record binding a downloaded package to an approved release.
- No background update service, scheduler, or safe unattended apply contract.
- No cloud-data synchronization requirement or implementation; `cloud.susankhya.com` remains a future customer/license/control portal only.

## 11. Existing Gaps Before Safe App Update Apply

- No filesystem snapshot or atomic whole-app swap verified before `AppPackageService::moveInstalledTree()` removes/replaces an existing app directory.
- Database transaction boundaries in app install do not roll back earlier filesystem movement.
- No pre-apply database backup/recovery point or post-apply restoration test.
- No package-to-channel manifest checksum/signature binding.
- No compatibility matrix spanning core, every installed app, every module, PHP extensions, Composer dependencies, and schema state.
- No mandatory post-apply smoke suite for route discovery, critical app/module activation, migration integrity, and derived assets.
- Existing restore conflict modes are not a product-update rollback protocol.

## 12. Recommended V1 Local Deployment Flow

The recommended local-first sequence is a target flow, not current behavior:

```text
clean source and environment
  -> deployment readiness
  -> deterministic release package + checksum
  -> filesystem-backed channel manifest
  -> compatibility/read-only preview
  -> verified backup + recovery-point evidence
  -> explicit local apply
  -> migration and post-apply verification
  -> documented restore rehearsal
```

Use one controlled filesystem-backed channel before Windows installation or a hosted service. A successful readiness check is one input to the flow; it is not an approval to replace files or schemas.

## 13. Recommended V1 Package / Channel Shape

The channel shape already proposed in `local-deployment-update-channel-plan.md` remains appropriate:

```text
storage/update-channels/local/
  channel.json
  releases/
    susankhya-os-<version>.zip
    susankhya-os-<version>.json
```

V1 channel metadata should bind at least:

- channel schema/version and release identity;
- package file name, immutable SHA-256 checksum, size, and creation time;
- core/app/module compatibility requirements and migration warnings;
- readiness summary, release notes, and explicit apply eligibility;
- backup/restore prerequisites and post-apply verification plan.

The local channel may begin with checksum verification. Package signing, key rotation, entitlement, revocation, hosted retrieval, and cohort rollout are prerequisites for later private online updates, not current V1 behavior.

## 14. Required Backup / Restore Contract

Before any controlled local apply is implemented, the contract must require:

1. a verified filesystem/application backup of exactly the target installation, excluding only declared reproducible assets;
2. a verified complete database backup or supported provider recovery point, not only selected module-table export;
3. an immutable record binding installed versions, app/module state, schema versions, configuration-preservation rules, release identity, and checksums;
4. explicit operator confirmation after compatibility preview and before mutation;
5. bounded apply sequencing with observable progress and failure capture;
6. post-apply verification of database, app/module registry, migrations, routes, writable assets, and critical workflows; and
7. a rehearsed restore that returns code, database, configuration, and generated assets to one compatible state.

Release/restore history tables and Studio snapshots may support evidence, but none replaces this contract.

## 15. Work Order / Next Steps

1. Define a documented V1 release manifest and filesystem local-channel schema, including checksum semantics.
2. Inventory the actual release-generation entrypoint and produce one controlled release-package preview without applying it.
3. Define and test a database backup/recovery provider contract plus configuration preservation rules.
4. Define a compatibility preview that binds package, installed state, migration plan, and recovery prerequisites.
5. Define controlled local apply and post-apply verification only after backup/restore rehearsal passes.
6. Define Windows installer bootstrap acceptance criteria only after local apply is proven.
7. Design private `update.susankhya.com` distribution, signing, entitlement, and audit behavior only after local-channel proof.

## 16. Explicit Non-Goals For This Slice

This audit does not authorize:

- implementation of `storage/update-channels/local`, `channel.json`, or any local apply path;
- database backup, database restore, rollback, installer, or Windows packaging implementation;
- `update.susankhya.com`, online updates, entitlement, signing, or cloud-data synchronization;
- moving or renaming legacy Suite/Bundle/Plugin services or metadata;
- changes to app/module lifecycle, migrations, packages, storage contents, Composer dependencies, or generated assets;
- Hospitality implementation or shared-app extraction.

## Evidence Sources

- `scripts/system/check_deployment_readiness.sh`
- `scripts/system/check_deployment_readiness_portable.php`
- `scripts/system/deployment-readiness-orchestration-contract.md`
- `scripts/system/deployment-readiness-portability.md`
- `scripts/assets/README.md`
- `app/Services/DeploymentReadinessService.php`
- `app/Services/ReleasePackagingService.php`
- `app/Services/ReleaseHistoryService.php`
- `app/Services/UpgradeAssistantService.php`
- `app/Services/VersionCatalogService.php`
- `app/Services/RestoreHistoryService.php`
- `app/Services/SuiteExportService.php`
- `app/Services/SuiteRestoreService.php`
- `app/Services/AppPackageService.php`
- `app/Core/PackageManager.php`
- `app/Services/CoreSetupService.php`
- `app/Services/AppMigrationService.php`
- `README.md`, `storage/README.md`, and `composer.json`
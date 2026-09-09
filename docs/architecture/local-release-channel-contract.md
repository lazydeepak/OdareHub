# Local Release Channel Contract

Status: V1 design contract. No package generator, channel directory, preview client, apply operation, installer, backup/restore implementation, hosted service, or runtime behavior is created by this document.

Date: 2026-08-19

## 1. Purpose

Define the release-package identity, filesystem-backed local channel, compatibility, checksum, preview, backup, and apply gates required before OdareHub ERP can implement controlled local delivery.

## 2. Scope

V1 distributes one OdareHub modular-monolith runtime package through a local filesystem channel. It does not create separate deployable suites, shared-app packages, plugin-store packages, or Hospitality artifacts.

V1 begins with the conceptual `app` lane only. `runtime` and `support` lanes are reserved for future compatible expansion and must not be implemented by assuming their behavior now.

## 3. Terms

| Term | Meaning |
| --- | --- |
| Release Package | Immutable ZIP artifact containing one approved release payload. |
| Release Metadata | Sidecar JSON describing one package and its compatibility, checksum, readiness, and release notes. |
| Channel | Filesystem directory exposing a channel manifest and release artifacts. |
| Preview | Read-only package and installed-state compatibility evaluation. |
| Apply | Future explicit mutation that replaces approved runtime files and executes approved migrations. Not implemented in this slice. |
| Recovery Point | Verified code/configuration/database state from which one compatible installation can be restored. |
| Lane | Conceptual artifact class: `app`, `runtime`, or `support`. |

`suite` and `bundle` remain compatibility-only names in existing services. They are not channel lanes or first-class release owners.

## 4. Release Package Identity

Each release identity must contain:

| Field | V1 rule |
| --- | --- |
| `product_id` | Literal `odarehub`, consistent with the existing local-channel planning baseline. |
| `product_name` | Human display name, initially `OdareHub ERP`. |
| `release_version` | Immutable product release version. |
| `build_id` | Immutable build identifier; a source commit identifier is preferred when available. |
| `channel` | Local channel name, initially `local`. |
| `lane` | `app` in the first implementation. |
| `created_at` | UTC ISO-8601 timestamp. |
| `schema_version` | Release metadata schema version. |

The tuple `$product_id + $release_version + $build_id + $lane` must identify exactly one payload. Rebuilding a different payload with the same tuple is prohibited.

## 5. Release Package Contents

The V1 `app` lane is one runtime package, not a per-app business package. Its target payload includes only release-owned runtime material:

```text
app/
apps/
platform/
plugins/
public/
resources/
vendor/
composer.json
composer.lock
bin/
release_metadata.json
```

`vendor/` is included in V1. The repository README documents deployments that cannot run Composer and synchronize `vendor/`; including the lockfile-resolved vendor tree avoids making Composer a client installer prerequisite. Composer remains a release-build concern and is not run by V1 preview or apply.

The package may include derived public assets that the release build has verified. Release metadata must state whether asset regeneration is required after apply.

## 6. Files And Paths To Exclude

Release construction must exclude local, credential-bearing, customer-owned, or non-runtime material, including:

```text
.git/
tests/
phpunit.xml*
node_modules/
.DS_Store
storage/
packages/
storage/db_config.php
storage/update-channels/
local cache/, tmp/, temp/ directories
local backups, restore points, exports, logs, uploads, and customer files
```

Tests may be retained only when a future release-build contract proves a runtime requirement. Source documentation, development tools, and other non-runtime files are excluded unless a later manifest expressly includes them.

Exclusion is not deletion: customer files remain on the target installation and are governed by preservation rules.

## 7. Files And Paths To Preserve On Install/Update

Apply must preserve target-owned state by default. A release package must never overwrite or delete these paths merely because they are absent from its payload:

| Preserve class | Minimum paths / examples |
| --- | --- |
| Database configuration | `storage/db_config.php`; environment-provided `ERP_DB_*` values remain external to the package. |
| Customer files and uploads | Existing upload/customer-file paths, including known `storage/app_files/`, `storage/branding/`, and any future declared customer-data roots. |
| Operational evidence | Logs, imports, exports, release/restore previews, audit history, snapshots, and backups under `storage/`. |
| Generated local customer state | Approved runtime data and Studio/app-generated state under `storage/`, subject to separate ownership/retention contracts. |
| Package/customer lifecycle state | Existing `packages/` content and installed/customer package state. |
| Recovery artifacts | Backups, restore points, and the local update-channel storage itself. |

Future package manifests must declare any additional writable/customer-data root. Undeclared deletion is forbidden.

## 8. Release Metadata Schema

Each package has a sidecar `<package>.json` metadata document. The ZIP may contain `release_metadata.json`, but the ZIP's own SHA-256 belongs in the sidecar and channel record to avoid self-referential hashing.

```json
{
  "schema_version": "odarehub.release.v1",
  "product_id": "odarehub",
  "product_name": "OdareHub ERP",
  "release_version": "1.0.0",
  "build_id": "git:<immutable-commit-or-build-id>",
  "channel": "local",
  "lane": "app",
  "created_at": "2026-08-19T00:00:00Z",
  "package": {
    "filename": "odarehub-1.0.0.zip",
    "size_bytes": 0,
    "sha256": "<64-lowercase-hex>"
  },
  "compatibility": {
    "minimum_product_version": "0.0.0",
    "maximum_product_version": null,
    "core": {"minimum": "0.0.0"},
    "apps": [],
    "modules": [],
    "schema": {"minimum": null, "target": null},
    "php": {"minimum": "8.1.0"},
    "php_extensions": ["mysqli", "json", "zip", "gd"]
  },
  "readiness": {"status": "passed", "evidence": []},
  "migration": {"warnings": [], "requires_backup": true},
  "assets": {"regeneration_required": false},
  "release_notes": "",
  "apply_eligibility": {"requires_preview": true, "requires_operator_approval": true}
}
```

The metadata schema is a target contract. Existing `ReleasePackagingService` metadata is not yet this schema.

## 9. Local Channel Directory Shape

Do not create this path in this slice. The future shape is:

```text
storage/update-channels/local/
  channel.json
  releases/
    odarehub-<release-version>-<build-id>.zip
    odarehub-<release-version>-<build-id>.json
```

The local channel is deployment state, not source code and not a package source-of-truth. The source repository and release build record remain authoritative.

## 10. `channel.json` Schema

`channel.json` is an index only; it cannot duplicate full package content or grant apply authority.

```json
{
  "schema_version": "odarehub.local-channel.v1",
  "channel": "local",
  "product_id": "odarehub",
  "generated_at": "2026-08-19T00:00:00Z",
  "lanes": {
    "app": {
      "current": {
        "release_version": "1.0.0",
        "build_id": "git:<immutable-commit-or-build-id>",
        "metadata": "releases/odarehub-1.0.0-<build-id>.json",
        "package": "releases/odarehub-1.0.0-<build-id>.zip",
        "sha256": "<64-lowercase-hex>",
        "size_bytes": 0
      }
    },
    "runtime": {"current": null},
    "support": {"current": null}
  }
}
```

V1 reads only the `app` lane when preview/apply is later authorized. `runtime` and `support` entries must remain null until their own contracts exist.

## 11. Compatibility Rules

Preview must reject a package when any required condition fails:

- product ID, metadata schema, lane, package path, JSON shape, or checksum format is invalid;
- installed product/core/app/module version is outside the declared range;
- schema baseline, pending migration state, required app/module state, PHP version, or required PHP extension is incompatible;
- the required vendor tree or lockfile identity is absent or does not match the package record; or
- the channel references a release other than the validated metadata/package pair.

Compatibility may warn, but must not auto-resolve, legacy Suite/Bundle naming. Existing lifecycle services may supply evidence until app/module vocabulary is fully normalized.

## 12. Checksum Rules

1. SHA-256 is calculated over the final ZIP byte stream after release construction.
2. The sidecar metadata and `channel.json` must contain the same lowercase 64-character checksum and exact byte size.
3. Preview recomputes the SHA-256 from disk and rejects any mismatch before reading package content for eligibility.
4. A checksum proves integrity only; it is not a release signature, entitlement, or authorization decision.
5. V1 does not claim signing. Private online updates later require a separate signed metadata, key-rotation, revocation, and verification contract.

## 13. Readiness And Preview Rules

Readiness and preview are separate, read-only gates:

| Gate | Required evidence | Mutation |
| --- | --- | --- |
| Release readiness | Clean authoritative deployment readiness result, resolved architecture failures, and release-build evidence. | Only the existing explicitly governed derived-asset publishing behavior. |
| Channel preview | Channel JSON parse, metadata parse, checksum/size verification, compatibility evaluation, installed-state inspection, and recovery-precondition inspection. | None. |

A preview record may be stored as evidence, but it cannot create a channel, copy a ZIP, alter application files, run a migration, write configuration, or invoke existing upgrade apply operations.

## 14. Backup/Restore Preconditions

Before a future apply can become eligible, it must have verified evidence of:

- complete database backup or supported database recovery point;
- application/filesystem backup excluding only explicitly reproducible assets;
- preservation inventory for configuration, customer files, customer state, and existing package/channel/backup artifacts;
- restore procedure matching the target package and schema state; and
- a current successful restore rehearsal on a representative controlled installation.

Existing `SuiteExportService`, `SuiteRestoreService`, histories, and schema snapshots do not satisfy these product-update preconditions by themselves.

## 15. Apply Eligibility Rules

Future apply must fail closed unless all are true:

1. a fresh release readiness result is clean;
2. channel/metadata/package identity, size, and SHA-256 are valid;
3. compatibility preview is successful and bound to the exact package checksum;
4. backup/recovery evidence is current and verified;
5. no pending failed update, failed restore, or unresolved recovery state exists;
6. an authorized operator gives explicit approval after preview; and
7. a post-apply verification and recovery plan is attached to the apply record.

Apply is deliberately not implemented by this contract.

## 16. Post-Apply Verification Requirements

The later apply design must verify:

- package identity/checksum recorded against the actual installed payload;
- database connectivity, migration status, schema snapshots, and app/module registry health;
- app discovery, route/runtime artifact refresh, and required module lifecycle state;
- writable storage and derived asset availability;
- selected critical authenticated workflow/route smoke checks; and
- release, apply, and verification evidence persisted for support and recovery.

A process exit code, package extraction, or successful file copy alone is insufficient.

## 17. Failure And Recovery Expectations

Future apply must stop on the first unsafe condition, preserve the failed-state evidence, and present one of: no mutation occurred, recovery in progress, or recovery required. It must never present an ambiguous success state.

Recovery must restore code, database, configuration, preserved customer state, and generated delivery state to a compatible point. Existing `UpgradeAssistantService` repair and `SuiteRestoreService` conflict actions remain legacy/local lifecycle mechanisms; they are not the recovery authority for this contract until separately proven.

## 18. Relationship To Existing Services

| Existing service | Contract role | Not its V1 role |
| --- | --- | --- |
| `DeploymentReadinessService` | Local readiness evidence source. | Channel/package validator or apply authority. |
| `ReleasePackagingService` | Existing archive producer to evolve behind the release metadata contract. | Installer package generator or channel publisher today. |
| `ReleaseHistoryService` | Release preview/generation history evidence. | Channel index or authorization source. |
| `PackageManager` / `AppPackageService` | Legacy/app package parsing and lifecycle transport evidence. | Safe product-update apply implementation. |
| `UpgradeAssistantService` | Existing local lifecycle preview/apply evidence. | Channel-aware release apply or rollback guarantee. |
| `VersionCatalogService` | Current local version evidence. | Online release catalog. |

The contract is additive. It neither renames these services nor changes legacy Suite/Bundle/package terminology.

## 19. Future Private Online Update Mapping

After local-channel proof, `update.susankhya.com` may provide the same logical channel/index and sidecar metadata through authenticated HTTPS distribution. It must add:

- signed metadata/package verification with public-key rotation and revocation;
- customer/license/entitlement authorization, audit, and release-access policy;
- transport security, availability, retention, and rollout/cohort policy; and
- no business-data synchronization.

`cloud.susankhya.com` remains customer/license/control portal first. It is not a data-sync or ERP-runtime migration in this roadmap.

## 20. Non-Goals

This contract does not authorize:

- creating `storage/update-channels/local`, `channel.json`, sidecar metadata, or release ZIPs;
- modifying `ReleasePackagingService`, package/install services, upgrade services, or any runtime path;
- database backup/restore, rollback, installer, Windows bootstrap, or controlled local apply implementation;
- package signing, private hosted update delivery, entitlement, background updates, or cloud synchronization;
- Suite runtime, Hospitality work, shared-app extraction, or large app/module refactors.

## Related Documents

- `docs/architecture/deployment-update-foundation-audit.md`
- `docs/architecture/local-deployment-update-channel-plan.md`
- `docs/architecture/odarehub-productization-roadmap.md`
- `docs/architecture/current-ownership-inventory.md`
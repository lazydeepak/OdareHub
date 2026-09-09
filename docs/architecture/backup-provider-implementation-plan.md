# Backup Provider Implementation Plan

Status: V1 implementation plan. No provider code, dump, archive, recovery point, storage directory, CLI, route, installer, update apply, or hosted update behavior is created by this document.

Date: 2026-08-19

## 1. Purpose

Plan the smallest recovery-point provider that satisfies `backup-restore-recovery-contract.md` before local update apply can be implemented.

## 2. Scope

V1 supports local Windows/client and comparable local PHP installs running MySQL or MariaDB. It creates a complete database dump and a filesystem archive for one OdareHub installation, verifies both, records non-secret metadata, and supports a separate restore rehearsal.

V1 does not require cloud snapshots, Studio, a hosted update service, a Windows installer, or a multi-provider abstraction.

## 3. Current Constraints

| Constraint | Evidence | Plan response |
| --- | --- | --- |
| Database runtime | `App\\Core\\DB` uses `mysqli`; README names MySQL/MariaDB. | Use a local MySQL/MariaDB dump provider. |
| DB configuration | `storage/db_config.php` or `ERP_DB_*`; credentials must not be committed. | Read config through existing resolution only; never put secrets in metadata/logs/arguments. |
| Existing restore | Suite export/restore handles selected tables/packages. | Keep separate; do not reuse as product database recovery. |
| Recovery gate | Update apply is blocked pending verified database + filesystem recovery. | Provider must produce a verified recovery point and rehearsal evidence. |
| System tools | Registered tools are governed maintenance workers, not bypasses. | First execution entrypoint is a controlled CLI with explicit authorization/audit requirements. |
| Core lock | `/app` remains locked absent explicit approval. | Provider is Platform-owned; do not alter Core DB behavior in V1. |

## 4. Recommended V1 Provider Choice

Use one local **MySQL/MariaDB dump provider** plus one **filesystem ZIP archive provider**.

| Provider | V1 choice | Reason |
| --- | --- | --- |
| Database | `mysqldump`-compatible executable, with MariaDB-compatible behavior validated in tests | Matches current `mysqli` runtime and local Windows/client requirement. |
| Filesystem | ZIP archive created through PHP `ZipArchive` or equivalent local implementation | Compatible with the existing PHP/ZIP requirement and Windows distribution. |
| Provider abstraction | Minimal internal contract only | Avoid multiple cloud/provider implementations before a second real provider exists. |

V1 must preflight the dump executable, server/version compatibility, permissions, free space, and archive writability before it creates a recovery point. If the provider is unavailable, update apply remains blocked; V1 must not fall back to selected-table export.

## 5. Database Backup Strategy

The provider must create one complete, consistent dump for the configured application database.

Required V1 behavior:

- resolve database host, port, name, user, charset, and engine identity through existing configuration resolution;
- invoke the approved `mysqldump`/MariaDB-compatible binary without exposing password text in process listings, metadata, logs, or errors;
- use a temporary restricted-permission defaults/config file or an equivalent secret-safe mechanism, then remove it on completion/failure;
- request a consistent dump strategy appropriate to the engine, including schema, data, triggers, routines, and events where supported;
- compress only after successful dump creation, then calculate archive size and SHA-256;
- record server/provider version, non-secret database identity, command capability/version, dump format, and checksum; and
- verify dump readability and a controlled restore rehearsal before accepting the provider for update eligibility.

The initial implementation must document behavior for non-transactional tables and unsupported server features as a blocking or explicit warning condition, never silently claim a consistent backup.

## 6. Filesystem Backup Strategy

The provider must create one ZIP archive representing the recovery scope required for a compatible installation.

Include:

- release-owned runtime payload identity: `app/`, `apps/`, `platform/`, `plugins/`, `resources/`, `public/`, `vendor/`, `composer.json`, `composer.lock`, and `bin/` as applicable;
- `storage/db_config.php` as protected payload or a documented secure external configuration recovery method;
- customer files/uploads, customer-generated documents, required app/generated state, and package state;
- recovery and release evidence required by the preservation inventory.

Exclude only declared reproducible caches/temp files and derived assets with deterministic regeneration proof. The archive manifest must list included roots, exclusions, per-root status, archive checksum, byte size, and the regeneration steps for excluded reproducible assets.

## 7. Recovery Point Metadata

Proposed metadata schema: `odarehub.recovery-point.v1`.

```json
{
  "schema_version": "odarehub.recovery-point.v1",
  "recovery_point_id": "rp-<uuid-or-random-id>",
  "created_at": "2026-08-19T00:00:00Z",
  "created_by": "operator-identity",
  "reason": "update_apply",
  "installation": {
    "product_id": "odarehub",
    "release_version": "1.0.0",
    "build_id": "git:<id>",
    "app_manifest_checksums": [],
    "module_manifest_checksums": [],
    "schema_state": [],
    "migration_state": []
  },
  "update_context": {
    "channel": "local",
    "package_sha256": "<optional-update-package-checksum>"
  },
  "database": {
    "provider": "mysqldump",
    "provider_version": "<non-secret-version>",
    "identity": {"host_label": "local", "port": 3306, "database": "erp"},
    "payload": {"path": "database.sql.gz", "sha256": "<hash>", "size_bytes": 0}
  },
  "filesystem": {
    "provider": "ziparchive",
    "payload": {"path": "filesystem.zip", "sha256": "<hash>", "size_bytes": 0},
    "preservation_inventory_sha256": "<hash>"
  },
  "verification": {"status": "verified", "rehearsal": "required"}
}
```

Metadata must contain no passwords, tokens, raw configuration values, private keys, or customer-data contents.

## 8. Storage Layout

Do not create this layout in the planning slice. The future local layout is:

```text
storage/recovery-points/
  <recovery-point-id>/
    recovery-point.json
    database.sql.gz
    filesystem.zip
    checksums.json
    verification.json
    rehearsal.json
```

The directory is Platform-owned recovery evidence. It is excluded from release packages and must be protected as sensitive customer operational data.

## 9. Service Boundaries

The planned provider belongs under a new Platform recovery capability, not Core, Studio, Shell, Manufacturing, or SBAIO.

| Planned responsibility | Proposed owner/artifact |
| --- | --- |
| Recovery point orchestration and state machine | `platform/Recovery/RecoveryPointService.php` |
| MySQL/MariaDB dump/restore adapter | `platform/Recovery/MySqlDumpProvider.php` |
| Filesystem archive adapter | `platform/Recovery/FilesystemArchiveProvider.php` |
| Metadata/checksum verification | `platform/Recovery/RecoveryPointVerifier.php` |
| Restore rehearsal coordinator | `platform/Recovery/RestoreRehearsalService.php` |
| Non-secret persistence/audit adapter | Platform recovery history adapter, designed after metadata schema is validated |

These names are planning targets, not files to create now. The provider may use narrow array/value-object result packets with `state`, `diagnostics`, `payloads`, and `recovery_point_id`; it must not expose raw shell command strings or secrets to callers.

## 10. CLI / Admin Entry Points

V1 execution should start with one controlled CLI entrypoint, proposed as:

```text
scripts/system/recovery_point.php
```

Planned modes:

- `preflight` - read-only provider/configuration/capability check;
- `create` - explicit authorized recovery-point creation;
- `verify` - checksum and payload readability verification;
- `rehearse` - controlled restore rehearsal against an isolated target;
- `status` - read-only recovery-point state inspection.

When implemented, the entrypoint must be registered as a governed System Tool and must not become a direct database editor. V1 has no web/admin mutation route. A later Platform admin page may expose read-only status/history and delegate only through the same service/authorization path.

## 11. Verification Strategy

Verification is separate from creation:

1. validate recovery-point metadata schema and non-secret fields;
2. confirm both payload files exist, are readable, and match SHA-256 and byte size;
3. validate dump archive structure/readability without mutating the production database;
4. validate ZIP structure, path safety, expected included roots, and preservation inventory;
5. compare installed product/app/module/migration fingerprints with recorded metadata; and
6. mark the point `verified` only after all required checks pass.

Creation failure must leave `failed_without_mutation` if target state was not changed; partial recovery-point files must be clearly marked unusable and excluded from update eligibility.

## 12. Restore Rehearsal Strategy

Restore rehearsal is mandatory before update apply. It must run against an isolated database and filesystem target, never the active customer installation.

The rehearsal must:

- restore the filesystem archive and database dump to the isolated target;
- use required preserved configuration through a secure test configuration path;
- run bootstrap, registry, schema/migration, writable-path, and readiness checks;
- run a small authenticated smoke suite appropriate to the installation; and
- record a non-secret result bound to the recovery-point ID, provider versions, verification timestamp, and final state.

Only a successful rehearsal for the same provider/format family makes a recovery point eligible for a future update apply.

## 13. Security / Secret Handling

- Never pass database passwords as visible command-line arguments.
- Create temporary provider credential material with restrictive permissions and remove it in success/failure cleanup.
- Redact credentials, DSNs, environment values, access tokens, filesystem secrets, and customer-data samples from all logs, metadata, diagnostics, and status output.
- Restrict recovery-point directories and CLI execution to authorized local operators/service accounts.
- Treat backup payloads as sensitive; encryption-at-rest and transport/key-management policy is a provider deployment decision to define before production rollout.

## 14. Retention And Cleanup

Do not implement cleanup until retention policy is approved. The future policy must define:

- minimum recovery-point count/age per supported installation;
- longer retention for recovery points referenced by release/apply/restore records;
- safe expiration of incomplete or failed temporary payloads after audit capture;
- disk-space thresholds and preflight blocking behavior; and
- secure deletion and customer/legal retention expectations.

Cleanup must never remove the only verified recovery point eligible for a pending update or an unresolved recovery state.

## 15. Test Plan

| Test layer | Required proof |
| --- | --- |
| Unit | Metadata validation, path containment, checksum comparison, redaction, state transitions, and provider command construction without real secrets. |
| Provider integration | Disposable MySQL/MariaDB database dump and restore; schema/data/triggers/routines/events expectations. |
| Filesystem integration | ZIP include/exclude/preservation rules, path traversal rejection, and archive checksum verification. |
| Failure paths | Missing dump executable, insufficient permissions/disk, corrupt dump/archive, checksum mismatch, missing config, incompatible migration state, and interrupted create/restore. |
| Rehearsal | Isolated restore reaches bootstrap, registry, migration, writable-path, readiness, and smoke-check success. |
| Regression | Existing Suite export/restore and app lifecycle behavior remain unchanged. |

No test may create or restore a recovery point against a real customer installation.

## 16. Implementation Phases

1. Add read-only provider preflight and metadata/value-object validation tests.
2. Add the MySQL/MariaDB dump adapter with disposable-database integration tests.
3. Add filesystem archive creation/verification with fixture-based tests.
4. Add atomic recovery-point assembly, non-secret metadata, checksums, and failed-state handling.
5. Add isolated restore rehearsal coordinator and acceptance tests.
6. Register the governed CLI with documentation, authorization, retention safeguards, and dry-run/status modes.
7. Only after all prior phases pass, design the read-only local package/channel compatibility preview.
8. Keep update apply, installer upgrade, and private online updates blocked until rehearsal evidence is accepted.

## 17. Risks And Open Questions

| Risk / question | Required resolution before production use |
| --- | --- |
| `mysqldump` unavailable or version-incompatible | Preflight contract and supported client-install prerequisites. |
| Managed/local DB permissions insufficient for routines/events | Provider capability report; block update apply when required coverage is unavailable. |
| Non-transactional tables | Explicit consistency policy and blocking/warning behavior. |
| Windows process/permission behavior | Validate executable discovery, temporary credential permissions, archive permissions, and cleanup on supported Windows installs. |
| Storage capacity | Preflight free-space policy for dump, archive, rehearsal, and retained points. |
| Config source is environment-only | Define secret-safe preservation identity and rehearsal injection. |
| Restore target isolation | Define disposable database/filesystem naming and cleanup ownership before rehearsal code. |
| Later provider abstraction | Add only when a second provider has a real supported deployment need. |

## 18. Non-Goals

This plan does not authorize:

- writing any PHP, shell, provider, CLI, route, schema, or UI code;
- creating `storage/recovery-points`, dumps, ZIPs, metadata, checksums, restore points, or rehearsal targets;
- update apply, installer, hosted update service, signing, entitlement, or cloud synchronization;
- changing existing Suite export/restore, migration, DB configuration, or retention behavior;
- Studio dependency, Hospitality, Suite runtime, shared-app extraction, or large refactors.

## Related Documents

- `docs/architecture/backup-restore-recovery-contract.md`
- `docs/architecture/local-release-channel-contract.md`
- `docs/architecture/deployment-update-foundation-audit.md`
- `docs/architecture/local-deployment-update-channel-plan.md`
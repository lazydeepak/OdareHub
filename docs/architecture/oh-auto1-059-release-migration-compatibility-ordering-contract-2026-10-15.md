# OH-AUTO1-059 — Release Migration Compatibility and Ordering V1

Date: 2026-10-15
Status: **Proposed for architecture and release-authority approval**
Scope: **REL-003 only; no migration execution or schema mutation**

## 1. Decision boundary

This document defines a V1 release-preflight contract. It does not create a
second migration runner, change the database schema, execute SQL, alter an
installed ledger, or authorize downgrade/rollback execution.

The existing migration executor remains the execution authority. The proposed
release contract supplies verified inventory, ownership, dependency, checksum,
and target-schema evidence to the existing compatibility boundary before any
future apply operation.

## 2. Existing authorities and observed behavior

| Concern | Existing authority | Current behavior | Gap for REL-003 |
|---|---|---|---|
| Migration execution | `app/Services/AppMigrationService.php` | Reads each owner's `migrations_path`, sorts that owner's `*.sql` files lexicographically, executes SQL, and records `core_app_migrations`. | No cross-owner dependency graph or release-wide order. DDL failure is not globally transactional. |
| Installed migration evidence | `platform/Updates/InstalledSystemEvidenceProvider.php` | Reads the migration ledger, validates owner-relative names, duplicate identities, checksums, per-owner order, pending/failed state, and application ownership. | No authoritative release migration inventory or cross-owner ordering proof. |
| Release preflight | `platform/Updates/MigrationCompatibilityPreflightService.php` | Rejects pending/failed/malformed evidence and requires identity, ownership, checksum, ordering, and target-schema evidence flags. | Evidence flags are caller-supplied and do not yet describe a canonical target inventory or dependency graph. |
| Whole installed compatibility | `platform/Updates/InstalledSystemCompatibilityPreflightService.php` | Validates product/component/schema/version requirements and delegates migration preflight. | Downgrade and schema checks exist, but target schema identity and migration-set compatibility remain incomplete. |
| Migration ownership | App/module manifests and `migrations_path` | Owners declare paths; application registry supplies installed owner records. | Plugin/module dependency semantics are not one release-wide migration contract. |
| Recovery | Platform recovery contracts | Recovery point and restore rehearsal are prerequisites for apply eligibility. | No migration rollback is inferred from SQL transaction behavior. |

The evidence confirms one execution authority and one preflight family already
exist. V1 should extend their evidence contract rather than introduce another
runner or ledger.

## 3. V1 target-schema and migration identity contract

### 3.1 Release declaration

Each release metadata document must declare one immutable migration inventory:

```json
{
  "schema_version": "susankhya.release.v1",
  "migration_contract": "susankhya.release-migrations.v1",
  "target_schema_id": "schema:<opaque-release-state>",
  "migration_inventory": [
    {
      "owner_type": "app",
      "owner_key": "platform",
      "migration_id": "platform:20260506_000001_create_platform_widget_blueprints",
      "source_name": "20260506_000001_create_platform_widget_blueprints.sql",
      "version": "1.0.0",
      "checksum": "<sha256 of exact SQL bytes>",
      "requires": [],
      "reversible": false
    }
  ]
}
```

The inventory is evidence and authorization input, not an execution command.
The release builder must derive it from owner-declared migration files and
reject duplicate or unsafe identities before metadata is accepted.

### 3.2 Identity rules

The canonical migration identity is:

```text
<owner_type>:<owner_key>:<source_name>
```

Rules:

- `owner_type` is one of the approved owner categories (`app`, `module`, or
  another explicitly governed extension type); arbitrary plugin names are not
  implicit owners.
- `owner_key` must resolve to the installed or release-declared owner.
- `source_name` is a basename under that owner's declared migration directory;
  traversal, separators, empty names, and duplicate identities are invalid.
- `migration_id`, owner, source name, version, checksum, and dependency list
  are immutable within a release statement.
- The checksum is SHA-256 over the exact migration SQL bytes. A ledger checksum
  mismatch is a hard compatibility failure, not a warning.
- Filename order is a local owner evidence check only; it is not the complete
  cross-application release order.

### 3.3 Target-schema identity

`target_schema_id` is a release-owned opaque identity for the complete supported
schema state after the inventory is applied. It is not the product version,
Git HEAD, latest migration filename, timestamp, or a generated runtime value.

The installed evidence must provide exactly one authoritative schema identity
that matches the release's declared source/target relationship. A legacy
schema version string may remain compatibility evidence, but cannot substitute
for the target identity once this contract is active.

The release must declare:

- supported source schema identities/ranges;
- exactly one target schema identity;
- the migration inventory needed for each supported source state; and
- whether a release has no schema change (`target_schema_id` equal to the
  verified installed identity).

Missing, duplicate, unknown, or contradictory schema identity is blocked.

## 4. Installed ledger compatibility

The existing `core_app_migrations` ledger remains the installed evidence source
for this V1. Preflight compares it with the release inventory without writing
to it.

Required comparisons:

1. Every installed applied migration belongs to a known owner and valid
   identity format.
2. Every installed migration checksum equals the release inventory checksum
   when that migration is retained by the target release.
3. Every required migration for the installed component set is either applied
   with a matching checksum or appears in the release plan as pending work.
4. A migration present in installed evidence but absent from the supported
   release lineage is `unknown_installed_migration` and blocks eligibility.
5. Duplicate identity, modified SQL, failed status, malformed owner/name,
   missing execution order, or unexplained gaps are blocking contradictions.
6. The ledger's recorded order must match the release graph's deterministic
   order for already-applied migrations; per-owner lexical order alone is not
   enough.

The current ledger table has a uniqueness constraint per owner/name and stores
one status row. That is useful evidence but insufficient as append-only
attempt history. A future persistence decision may extend it or add a governed
attempt ledger; this proposal does not choose a schema change.

## 5. Disabled applications and modules

Disabled does not mean unknown or erased. An installed disabled owner remains
part of identity and migration evidence, but its disabled status must never
cause automatic activation.

V1 rules:

- A disabled installed owner with no required target migration may remain
  disabled and participates in checksum/identity checks.
- A disabled installed owner with required pending migrations is previewed as
  `disabled_owner_requires_migration`. Apply eligibility is blocked unless a
  separately approved maintenance policy proves those migrations can execute
  safely without activation.
- An uninstalled owner is not silently treated as an installed schema owner;
  release metadata must explicitly classify whether its migrations are absent,
  optional, or required. Ambiguity blocks.
- A dependency on a disabled or uninstalled owner is unresolved and blocks the
  dependent migration; no lifecycle operation is inferred by preflight.

## 6. Cross-application ordering

### 6.1 Graph authority

The release migration inventory is the graph input. Each migration declares
explicit `requires` identities. Existing app/module dependency declarations are
supporting owner-level evidence, not a replacement for migration edges.

Required validation before execution:

- every dependency resolves to an inventory identity or an already verified
  installed prerequisite;
- an owner-level dependency must resolve to an installed/declared owner;
- missing owners/dependencies fail closed;
- duplicate migration identities fail closed;
- cycles fail closed with the cycle identities reported;
- dependencies cannot point to a later incompatible target state; and
- a disabled owner cannot satisfy an active migration dependency merely by
  existing in the registry.

### 6.2 Deterministic order

Use a stable topological sort:

1. explicit platform/Core prerequisite phase;
2. graph-ready migrations with all `requires` satisfied;
3. lexical tie-break by `owner_type:owner_key:migration_id`; and
4. post-order validation that every declared dependency precedes its consumer.

Independent migrations therefore have reproducible order, while explicit
dependencies—not alphabetical coincidence—govern cross-owner sequencing.

The order is a preflight artifact bound to the release metadata and exact
inventory. It does not itself execute SQL or mutate the ledger.

## 7. Failure, partial application, and recovery

### Preconditions

Before migration execution is ever authorized, the future apply boundary must
have verified release authenticity, package integrity, target-schema
compatibility, migration inventory/order, current installed evidence, and a
fresh verified recovery point.

### MySQL transaction boundary

Migration execution must not be described as globally transactional. MySQL DDL
may implicitly commit, and application SQL can leave durable partial state.
The runner may use transactions where effective, but transaction success is
not recovery proof.

### Failure evidence

The future execution record must preserve:

- release/build/package identity;
- target schema identity and ordered migration plan;
- current migration attempt and owner;
- SQL checksum and start/finish status;
- failure class/message safe for operations; and
- whether activation occurred.

A failed or interrupted migration leaves the installation in
`migration_recovery_required` or `migration_state_ambiguous`, never a normal
success state. A partially applied migration cannot be marked applied merely
because its SQL started or because a table happens to exist.

Recovery authority is the Platform backup/restore contract. No migration
runner may invent SQL down-migrations or silently restore from an unverified
point.

### Downgrade policy

V1 is forward-only. A release with a lower product version, incompatible target
schema, missing reverse plan, or migration inventory that is not explicitly
declared downgrade-safe is rejected as `unsupported_downgrade`.

Rollback means restoring a verified recovery point, not executing guessed
reverse SQL. A future reversible migration contract would require explicit
owner declarations, tested reverse operations, and separate approval.

Installed identity remains the prior successful identity until final activation
and post-migration verification complete. If migrations have changed the schema
but activation did not complete, the identity is contradictory and recovery is
required; it must not be rewritten to hide the partial state.

## 8. Gap matrix

| Area | Already implemented | Incomplete | Proposed V1 / approval needed |
|---|---|---|---|
| Owner migration discovery | Manifest `migrations_path`, owner directories | No release-wide immutable inventory | Signed release inventory and owner identity rules |
| Ledger evidence | `core_app_migrations`, owner/name/checksum/status checks | One-row status is not attempt history | Append-only attempt/failure evidence decision |
| Per-owner ordering | `AppMigrationService` lexical file order | Cross-owner ordering absent | Explicit dependency graph and stable topological order |
| Schema evidence | `core_schema_snapshots`, schema version checks | No authoritative target-schema identity | Installation-owned schema identity contract |
| Compatibility | Existing preflight rejects pending/failed/malformed evidence | Caller-supplied flags and weak target lineage | Compare installed ledger to release inventory |
| Disabled owners | Registry status is observed | No migration eligibility rule | Block required migrations unless approved maintenance policy |
| Failure handling | Failed ledger rows and install verification evidence | DDL partial state and retry history are ambiguous | Recovery-required state and attempt evidence |
| Downgrade | Product-version downgrade is rejected | No migration reverse contract | Forward-only V1; recovery-point rollback only |
| Recovery | Platform recovery/rehearsal contracts | Not coupled to migration execution | Recovery point prerequisite before apply |

## 9. Decision and evidence states

The future preflight should distinguish:

- `compatible_upgrade` — inventory, source schema, ledger, checksums, and graph
  all pass;
- `no_schema_change` — exact installed target identity is already present;
- `missing_migration` — required inventory item is not applied and is planned;
- `unknown_installed_migration` — installed item has no supported lineage;
- `migration_checksum_mismatch` — bytes differ from authoritative inventory;
- `migration_dependency_missing` / `migration_dependency_cycle`;
- `duplicate_migration_identity`;
- `schema_identity_missing` / `schema_identity_conflict`;
- `disabled_owner_requires_migration`;
- `migration_recovery_required` / `migration_state_ambiguous`; and
- `unsupported_downgrade`.

Only the first two may be compatible states. A planned missing migration is
not apply authorization; it becomes eligible only after the complete graph,
recovery, release, and operator gates pass.

## 10. Isolated fixture validation

The accompanying pure fixture probe models the proposed comparison and graph
rules without loading application bootstrap, connecting to a database, or
executing SQL. It covers:

- compatible upgrade and deterministic independent ordering;
- missing migration;
- unknown installed migration;
- checksum mismatch;
- dependency cycle;
- duplicate identity;
- unsupported downgrade;
- partially applied/failed evidence;
- disabled owner with required migration; and
- missing dependency.

These fixtures validate contract decisions only. They are not database tests
and do not prove installed-schema behavior.

## 11. Implementation sequence after approval

1. Approve the release migration metadata and target-schema contract.
2. Define the persistence/transition record for migration attempts and schema
   identity without changing execution yet.
3. Extend read-only evidence collection to produce canonical inventory and
   graph diagnostics.
4. Extend `MigrationCompatibilityPreflightService` to consume that evidence;
   keep apply disabled.
5. Add owner-runner integration tests in an authorized disposable database.
6. Separately approve migration execution, recovery, and forward-only apply
   behavior.

No step may introduce a second migration runner or infer schema identity from
release version, Git, timestamps, or table presence.

## Required decisions

Architecture/release authority must approve or reject:

1. The canonical migration identity and release inventory shape.
2. Opaque target-schema identity semantics.
3. Explicit migration dependency edges and stable topological ordering.
4. Disabled-owner eligibility behavior.
5. Append-only failure/attempt evidence and ambiguous-state blocking.
6. Forward-only V1 and recovery-point rollback boundary.
7. The future persistence shape and owner of schema/attempt identity.

Until accepted, REL-003 remains pending and release apply remains blocked.

## Evidence

- `app/Services/AppMigrationService.php`
- `platform/Updates/InstalledSystemEvidenceProvider.php`
- `platform/Updates/MigrationCompatibilityPreflightService.php`
- `platform/Updates/InstalledSystemCompatibilityPreflightService.php`
- `docs/architecture/local-release-channel-contract.md`
- `docs/architecture/backup-restore-recovery-contract.md`
- `docs/architecture/oh-auto1-057-release-authority-installed-identity-design-2026-10-15.md`

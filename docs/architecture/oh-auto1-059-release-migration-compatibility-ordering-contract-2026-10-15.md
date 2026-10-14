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
| Application migration execution | `app/Services/AppMigrationService.php` | Reads each app owner's `migrations_path`, sorts that owner's `*.sql` files lexicographically, executes SQL, and records `core_app_migrations`. | No cross-owner dependency graph or release-wide order. DDL failure is not globally transactional. |
| Installed migration evidence | `platform/Updates/InstalledSystemEvidenceProvider.php` | Reads the migration ledger, validates owner-relative names, duplicate identities, checksums, per-owner order, pending/failed state, and application ownership. | No authoritative release migration inventory or cross-owner ordering proof. |
| Release preflight | `platform/Updates/MigrationCompatibilityPreflightService.php` | Rejects pending/failed/malformed evidence and requires identity, ownership, checksum, ordering, and target-schema evidence flags. | Evidence flags are caller-supplied and do not yet describe a canonical target inventory or dependency graph. |
| Whole installed compatibility | `platform/Updates/InstalledSystemCompatibilityPreflightService.php` | Validates product/component/schema/version requirements and delegates migration preflight. | Downgrade and schema checks exist, but target schema identity and migration-set compatibility remain incomplete. |
| Migration ownership | App manifests and `migrations_path`; separate legacy module/plugin paths also exist | Application registry supplies installed app records; module/plugin runtime paths are not normalized into the application ledger. | Plugin/module dependency semantics require governed adapters before release participation. |
| Recovery | Platform recovery contracts | Recovery point and restore rehearsal are prerequisites for apply eligibility. | No migration rollback is inferred from SQL transaction behavior. |

The evidence confirms one application execution authority and one preflight
family already exist. V1 should extend the application execution contract and
its evidence boundary rather than introduce another runner or ledger. Legacy
module/plugin paths remain outside the application-lane claim until explicitly
adapted.

### 2.1 Existing execution-order boundary

`AppMigrationService::runMigrations()` accepts only one owner key, one version,
and one migrations directory. It discovers every `*.sql` file in that
directory, sorts those files lexically, and processes the whole owner-local
batch. It cannot receive a release-wide ordered list or select one exact file.

Therefore the following valid release graph cannot be executed by the current
interface as one plan:

```text
app:owner-a:001_a.sql
  -> app:owner-b:001_b.sql
  -> app:owner-a:002_a.sql
```

Calling Owner A first processes `001_a.sql` and then reaches `002_a.sql` before
Owner B's prerequisite. Calling Owner B first reaches a missing Owner A
prerequisite. No sequence of whole-owner calls can express
`A001, B001, A002`, because the current interface has no exact-step selection.
This is an integration limitation, not a reason to create a second SQL
executor.

### 2.2 Actual callers and transaction boundary

The current callers are:

- `AppInstallService::installInternal()` — installs one app and invokes the
  owner-local runner inside its surrounding database transaction;
- `AppLifecycleService::repair()` — repairs one installed app and invokes the
  owner-local runner without the same install orchestration;
- `CoreSetupService::runCoreBundleMigrations()` — loops over eligible core
  bundles and invokes the owner-local runner per bundle.

The outer install transaction does not make arbitrary migration DDL globally
transactional: MySQL DDL may implicitly commit. Repair and core-bundle setup
also demonstrate that the service is not universally called through the install
transaction. A release plan must therefore provide its own preflight and
failure-state boundary while retaining this service as the SQL authority.

Application migrations are recorded in `core_app_migrations`. Legacy/plugin or
module-specific migration paths use other runtime conventions and are not
silently reclassified as application-ledger rows by this proposal. They need a
separate governed owner adapter before they can participate in a REL-003
release inventory.

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
      "migration_id": "app:platform:20260506_000001_create_platform_widget_blueprints.sql",
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

The canonical release migration identity is:

```text
<owner_type>:<owner_key>:<source_name>
```

Rules:

- `owner_type` is one of the approved owner categories (`app`, `module`, or a
  separately approved extension type); arbitrary plugin names are not implicit
  owners.
- For an app, `owner_key` is the canonical app key. For a module, the V1
  proposal uses the namespaced key `<app_key>/<module_key>`, for example
  `module:manufacturing/Orders:001_orders.sql`. A module identity is proposed
  metadata until a governed module ledger adapter is approved.
- `owner_key` must resolve to the installed or release-declared owner.
- `source_name` is a basename under that owner's declared migration directory;
  traversal, separators, empty names, and duplicate identities are invalid.
- `migration_id`, owner, source name, version, checksum, and dependency list
  are immutable within a release statement.
- The checksum is SHA-256 over the exact migration SQL bytes. A ledger checksum
  mismatch is a hard compatibility failure, not a warning.
- The legacy application ledger key remains `<app_key>:<basename>`; the release
  identity adds the explicit owner type and therefore must be mapped
  deterministically rather than compared as an unqualified string.
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

The current retry behavior is specifically unsafe for the proposed
fail-closed evidence model: when a matching row has `status='failed'`,
`runMigrations()` deletes that row before retrying. If the retry fails, a new
failed row is written, but the prior attempt evidence is lost. Until an
attempt-history decision is approved, the future release-plan path must refuse
to retry an existing failed identity and return `migration_recovery_required`.
It must not call the deletion branch. Changing that runtime behavior requires a
separate implementation approval and, if append-only history is selected, a
database/schema decision.

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

### 6.3 Minimal integration contract with the existing executor

After approval, the smallest compatible extension is an exact-step entry point
on `AppMigrationService`, conceptually:

```text
executePlannedStep(
  appKey,
  version,
  migrationsDir,
  migrationName,
  expectedChecksum,
  expectedPlanPosition
) -> MigrationStepResult
```

This is an interface contract, not an implementation in this task. The method
would remain the only path that calls `AppSchemaSyncService::runAdditiveSql()`.
It must:

1. accept one authorized owner-relative basename only;
2. resolve that basename beneath the supplied owner migration directory and
   reject traversal, path mismatch, or missing files;
3. hash the exact bytes and compare them with `expectedChecksum` before SQL;
4. read the installed ledger row for the legacy `<app_key>:<basename>` key;
5. skip only an applied row whose checksum matches exactly;
6. refuse an applied checksum mismatch, an existing failed row, or an
   inconsistent plan position with a structured blocking result;
7. execute exactly that one SQL file through the existing schema-sync authority;
8. record applied evidence only after SQL success; and
9. preserve failure evidence and return `migration_recovery_required` on any
   execution exception. It must not delete a failed row to make a retry fit the
   current unique key.

Dependency satisfaction and plan order remain the release coordinator's
responsibility: the coordinator invokes a step only after all `requires`
identities are verified applied or successfully completed earlier in the same
plan. The executor verifies the exact step and ledger consistency; it does not
discover, sort, or silently execute neighboring files.

This contract deliberately does not solve append-only retry history. A future
attempt ledger or explicit failed-row transition must be approved before a
retry operation is introduced. Until then, recovery is an operator/recovery
authority decision, not an implicit migration retry.

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

Under the current unique `(app_key, migration_name)` ledger constraint, a
failed row must be retained as the durable blocking marker for the future
release-plan path. The existing deletion-before-retry branch means the current
runner does not guarantee that evidence preservation. That branch conflicts
with V1 evidence preservation; the future exact-step contract must bypass or
refuse it. Removing or replacing it is a separately authorized runtime/database
change.

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
| Owner migration discovery | Manifest `migrations_path`, owner directories | No release-wide immutable inventory; module/plugin ledgers differ | Signed release inventory and owner identity rules; governed adapters |
| Ledger evidence | `core_app_migrations`, owner/name/checksum/status checks | One-row status is not attempt history | Append-only attempt/failure evidence decision |
| Per-owner ordering | `AppMigrationService` lexical file order | Cross-owner ordering absent; whole-owner API cannot interleave A/B/A | Exact planned-step extension on the existing executor plus graph coordinator |
| Schema evidence | `core_schema_snapshots`, schema version checks | No authoritative target-schema identity | Installation-owned schema identity contract |
| Compatibility | Existing preflight rejects pending/failed/malformed evidence | Caller-supplied flags and weak target lineage | Compare installed ledger to release inventory |
| Disabled owners | Registry status is observed | No migration eligibility rule | Block required migrations unless approved maintenance policy |
| Failure handling | Failed ledger rows and install verification evidence | Runner deletes failed row before retry; DDL partial state and retry history are ambiguous | Refuse retry while failed; preserve evidence; later attempt-history decision |
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
- the interleaving boundary `A001 -> B001 -> A002` versus whole-owner batches;
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
2. Approve the exact-step extension and its failed-row refusal semantics.
3. Decide whether failed attempts require a new append-only persistence record;
   do not use delete-and-retry as evidence preservation.
4. Extend read-only evidence collection to produce canonical inventory and
   graph diagnostics.
5. Extend `MigrationCompatibilityPreflightService` to consume that evidence;
   keep apply disabled.
6. Add owner-runner integration tests in an authorized disposable database.
7. Separately approve migration execution, recovery, and forward-only apply
   behavior.

No step may introduce a second migration runner or infer schema identity from
release version, Git, timestamps, or table presence.

## Required decisions

Architecture/release authority must approve or reject:

1. The canonical migration identity and release inventory shape.
2. Opaque target-schema identity semantics.
3. Explicit migration dependency edges and stable topological ordering.
4. Disabled-owner eligibility behavior.
5. Exact planned-step integration with `AppMigrationService`.
6. Failed-row retention/refusal and append-only failure/attempt evidence.
7. Forward-only V1 and recovery-point rollback boundary.
8. The future persistence shape and owner of schema/attempt identity.

Until accepted, REL-003 remains pending and release apply remains blocked.

## Evidence

- `app/Services/AppMigrationService.php`
- `platform/Updates/InstalledSystemEvidenceProvider.php`
- `platform/Updates/MigrationCompatibilityPreflightService.php`
- `platform/Updates/InstalledSystemCompatibilityPreflightService.php`
- `docs/architecture/local-release-channel-contract.md`
- `docs/architecture/backup-restore-recovery-contract.md`
- `docs/architecture/oh-auto1-057-release-authority-installed-identity-design-2026-10-15.md`

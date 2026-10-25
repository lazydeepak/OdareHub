# OH-AUTO3-ORG-CONTRACT-001 — Persistent Membership Implementation Authorization Package

**Date:** 2026-10-26
**Status:** Persistence source implemented; governed disposable database verification passed; runtime integration remains separately gated
**Owner:** Platform Organization

## 1. Decision boundary

This package defines the first persistent organization-membership slice that
may be reviewed for separate authorization. It does not create SQL, migrations,
repositories, routes, session integration, ACL behavior, Staff bindings, or
database state.

The canonical identifiers are:

- organization: `org_companies.id` (`INT`)
- authenticated account: `users.id` (`INT`)

The existing `OrganizationService::primaryCompany()` value is configuration
fallback only and must not participate in membership resolution. No existing
assignment, role, branch, email, name, employee code, or Staff record is a
membership source.

## 2. Exact proposed file scope

The implementation below is now present. Database execution and runtime
integration remain separately bounded.

### Persistence implementation

1. `apps/Platform/modules/Organization/migrations/006_create_user_organization_memberships.sql`
   - creates the membership table and its indexes/constraints;
   - must be executed only through the approved migration ledger and exact-step
     authority; no installed execution was performed here.
2. `apps/Platform/modules/Organization/Contracts/OrganizationMembershipRepositoryContract.php`
   - declares the narrow read/create/revoke/replace contract below;
   - contains no ACL or Auth implementation.
3. `apps/Platform/modules/Organization/Services/OrganizationMembershipRepository.php`
   - owns parameterized membership SQL and transaction/concurrency behavior;
   - returns normalized evidence to the existing pure resolver;
   - does not grant permissions or resolve Staff.
4. `apps/Platform/Tests/probe_organization_membership_repository.php`
   - disposable-database-only repository and constraint regression probe;
    - refuses execution unless the isolated source-root/database authorizer is
      present; the governed disposable run passed 17/17 assertions.

### Existing files that must remain unchanged in this slice

- `apps/Platform/Services/OrganizationMembershipResolver.php`
- `apps/Platform/Services/OrganizationMembershipContext.php`
- `apps/Platform/modules/Organization/Services/OrganizationService.php`
- Core/Auth/session/ACL files under `app/`
- SBAIO Staff, Leave, and Timecard files
- Shell, routes, deployment, activation, and generated artifacts

The repository may call the resolver only after loading explicit evidence. It
must not move database access into the pure resolver.

## 3. Proposed schema contract

The following is a design specification, not executable SQL.

### Table: `org_user_memberships`

| Column | Proposed type/constraint | Meaning |
|---|---|---|
| `id` | `BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY` | Opaque membership record identity |
| `user_id` | `INT NOT NULL` | Exact `users.id` authority |
| `organization_id` | `INT NOT NULL` | Exact `org_companies.id` authority |
| `status` | `ENUM('active','inactive','revoked') NOT NULL` | Explicit lifecycle state; no implicit state from another table |
| `valid_from` | `DATETIME NULL` | Inclusive lower temporal bound |
| `valid_until` | `DATETIME NULL` | Exclusive upper temporal bound |
| `activated_at` | `DATETIME NULL` | Server-recorded activation time |
| `activated_by_user_id` | `INT NULL` | Server-derived activation actor |
| `revoked_at` | `DATETIME NULL` | Server-recorded revocation time |
| `revoked_by_user_id` | `INT NULL` | Server-derived actor; not membership identity |
| `revocation_reason` | `VARCHAR(255) NULL` | Bounded operational reason |
| `created_at` | `TIMESTAMP NOT NULL` | Creation evidence |
| `created_by_user_id` | `INT NULL` | Server-derived creator/auditor |
| `updated_at` | `TIMESTAMP NOT NULL` | Last lifecycle update |

Required foreign keys:

- `user_id REFERENCES users(id)` with `ON DELETE RESTRICT ON UPDATE CASCADE`;
- `organization_id REFERENCES org_companies(id)` with `ON DELETE RESTRICT ON UPDATE CASCADE`;
- `revoked_by_user_id REFERENCES users(id)` with `ON DELETE RESTRICT ON UPDATE CASCADE`;
- `activated_by_user_id REFERENCES users(id)` with `ON DELETE RESTRICT ON UPDATE CASCADE`;
- `created_by_user_id REFERENCES users(id)` with `ON DELETE RESTRICT ON UPDATE CASCADE`.

Organization deletion must not silently erase membership history. User and
organization lifecycle deletion therefore requires an explicit policy decision
or must be refused by the foreign keys.

### Active uniqueness

The table must enforce at most one `active` row for each
`(user_id, organization_id)` while allowing multiple retained inactive/revoked
historical rows. The proposed portable implementation is a nullable active
key maintained by the database:

- active row: `active_uniqueness_key = 1`;
- inactive/revoked row: `active_uniqueness_key = NULL`;
- unique index: `(user_id, organization_id, active_uniqueness_key)`.

If the supported database cannot enforce this with a generated/check-backed
column, the migration must stop for owner review; application-only uniqueness
is not acceptable. A check must ensure the key is `1` only for `status='active'`
and NULL otherwise.

### Temporal and lifecycle checks

Database checks and repository validation must enforce:

- `valid_from IS NULL OR valid_until IS NULL OR valid_from < valid_until`;
- `status='revoked'` requires `revoked_at IS NOT NULL`;
- `status<>'revoked'` does not carry `revoked_at`;
- `status='active'` requires server-derived activation time; the repository
  additionally requires a positive activation actor and the foreign key
  preserves that actor identity;
- revoked/activation actor non-null requirements are repository-enforced because
  MySQL 9.6 rejects `CHECK` references to foreign-key columns;
- active records may be temporally future or expired, but the resolver must
  exclude them outside the window;
- a new active record cannot overlap an existing active record for the same
  user/organization; the repository must close/revoke the old record first;
- no organization is inferred from branch, fiscal, hierarchy, or primary flags.

The database uniqueness rule protects concurrent active insertion. Temporal
overlap and lifecycle transition rules remain transactionally validated by the
repository and must not be assumed to follow from the unique index alone.

## 4. Lifecycle contract

### Create

Create accepts server-derived `user_id`, `organization_id`, actor, and optional
validity bounds. It rejects missing parents, malformed bounds, invalid actors,
and any existing active row for the pair. A duplicate-key conflict is returned
as a deterministic `membership_conflict`; it is never converted into success.

### Activate

Activation is permitted only for an existing inactive record whose parent
organization remains active and whose validity evidence is valid. It must lock
the pair, verify no active peer, set the active key, and treat a zero-row or
unique-key result as a conflict.

### Revoke

Revoke is idempotence-aware but not success-by-default:

- active → revoked updates exactly one row and records server time/actor;
- already revoked returns `already_revoked` without changing history;
- inactive, missing, or contradictory rows deny;
- no delete is used to represent revocation.

### Expiry and reactivation

Expiry is derived from `valid_until`; it does not silently rewrite the row.
Reactivation is an explicit lifecycle command with the same lock and unique
active-key checks as activation. A new membership period must retain the old
row as historical evidence rather than overwrite it.

## 5. Repository contract

The proposed contract is intentionally narrow:

```text
listEvidenceForUser(userId, observedAt): list<MembershipEvidence>
findById(membershipId): MembershipRecord|null
create(CreateMembershipCommand): MembershipRecord
activate(membershipId, actorUserId, observedAt): LifecycleResult
revoke(membershipId, actorUserId, observedAt, reason): LifecycleResult
replaceActive(userId, organizationId, command, actorUserId, observedAt): LifecycleResult
```

Rules:

1. Every `userId`, organization ID, record ID, actor ID, and timestamp is
   server-derived or validated at the repository boundary.
2. `listEvidenceForUser` returns only explicit membership columns in the shape
   consumed by `OrganizationMembershipResolver`.
3. The repository does not return ACL decisions, application permissions,
   Staff IDs, Leave ownership, branch scope, or UI context.
4. All SQL is parameterized; table/column names are fixed source literals.
5. Missing, malformed, contradictory, or orphaned records fail closed with a
   typed/structured repository result.
6. A successful write returns read-back evidence from the same transaction.

The repository is not an Auth/session resolver. A later Platform integration
service must explicitly obtain authenticated `users.id` and pass a requested
organization context through the existing pure resolver.

## 6. Concurrency guarantees

### Create

1. Begin a transaction.
2. Lock and verify the target `users` and `org_companies` rows with
   `SELECT ... FOR UPDATE`.
3. Lock existing membership rows for the exact pair in deterministic `id`
   order.
4. Validate parent active state, validity, and absence of an active row.
5. Insert the row with the active uniqueness key.
6. Commit, then read back the exact record within the transaction boundary.

Two concurrent creates for the same pair must produce one success and one
structured conflict. A duplicate-key exception is expected contention, not a
reason to retry blindly.

### Revoke/activate

Lock the target membership row and all active peers for the pair, validate the
observed lifecycle version/timestamps, perform one state transition, and
require exactly one affected row. Stale or zero-row transitions return
`membership_state_conflict`.

### Replace active membership period

`replaceActive` must lock all rows for the exact user/organization pair,
revoke/close the current active period, insert the new period, and commit as
one transaction. It must never delete the predecessor or temporarily commit a
state that exposes two active rows.

### Multi-organization selection

There is no global single-organization lock: users may have active memberships
in multiple organizations. Resolution remains explicit. The repository returns
the complete server-side evidence set; the existing resolver denies missing
selection when more than one organization is eligible.

## 7. Migration and rollback procedure

### Preconditions

- Separate owner approval for schema and database execution.
- Disposable isolated source root and attested disposable database for tests.
- `core_apps` owner registration for the Platform Organization module.
- Exact checksums and ordered plan evidence for migrations 001–005 and the new
  006 step.
- Verified backup/recovery point before any installed or production apply.
- Database engine support confirmed for the active uniqueness-key/check design.

### Migration inventory

The proposed inventory contains exactly one new owner migration:

`apps/Platform/modules/Organization/migrations/006_create_user_organization_memberships.sql`

It must depend on the existing Organization schema migrations needed for
`org_companies` and on the core schema step that creates `users`. It must not
discover or execute neighboring files, and it must use
`AppMigrationService::executePlannedStep()` with independently validated plan
evidence. The migration ledger must preserve `failed` evidence and forbid
automatic retry.

### Data preflight

Before apply, read-only checks must prove:

- target table is absent or structurally identical and empty;
- `users` and `org_companies` parent tables exist with compatible key types;
- no legacy membership source exists;
- no role, assignment, branch, email, name, employee-code, or Staff rows are
  eligible for import;
- no ambiguous source records require a migration decision.

This first migration contains no data backfill. Any proposed import is a new
separately authorized migration and must not be invented from existing data.

### Failure and rollback

- SQL failure preserves the failed migration ledger row and reports recovery
  required; it is not automatically retried.
- Applied schema is not rolled back by guessed reverse SQL.
- Rollback means restoring a verified recovery point through the Platform
  backup/restore authority, followed by schema and ledger verification.
- A disposable rehearsal must prove restore of both schema and migration ledger
  state before any installed apply is considered.
- Post-apply read-back must verify table constraints, foreign keys, indexes,
  and zero unintended rows.

## 8. Required regression coverage

### DB-free coverage already complete

`apps/Platform/Tests/probe_organization_membership_authority.php` covers
**26/26** assertions, including exact user binding, explicit selection,
validity windows, stale/expired, inactive, revoked, duplicate, malformed,
cross-user, invalid identity, and deterministic fingerprint behavior.

### Required disposable database probe

The separately authorized repository probe must prove at minimum:

**Schema/constraints**

- parent foreign keys reject unknown users and organizations;
- delete behavior preserves/restricts membership history;
- active uniqueness allows one active row and rejects a second;
- multiple inactive/revoked historical rows are retained;
- lifecycle checks reject invalid status/revocation combinations;
- temporal checks reject reversed bounds.

**Repository behavior**

- create/read returns exact IDs and normalized resolver evidence;
- inactive, revoked, future, expired, duplicate, cross-user, and orphan cases
  fail closed;
- explicit multi-organization selection succeeds only for a server-owned
  membership;
- no role, assignment, branch, email, name, employee code, or Staff value is
  read by the repository.

**Concurrency**

- concurrent same-pair create yields exactly one success and one conflict;
- concurrent revoke/activate yields one valid transition and one structured
  stale/conflict result;
- replace-active never exposes two active rows;
- rollback restores baseline rows, constraints, and migration ledger evidence.

**Isolation and safety**

- the disposable runner refuses repository execution without attested isolated
  configuration;
- installed/repository databases are never contacted;
- all mutations are rolled back or the disposable database is destroyed by its
  runner-owned cleanup;
- no route, session, ACL, Staff, Leave, or Timecard path is invoked.

## 9. Explicit authorization matrix

| Change | Decision |
|---|---|
| This design document and DB-free contract review | GO for design review |
| New SQL migration 006 source | GO under development-only authorization; execution remains governed |
| Repository contract/implementation | GO as Platform-owned persistence source; no runtime caller wired |
| Disposable DB repository probe | PASS: authorizer 24/24; repository probe 17/17 |
| Auth/session organization-context wiring | BLOCKED; separate Core/Auth boundary approval |
| ACL organization intersection/query enforcement | BLOCKED; separate ACL runtime approval |
| SBAIO Staff relation or binding | BLOCKED; SBAIO owner approval and schema contract required |
| Leave/Timecard integration | BLOCKED; no historical reinterpretation |
| Installed database, activation, deployment, production apply | BLOCKED |

## 10. Final disposition

**GO:** implementation-ready migration/repository source and DB-free resolver
regression reuse. The repository source is implemented but not runtime-wired.

**PASS:** fresh runner-owned disposable schema/repository verification,
two-process concurrency, lifecycle replacement, and rollback-preservation
evidence passed. The isolated config fingerprint remained unchanged and the
runner cleaned all disposable resources.

**BLOCKED:** installed schema execution, authenticated integration, ACL,
Staff, Leave, Timecard, deployment, and activation. No schema, migration,
repository execution, database,
concurrency, Auth/session, ACL, Staff, Leave, Timecard, deployment, and
activation operation listed above until its separate authority gate is
approved.

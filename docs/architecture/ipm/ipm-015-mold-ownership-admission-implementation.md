# IPM-015 — Mold Ownership Admission Implementation and Activation Readiness

**Status:** Source implementation complete; runtime activation intentionally
disabled pending governed database and schema qualification.

**Baseline:** `644a9df2f83e101250fb98572deed3be44cf04f3`

## 1. Implementation decision

Products now owns the mutation-admission boundary through
`ProductMoldMutationAdmission`. It reuses `ProductMoldOwnership` and the
existing Platform membership context; it does not introduce a second resolver,
ownership framework, or mold master.

The handler integration is source-complete but guarded by the literal
`isRuntimeActivationEnabled(): false` gate. No request input, session value, or
raw organization identifier can enable it.

## 2. Creation path

When activation is eventually authorized:

1. the existing lead/admin assignment check remains first;
2. an explicit `organization_id` selection is resolved through the existing
   membership resolver;
3. ownership is taken only from the returned opaque
   `OrganizationMembershipContext`;
4. client fields that attempt to provide mold/owner organization identity are
   rejected;
5. the derived organization ID is inserted into `part_molds.organization_id`.

The current mold form does not deliver the required explicit selection, so the
inactive gate prevents this path from changing current behavior.

## 3. Deletion path

When activation is eventually authorized:

1. the existing lead/admin assignment check remains first;
2. the target row is read only for `id`, `product_id`, and
   `organization_id`;
3. product identity is checked before admission;
4. membership context and exact mold ownership are admitted through
   `ProductMoldOwnership`;
5. the delete predicate includes mold ID, Product ID, and organization ID.

Missing context, malformed selection, NULL ownership, cross-organization
ownership, missing target, and product mismatch all fail closed with a generic
outcome and no protected mold row in the admission result.

## 4. Alias and security coverage

Both canonical and compatibility POST aliases converge on the same handlers.
They retain existing Manufacturing app access, session bootstrapping, CSRF,
and lead/admin assignment checks. The compatibility deletion alias forwards
the future organization selection to the same handler; it cannot bypass the
admission boundary.

Part 360 remains unchanged. Revision routes and revision reads/mutations remain
inactive.

## 5. Activation blockers

The literal runtime gate must remain disabled until all of the following are
independently verified:

- Migration 016 applies successfully and idempotently in an owner-controlled
  disposable database;
- the nullable ownership column, index, foreign key, and restrictive delete
  behavior are proven;
- legacy NULL rows remain preserved and denied without backfill or inference;
- the existing runtime `PartEngineeringSchemaService::ensureSchema()` path is
  shown compatible with the migrated schema, or a separately authorized
  replacement is introduced;
- the mold form and every mutation caller deliver explicit organization
  context;
- both aliases are covered by authenticated HTTP/runtime acceptance;
- creation persists only validated context-derived ownership;
- deletion proves owner-scoped read and mutation behavior;
- rollback, duplicate, missing-row, and cross-organization cases are proven.

No database qualification, runtime activation, deployment, or production
change occurred in IPM-015.

## 6. Verification

- IPM-015 mutation admission: **36/36**
- IPM-014 boundary: **40/40**
- IPM-013 ownership foundation: **47/47**
- IPM-010: **24/24**
- IPM-011: **43/43**
- IPM-012: **38/38**
- Product ownership: **26/26**
- Product detail boundary: **20/20**
- Product read boundary: **14/14**
- QR Product boundary: **36/36**
- Platform authenticated context: **16/16**
- Platform membership authority: **30/30**
- Isolated source-root runner: **17/17**

Architecture gates, deletion-family gates, PHP lint, diff hygiene, and
read-only deployment readiness remain required for closeout. Governed
`OH_IPM` database qualification is **BLOCKED / NOT RUN**.

## 7. Next milestone

The next bounded milestone is owner-authorized disposable Migration 016
qualification and authenticated runtime acceptance. It may enable both mold
handlers together only after the activation blockers above are closed. Mold
revision creation, editing, release, supersession, archival, and read APIs
remain deferred.

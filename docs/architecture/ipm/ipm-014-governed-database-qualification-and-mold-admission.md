# IPM-014 — Governed Database Qualification and Mold Admission

**Status:** Source admission qualified; runtime enforcement and database
qualification blocked by missing owner-controlled `OH_IPM` evidence.

**Baseline:** `e0a669f9fe058594f290021dd0e74341dd5b03e4`

## 1. Qualification decision

The required `OH_IPM` authorization contract is not present in the current
environment. The following evidence is missing:

- `OH_IPM_REPO`
- isolated `OH_IPM_APP_ROOT`
- runner-owned `OH_IPM_DB_RESOURCE_ROOT`
- disposable loopback database port, name, user, process ID, socket, and
  datadir attestation
- configuration identity and runner-owned cleanup evidence

No disposable or installed database was accessed. The governed database probe
must remain unexecuted until `DisposableDbTestEntryPointGuard::authorize()`
can validate all required evidence before Composer/bootstrap/SQL.

## 2. Safe admission result

The existing Products-owned `ProductMoldOwnership` foundation is the smallest
safe admission seam:

- creation resolves an explicit organization selection through
  `ProductOrganizationScope` and the Platform membership resolver;
- creation ownership can only be taken from the returned opaque
  `OrganizationMembershipContext`;
- deletion admits an existing mold only when its strict positive
  `organization_id` matches that context;
- missing, malformed, spoofed, revoked/unavailable, NULL-owned, and
  cross-organization cases fail closed;
- denied mold results contain no mold row.

The IPM-014 probe verifies this behavior without SQL.

## 3. Why runtime enforcement remains inactive

The existing mold form does not provide an explicit organization selection,
and the deletion request supplies only `mold_id` and `product_id`. Part 360
does not establish a validated organization context. Activating only one
handler or deriving ownership from the Product, creator, assignment, session,
or revision organization would create a partial or unauthorized path.

Accordingly, `createMold()` and `deleteMold()` remain behaviorally unchanged.
Both canonical and compatibility aliases still converge on those handlers and
retain app access, CSRF, and lead/admin assignment checks. No alias is
partially activated.

## 4. Preserved boundaries

- Products remains the canonical `part_molds` master owner.
- Migration 016 remains additive, nullable, indexed, and restrictive.
- No ownership backfill or inference occurred.
- Runtime `PartEngineeringSchemaService::ensureSchema()` callers remain
  unchanged.
- Part 360 remains unchanged and separately blocked.
- IPM revision routes and workflows remain inactive.
- No duplicate organization resolver or mold master was introduced.

## 5. Remaining activation dependencies

Before runtime mold admission can activate, IPM-014 requires:

1. owner-approved explicit organization-context delivery for mold creation;
2. an owner-scoped deletion read/predicate that runs before mutation;
3. complete canonical and compatibility alias coverage;
4. disposable migration qualification proving column type/nullability,
   index/FK integrity, legacy NULL preservation, restrictive deletion,
   rollback, and ledger-idempotent repeat application;
5. a separately reviewed decision for runtime `ensureSchema()` retirement or
   compatibility proof.

Revision ownership matching, row-lock/concurrency policy, ownership-change
policy, release/supersession/archival behavior, and revision activation remain
later milestones.

## 6. Verification

- IPM-014 source admission boundary: **37/37**
- IPM-013 ownership foundation: **45/45**
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

Architecture, deletion-family, PHP lint, diff hygiene, and read-only
deployment checks remain required for closeout. Database qualification is
explicitly **BLOCKED / NOT RUN**.

## 7. Next eligible milestone

The next eligible milestone is **IPM-015 — owner-authorized disposable
qualification and bounded mold mutation admission**, only after the
`OH_IPM` evidence package and explicit organization-context delivery contract
are supplied. It may then qualify Migration 016 and activate both handlers
together; it must not activate revision workflows.

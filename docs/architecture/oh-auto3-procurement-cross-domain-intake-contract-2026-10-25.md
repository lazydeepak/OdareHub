# OH-AUTO3 Procurement Cross-Domain Intake Contract Closure

**Identifier:** `OH-AUTO3-PROC-CONTRACT-PRE`
**Status:** Contract closure with bounded descriptor implementation
**Shared App promotion:** Not authorized
**Database/runtime intake change:** Not performed

## 1. Resolved decisions

### Canonical source identity

The logical identity is the ordered tuple:

```text
source_app + source_entity + source_record_id + source_revision
```

Each value is opaque to Procurement except for bounded syntax/length validation.
`source_revision` is mandatory for the new descriptor. Existing Manufacturing
rows remain revisionless legacy records and are represented at the descriptor
boundary with the explicit compatibility value `legacy`. The authoritative
legacy source entity is exactly `mfg_procurement_demands`, matching the existing
`source_ref_type` value; no historical row is rewritten.

### Replay and idempotency key

The descriptor derives a SHA-256 key from the four source-identity values. The
requesting app owns the source identity; Procurement owns acceptance and any
future persistence of uniqueness. A caller-supplied key is never trusted.

The key is currently diagnostic/transport evidence only. It is not persisted,
and therefore does not yet claim concurrency-safe duplicate prevention.

### Authorization evidence

The descriptor must carry server-resolved evidence for:

- originating app and source entity;
- authenticated actor;
- organization scope;
- source-record scope;
- fixed capability `procurement.request.intake`.

The validator proves only shape and bounded values. The requesting app must
prove source authorization and scope; Procurement must separately verify its
own acceptance authority before any future mutation.

### Correction and cancellation

Revision changes are distinct identities and must not overwrite an existing
request. No automatic update, supersession, cancellation, or historical rewrite
is introduced by this slice. Those outcomes require an owner-approved policy
based on the current Procurement request state.

## 2. Decisions still blocked

These cannot be settled without owner-level policy or persistence evidence:

1. database representation and uniqueness enforcement for revisioned provenance;
2. transaction/lock behavior for concurrent first submissions;
3. exact conflict response when a revision collides with existing provenance;
4. correction/supersession behavior for draft, approved, active, closed, and
   cancelled requests;
5. concrete organization and record-scope authority providers.

Until these are approved, the descriptor is not wired into the Manufacturing
route or any write path.

## 3. Existing behavior preserved

- `/apps/procurement/requests/intake/manufacturing` remains unchanged.
- `mfg_procurement_demands` remains Manufacturing-owned.
- Existing `source_app`, `source_ref_type`, and `source_ref_id` values remain
  readable.
- Procurement remains the owner of requests, suppliers, purchase orders,
  receipts, lifecycle, permissions, and audit.
- No schema, database, route, Core, or historical-data operation occurred.

The current Manufacturing intake still uses read-before-insert and the legacy
three-field provenance tuple. It remains a known concurrency gap until the
approved persistence decision exists.

## 4. Bounded implementation

Added `ProcurementIntakeDescriptor` and
`ProcurementIntakeDescriptorValidator` under the Procurement app. They provide:

- immutable normalized descriptor data;
- bounded source identity, actor, scope, and capability validation;
- server-derived replay key;
- no database, filesystem, route, authentication, or Procurement mutation.

The implementation is independently verifiable and deliberately does not
change existing intake behavior.

## 5. Acceptance criteria for future wiring

Before any intake path uses the descriptor for mutation, a focused proof must
show that old and future adapters call the same Procurement authority, exact
replays resolve one request under concurrent execution, revision conflicts are
explicit, authorization fails before mutation, audit failure rolls back, and
source cancellation/unavailability cannot corrupt Procurement state.

## 6. Next milestone

Approve the persistence and correction policy, then implement one Procurement-
owned intake authority accepting the descriptor while retaining the existing
Manufacturing route as a compatibility adapter. Do not add a loader or shared
runtime extension architecture.

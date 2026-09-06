# Shared App and App Extension Readiness

Status: Docs-only readiness audit. No runtime behavior. No file moves. No PHP changes.

Date: 2026-08-23

Builds on:

- `docs/architecture/hospitality-readiness-audit.md`
- `docs/architecture/app-ownership-classification-and-hospitality-readiness.md`
- `docs/discussions/hospitality-suite-preimplementation-notes.md`
- `docs/active/hospitality-app-foundation.md`
- `ARCHITECTURE.md`, Charter v1, APP-CONTRACT, MODULE-CONTRACT

## 1. Domain Apps Today

| App | Evidence | Notes |
|---|---|---|
| `apps/Manufacturing` | manifest type business; IPM-specific module set | Reference Domain App |
| `apps/SBAIO` | manifest type business; staff/payroll/sales module set | Reference Domain App |
| `apps/Hospitality` | manifest type business v0.1.0; five owned modules | Active Domain App under construction |
| `apps/Procurement` | manifest type business v0.1.0; app-scoped permissions | Domain App implementation today; see section 3 |

System Apps (not Domain Apps): Shell, Platform, Studio. Generated sample apps under
`apps/Generated/` are scaffold artifacts, not product apps.

## 2. Shared App Candidates

No Shared App exists today. Candidates, in the order the platform will plausibly need them:

| Candidate | Why it is a candidate | Blocking gap |
|---|---|---|
| Parties | Guests (Hospitality) and Customers (SBAIO) are both person/org identity records | Needs owner-neutral identity schema, multi-domain scoping, per-domain permission mapping, and two real consuming domains before promotion |
| Billing | Folio/charges (Hospitality) is a clean future seed | Needs payments/invoice workflow decisions and a second consumer |
| Inventory / Items | Room amenities and F&B could need stock later | Hospitality v1 deliberately avoids item catalogs; no seed exists yet |
| Procurement (promoted) | Generic purchasing concepts already implemented standalone | See section 3 |

Promotion rule for all candidates: a Shared App requires explicit approval plus
cross-domain contracts (owner-neutral entities, per-domain data scoping, per-domain
permissions). A second real consuming domain must exist or be committed. Promotion is
never a side effect of one app's feature work.

## 3. Procurement: Status and Promotion Requirements

Decision (unchanged, restated with precision):

```text
Procurement today: Domain App implementation.
Trajectory: strongest existing Shared App candidate.
```

What must change before promotion:

1. Owner-neutral data contracts - supplier/request/order/receipt records scoped by
   requesting domain instead of implicitly global.
2. Per-domain permission mapping - beyond `procurement.view/manage/approve`; each
   consuming domain needs its own authorization surface into procurement actions.
3. Cross-domain request intake generalized - the current Manufacturing intake path
   (`/requests/intake/manufacturing`) becomes a declared, domain-neutral contribution
   mechanism rather than a hardcoded bridge.
4. A second committed consumer - e.g. Hospitality purchasing - or an approved plan for one.
5. Its own approved contract change - documented like other architecture contracts, not
   folded into another app's task.

Until then: no app may depend on or fork Procurement internals.

## 4. SBAIO Classification

```text
SBAIO is a Domain App.
It is not a "shared business suite" and not a collection of reusable modules today.
```

Its eleven modules are single-parent App Modules (MODULE-CONTRACT). The legacy
`legacy_bridge_plugins` metadata records history; it does not make modules shareable.

Tentative future direction: individual SBAIO modules could seed shared capabilities
(Customers -> Parties is the most obvious). Any such extraction follows the same
promotion rule as section 2 - it does not promote SBAIO itself.

The word "suite" in SBAIO product language remains legacy/product vocabulary
(see readiness audit section 3).

## 5. Manufacturing Products: Confirmed Local

Confirmed: `apps/Manufacturing/modules/Products` is the Manufacturing-owned Parts Master
module (manifest evidence: `feature_key: parts_master`, surface `/apps/manufacturing/products`,
naming convention Item Code = Part Number).

It is not a shared Items/Product app seed. A future shared Items app would be designed
fresh against cross-domain contracts; it would not simply relabel this module. Nothing in
Hospitality may import or depend on it.

## 6. How App Extensions Are Represented Now

Loader check (from the readiness audit, still true): every discovery path globs only
`apps/*/modules*`. There is no `extensions/` loader support, and none may be created now.

Official representation until a real extension contract exists:

```text
An App Extension is a normal runtime-compatible module under apps/<Owner>/modules/
carrying explicit ownership metadata in its plugin.json:

  "ownership_type": "app_extension",
  "extension_of": "<shared-app-key-or-null>",
  "note": "why this local stand-in exists"

Rules:
- It registers through its owning app like any module.
- Its naming, permissions, and docs must say it is a local stand-in.
- It must never masquerade as the shared app it stands in for.
- If no shared app exists yet, extension_of stays null.
```

Hospitality currently has zero extension-shaped modules (folio lives inside FrontDesk by
foundation-brief decision). This convention applies if one ever becomes necessary.

`extensions/` directories remain forbidden at runtime. Whether extension metadata ever
earns loader recognition is deferred (section 10).

## 7. What Hospitality Must Avoid in Slice 6+ (Shared-App Debt Rules)

1. No cross-app reads or writes: no queries against `procurement_*`, `sbaio_*`,
   `mfg_*`, `products`, or any non-`hosp_` table; no routes into other apps.
2. No code forking of other apps' services/models to "reuse" them.
3. No pretending local stand-ins are shared: guest records stay "Hospitality-local";
   folio stays inside FrontDesk; labels/docs must say so.
4. No premature entity promotion: do not rename `hosp_guests` to parties-like concepts,
   do not generalize folio into invoices, do not introduce item catalogs.
5. Keep extraction seams clean (section 8): tables stay narrow and keyed correctly,
   totals computed on read, status enums small, no business logic smeared across tables.
6. No new loaders, no `suites/`, no `extensions/` directory, no compatibility aliases.
7. New dependencies arrays stay empty in manifests; permission keys stay `hospitality.*`.

These rules let Slice 6 (Reservations) and later slices proceed without creating debt a
future shared-app promotion would have to unwind.

## 8. Future Extraction Paths

Each path states the current seam and what a future promotion would do. All are
documentation-level plans; none authorize work now.

### 8.1 Guests -> future Parties

- Current seam: `hosp_guests` holds generic identity fields (full_name, email, phone,
  id_document_ref) with nothing Hospitality-specific except the table prefix.
- Future promotion: create shared Parties app with owner-neutral identity records;
  migrate `hosp_guests` rows into parties; Hospitality keeps a `party_id` reference
  (or a compatibility view during migration window); reservation links re-point.
- Kept clean by: identity-only columns, no booking logic inside the guests table.

### 8.2 Folio/charges -> future Billing

- Current seam: `hosp_folios` (one per reservation) and `hosp_folio_charges` are
  separate tables inside FrontDesk scope; totals are computed on read; no payment,
  tax, or posting logic exists.
- Future promotion: extract both tables into a Billing-shaped structure keyed by
  reservation/stay references; add invoice/payment workflows there.
- Kept clean by: folio-in-FrontDesk being a UI/workflow home only, never a data-model
  entanglement; no cached totals; charge_type enum kept minimal.

### 8.3 Room inventory/stock needs -> future Inventory/Items

- Current seam: intentionally none. Housekeeping tracks room status, not supplies;
  v1 has no item catalog anywhere in Hospitality.
- Future trigger: if amenity/F&B stock becomes a real requirement, first evaluate the
  shared Items/Inventory candidate instead of building local catalogs.
- Kept clean by: absence - the strongest seam is not needing one.

### 8.4 Purchasing needs -> future shared Procurement

- Current seam: none. Hospitality has zero dependency on or copy of `apps/Procurement`.
- Future trigger: a real Hospitality purchasing requirement should become the second
  consuming domain that triggers Procurement promotion per section 3 - it must not be
  solved with local purchasing forks.
- Kept clean by: empty dependencies arrays and the cross-app isolation rules in section 7.

## 9. Official Now vs Tentative Future Direction

Official now (binding on current work):

- Ownership types and classifications in sections 1-5.
- Procurement is a Domain App today; no app depends on or forks it.
- SBAIO is a Domain App; its modules are not shared assets.
- Manufacturing Products is the local Parts Master.
- Extensions are represented as ordinary modules with explicit ownership metadata
  (section 6); `extensions/` runtime directories are forbidden.
- The shared-app debt avoidance rules in section 7 bind Hospitality Slice 6 and later.
- First usable Hospitality scope stays rooms/guests/reservations/check-in-out/
  housekeeping/basic folio, all local.

Tentative future direction (explicitly not authorized):

- Every promotion in sections 2 and 8 (Parties, Billing, Items/Inventory, Procurement
  promotion, SBAIO module extractions).
- Loader recognition for extension metadata.
- Multi-property/multi-hotel scoping for Hospitality.

Any of these requires its own approved contract/planning task before implementation.

## 10. Open Questions Carried Forward

1. What exact event should trigger the Parties evaluation (second identity consumer
   appearing, or a concrete cross-domain feature need)?
2. Should Procurement promotion be planned proactively, or wait for Hospitality's real
   purchasing requirement?
3. Would extension metadata ever justify a loader contract, or is the module-with-
   metadata representation permanently sufficient?

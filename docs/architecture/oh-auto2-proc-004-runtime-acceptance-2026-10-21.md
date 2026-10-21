# OH-AUTO2-PROC-004 — Procurement Operator Queue Runtime Acceptance

**Date:** 2026-10-21
**Scope:** authenticated runtime acceptance of the bounded, read-only
Procurement operator request queue
**Environment:** runner-owned staged source root, runner-owned MySQL, and
loopback HTTP only

## Safety boundary

No request was sent to `os.susankhya.com` or any production-like host. The
application ran from:

`/private/var/folders/_w/4wgfpvdd1_55pjmgt2yt99740000gn/T/oh-auto2-proc004-source/source-root`

MySQL was independently attested on loopback `127.0.0.1:33387` with database
`oh_proc004_fixture`; HTTP ran on `127.0.0.1:33487`. The database contained
runner-owned fixtures only. No installed repository database, deployment,
activation, schema operation, or production operation was used.

## Acceptance matrix

The real authenticated login and Procurement POST creation paths were used for
Alice and Bob. The final staged correction state passed:

| Check | Result |
|---|---|
| Alice own-request projection | PASS — only `PROC004-ALICE-1` |
| Alice displayed fields | PASS — `approved`, `12.50`, `2026-11-30`, `PROC004-PO-1` |
| Bob own-request projection | PASS — only `PROC004-BOB-1` |
| Cross-handle `/u/bob-proc004/*` from Alice | PASS — redirects to `/u/alice-proc004/dashboard` |
| Manage-only identity | PASS — redirects to its dashboard; no queue |
| Approve-only identity | PASS — redirects to its dashboard; no queue |
| Unassigned identity | PASS — no Procurement queue |
| Display identity | PASS — redirects to `/displays/user/display-proc004` |
| Anonymous access | PASS — redirects to `/login` |
| Blank/space/NULL `created_by` rows | PASS — all hidden |
| Admin compatibility | PASS — `/apps/procurement` and `/apps/procurement/requests` HTTP 200 |
| Read-only behavior | PASS — no operator mutation form or action rendered |

The final fixture postcondition was five request rows totaling `25.75`
quantity and one purchase order. The five rows were two owned requests plus
blank, whitespace, and NULL ownership controls; the controls were not exposed
to either owner.

## Confirmed corrections

Two minimal generic Shell corrections were required by the authenticated
runtime and are the only production-source corrections in this acceptance:

1. `apps/Shell/routes.php` imports
   `Apps\Shell\Services\OperatorSurfaceContributionRegistry`, preventing the
   contributed-focus route from failing with an unqualified-class 500.
2. `apps/Shell/Services/OperatorLayerService.php` forwards resolved
   `permissions` into the generic operator composer context, allowing the
   Procurement provider's existing `procurement.view` gate to evaluate.

No Procurement business logic, permission, schema, Core, or mutation behavior
was added. The initial runtime 500 and subsequent generic-dashboard fallback
were both reproduced before these corrections and absent afterward.

## Offline and source validation

- Procurement operator queue probe: **20/20**.
- PHP lint: touched Shell files passed.
- Operator confinement, Shell rendering, and business-app/module contract
  gates passed.
- Database-backed acceptance used only the attested disposable runtime.

Final disposable evidence:

- SQL dump SHA-256:
  `9198c0494003cf11622b355e3decc6025ac1a37aef11cecb3cf4f423b1ab36d6`
- Attestation JSON SHA-256:
  `6e0e8b437daed196fd48fc7df8bf4c65b6047d02869df5f2a1f83e714203e678`

The dump and attestation were created before teardown. The loopback HTTP
server, disposable MySQL process, staged source root, and runner-owned
temporary artifacts were stopped or removed after final validation. No
production-like resource was touched.

## Acceptance disposition

OH-AUTO2-PROC-004 is **accepted** for the bounded authenticated read-only
operator queue, qualified by disposable-runtime evidence. Approval,
cancellation, PO creation, supplier, receiving, live installed-database
rollback, and production deployment remain outside this acceptance.

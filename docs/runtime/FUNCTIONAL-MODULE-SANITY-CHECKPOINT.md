# Functional Module Sanity Checkpoint

## Date

- 2026-05-23

## Scope And Method

- Read-only browser validation only.
- No code changes.
- No route changes.
- No DB/migration changes.
- No form submissions.
- No record create/update/delete actions.

## Baseline Status

- Architecture gates: PASS
- Deployment readiness: PASS
- Runtime smoke checkpoint: already recorded
- Functional sanity sweep: completed read-only
- Global blocker: none identified

## Functional Matrix

| Area | URL Tested | Final URL | Status | Surface | Evidence Marker | Data Presence | Result |
|---|---|---|---:|---|---|---|---|
| Admin | /admin/lazydeepak | /admin/lazydeepak | 200 | Admin wrapper | Home | cards visible | PASS |
| Operator | /u/lazydeepak/dashboard | /u/lazydeepak/dashboard | 200 | Operator surface | Overview | cards visible | PASS |
| Manufacturing | /apps/manufacturing | /apps/manufacturing | 200 | Admin wrapper + Manufacturing | Manufacturing Portal | table/cards visible | PASS |
| Manufacturing | /products | /apps/manufacturing/products | 200 | Admin wrapper + Manufacturing | Parts Master | table/cards visible | PASS |
| Manufacturing | /machines | /machines | 200 | Admin wrapper + Manufacturing | Machines | table/cards visible | PASS |
| Manufacturing | /pre-orders | /pre-orders | 200 | Admin wrapper + Manufacturing | Pre Orders | table/cards visible | PASS |
| Manufacturing | /production-plans | /apps/manufacturing/production-plans | 200 | Admin wrapper + Manufacturing | Production Plans | table/cards visible | PASS |
| Manufacturing | /production-entries | /production-entries | 200 | Admin wrapper + Manufacturing | Production Entries | table/cards visible | PASS |
| Manufacturing | /qc-plans | /qc-plans | 200 | Admin wrapper + Manufacturing | QC Plans | table/cards visible | PASS |
| Manufacturing | /qc-entries | /qc-entries | 200 | Admin wrapper + Manufacturing | QC Entries | table/cards visible | PASS |
| Manufacturing | /dispatch-entries | /dispatch-entries | 200 | Admin wrapper + Manufacturing | Dispatch Entries | table/cards visible | PASS |
| Manufacturing | /apps/manufacturing/daily-orders | /apps/manufacturing/daily-orders | 200 | Admin wrapper + Manufacturing | Daily Orders | table/cards visible | PASS |
| Manufacturing | /apps/manufacturing/materials | /apps/manufacturing/materials | 403 | Access-gated | Forbidden response | blocked for current account | INVESTIGATE (authorized user required) |
| Manufacturing | /apps/manufacturing/materials/master | /apps/manufacturing/materials/master | 403 | Access-gated | Forbidden response | blocked for current account | INVESTIGATE (authorized user required) |
| Studio | /apps/studio | /apps/studio | 200 | Admin wrapper + Studio | ERP App Studio | tables/cards visible | PASS |
| Studio | /apps/studio/library | /apps/studio/library | 200 | Admin wrapper + Studio | ERP App Studio | library content visible | PASS |
| Studio | /apps/studio/history | /apps/studio/history | 200 | Admin wrapper + Studio | ops.gui_studio.history_title | history tables/cards visible | PASS with known debt |
| Studio | /apps/studio/library/module?app_key=inventory_app&module_key=parts_master | same | 200 | Admin wrapper + Studio | ERP App Studio | module/library content visible | PASS |
| Studio | deep library item load behavior | /apps/studio?library_item=... | 200 | Admin wrapper + Studio | ERP App Studio | item load behavior visible | PASS |
| Governance | /ops/access-control | /ops/access-control | 200 | Admin wrapper + Governance | Access Control Board | table/cards visible | PASS |
| Known debt | /ops | /ops | 404 | no exact route | 404 Not Found | none | PASS with known debt |

## Guessed Or Non-UI-Linked 404s

These paths returned 404 during manual probing and are treated as guessed route-shape checks, not linked UI breakage unless a UI link is found to these exact paths.

- /apps/manufacturing/parts-master
- /apps/manufacturing/machines
- /apps/manufacturing/pre-orders
- /apps/manufacturing/production-entries
- /apps/manufacturing/qc-plans
- /apps/manufacturing/qc-entries
- /apps/manufacturing/dispatch-entries
- /apps/manufacturing/material-management
- /apps/studio/search

## Console And Error Notes

- No immediate JS fatal errors observed on passing screens.
- Observed browser errors were request-level 404 or 403 on the endpoints listed above.

## Known Debt Tracking

- /ops root 404 remains a known compatibility shape.
- Studio history title currently shows localization key text: ops.gui_studio.history_title.
- Material Management pages require an authorized user for full validation.
- Guessed route-shape 404s should not be treated as linked UI breakage unless a real UI link targets those exact paths.

## Blocker Assessment

- No global blocker found for next phase.
- Scoped follow-up required: run Material Management checks with an authorized account.

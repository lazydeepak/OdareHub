# OH-AUTO4-002 — Product Readiness and Priority Reconciliation

**Repository:** `/Users/lazydeepak/dev/OdareHub`
**Verified baseline:** `bb0abecc20dd711973172c2d1ec9da07810196ff`
**Branch:** `main`
**Status:** **Assessment complete; one bounded next slice recommended**
**Mode:** Read-only assessment and documentation-only delivery

## 1. Executive result

OdareHub has substantial bounded workflow evidence, but readiness is uneven.
Manufacturing, Procurement, and Hospitality each have meaningful completed
slices. Shell operator foundations and platform lifecycle mechanisms are also
substantial. The highest-value unfinished business workflows remain blocked by
record authority, transaction/audit, database-environment, or owner-policy
dependencies.

One independently executable correction is established without reopening SBAIO
authorization: the SBAIO operator workspace emits admin `/apps/sbaio/*`
destinations from an `/u/*` surface. The next assignment should contain those
links and render an unavailable state until a separately approved jailed
operator workflow exists. This is a route-confinement and product-clarity fix,
not personnel authorization implementation.

## 2. Repository and working-tree verification

Verified before assessment:

```text
Git root:    /Users/lazydeepak/dev/OdareHub
Remote:      https://github.com/lazydeepak/OdareHub.git
Branch:      main
HEAD:        bb0abecc20dd711973172c2d1ec9da07810196ff
origin/main: bb0abecc20dd711973172c2d1ec9da07810196ff
```

The working tree is intentionally dirty. Existing modified and untracked
artifacts include compliance/history records, Platform/Procurement/SBAIO work
records, lifecycle logs, architecture reports, incident evidence, a probe
backup, `document/`, and the PHPUnit cache. They were inspected only as
evidence and preserved without staging, cleanup, revert, or deletion.

## 3. Assessment boundary and evidence

Reviewed:

- `AGENTS.md`, `AGENT-COMPLIANCE-CHECKLIST.md`, and `docs/CURRENT.md`;
- SBAIO, Shell, Manufacturing, Procurement, Hospitality, and Platform
  engineering work registers and owner rules;
- `docs/architecture/oh-auto3-001-product-readiness-functional-gap-audit-2026-10-22.md`;
- `docs/architecture/oh-auto2-gov-003-cross-workstream-product-readiness-prioritization-2026-10-21.md`;
- `docs/architecture/consolidated-production-readiness-reconciliation-2026-10-14.md`;
- Hospitality readiness/lifecycle records and active operator plans;
- current SBAIO operator contribution/view, admission services, and routes;
- recorded focused probes and containment evidence.

No application code, Core/Auth/ACL, database, SQL, migration, activation,
deployment, browser, HTTP, or live-runtime operation was performed.

## 4. Current product-readiness matrix

| Workstream | Current state | Evidence-backed readiness | Main limitation or blocker |
|---|---|---|---|
| **Shell/operator foundation** | Worker dashboard, task focus, route confinement, assignment/scope filtering, overlays, and owner contribution boundaries are established. | **Bounded foundation ready**; focused probes and architecture gates are repeatedly recorded passing. | Shell must not invent business workflows; some owner contributions remain unavailable or incomplete. Live acceptance is environment-bound where noted. |
| **Manufacturing** | Processing, Parts, Production, Assembly/QC scope, machine filters, and operator navigation have bounded owner projections and probes. | **Partial / usable bounded slices**; recent UX and authorization corrections are complete. | Dispatch lifecycle lacks operator record scope, complete legal transitions, transaction/rollback, audit, and safe schema ownership. Material aggregate restoration lacks composite record scope. |
| **Processing and Parts** | Scoped Processing projection/actions and bounded Parts search/detail navigation exist. | **Ready for the recorded bounded scope**; do not reopen without regression evidence. | Live persistence/transaction acceptance remains environment-bound; broader machine/production sources remain excluded without equivalent scope evidence. |
| **Procurement** | Creator-owned read-only request queue has offline and disposable authenticated runtime acceptance; PO-to-receipt handoff is complete. | **Bounded read workflow accepted**. | Approval, cancellation, PO creation, supplier mutation, and receiving remain explicitly deferred; persistence-level descriptor uniqueness and correction policy are unresolved. |
| **Hospitality** | Rooms, guests, reservations, front desk/folio, housekeeping, operator focus, lookup/search/pagination, and lifecycle foundations exist. | **Strong bounded readiness**; disposable runtime and authenticated HTTP evidence are recorded for prior slices. | Newer search/pagination work remains offline-only; browser acceptance is environment-bound. Higher-risk money/stay mutations remain deferred under active plans. |
| **Platform services** | Generic app lifecycle, organization evidence resolver, disposable membership repository proof, release/update and recovery foundations exist. | **Governance foundation available; selected DB-free seams ready**. | Organization persistence/runtime Auth/ACL integration remains blocked or uncommitted; production release apply, trust, activation, and deployment remain unauthorized. |
| **SBAIO** | Admin modules and reports exist; personnel/payroll indexes, mutations, reports, exports, dashboards, widgets, bulk I/O, and Data Exchange are fail-closed where record authority is absent. | **Intentional containment, not feature readiness**. | Owner decision package is awaiting review. Staff binding, organization authority, supervisor scope, payroll confidentiality, Leave/Timecard provenance, and bulk authority remain unresolved. |
| **Module lifecycle/integration boundaries** | App/module ownership, manifests, generic lifecycle behavior, and several runtime boundary probes exist. | **Bounded lifecycle evidence**. | Universal module active-state/dispatch authority, clean integrated baseline, CI/live acceptance, and cross-domain Shared Parties adoption are incomplete. |

## 5. Completed work not to reopen without regression evidence

- Shell worker/task and responsive navigation foundations;
- Manufacturing Processing scope and Assembly/QC operator authority;
- Manufacturing Parts search/detail and recent operator UX corrections;
- Procurement creator-owned operator request queue and PO-to-receipt handoff;
- Hospitality reservation/front-desk/housekeeping foundations and recorded
  disposable acceptance;
- SBAIO personnel/payroll/dashboard/widget/bulk-I/O/Data Exchange containment;
- release/update disposable proof and generic Hospitality lifecycle evidence.

The recorded completion evidence is not a production-readiness claim. It means
these slices should not be reimplemented or refactored without a reproduced
regression.

## 6. Ranked engineering candidates

| Rank | Candidate | Impact | State | Why it ranks here |
|---:|---|---|---|---|
| 1 | **SBAIO operator navigation confinement correction** | High security/product clarity | **Executable** | A current `/u/*` workspace emits `/apps/sbaio/*` targets. It is a demonstrable boundary defect independent of unresolved personnel policy. |
| 2 | Hospitality operator browser/HTTP acceptance | High evidence value | Environment-blocked | Server-side and disposable evidence exists, but the requested assignment forbids browser/live tests and no approved executor is available. |
| 3 | Manufacturing dispatch operator lifecycle | Very high workflow value | Authorization/architecture-blocked | Missing record scope, legal transition matrix, transaction/rollback, audit, and schema ownership. |
| 4 | Procurement approval/receiving mutations | High workflow value | Deferred/assignment-blocked | Auto 2 explicitly kept mutations deferred; transaction and ownership acceptance are incomplete. |
| 5 | Platform organization membership runtime integration | Foundational | Owner/DB-blocked | DB-free resolver evidence exists, but persistence, Auth/ACL intersection, Staff binding, and database authorization are unresolved. |
| 6 | SBAIO personnel/Leave/Payroll restoration | High business value | Owner-decision-blocked | The OH-AUTO4-001 package is awaiting review; implementation must remain blocked. |
| 7 | Shell account-panel extraction | Low product value | Technically ready | Safe structural cleanup, but it does not close a meaningful unfinished customer workflow and is intentionally deprioritized. |

## 7. Recommended next executable assignment

### OH-AUTO4-SBAIO-002 — Contain SBAIO operator navigation to `/u/*`

**Owner:** SBAIO app, with existing Shell operator confinement as the generic
boundary.
**Type:** Functional/security containment correction.
**Authorization required:** Ordinary app-owned code-change authorization only;
no new permission, identity binding, organization policy, database, migration,
or sensitive personnel capability approval.
**Database requirement:** None.
**Core change:** None.

#### Actual problem and evidence

`apps/SBAIO/Views/operator/sbaio.php` currently defines tabs whose targets
include `/apps/sbaio`, `/apps/sbaio/attendance`, `/apps/sbaio/timecards`,
`/apps/sbaio/payroll`, `/apps/sbaio/leave`, `/apps/sbaio/staff`, and
`/apps/sbaio/schedules`. The view emits those targets from the operator
workspace. `apps/SBAIO/Services/OperatorSurfaceContributionService.php` also
declares the operator SBAIO contribution while the sensitive providers remain
unavailable.

This conflicts with the repository's mandatory operator rule: operator links,
forms, redirects, and actions must stay inside `/u/{username}/*`. It also creates
a misleading product promise: a user is shown an operator tab that sends them
to an admin surface rather than receiving a jailed workflow or an unavailable
state.

#### Narrow implementation boundary

1. Remove admin-path destinations from the operator SBAIO view.
2. Keep only an existing same-workspace overview destination, or render
   unavailable/non-actionable states for tabs without an approved jailed route.
3. Do not add `/u/*` business workflows in this slice.
4. Do not load Staff, Leave, Attendance, Timecard, Payroll, or other sensitive
   providers to make the tabs appear functional.
5. Preserve existing assignment admission, Shell route authority, localization,
   operator wrapper, and fail-closed provider services.

#### Isolated regression strategy

Add a database-free static/rendered view probe that:

- asserts every emitted operator `href`, form action, and redirect target is
  confined to `/u/{username}/*` or is absent/unavailable;
- rejects `/apps/`, `/ops/`, `/admin/`, and raw module/admin destinations in the
  operator SBAIO view;
- verifies no sensitive provider or SQL path is loaded by the navigation-only
  surface;
- preserves same-user URL normalization and existing assignment gating;
- checks localized unavailable-state output for unsupported tabs;
- runs PHP lint, operator confinement, Shell rendering, business app/module,
  and diff-hygiene checks.

#### Acceptance criteria

- No operator SBAIO navigation emits an admin or external destination.
- Unsupported personnel tabs cannot imply an authorized empty result or expose
  a direct admin route.
- The existing SBAIO contribution remains assignment-gated and generic Shell
  rendering remains unchanged.
- No route, permission, schema, SQL, provider, identity, or lifecycle behavior
  is added.
- The focused probe passes deterministically without database, browser, HTTP,
  activation, or deployment access.

This assignment improves security and product correctness without deciding the
pending SBAIO authorization contract. It is therefore the highest-value
eligible slice identified by this reconciliation.

## 8. Blocked workstreams

The following must not be selected as implementation work until their stated
dependencies are resolved:

- SBAIO personnel, Leave, Timecard, Payroll, dashboard, widget, report, export,
  bulk I/O, and Data Exchange restoration: OH-AUTO4-001 owner decisions remain
  pending.
- Manufacturing dispatch lifecycle: record scope, legal transitions,
  transaction/audit, and schema ownership are absent.
- Manufacturing Material aggregate restoration: composite source scope is not
  proven.
- Procurement approval, cancellation, PO creation, supplier mutation, and
  receiving: explicitly deferred and require new qualified workflow authority.
- Hospitality higher-risk folio/stay mutations: money and lifecycle authority
  remain deferred.
- Hospitality browser/live acceptance: environment and executor blocked under
  this assignment's restrictions.
- Platform organization persistence/Auth/ACL integration: owner and database
  authorization remain unresolved.
- Production release/apply/deployment: human-gated authority and target
  acceptance remain absent.

## 9. Final recommendation

Proceed next with **OH-AUTO4-SBAIO-002 — Contain SBAIO operator navigation to
`/u/*`**, subject to ordinary implementation assignment confirmation. It is the
only reviewed candidate with a current demonstrable defect, no database or
policy dependency, no Core change, and a deterministic noninteractive
regression strategy.

Do not infer that this containment correction restores any SBAIO personnel
functionality. Keep the OH-AUTO4-001 owner-decision package and all sensitive
SBAIO admission services fail-closed while owner review continues.

**Product readiness verdict:** substantial bounded capability, not production
ready; one narrow containment correction is executable, while the highest-value
business workflow expansions remain blocked by explicit dependencies.

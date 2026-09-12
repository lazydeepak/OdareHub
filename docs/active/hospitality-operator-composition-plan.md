# Hospitality Operator / Workspace Composition Plan

Status: Active implementation handoff. Operator composition slices 2-5 are implemented
and statically validated; live authenticated browser acceptance remains environment-bound.

Owner: Hospitality App (apps/Hospitality)

Created: 2026-08-24

Builds on:

- `docs/architecture/hospitality-foundation-release-readiness.md` (Foundation complete;
  recommended next milestone)
- `docs/experience-composition-architecture-plan.md` (mandatory reading for `/u/*`
  composition; canonical resolution pipeline)
- `docs/runtime/operator/operator-layer-implementation-guide.md` (operator layer model)
- `apps/Manufacturing/manifest.json` hooks + `apps/SBAIO/Services/OperatorSurfaceContributionService.php`
  (the two proven app-contribution precedents)

## 1. Objective

Compose the completed Hospitality Foundation surfaces into the operator layer
(`/u/{username}/*`) using the existing app-contribution mechanism only - no Core
change, no new loader, no Shell composition redesign, no shared-app dependencies.

First deliverable: a read-only **Hospitality operator focus** (front-desk/housekeeping
workboard summary) plus a contributed **Hospitality sidebar section**, visible only to
users whose assigned apps include `hospitality`.

## 2. Architecture Laws Involved

From the Charter, experience-composition plan, and wrapper confinement rules:

1. Apps contribute capability content; Shell composes the frame. No Hospitality chrome
   in Shell beyond the documented route seam (section 5.4).
2. Operators are jailed in `/u/{username}/*`. Every link, form target, and redirect in
   contributed views MUST resolve inside that prefix (enforced by
   `check_operator_confinement.sh` over `apps/*/Views/operator`).
3. ACL owns authorization; Workspace Profile shapes experience. A layout decision never
   grants access. Contributed surfaces gate on app assignment + `hospitality.view`.
4. One feature = one source: the operator focus consumes the SAME Foundation services
   (`FrontDeskService`, `HousekeepingService`) - no duplicated SQL or forked logic.
5. Suite is product language; the app key `hospitality` is runtime truth.
6. No Studio dependency: the operator surface works when Studio is absent/disabled.

## 3. Likely Roles (v1 Personas)

| Persona | Authority | Sees | Why |
|---|---|---|---|
| Front Desk Clerk | app_user assigned to hospitality | Arrivals today/upcoming, in-house stays, folio totals (read-only) | Desk staff monitor the day; actions remain on the admin surface until an action-capable slice is approved |
| Housekeeping Attendant | app_user assigned to hospitality | Room status board summary (clean/dirty/pending counts + room list) | Current-status visibility; no history/work-order workflow exists in v1 |
| Duty Manager (floor) | app_user or leader assigned to hospitality | Combined KPI strip: arrivals today, in-house count, rooms needing attention | Same read-only focus serves all three personas; Workspace Profile may later hide sections |

Role differentiation beyond app assignment + `hospitality.view` is deliberately NOT
modeled in v1: Hospitality declares exactly two app-level permissions today, and inventing
persona-specific permission keys would add ACL surface without a consumer. Workspace
Profile shaping (hide/order sections per persona) is the sanctioned differentiator.

## 4. Mechanism Inventory (verified against code)

The platform already supports everything this slice needs:

| Need | Existing mechanism | Evidence |
|---|---|---|
| Sidebar section | manifest `hooks[]` entry `{type: operator_surface, surface: operator, region: sidebar}` -> app provider class | `apps/SBAIO/manifest.json:132`, consumed by `OperatorSurfaceContributionRegistry::sidebarSections()` |
| Focus view mapping | same hook family, `region: focus_views` returning `view_map[focus] => absolute view path` | `OperatorSurfaceContributionRegistry::focusViewMap()`, preferred over Shell fallback by `$resolveFocusView` |
| App-owned data adapters | `apps/{App}/Services/OperatorLayerAdapters/*.php`, pure-data static classes | Manufacturing pattern (`Apps\Manufacturing\Services\OperatorLayerAdapters\*`) |
| App-owned operator views | `apps/{App}/Views/operator/*.php` | `apps/SBAIO/Views/operator/sbaio.php`, `apps/Manufacturing/Views/operator/*` |
| Route seam | one Shell-owned GET route per focus slug + slug listed in `$KNOWN_VIEW_SLUGS` | `/u/sbaio` precedent, `apps/Shell/routes.php:206,1045` |
| Assignment gating | registry loads payloads only for active+assigned apps; providers double-check assignment in-context | `loadOperatorSurfacePayloads()` -> `activeAssignedApps()`; SBAIO `isSbaioAssigned()` |
| View token shaping | `ResolvedExperienceConsumerService::operatorViews()` (allow-always when `operator_views` unset; profile/override filtering when set) | `plugins/Base/Services/ResolvedExperienceConsumerService.php:47` |

## 5. Proposed Composition

### 5.1 Surfaces (first slice)

Single new operator focus `hospitality` rendering three sections from local services:

```text
/u/{username}/hospitality        (GET, read-only)
```

Sections:

1. KPI strip: arrivals today, in-house stays, rooms needing attention
   (dirty+maintenance+out_of_service), open folio total (computed, posted charges only).
2. Arrivals list (booked, today/upcoming): guest label, room label, planned dates.
3. Housekeeping snapshot: per-active-room current status chips incl. pending state.

All data comes from `FrontDeskService::board()`, `FrontDeskService::folioSummaries()`,
and `HousekeepingService::board()` - zero new SQL. Schema-pending installs render the
localized unavailable state (Foundation pattern), never crash.

### 5.2 Files (all app-owned unless noted)

```text
apps/Hospitality/manifest.json                      # add operator_surface hooks (sidebar + focus_views)
apps/Hospitality/Services/OperatorContributionService.php   # contribute(): regions + assignment guard
apps/Hospitality/Services/OperatorLayerAdapters/HospitalityBoardAdapter.php  # pure data over existing services
apps/Hospitality/Views/operator/hospitality.php     # read-only sections, jailed links
apps/Hospitality/Resources/lang/{en,ja,ne}.php      # new operator.* keys (tr()-driven)

# Required generic Shell seam (precedent: /u/sbaio):
apps/Shell/routes.php                               # GET /u/hospitality handler + KNOWN_VIEW_SLUGS entry
```

No module plugin.json changes are required for v1; the contribution is app-level. If
module attribution is later desired, modules declare it through the app as usual.

### 5.3 Provider contract

`OperatorContributionService::contribute(array $request): array` mirrors the SBAIO shape:

- Reject unless `$request['surface'] === 'operator'` and the context's assigned apps
  include `hospitality` (defense in depth; the registry already filters).
- `sidebar` -> one section titled via locale key `hospitality.operator.sidebar`, item
  href `/u/{context username}/hospitality` built from the passed context only.
- `focus_views` -> `['hospitality' => APP_ROOT . '/apps/Hospitality/Views/operator/hospitality.php']`.
- Every rendered element enforces `hospitality.view` server-side before output; denied
  users get the localized empty/unavailable state, not a fatal.

### 5.4 Shell seam justification (bounded, law-conformant)

`/u/*` routes are Shell-owned by architecture law ("routes are declared in
apps/Shell/routes.php"). The seam mirrors the SBAIO precedent exactly as it exists in
history (commit `2de70402`, verified 2026-08-24), which is wider than this section
originally claimed. The TRUE minimal footprint for one new operator focus is:

1. `apps/Shell/routes.php` - one `$KNOWN_VIEW_SLUGS` entry plus one GET `/u/{slug}`
   handler delegating to `$resolveOperatorGetRoute(slug, slug)` and
   `OperatorLayerService::render()` (assignment gate at route level via the second arg).
2. Focus branch registration in the current post-refactor composer surface
   (`OperatorDashboardComposer.php`): an `$isHospitalityFocus` flag, an include branch
   that prefers the app-contributed view via `$resolveFocusView('hospitality', ...)`,
   and exclusion from the dashboard-fallback chain. There is no generic contributed-focus
   path; without these lines the route would render the generic dashboard.
3. Focus URL/label map entries where the refactor keeps them (e.g.
   `OperatorFocusLabelComposer.php`) and the `wrapper.operator.focus.hospitality` key in
   the Shell-owned locale catalogs `apps/Shell/Resources/lang/{en,ja,ne}.php`.

No other Shell composition, chrome, or behavior changes are authorized. Sidebar/nav/
content logic stays in the app provider and views; this seam only registers the focus so
the platform can render the app-contributed view.

## 6. Shared App Extension Contract Preservation

Per `docs/architecture/shared-app-extension-readiness.md` section 7, this slice must not
create shared-app debt:

- Adapter reads only `hosp_*` data THROUGH Foundation services (no direct cross-table
  queries even locally; services remain the single data source).
- No references to Billing/Parties/Items/Inventory/Accounting/Procurement concepts in
  labels or code; folio totals stay labeled as local records in the UI note style used
  by the admin surface.
- Guests/folio extraction seams untouched: no new columns, no denormalization, no
  cached totals.
- Dependencies arrays stay empty; no new permission keys (`hospitality.view` only).
- If a future shared Parties/Billing app absorbs guests/folio, this operator surface
  migrates with the owning service calls unchanged in shape - adapters are the seam.

## 7. Slice Sequence (each independently verifiable)

1. **Plan acceptance (this doc)** - completed; review against experience-composition
   pipeline; no code.
2. **Adapter + provider slice** - completed; `HospitalityBoardAdapter`,
   `OperatorContributionService`, manifest hooks, locale keys; probe proves registry
   loads provider for assigned users, rejects unassigned, and adapter returns honest
   null pre-schema.
3. **View + route slice** - operator view, Shell route + slug, sidebar wiring; browser
   smoke at mobile/desktop widths; confinement grep clean.
4. **Probe hardening** - extend/add Hospitality probe asserting: hook declaration
   matches provider file; view map path exists; every emitted URL starts with
   `/u/`; locale coverage of all `hospitality.operator.*` keys in en/ja/ne; no foreign
   needles in new files.
5. **Gate pass** - core lock, business app/module contracts, operator confinement,
   display readonly (unchanged), shell rendering contract, `git diff --check`.

## 8. Risks And Mitigations

| Risk | Mitigation |
|---|---|
| Shell route edit oversteps app/Shell boundary | Two-line-bounded edit mirroring `/u/sbaio`; documented here as the authorized seam; shell rendering contract + confinement gates must pass |
| New focus leaks to users without hospitality assignment | Registry filters by assigned apps AND provider re-checks; probe asserts both branches |
| Profile/override interaction regressions | `operator_views` remains unset-by-default (allow-always) - no behavior change for existing users; Workspace Profile restriction continues to work through existing token filtering |
| Operator layer becomes a second business truth | Read-only v1; admin surfaces remain the mutation owner; actions require a separately approved slice with explicit CSRF/manage mapping |
| Stale data confusion (folio totals) | Totals labeled computed-at-read with the existing local-folio note key |
| Localization drift | All strings via `tr()`; probe asserts en/ja/ne parity for new keys |
| Confinement violation | No external links; pagination/detail deferred; gate scan over `apps/Hospitality/Views/operator` must be clean |

## 9. Acceptance Checks (definition of done)

- [x] Probe suite extended per slice 4 passes (existing 267 assertions stay green).
- [ ] `/u/{user}/hospitality` renders for an assigned user with schema applied;
      renders localized unavailable state when schema is pending; 403-equivalent
      empty state when `hospitality.view` denied.
- [ ] Sidebar shows the Hospitality section only for assigned users (live render pending).
- [x] Zero links/forms outside `/u/{username}/*` in contributed views.
- [x] `rg` needle scan: no `procurement_|sbaio_|mfg_|billing|parties|inventory`
       concepts in new Hospitality operator files.
- [x] Gates: core lock PASS, business contracts PASS, operator confinement PASS,
       `git diff --check` clean.
- [x] Docs: `engineering/Hospitality/work.md` records evidence per slice.

Slice 2 evidence: `apps/Hospitality/Tests/probe_operator_slice2_contribution.php`
passes 18/18. The focus view path is declared for the next slice, but the actual
operator view and Shell route remain intentionally unimplemented until slice 3.

Slice 4/5 evidence: `probe_operator_slice3_view_route.php` passes 29/29, including
manifest/provider agreement, `/u/` route confinement, locale parity, and foreign-needle
checks. `check_operator_confinement.sh` includes Hospitality and passes. Live browser
acceptance is the only remaining unchecked item; no repository-local browser harness or
approved local HTTP executor was available during the handoff.

Coverage update (2026-09-12): all eight operator probes now run inside
`apps/Hospitality/Tests/run_all_hospitality_probes.php` (16/16 groups, 494 assertions);
previously only the foundation group was registered. Live browser acceptance remains the
sole unchecked item and is still environment-bound - re-verified this session: no local
`.env`, MySQL not running, no local HTTP executor - so it needs a provisioned local
stack (DB + assigned user with `hospitality.view`) before it can be closed.

POST-static-completion runtime finding (2026-08-24): a Base resolved-experience
catalog omission prevented app-contributed focus tokens (including hospitality)
from passing the shared operator view gate at runtime. A generic compatibility
bridge fix in plugins/Base - merging `{app}.operator.focus_views` contributions
from materialized core_app_hooks into the catalog and its override normalizer -
resolves routing for contributed focuses without hardcoding any business app.
Behavioral route proof for GET focus + confined operator actions lives in
`apps/Hospitality/Tests/probe_operator_slice{4,5}_*.php`; authenticated browser
smoke remains separate deferred acceptance evidence.

## 10. Non-Goals

- Operator-layer write actions - deferred when this plan was written, then landed
  one at a time under `docs/active/hospitality-operator-actions-plan.md`
  (manage-gated, CSRF, jailed POST route, single domain service, own-handle
  binding): housekeeping status update, front-desk check-in, check-out,
  add-charge, cancel, and no-show. Charge void remains deferred (destructive
  financial-record mutation). This plan still governs the read-only composition;
  it does not authorize new write actions.
- Display/kiosk (`/displays/*`) Hospitality surfaces.
- Workspace Profile preset authoring (e.g., `hospitality_front_desk` profile) -
  belongs to Access Control/Studio workflows after the surface exists.
- Widgets via `OperatorLayerWidgetService`, real-time refresh, push notifications.
- Any Core, loader, Shell composition, or shared-app change beyond section 5.4.

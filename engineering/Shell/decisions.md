# Shell — Decisions

## Decision Log

### 2026-10-16 — OH-AUTO2-117 recent-activity entity enrichment boundary

**Status:** Accepted — fail closed until owner authorization exists

An audit row restricted to the authenticated actor does not independently prove
current access to its referenced Manufacturing or Procurement record. The Shell
recent-activity composer therefore requires an owner-provided authoritative
entity authorization resolver before performing any secondary entity lookup.
Without that resolver, it preserves the audit event and presentation counts but
returns a localized record-details-unavailable fallback that contains no entity
type or record ID. No new scope mapping, permission, or business authorization
model is introduced. Authorized owner fixtures may retain existing enrichment
through the resolver seam.

### 2026-10-16 — OH-AUTO2-118 recent-activity payload safety

**Status:** Accepted — preserve safe audit metadata; suppress unauthorized details

The production operator context does not currently bind an exact-record owner
resolver for any supported Manufacturing or Procurement entity type. The
fail-closed enrichment boundary therefore remains active. Additionally,
`note_text`, `reason_text`, and `new_state` are owner-controlled payload fields
and are not independently safe merely because the audit row belongs to the
authenticated actor. When exact entity authority is absent or denied, those
fields are suppressed and the existing event counts, type/action/module/date,
actor, ordering, seven-day window, and 200-row limit remain available. No new
authorization model or broad fallback is introduced.

### 2026-10-16 — OH-AUTO2-103 host-provider containment boundary

**Status:** Accepted — bounded Shell composition admission

The existing read-only assignment/scope preflight is the sole admission
decision for `/me` host providers. `AdminLayerService` admits the registry only
when the preflight state is explicitly `unrestricted`; `AdminSurfaceComposer`
defaults to deny when no admission decision is supplied. Restricted, malformed,
denied, unavailable, and missing-evidence requests return before host-provider
discovery, dynamic file loading, or callback execution.

This is a narrow composition boundary, not a provider capability allowlist or
record-scope reconstruction. Valid unrestricted behavior, provider ordering,
permission-profile filtering, GET route checks, and other host surfaces remain
unchanged. Unrestricted provider mutation and record-scope risks remain open
for OH-AUTO2-104/105/106.

### 2026-10-16 — OH-AUTO2-100 restricted My Work preflight boundary

**Status:** Accepted — bounded non-mutating restricted path

`/me` must evaluate existing assignment and operational-scope evidence through
the Platform context contract before entering the legacy Base resolver. When
scope is restricted, malformed, denied, or unavailable, Shell carries the
preflight context into My Work and Manufacturing returns the existing
localized unavailable state before schema/bootstrap, queue, notification,
escalation, or role-link providers. Explicit unrestricted evidence preserves
the established resolver path.

This is a preflight boundary, not a new authorization model. Missing tables or
evidence fail closed, no new scope fields are introduced, and the complete
`/me` request is not claimed to be non-mutating because parallel host and
admin providers remain outside this correction.

### 2026-10-15 — OH-AUTO1-091 Part-detail action rendering boundary

**Status:** Accepted

**Decision**

Manufacturing owns the semantic Part-detail action list. Shell owns the final
rendering safety boundary: each action must normalize to the authenticated
operator's same-user `/u/{username}/*` address and pass the existing Platform
GET route authority. Missing authority, unsafe URLs, denied routes, and
read-only interaction profiles suppress the action rather than rendering an
unusable link. This does not grant or infer POST/mutation permission.

### 2026-10-15 — OH-AUTO1-086 role-home effective-access boundary

**Status:** Accepted

**Decision**

Role-home owner contributions are renderable only when the existing Platform
access-policy contract confirms the authenticated identity, active assigned app,
optional effective module visibility, module access, and any declared authority
or permission-profile evidence. The compatibility host registry remains a
provider/discovery boundary; its output is not treated as authorization when
policy or evidence is unavailable.

All operator widget and role-home links use one strict relative same-user URL
shape validator and then the existing GET route-access authority. Assignment
profile resolution preserves authoritative `dashboard_type` and explicit
profile fields before applying conservative read-only precedence.

**Consequences**

- External, scheme-relative, cross-user, traversal, malformed, and unsafe-query
  destinations cannot survive operator rendering.
- Unassigned, disabled, module-invisible, unauthorized, or unverifiable owner
  contributions are omitted, including non-clickable widgets.
- Shell consumes Platform decisions and does not implement business ACL rules.
- The existing worker, leader, read-only, task, `/me`, admin, and display paths
  remain unchanged outside this focused boundary closure.

### 2026-10-15 — OH-AUTO1-085 role-shaped operator home boundary

**Status:** Accepted

**Decision**

The existing operator dashboard keeps its worker-first OH-AUTO1-081–084 path.
Leader and read-only home experiences are shaped by a Shell-owned removable
composer over the already-resolved assignment context and the existing
`HostSurfaceRegistryService::build('me', ...)` owner-contribution boundary.
Shell may filter interaction profiles, order regions, render neutral widgets,
and enforce same-user `/u/{username}` plus route-authority checks; it must not
resolve business permissions, query business data, or inherit another user's
worker assignments.

Read-only evidence is conservative: action and form contributions are omitted
and the existing `/u/tasks/advance` POST contract rejects read-only mutation.
Missing or ineligible profile evidence does not activate the role-shaped home.

**Consequences**

- Owner apps/modules retain contribution semantics and data ownership.
- Workers retain dashboard parity and task-specific action behavior.
- Leaders receive only authorized owner-provided review/monitoring content.
- Read-only and display/admin identities cannot gain operator mutation controls.
- The temporary Base host-surface registry remains an explicit compatibility
  bridge until the resolved-experience runtime contract replaces it.

### 2026-10-15 — OH-AUTO1-081 worker dashboard read authority

**Status:** Accepted

**Decision**

The worker-first operator dashboard consumes only the existing assigned-task
read projection, `OperatorTaskService::getForUser()`. Shell composes that data
into presentation lanes but does not call `OperatorWorkboardService::build()`
because that compatibility path ensures schema and is not safe for dashboard
rendering.

Workspace Profile navigation remains shaping-only: profile sections are
filtered through the resolved operator-view set, assigned applications, and
the `/u/{user}/*` route boundary before rendering.

**Consequences**

- Dashboard rendering is read-only and does not introduce schema authority.
- Empty, provider-failure, and identity-mismatch states remain explicit.
- Task meaning, identifiers, statuses, priorities, destinations, and scopes
  remain provider-owned; Shell only classifies verified active records by due
  evidence and blocked/overdue attention state.
- The existing specialized operator views and `/me` compatibility routing are
  unchanged.

### 2026-10-15 — OH-AUTO1-082 worker access and evidence hardening

**Status:** Accepted

**Decision**

Worker task presentation requires a positive authenticated user ID, an
identity-bound workspace, and an injected resolver backed by the existing
route-access authority. Shell performs strict same-user relative URL and query
validation before asking that authority; it does not create a new access model.
Workspace Profile destinations use the same final route check after resolved
view and assigned-app shaping.

The current `OperatorTaskService` contract is authoritative for active statuses:
`open` and `in_progress`. Unsupported states such as `blocked` are ignored until
an owner contract provides them. Effective user scope is labeled separately
from optional task item scope; missing item scope is not synthesized.

**Consequences**

- Identity and destination failures fail closed without changing dashboard
  composition or specialized routes.
- Profile navigation cannot grant access, cross users, escape `/u/*`, or use
  unsafe query destinations; desktop/mobile consumers share the filtered set.
- The route-access resolver remains the existing authority, while the Shell
  retains only presentation and conservative URL-shape validation.

### 2026-10-15 — OH-AUTO1-083 task-to-action navigation

**Status:** Accepted

**Decision**

Task-to-action navigation uses the existing operator task route with an
allowlisted `task_id` query parameter. When the provider does not supply a
task-specific destination, Shell derives `/u/{username}/tasks?task_id={id}`
only after the OH-AUTO1-082 same-user URL and route-authority checks. Existing
valid owner destinations remain unchanged.

The task page resolves the selected record from the same assigned-task read
projection already loaded for the authenticated user. It renders a focused
summary and a return-to-list link; it does not add a detail endpoint, database
query, mutation, or new task capability.

**Consequences**

- Workers land on the assigned task they selected rather than a generic list.
- Assignment identity, status, scope evidence, and route authorization remain
  governed by existing providers and authorities.
- Specialized operator views, `/me` compatibility routing, and production
  release/update controls remain unchanged.

### 2026-10-15 — OH-AUTO1-084 exact task selection and actionable focus

**Status:** Accepted

**Decision**

The worker task route is item-bound at both navigation and rendering
boundaries. For `/u/{username}/tasks`, Shell removes conflicting `task_id` or
`id` selectors and appends the verified provider task ID. Duplicate selector
shapes fall back to the same canonical item-bound destination rather than
preserving owner-provided ambiguity.

The task page accepts only one raw, canonical positive decimal `task_id` that
fits the PHP integer range. Arrays, aliases, duplicate keys, suffixes,
whitespace, floating-point/exponent forms, zero, negative values, and overflow
are rejected before collection selection. Selection still searches only the
already-authorized assigned-task projection.

The selected focus reuses the existing `/u/tasks/advance` POST contract,
including CSRF and provider authorization. It exposes Start for `open` and
Mark Done for `in_progress`; completed and cancelled records remain
actionless. POST redirects retain the canonical selected ID without adding a
new action or lookup authority.

**Consequences**

- A worker cannot use an owner-supplied task selector to switch the selected
  record represented by a dashboard card.
- Malformed selectors fail visibly and do not silently coerce to another task.
- The selected task is actionable without duplicating status-transition logic
  or introducing a second mutation endpoint.

### 2026-07-20 — Runtime catalogs and owner providers are search discovery authority
**Status:** Accepted

**Decision**
UI, navigation, and runtime artifacts enter the canonical search pipeline through owner-declared providers. The Platform provider adapts authoritative navigation, app-hook, dashboard, menu, action, permission, and runtime-route catalogs without containing business-owner semantics. Manufacturing retains its workflow actions and fallback chart definitions. Final GET/POST discovery uses `RouteRuntimeAuthority`, seeded after all enabled owners register routes; search does not parse PHP route files.

Provider-specific labels and intent group priorities remain with providers. Core owns only shared matching, scope construction, result authorization, deterministic precedence-aware deduplication, grouping, limits, and response delivery.

**Consequences**
- Core no longer knows page, menu, tile, chart, form, workflow, or route-discovery implementations.
- Runtime-registered, grouped, conditional, and generated routes can be discovered without regex limitations.
- Cross-provider conflicts resolve by explicit precedence and deterministic fingerprint tie-breaking rather than provider load order.
- Existing result URLs and route contracts remain unchanged.
- Database indexing remains a separate evidence-gated decision after live profiling.

### 2026-07-20 — Search candidates are owner-declared providers
**Status:** Accepted

**Decision**
Business apps declare search providers through an optional app-root `search.php`. The generic Platform registry discovers only direct, canonical owner declarations and accepts implementations of `SearchProviderInterface`. Core orchestrates providers without naming apps, then applies result-level route authorization, deduplication, grouping, and limits. Business SQL, labels, result URLs, and metadata remain with the owner provider.

**Consequences**
- Adding another business search domain no longer requires adding its tables or semantics to Core.
- Manufacturing entity SQL and formatters are now Manufacturing-owned.
- Existing compatibility URLs remain until their route migration is separately approved.
- Remaining Core search debt is UI/navigation/artifact discovery, which can migrate to Platform/owner providers incrementally.

### 2026-07-20 — Search delivery is server-authorized and single-entry
**Status:** Accepted

**Decision**
Admin and Operator search use the authenticated `/api/search` entry point. Surface owners publish only already-resolved candidates into an opaque, user-bound server session scope. The generic Platform search capability rechecks user identity, path-prefix confinement, and route authorization before ranking and delivery. Browser code renders results and handles keyboard interaction but does not discover, authorize, or score candidates.

**Consequences**
- Operator search now requires connectivity to the application server; the former offline DOM scan is intentionally retired.
- Candidate catalogs remain owner-controlled. Operator normalization, deduplication, ranking, limits, and delivery confinement now have one server-side implementation; the legacy Admin discovery internals remain migration debt behind the same entry point.
- Database FULLTEXT/search-document indexing will be introduced only after live query-volume and `EXPLAIN` evidence; ordinary leading-wildcard `LIKE` searches are not assumed to benefit from B-tree indexes.

### 2026-07-19 — Runtime theme delivery freshness uses source fingerprints
**Status:** Accepted

**Decision**
Generated `public/assets/theme.css` records a deterministic SHA-256 fingerprint of its manifest, compiler/fingerprint implementation, enabled and auto-discovered source CSS, and enabled legacy base. Dynamic page bootstrap compares that fingerprint with current repository sources and rebuilds missing or stale theme output before rendering. File timestamps are not freshness authority.

**Consequences**
- A normal repository update no longer requires a manual theme compiler command when the PHP runtime can write the generated asset directory.
- Existing but stale output is repaired; it is not accepted merely because it is non-empty.
- Dry-run compilation remains non-mutating.
- If deployment permissions forbid runtime writes, deployment orchestration must still publish assets under an authorized account.

### 2026-07-19 — Basic surface geometry resolves from rendering Foundation
**Status:** Accepted

**Decision**
Shared spacing, corner radii, panel/card shape, icon-chip dimensions, and safe-area compatibility values resolve from Shell rendering Foundation and Shell system geometry. Composed themes retain surface colors, gradients, borders, and effects, but cannot determine these structural values. Legacy admin names (`--radius`, `--surface*`, `--border`) are explicitly mapped at the Shell boundary instead of remaining undefined.

**Consequences**
- Admin launcher groups and links have valid, stable corner geometry under every composed theme.
- Existing Shell selectors using legacy spacing/radius/safe-area names resolve through Foundation without a broad selector rewrite.
- Semantic themes no longer declare card-radius or icon-chip geometry.

### 2026-07-19 — Shell aggregate CSS is publishing-only
**Status:** Accepted

**Decision**
`apps/Shell/styles/shell.css` remains the canonical source-side import map and a published asset, but its manifest entry is marked `runtime: false`. Runtime surfaces and isolated Studio previews load only the explicit, surface-filtered, cache-busted Shell files from `StyleRegistryService`.

**Consequences**
- A Shell file cannot enter the cascade both directly and through the aggregate import chain.
- Child Shell files always receive their own file-version cache key.
- Isolated previews share the same Foundation/theme/Shell ordering contract as runtime without loading unrelated surface files.

### 2026-07-19 — Shell geometry and overlay activation contracts
**Status:** Accepted

**Decision**
The canonical sidebar/content geometry sheet owns a mobile-first admin shell column from the smallest viewport through 900px and switches directly to the desktop grid at 901px. Overlay visual preferences describe effect parameters only; they cannot activate blur or dimming. Visual effects require the overlay manager's explicit `data-shell-overlay-open="true"` lifecycle state, which is removed when the final overlay closes or controller state resets.

**Consequences**
- No unowned 861–900px fallback layout exists.
- Stale visual-preference classes cannot blur inactive content without an active overlay.
- Empty overlay hosts are non-rendering by default.

### 2026-07-19 — Basic control geometry is theme-independent
**Status:** Accepted

**Decision**
Button/control height, radius, padding, spacing, field sizing, checkbox dimensions/radius, and radio shape are rendering primitives owned by Shell Foundation. Composed themes may vary control colors, borders, shadows, effects, and color-bearing artwork, but cannot redefine structural control geometry.

**Consequences**
- Authenticated and first-boot surfaces consume the same Foundation geometry.
- Theme switching cannot resize or reshape basic buttons, checkboxes, or radios.
- Compatibility `--control-*` aliases resolve only from `--render-*` primitives.

### 2026-07-05 — Shell Owner Taxonomy Baseline v1
**Status:** Accepted

**Context**
`OperatorSurfaceComposer` decomposition work was progressing while Shell owner taxonomy guidance remained implicit in scattered scan findings. Without a fixed Shell owner-structure model, helper extraction could force repeated relocations later.

**Decision**
Owner Structure Scan now exposes a canonical Shell Domain Model with four architectural domains and mapped implementation folders:
- Runtime: `Composers`, `Services`, `Views`, `Resources`
- Styling: `styles`, `DesignSystem`, `Resources/css/essential`, `Resources/rendering`
- Contracts: `routes.php`, `navigation.php`, `layout_contract.php`, `dashboard_widgets.php`, `sidebar.php`, `sidebar_sources`
- Quality: `Tests`, `AGENTS.md`

The taxonomy decisions are fixed in the contract payload:
- `DesignSystem` vs `styles`: keep both (`styles` = runtime CSS delivery, `DesignSystem` = governance/contracts/diagnostics)
- Runtime domain scope: contract defines runtime ownership boundaries, while implementation placement can evolve without contract rewrite
- Additional top-level domains: not required in this slice

The contract now includes explicit ownership boundaries per domain (`owns` and `never`) for Runtime, DesignSystem, styles, Contracts, and Quality.

**Consequences**
- Decomposition work can continue with stable destination domains and fewer future structural reversals.
- Owner Structure Scan now communicates architectural intent separately from physical folder inventory.
- Future folder moves must align with this baseline or intentionally revise the domain model decision first.

**Evidence**
- `apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureContractV2DiagnosisService.php`
- `apps/Studio/Tools/OwnerStructureScan/Views/preview.php`
- `apps/Studio/tests/probe_owner_structure_shell_contract.php` (`48/48`)
- `scripts/architecture/check_studio_boundary.sh` (`PASS`)

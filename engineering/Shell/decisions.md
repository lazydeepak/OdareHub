# Shell — Decisions

## Decision Log

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

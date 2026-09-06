# Styling Ownership Boundary Verification (2026-06-05)

## Scope

Verification-only audit against the active contracts:

- Theme Architecture V1 (`docs/architecture/theme-source-compilation-migration.md`)
- Shell Behavior & Rendering Contract V1 (`docs/architecture/shell-behavior-rendering-contract-v1.md`)
- Universal Component Contract V1 (`docs/architecture/universal-component-contract-v1.md`)
- Shell ownership contract (`apps/Shell/manifest.json`)

No runtime/CSS edits were performed as part of this audit.

## 1) Theme Ownership Findings

Observed source-theme files:

- `resources/themes/foundation.css`
- `resources/themes/semantic/semantic.css`
- `resources/themes/light.css`
- `resources/themes/dark.css`
- `resources/themes/liquid-glass.css`
- `resources/themes/paper.css`

Findings:

- Theme sources are token/value-centric (custom properties, theme-mode/style variable assignment).
- No class-selector component blocks found in `resources/themes/**/*.css` during selector scan.
- Runtime compile contract is active: source files -> `scripts/assets/compile_theme_sources.php` -> `/assets/theme.css` (served from `public/assets/theme.css`), per current architecture.

Boundary status: **Compliant with Theme value ownership**.

## 2) Shell Ownership Findings

Observed Shell styling sources:

- `apps/Shell/styles/components.css`
- `apps/Shell/styles/operator.css`
- `apps/Shell/styles/shell.css`
- `apps/Shell/styles/admin.css`
- `apps/Shell/styles/setup.css`

Confirmed Shell-owned behavior exists in Shell CSS/JS contract surfaces:

- wrapper/layout mechanics (sidebar/topbar/content shell)
- overlays and backdrop orchestration (`.shell-overlay`, overlay z-layers)
- breakpoints/media-query behavior
- z-index layer map tokens and shell-level positioning

Drift signals found in Shell CSS:

- `apps/Shell/styles/components.css` still contains app/domain-coupled selectors such as `.coverage-kpi` and approval-inbox-specific `.ai-*` classes.
- `apps/Shell/styles/shell.css` still carries `.menu .group` and `.ai-*` variant rules used by Base views.

Boundary status: **Mostly compliant on behavior ownership, with known transitional business-selector debt in Shell**.

## 3) App Ownership Findings

Observed representative app/module CSS:

- `apps/Manufacturing/styles/manufacturing.css`
- `apps/Manufacturing/modules/Coverage/styles.css`
- `apps/Manufacturing/modules/ProductionEntries/styles.css`
- `apps/SBAIO/styles/sbaio.css`

Findings:

- Manufacturing/SBAIO/Coverage style files are owner-local and use prefixed business selectors (`.mfg-*`, `.coverage-*`, `.sbaio-*`, `.we-*`).
- App/module CSS primarily consumes shared theme tokens (`var(--style-*)`, `var(--tone-*)`, `var(--text)`, etc.) rather than redefining a parallel token system.
- One module-local sticky header exists (`.we-header { position: sticky; z-index: 50; }`) and is scoped to work-entry module UX, not global wrapper chrome.

Boundary status: **Compliant with app/module ownership model**.

## 4) Setup/Auth Findings

Evidence:

- Shell manifest declares setup/auth style ownership at Shell level:
  - `apps/Shell/manifest.json` (`shell.setup` on admin surface; `shell.app` + `shell.components` include auth surface)
- Auth wrapper loads global + `forSurface('auth')` styles via style registry:
  - `public/views/layouts/auth_header.php`
- Header wrapper loads global + admin/auth surface styles via style registry:
  - `public/views/layouts/header.php`
- Setup-specific selectors are isolated in `apps/Shell/styles/setup.css` under `.setup-*`/`.setup-bridge-*` namespace.

Boundary status: **Compliant with Shell-owned setup/auth wrapper model**.

## 5) Universal Component Findings

Evidence from Shell component layer:

- Generic primitives present in `apps/Shell/styles/components.css` (`.card`, `.btn`, `.table-wrap`, `.status-chip`, control rows/fields).
- Universal component tokens/behaviors are centralized and reused across surfaces.

Drift signals:

- Generic primitive groups still include domain-specific classes (`.coverage-kpi`, `.ai-*`, `.menu .group`) in some shared effect groups.

Boundary status: **Universal-component foundation exists, but consolidation is incomplete due to mixed generic + domain selectors**.

## 6) Runtime Control Map

Runtime stylesheet assembly is registry-driven:

1. `StyleRegistryService::globals()` provides global stack in order:
   - `/assets/normalize.css`
   - `/assets/theme.css`
   - `/assets/layout.css`
   - `/assets/wrapper-shared.css`
2. `StyleRegistryService::forSurface(surface, context)` appends surface-owned CSS from manifests.
3. Entry points use this composition pattern:
   - `public/views/layouts/header.php` (admin/auth)
   - `public/views/layouts/auth_header.php` (auth)
   - `apps/Shell/Composers/OperatorSurfaceComposer.php` (operator)
   - `apps/Shell/Composers/WorkEntryComposer.php` (work-entry)
   - `apps/Shell/Views/display/floor.php` (display)

Interpretation:

- Theme values are globally injected once.
- Shell and app/module ownership are surfaced through registry ordering and manifest declarations.

## 7) Ownership Drift Analysis

### Confirmed drift/debt

1. **Shell contains business/domain selectors**
   - Approval inbox (`.ai-*`) and coverage (`.coverage-kpi`) classes remain in shared Shell CSS.
   - This crosses the strictest interpretation of app/module-only business CSS ownership.

2. **Legacy global layout layer (`/assets/layout.css`) carries a parallel token namespace (`--gs-*`)**
   - Global runtime stack includes `layout.css` before wrapper/app CSS.
   - This is operationally tolerated today but represents a parallel styling subsystem relative to the primary theme-token model.

### Not observed in this audit

- Theme-source files owning component structure/behavior selectors.
- App/module CSS redefining shell wrapper/topbar/sidebar ownership at global scope.
- Setup/auth styling owned by non-Shell apps.

## 8) Final Classification

**B. Partial readiness (architecture mostly aligned, with known transitional ownership debt).**

Rationale:

- Core ownership model is active and functioning (theme source contract, registry-driven runtime loading, app-local style files).
- Shell behavior ownership is correctly centralized.
- Remaining debt is explicit and bounded: business selectors in Shell shared CSS and legacy global layout token subsystem.

Recommended next action:

- Continue incremental extraction of remaining app/domain selectors from Shell shared CSS into owner app/module CSS while preserving runtime parity.
- Define and execute a bounded migration path for `layout.css` parallel token layer into the canonical theme/shell ownership model.

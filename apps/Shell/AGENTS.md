# /apps/Shell/AGENTS.md

> This directory owns Shell composition surfaces.
> Shell renders framework UI and app contributions; it does not own business logic.

---

## Scope

Shell owns:
- header/navigation frame
- sidebar composition
- layout slots/regions
- global UX utilities shared across apps
- wrapper/chrome CSS and shared utility styles
- runtime rendering of already-resolved admin/operator/display experience data

---

## Rules

- Do NOT add business-domain logic in shell.
- Do NOT hardcode app-specific links/widgets into shell.
- Render app/plugin contributions through contracts/hooks.
- Keep shell changes generic and removable.
- Do NOT make Shell the owner of ACL, Workspace Profile, Studio composition, or app/module capability catalogs.
- Do NOT make Shell the owner of app/module page CSS. View/component CSS belongs to the parent app/module that owns the rendered view.
- For experience work, follow `../../docs/experience-composition-architecture-plan.md`: Shell should move toward consuming `ResolvedExperience`, not inventing independent layout law.

### Localization And Theme Rules (Hard Requirements)

- Use localization keys/helpers for all user-visible text.
- Never hardcode UI strings in composer/view output (labels, placeholders, button text, helper text, headings, alerts, flashes).
- Keep Japanese terminology consistent across the surface; do not mix English and Japanese for the same concept.
- Adopt shared theme tokens and global CSS assets already used by shell surfaces.
- Keep Shell CSS limited to wrapper chrome, shared layout primitives, and cross-app utilities.
- Put app/module view-specific CSS in the owning app/module, even when that view is rendered through Shell.
- Do not add new inline style blocks or isolated style systems for shell UI unless explicitly approved.
- Keep role-aware behavior in data/permissions only; presentation text still follows localization rules.

---

## Required References

Follow these with priority:
- `../../AGENTS.md`
- `../../docs/experience-composition-architecture-plan.md`
- `../../docs/architecture/APP-CONTRACT.md`
- `../../docs/architecture/ROUTING-STANDARD.md`

---

## Operator Layer Architecture

### Active Phase

The Operator Layer is a separate UI experience (`/u/{username}`) distinct from Admin Layer (`/apps/*`).

### Components

**Orchestration Layer**:
- `OperatorLayerService.php` — Boots session, authenticates user, resolves context, composes UI
- `AdminLayerService.php` — Parallel service for admin layer

**Composition Layer**:
- `OperatorSurfaceComposer.php` — Renders complete operator layer UI with header, navigation, content sections
- `AdminSurfaceComposer.php` — Parallel composer for admin layer

**Widget Discovery**:
- `OperatorLayerWidgetService.php` — Discovers and aggregates module-contributed widgets, organizes by provider
- Uses standard widget registry pattern from all active modules

**Data Adapters**:
- `Services/OperatorLayerAdapters/` — Module-specific adapters that fetch data and transform for operator views
  - Pattern: Adapter returns pure data (arrays), view renders HTML
  - Examples: CoverageAdapter, QcAdapter, DispatchAdapter, MachinesAdapter, AssemblyAdapter, MaterialsAdapter
  - Transitional status: active runtime path until catalog-driven `ResolvedExperience` rendering is introduced

**Views**:
- `Views/operator/` — Operator-specific view templates (home, coverage, qc, production, etc.)
  - Never embed module pages
  - All links stay within `/u/{username}/*`
  - Use adapters as data source, not module services directly

### Design Principles

1. **Data-Driven**: Adapters fetch module data via existing services
2. **UI-Agnostic**: Views render operator-specific UI, not embedded module UI
3. **Self-Contained**: Operator layer is complete experience within `/u/{username}/*`
4. **Module-Independent**: No module code changes required; adapters call existing services
5. **Contract-Based**: Each module contributes via established widget registry contracts
6. **Resolved-Experience Bridge**: Operator/admin/display renderers consume resolved experience visibility data produced from owner catalogs, ACL, Workspace Profile, and user overrides while preserving compatibility defaults.

### Implementation Status

- ✅ Core orchestration (OperatorLayerService, AdminLayerService)
- ✅ UI composition (OperatorSurfaceComposer with hamburger menu)
- ✅ Widget discovery (OperatorLayerWidgetService, module grouping)
- ✅ Data adapters for current manufacturing operator/display data paths
- ✅ Custom operator views for current production-ready route set
- ✅ ResolvedExperience visibility consumption bridge active for operator/admin/display surfaces

### Next Phase

1. Keep compatibility defaults and parity diagnostics in place while live data finishes migrating.
2. Remove compatibility fallbacks only when diagnostics show no active dependency.
3. Continue moving renderer-specific layout shape toward owner catalog and Workspace Profile contracts without making Shell own business capabilities.

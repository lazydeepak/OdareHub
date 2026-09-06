# Universal Component Contract Readiness Audit

**Status:** Read-only architecture audit
**Date:** 2026-06-05
**Scope:** Theme Architecture V1 + Shell Behavior & Rendering V1
**Recommendation:** Ready for contract definition, not ready for runtime consolidation

## 1. Executive Finding

Theme V1 establishes ownership of values. Shell Rendering V1 establishes ownership of wrapper behavior. The next platform layer is a **Universal Component Contract** governing reusable component anatomy, interaction, accessibility, responsive behavior, and contribution slots.

The correct ownership model is layered:

```text
Theme
  -> owns visual values and semantic tokens

Shell shared component contract
  -> owns generic component anatomy, states, accessibility, and wrapper-safe behavior

App/module owner
  -> owns business meaning, data, labels, actions, permissions, and semantic variants

Studio (future)
  -> edits approved component instances and composition metadata for the real owner
  -> never becomes runtime component truth or business owner
```

No reviewed component family should be assigned wholly to Studio. Most families should also not be assigned wholly to Shell. Shell should own reusable primitives; apps and modules should own semantic composites built from those primitives.

## 2. Contract Alignment

### Theme Architecture V1

Theme owns values:

- colors
- spacing
- radius
- shadows
- typography values
- blur and transition values
- theme variant overrides

Theme must not own component selectors, markup, business meaning, data shape, or interaction behavior.

### Shell Behavior & Rendering V1

Shell owns:

- component rendering primitives shared across owners
- wrapper-safe interaction behavior
- responsive and container behavior for generic components
- keyboard and focus behavior
- overlay placement and lifecycle
- shared state vocabulary

Shell does not own business dashboards, KPI definitions, table columns, chart meaning, business actions, or app-specific card variants.

### Surface Contribution Contract

Apps/modules own contribution meaning and data. Shell composes, normalizes, confines, and renders those contributions. This distinction must remain visible in every universal component API.

### Studio Operating Contract

Studio is a governed editor. It may later edit owner-owned component resources, layouts, and contribution metadata, but approved changes must return to owner artifacts. Studio must not become the component runtime or a hidden component-definition store.

## 3. Current Readiness Evidence

The repository already has broad shared usage:

- Generic `.card` markup appears across app, plugin, and admin surfaces.
- `.table-wrap` and table chrome are consumed across many owners.
- `hero-meta-card`, `dashboard-link-card`, `widget-card`, and tile anatomy are shared by multiple system and business surfaces.
- KPI, chart, quick-action, and table variants also exist as many owner-prefixed implementations.
- Action panels already depend on Shell overlay, z-index, Escape, outside-click, and scroll-lock behavior.

This proves demand for a shared contract. It does not prove that current Shell CSS is already a clean component catalog.

Current blockers to runtime consolidation:

1. Shared classes are mostly CSS conventions, not versioned component APIs.
2. Markup slots, required states, accessibility rules, and supported variants are not formally declared.
3. Generic and owner-specific selectors are mixed in Shell CSS, including known patterns such as Coverage, Product 360, approval inbox, QR, and Timecard selectors.
4. KPI and chart implementations have incompatible anatomy and density assumptions.
5. Responsive behavior is distributed across global media queries and owner CSS.
6. Some shared-looking components embed route, dashboard, or business assumptions.
7. Studio component/socket consumption is not connected to runtime and must remain disconnected until a contract exists.

## 4. Ownership Decision Matrix

| Component family | Shell/shared ownership | App/module ownership | Future Studio role | Decision |
|---|---|---|---|---|
| Cards | Generic surface, header/body/footer slots, elevation/state behavior, focus rules | Card purpose, content, business state, actions, owner-specific variants | Arrange/configure approved instances; edit owner artifacts | **Shared primitive + app-owned composite** |
| KPI cards | Generic metric anatomy, label/value/supporting text/trend/status slots, numeric accessibility | KPI definition, calculation, units, thresholds, tone meaning, drill target | Configure placement and approved display options; never define KPI truth | **Shared metric primitive + app-owned KPI semantics** |
| Dashboard tiles | Link-tile anatomy, disabled/current states, icon/badge slots, keyboard behavior | Capability meaning, target route, permission, label, badge data | Arrange visible authorized tiles through governed composition | **Shared tile renderer + app-owned declaration** |
| Tables | Table frame, overflow container, density, sticky-header option, empty/loading/error states, accessible action layout | Columns, data, sorting meaning, filters, row actions, permissions, business responsive rules | Edit approved column/layout metadata for owner resources | **Shared table primitives; app-owned table definition** |
| Chart containers | Frame, title/legend/action slots, loading/empty/error states, resize boundary, accessible fallback slot | Dataset, series, axes, aggregation, thresholds, visualization meaning | Configure approved chart presentation and placement; not query/data meaning | **Shared chart frame + app-owned visualization** |
| Quick links | Link/action-list anatomy, icon/description/badge slots, focus and confinement behavior | Link meaning, destination, permission, ordering candidates, localization | Arrange authorized links and prominence via governed experience composition | **Shared renderer + app-owned contribution** |
| Hero/meta cards | Generic summary/metric group anatomy and responsive wrapping | Summary meaning, values, units, statuses, drill actions | Configure approved summary composition for owner surface | **Shared summary primitive + app-owned semantics** |
| Action panels | Overlay/drawer lifecycle, focus, Escape, outside-click, scroll lock, placement, action-list anatomy | Action intent, permission, mutation contract, target, confirmation requirement | Arrange authorized actions; edit owner contribution metadata | **Shell-owned behavior + app-owned actions** |

## 5. Component-Specific Boundaries

### 5.1 Cards

Shell should own a small neutral surface primitive, not every named card class. A universal card contract may define:

- optional header, body, footer, media, and action slots
- static, linked, selectable, disabled, loading, and error states
- focus-visible and keyboard behavior
- density and container-responsive behavior

Names such as production card, payroll card, coverage card, queue card, or approval card remain owner components even when they use shared card anatomy.

### 5.2 KPI Cards

The universal unit is a **metric display primitive**, not a universal KPI definition.

Shell may render:

- label
- value
- unit
- supporting text
- trend
- status/tone
- drill link

The owner must define the calculation, source, freshness, thresholds, whether a positive trend is good or bad, and what a click means. Studio must never calculate or reinterpret KPI values.

### 5.3 Dashboard Tiles

Dashboard tiles are contribution renderers. Shell can own consistent tile anatomy and safe link behavior. The owner catalog must provide capability identity, route, permission, localized text, icon metadata, and optional status/badge data.

Workspace Profile and user overrides may shape visibility and order only after ACL filtering. Studio may edit that composition but cannot create capability authority.

### 5.4 Tables

A universal table contract should stop at rendering mechanics:

- frame and horizontal overflow
- table density
- sticky header support
- selection and row-action placement
- loading, empty, error, and pagination slots
- accessible labels and responsive fallback contract

Business tables remain owner-defined. Column meaning, joins, filters, row mutations, export behavior, and data authorization cannot move into Shell.

If a richer data-grid engine is introduced, its technical mechanics may be a Platform service, while the visible shared component contract remains Shell-owned and the data definition remains app/module-owned.

### 5.5 Chart Containers

Shell may own a chart frame but should not own chart semantics or a universal business chart.

The shared frame may govern:

- title, description, legend, and action slots
- sizing and resize behavior
- loading, empty, and error states
- accessible tabular/text fallback
- print/export containment

Apps/modules own chart type selection where it conveys meaning, series and axes, aggregations, thresholds, units, color semantics, and data access. A future Platform chart engine may provide technical rendering without taking business ownership.

### 5.6 Quick Links

Quick links should use a shared link collection or action-list renderer. Owners declare authorized candidates; Shell normalizes routes and confines wrappers. Resolved Experience determines final visibility.

Studio may later edit ordering, grouping, pinning, and prominence within the authorized set.

### 5.7 Hero/Meta Cards

`hero-meta-card` demonstrates reusable summary anatomy, but the class name currently mixes presentation and page-composition vocabulary. A future contract should define neutral summary primitives rather than making “hero” a universal business component.

The owner retains all metric and status meaning.

### 5.8 Action Panels

Action-panel behavior is the clearest Shell-owned family because it crosses overlay, z-index, focus, scroll-lock, Escape, and wrapper-confinement contracts.

Shell should own the panel/drawer behavior and generic action-list rendering. Apps/modules own every action declaration, permission, mutation endpoint, confirmation policy, and business consequence. Studio may arrange actions only within those declarations.

## 6. Proposed Universal Component Contract Scope

A future contract should define:

1. Stable component keys independent of incidental CSS class names.
2. Required and optional slots.
3. State vocabulary.
4. Accessibility requirements.
5. Theme token dependencies.
6. Responsive/container behavior.
7. Allowed owner extension points.
8. Contribution metadata shape where components render owner declarations.
9. Wrapper confinement and authorization requirements for interactive components.
10. Print, display, loading, empty, error, and disabled behavior.
11. Versioning and deprecation policy.
12. Studio editability, risk, approval, preview, and handover metadata.

The contract should not initially define a generalized visual page builder, arbitrary CSS overrides, business data queries, or owner-independent action creation.

## 7. Readiness Classification

**Classification B: Contract definition is ready; implementation consolidation is not ready.**

Evidence supports writing a Universal Component Contract because shared demand and ownership boundaries are clear. Implementation should wait until the contract identifies a minimal primitive set and separates current owner-specific selectors from genuine shared anatomy.

The initial contract should cover:

1. Surface/Card
2. Metric/Summary
3. Link Tile
4. Table Frame
5. Chart Frame
6. Link/Action List
7. Overlay Action Panel

It should not standardize current owner-specific composites merely because they use card-shaped presentation.

## 8. Recommended Next Phase

Proceed with a documentation-only **Universal Component Contract V1** before code migration.

That phase should:

1. Inventory existing shared and owner-prefixed implementations by component family.
2. Define the minimal primitive APIs and accessibility baseline.
3. Mark current global classes as stable, compatibility-only, or owner-leakage debt.
4. Define app/module contribution schemas for tiles, metrics, links, and actions.
5. Define Studio as a future governed editor of owner resources and composition metadata.
6. Define diagnostics before moving selectors or markup.

Do not yet:

- move CSS
- rename classes
- consolidate markup
- redesign dashboards
- introduce a component runtime registry
- enable Studio runtime component consumption
- migrate owner data or business semantics into Shell

## 9. Final Recommendation

The next platform layer after **Theme values** and **Shell behavior** is **shared component structure**.

Ownership should be:

- **Shell/shared:** neutral anatomy, states, accessibility, interaction, responsive behavior, and safe composition.
- **Apps/modules:** business semantics, data, labels, thresholds, columns, chart meaning, routes, permissions, and actions.
- **Studio later:** governed editing and composition of approved owner-owned component resources, never runtime ownership.

This layered model resolves the apparent conflict between a Shell universal component catalog and app-owned dashboards: Shell owns the reusable grammar; apps and modules own what the components say and do.

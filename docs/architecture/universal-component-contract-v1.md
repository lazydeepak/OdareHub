# Universal Component Contract V1

**Status:** Active architecture contract; implementation not authorized by this document
**Date:** 2026-06-05
**Owner:** Shell + Tooling/System Tools governance
**Contract version:** `universal-component.v1`
**References:**

- `docs/architecture/universal-component-contract-readiness-audit.md`
- `docs/architecture/shell-behavior-rendering-contract-v1.md`
- `docs/architecture/theme-source-compilation-migration.md`
- `docs/architecture/ODAREHUB-OS-ARCHITECTURE-CHARTER-V1.md`
- `docs/architecture/surface-contribution-contract.md`
- `docs/architecture/studio-operating-contract.md`

## 1. Purpose

This contract defines the platform layer between Theme-owned values and app/module-owned business surfaces.

The Universal Component layer provides neutral, reusable component structure and behavior that can be consumed across Admin, Operator, Display, Studio, app, module, and plugin surfaces without moving business ownership into Shell.

Canonical model:

```text
Theme values
  -> Shell universal component structure and behavior
  -> App/module-owned data, meaning, labels, and actions
  -> Resolved Experience visibility and shaping
  -> Runtime rendering
```

Studio may later inspect, compose, and edit approved component resources through governed workflows. Studio does not become runtime truth or the owner of component semantics.

This document defines ownership and component contracts only. It does not authorize CSS changes, markup migration, runtime registries, or UI redesign.

## 2. Ownership Laws

### 2.1 Shell Owns Neutral Structure And Behavior

Shell owns:

1. Stable universal component identities.
2. Neutral component anatomy and slots.
3. Shared state vocabulary.
4. Keyboard and focus behavior.
5. Generic responsive and container behavior.
6. Loading, empty, disabled, and error presentation mechanics.
7. Wrapper confinement behavior for links and actions.
8. Overlay lifecycle for overlay-based components.
9. Shared accessibility requirements.
10. Theme-token consumption points.

Shell must remain generic. It must not define Manufacturing, SBAIO, Payroll, Procurement, Platform governance, or other owner-specific component meaning.

### 2.2 Theme Owns Values Only

Theme owns values such as:

- colors and semantic tones
- spacing values
- radius values
- typography values
- border values
- shadows and depth values
- blur values
- transition durations and easing
- light/dark and style-variant overrides

Theme does not own:

- component markup
- component slot definitions
- interaction behavior
- responsive mechanics
- accessibility behavior
- business labels
- data shape
- routes or actions
- permissions or workflow meaning

Universal components consume approved foundation and semantic tokens. They must not require Theme to define component selectors.

### 2.3 Apps And Modules Own Business Meaning

Apps/modules own:

1. Data retrieval and calculation.
2. Business labels and localization keys.
3. Metric and status meaning.
4. Table columns and row semantics.
5. Chart series, axes, aggregation, and visualization meaning.
6. Routes and drill targets.
7. Action intent and mutation behavior.
8. Permission requirements.
9. Workflow states and consequences.
10. Owner-specific component variants and CSS.

An app/module may compose a semantic component from universal primitives. The result remains app/module-owned when its identity or behavior depends on business meaning.

### 2.4 Studio Is A Governed Future Composer

Studio may later:

- inspect universal component contracts
- arrange approved component instances
- edit owner-owned component declarations
- preview and diff component composition
- apply approved changes to owner artifacts
- keep drafts, approvals, snapshots, and handover records

Studio must not:

- own runtime component truth
- define business calculations
- grant permissions
- create owner-independent actions
- store hidden runtime component definitions
- make Shell or business apps depend on Studio drafts

### 2.5 Core Owns No Presentation Components

Core must not own:

- component templates
- component CSS
- card, table, chart, badge, or panel rendering
- component registries containing presentation meaning
- component responsive behavior
- component composition

Core may provide low-level primitives used by runtime systems, but presentation ownership remains outside Core.

## 3. Common Component Contract

Every universal component defined by this contract follows these rules.

### 3.1 Stable Identity

| Component | Contract key |
|---|---|
| Card | `shell.component.card.v1` |
| KPI Card | `shell.component.kpi-card.v1` |
| Dashboard Tile | `shell.component.dashboard-tile.v1` |
| Chart Container | `shell.component.chart-container.v1` |
| Table Wrap | `shell.component.table-wrap.v1` |
| Quick Link Card | `shell.component.quick-link-card.v1` |
| Hero / Meta Card | `shell.component.hero-meta-card.v1` |
| Action Panel | `shell.component.action-panel.v1` |
| Empty State | `shell.component.empty-state.v1` |
| Status Badge / Chip | `shell.component.status-badge.v1` |

Contract keys are architecture identities. They do not require a runtime component registry in V1.

### 3.2 Common States

Where applicable, universal components may support:

- `default`
- `hover`
- `focus-visible`
- `active`
- `selected`
- `current`
- `disabled`
- `loading`
- `empty`
- `error`
- `readonly`

Business workflow states such as approved, dispatched, overdue, blocked, paid, rejected, or machine-down remain owner meanings. Owners may map those meanings to shared semantic tones without transferring the meanings to Shell.

### 3.3 Theme Token Categories

Universal components may consume approved tokens from these categories:

- surface/background
- primary and muted text
- border and divider
- accent and focus
- success, warning, danger, and informational tone
- spacing and density
- radius
- shadow and depth
- typography
- transition
- blur, where allowed by the Shell rendering contract
- safe-area values for viewport-edge components
- z-index layer tokens for overlay components

Components must not hardcode a separate design system or use a business-domain token as a universal dependency.

### 3.4 Responsive Rules

1. Components are fluid by default.
2. Component-level adaptation should be container-aware.
3. Shell viewport breakpoints are reserved for wrapper-level behavior.
4. Components must not assume a fixed dashboard width.
5. Content must remain readable without horizontal viewport overflow.
6. Owner-specific responsive changes stay in owner CSS.
7. Display/kiosk variants may use owner-defined density when required by the owning surface.

### 3.5 Accessibility Rules

1. Semantic HTML is preferred over generic containers.
2. Interactive components must be keyboard operable.
3. Focus-visible state must be present and token-driven.
4. Labels and accessible names come from localized owner content.
5. Color must not be the only carrier of meaning.
6. Loading and state changes must be exposed appropriately to assistive technology.
7. Disabled appearance must match actual interaction state.
8. Reduced-motion preferences must be respected.
9. Components must preserve logical reading and tab order.
10. Charts and data-dense components require accessible alternatives.

### 3.6 Contribution And Authorization Rules

1. Owner declarations provide meaning, targets, and permission requirements.
2. ACL remains the hard authorization owner.
3. Workspace Profile and user overrides may shape authorized components.
4. Shell normalizes links/actions and enforces wrapper confinement.
5. Display components remain readonly and actionless.
6. A shared component must never make an unauthorized capability visible or executable.

## 4. Card

**Contract key:** `shell.component.card.v1`

### Purpose

Provide a neutral content surface that groups related information or controls without defining what that content means.

### Shell-Owned Structure

- optional header slot
- optional title and description slots
- body slot
- optional media slot
- optional footer slot
- optional action region
- static, linked, selectable, loading, disabled, and error mechanics
- generic spacing, focus, and container behavior

### App-Owned Data And Meaning

- card purpose and title
- business content and data
- status meaning
- actions and routes
- permissions
- owner-specific variants
- localization

### Allowed Theme Tokens

- surface and raised-surface values
- text and muted-text values
- border/divider values
- spacing, radius, shadow, and transition values
- focus and semantic tone values

### Responsive Behavior

- body content reflows naturally
- header and actions may wrap
- no fixed minimum width unless defined by an owner layout
- linked cards retain full keyboard access
- owner grids determine placement and span

### Accessibility Expectations

- use `article`, `section`, `a`, or another appropriate semantic element
- heading levels follow page hierarchy
- linked cards have one unambiguous accessible target
- loading and error states are announced when dynamically updated
- disabled cards must not remain operable

### Forbidden Ownership Violations

- Shell defining business card names or labels
- Theme defining card markup or behavior
- owner-specific card selectors added to Shell as universal rules
- Card granting access through visibility alone
- Studio storing live card truth
- Core rendering cards

## 5. KPI Card

**Contract key:** `shell.component.kpi-card.v1`

### Purpose

Display one primary metric with enough context to interpret it safely.

### Shell-Owned Structure

- label slot
- primary value slot
- optional unit slot
- optional supporting text slot
- optional trend slot
- optional status/tone slot
- optional freshness slot
- optional drill target
- loading, unavailable, and error mechanics

### App-Owned Data And Meaning

- metric definition and calculation
- source and freshness rules
- units and formatting
- thresholds
- trend direction meaning
- semantic tone mapping
- drill route and permission
- localized labels

### Allowed Theme Tokens

- card surface and border values
- primary, muted, and emphasized text
- numeric typography values
- semantic tone values
- spacing, radius, shadow, focus, and transition values

### Responsive Behavior

- value and unit remain associated
- long values may scale or wrap without clipping
- supporting text moves below the value when constrained
- KPI collections use owner or Shell-approved intrinsic grids
- no assumption that all KPI cards have equal content height

### Accessibility Expectations

- label and value have a clear reading order
- abbreviations and units are understandable
- trends are expressed in text, not only arrows or color
- unavailable data is distinguished from a numeric zero
- drill behavior is keyboard accessible
- auto-refresh does not cause disruptive announcements

### Forbidden Ownership Violations

- Shell calculating a KPI
- Shell assigning business thresholds
- Theme deciding whether a value is good or bad
- Studio defining metric truth
- KPI visibility bypassing ACL
- Core owning metric presentation

## 6. Dashboard Tile

**Contract key:** `shell.component.dashboard-tile.v1`

### Purpose

Render an authorized capability or destination as a consistent dashboard entry tile.

### Shell-Owned Structure

- primary target region
- label slot
- optional description slot
- optional icon slot
- optional badge/status slot
- optional metadata/footer slot
- current, disabled, unavailable, and focus states
- wrapper-safe link normalization

### App-Owned Data And Meaning

- capability identity
- target route
- permission requirements
- localized label and description
- icon meaning
- badge data
- lifecycle availability
- business grouping candidate

### Allowed Theme Tokens

- interactive surface values
- text, muted-text, accent, and focus values
- border, spacing, radius, shadow, and transition values
- semantic status values

### Responsive Behavior

- tile grids use intrinsic sizing
- labels and descriptions wrap
- icon and badge placement must not reduce target clarity
- narrow layouts may stack metadata below the primary label
- touch targets remain usable

### Accessibility Expectations

- the primary destination is a semantic link
- accessible name identifies the destination
- current state uses `aria-current` where applicable
- disabled/unavailable tiles are not misleadingly interactive
- badges supplement rather than replace text

### Forbidden Ownership Violations

- Shell inventing capability meaning
- a tile granting permission
- raw owner routes emitted without Shell confinement checks
- Workspace Profile exposing ACL-denied tiles
- Studio creating a capability by adding a tile
- Core owning dashboard tile composition

## 7. Chart Container

**Contract key:** `shell.component.chart-container.v1`

### Purpose

Provide a stable, accessible frame for an owner-defined visualization.

### Shell-Owned Structure

- title slot
- optional description slot
- plot region
- optional legend slot
- optional control/action slot
- loading, empty, unavailable, and error states
- accessible fallback region
- resize and print containment

### App-Owned Data And Meaning

- dataset and query
- aggregation
- chart type when it conveys meaning
- series and axes
- units
- thresholds and annotations
- legend labels
- color semantics
- refresh behavior
- drill actions and permissions

### Allowed Theme Tokens

- surface, plot-surface, border, and divider values
- text and muted-text values
- chart palette and semantic tone values
- spacing, radius, shadow, typography, focus, and transition values

### Responsive Behavior

- plot adapts to container size
- labels and legends may reflow
- controls wrap without covering the plot
- owner defines minimum meaningful plot dimensions
- a readable fallback is available when visualization cannot fit
- print/export layout must not clip essential information

### Accessibility Expectations

- chart has an accessible name and description
- essential findings are available as text or structured data
- color is not the only series distinction
- legends and controls are keyboard accessible
- animations respect reduced-motion preferences
- canvas/SVG content has an accessible fallback

### Forbidden Ownership Violations

- Shell defining business series or aggregations
- Theme defining chart meaning
- universal colors carrying owner-specific meaning without declaration
- Studio generating live report queries
- charts bypassing data authorization
- Core owning chart presentation

## 8. Table Wrap

**Contract key:** `shell.component.table-wrap.v1`

### Purpose

Provide safe overflow, density, state, and action-placement mechanics around an owner-defined data table.

### Shell-Owned Structure

- scroll/overflow frame
- optional caption/title region
- optional toolbar/filter slot
- table region
- optional pagination region
- optional bulk-action region
- loading, empty, and error states
- sticky-header and density mechanics
- generic responsive fallback contract

### App-Owned Data And Meaning

- columns and headings
- row data
- sorting and filtering meaning
- row identity
- row and bulk actions
- permissions
- pagination/query behavior
- exports
- business-specific responsive prioritization

### Allowed Theme Tokens

- table surface, header surface, row, hover, selected, and divider values
- text and muted-text values
- spacing, typography, focus, radius, shadow, and transition values
- semantic status values for owner-declared states

### Responsive Behavior

- horizontal overflow is contained
- header and row alignment remains intact
- owner may define priority columns or a card fallback
- controls remain reachable on touch devices
- sticky behavior must respect Shell topbar and scroll containers
- responsive transformation must not change data meaning

### Accessibility Expectations

- use semantic `table`, `caption`, `thead`, `tbody`, `th`, and `td`
- header associations are preserved
- sortable columns expose sort state
- row actions have specific accessible names
- selected rows expose selected state
- empty/loading/error states are distinguishable
- a visual card fallback must preserve relationships and reading order

### Forbidden Ownership Violations

- Shell defining business columns
- Shell owning row mutation logic
- Theme defining sorting or filtering behavior
- generic responsive rules silently hiding required business data
- Studio becoming the live data-query owner
- Core implementing table presentation

## 9. Quick Link Card

**Contract key:** `shell.component.quick-link-card.v1`

### Purpose

Render a concise, authorized shortcut to a destination or non-mutating entry action.

### Shell-Owned Structure

- primary link region
- label slot
- optional description slot
- optional icon slot
- optional badge/metadata slot
- optional grouping renderer
- focus, current, disabled, and unavailable states
- wrapper-safe target normalization

### App-Owned Data And Meaning

- destination and intent
- label and description
- icon meaning
- permission requirement
- ordering candidate
- grouping candidate
- availability and badge data

### Allowed Theme Tokens

- interactive surface, text, muted-text, accent, border, and focus values
- spacing, radius, shadow, and transition values
- semantic status values

### Responsive Behavior

- link collections reflow through intrinsic grids or lists
- text wraps without truncating essential meaning
- touch target size remains sufficient
- icons never become the only label
- owner-specific ordering is preserved unless shaped by Resolved Experience

### Accessibility Expectations

- use semantic links for navigation
- accessible name communicates destination or intent
- current destination is exposed when relevant
- badges have textual meaning
- disabled shortcuts are not focusable unless an explanation is available

### Forbidden Ownership Violations

- Shell inventing shortcuts from business routes
- quick links bypassing wrapper confinement
- quick links creating mutation behavior without an action contract
- Workspace Profile or Studio granting unauthorized access
- Theme owning destination meaning
- Core composing quick links

## 10. Hero / Meta Card

**Contract key:** `shell.component.hero-meta-card.v1`

### Purpose

Present compact high-level metadata or summary values near a page heading or primary context.

### Shell-Owned Structure

- label slot
- value slot
- optional unit slot
- optional supporting text slot
- optional status slot
- optional drill target
- summary-group wrapping behavior
- loading and unavailable states

### App-Owned Data And Meaning

- summary definition
- value and format
- units
- status meaning
- context relationship
- drill action
- localization
- permission requirements

### Allowed Theme Tokens

- subtle/raised surface values
- primary, muted, and emphasized text
- border, spacing, radius, shadow, focus, and transition values
- semantic tone values

### Responsive Behavior

- summary groups wrap fluidly
- labels remain associated with values
- long text does not force viewport overflow
- compact layouts may stack label above value
- component remains secondary to the page heading

### Accessibility Expectations

- label/value relationships are explicit
- repeated summaries have distinguishable labels
- interactive cards are semantic links or buttons
- status is expressed textually
- heading hierarchy is not fabricated inside every meta card

### Forbidden Ownership Violations

- Shell defining what a page summary means
- generic hero components absorbing app page composition
- Theme defining metadata semantics
- Studio changing source calculations
- hidden permission checks inside presentation
- Core rendering page metadata cards

## 11. Action Panel

**Contract key:** `shell.component.action-panel.v1`

### Purpose

Present a wrapper-safe collection of authorized actions in a Shell-governed overlay, drawer, popover, or anchored panel.

### Shell-Owned Structure

- trigger relationship
- title/description region
- action-list region
- optional grouping and separator mechanics
- close control
- backdrop and overlay placement when required
- focus management
- Escape and outside-click behavior
- scroll lock
- open/close state
- return-focus behavior

### App-Owned Data And Meaning

- action intent
- label and description
- target or handler
- mutation contract
- permission requirements
- confirmation and risk level
- disabled/unavailable reason
- audit requirements
- business consequences

### Allowed Theme Tokens

- raised/overlay surface values
- text, muted-text, accent, and focus values
- border, spacing, radius, shadow, blur, and transition values
- backdrop values
- semantic risk/status values
- Shell z-index and safe-area tokens

### Responsive Behavior

- anchored panel may become a drawer on narrow viewports
- action list remains scrollable within the viewport
- safe areas and bottom navigation are respected
- open panel does not cause page-width shifts
- Shell breakpoint and overlay contracts govern placement

### Accessibility Expectations

- trigger exposes expanded state and controls relationship
- panel has an accessible name
- focus moves into the panel when modal behavior applies
- Escape closes the panel
- focus returns to the trigger
- actions are semantic buttons or links
- destructive actions are clearly identified and confirmed as owner policy requires

### Forbidden Ownership Violations

- Shell defining business actions
- actions executing without server-side authorization
- app-owned overlays bypassing Shell overlay governance
- Theme owning open/close behavior
- Studio creating actions outside owner catalogs
- Core owning action-panel presentation

## 12. Empty State

**Contract key:** `shell.component.empty-state.v1`

### Purpose

Explain the absence of content and provide a safe next step when one is authorized and meaningful.

### Shell-Owned Structure

- optional icon/illustration slot
- title slot
- description slot
- optional primary action slot
- optional secondary action/help slot
- compact and full-region layouts
- distinction between empty, filtered-empty, unavailable, and error states

### App-Owned Data And Meaning

- reason content is absent
- localized title and description
- whether creation or recovery is allowed
- next-step actions
- permissions
- domain-specific guidance

### Allowed Theme Tokens

- subtle surface values
- text, muted-text, accent, and focus values
- spacing, radius, border, illustration/icon, and transition values
- semantic information/warning/error values where appropriate

### Responsive Behavior

- content remains centered or context-aligned without fixed dimensions
- actions wrap or stack on narrow containers
- descriptions retain readable line length
- compact empty states fit inside cards, tables, and panels

### Accessibility Expectations

- state is expressed in text
- dynamic empty states use an appropriate status announcement
- actions are keyboard accessible
- decorative illustrations are hidden from assistive technology
- errors are not mislabeled as ordinary emptiness

### Forbidden Ownership Violations

- Shell writing business-specific empty-state guidance
- empty states exposing unauthorized create actions
- Theme deciding recovery behavior
- Studio inventing next-step capability
- empty state replacing server-side error handling
- Core owning empty-state presentation

## 13. Status Badge / Chip

**Contract key:** `shell.component.status-badge.v1`

### Purpose

Display a compact textual status, category, count, or state marker using shared visual mechanics.

### Shell-Owned Structure

- text slot
- optional icon slot
- optional count slot
- optional removable/selectable behavior for filter-chip use
- neutral and semantic tone presentation
- compact sizing and focus mechanics

### App-Owned Data And Meaning

- status/category meaning
- localized label
- mapping from business state to semantic tone
- count value
- filter/removal behavior
- permissions when interaction changes state

### Allowed Theme Tokens

- neutral and semantic tone surfaces, borders, and text values
- spacing, radius, typography, focus, and transition values
- icon values

### Responsive Behavior

- text may wrap only where the owning layout permits
- collections wrap without overlap
- counts remain legible
- interactive chips maintain usable touch targets
- badges must not force table columns or cards beyond their containers

### Accessibility Expectations

- status is readable as text
- color and icon are supplemental
- interactive chips use semantic buttons
- selected/removable states are exposed
- counts have enough context to be understood
- abbreviations have accessible expansion where needed

### Forbidden Ownership Violations

- Shell assigning business status meaning
- raw business status strings becoming global CSS selectors
- Theme mapping workflow states to behavior
- interactive chips mutating without authorization
- Studio redefining owner status taxonomies
- Core owning badge presentation

## 14. Universal Component Extension Rules

Apps/modules may extend universal components when:

1. The universal primitive remains recognizable and accessible.
2. The extension is owner-scoped.
3. Business selectors remain in owner CSS.
4. Theme values are consumed through approved tokens.
5. Wrapper confinement and authorization remain intact.
6. Owner-specific responsive behavior does not alter Shell chrome.
7. The extension does not silently redefine the universal contract.

An owner component should not be promoted to the universal catalog merely because several screens use it. Promotion requires proof that its structure and behavior are domain-neutral.

## 15. Versioning And Compatibility

1. V1 contract keys are stable architecture identifiers.
2. Existing CSS classes are compatibility implementations, not automatically canonical APIs.
3. A future implementation plan must classify current selectors as:
   - canonical universal candidate
   - compatibility-only
   - owner-specific
   - owner-leakage debt
4. Breaking anatomy or state changes require a new contract version or documented migration.
5. Deprecation must include ownership, replacement, validation, and removal checkpoints.
6. Runtime must not consume Studio drafts as a compatibility mechanism.

## 16. Validation And Future Enforcement

This contract is documentation-only in V1 Prompt 1.

Future diagnostics may validate:

- required contract references
- universal selector ownership
- owner-specific selector leakage into Shell
- Theme values-only boundaries
- accessible state coverage
- wrapper confinement for interactive components
- display readonly behavior
- Studio runtime disconnection
- Core presentation-component absence

Diagnostics must be read-only and must not rewrite components or owner artifacts.

## 17. Non-Goals

This contract does not authorize:

1. CSS refactoring.
2. Component rewrites.
3. Visual redesign.
4. Studio implementation.
5. App dashboard redesign.
6. Selector renaming.
7. Markup consolidation.
8. A runtime component registry.
9. A generalized page builder.
10. Business data/query migration into Shell.
11. Core changes.
12. Automatic promotion of existing classes to canonical components.

## 18. Contract Decision

Universal Component Contract V1 establishes:

- **Theme owns values.**
- **Shell owns neutral reusable structure and behavior.**
- **Apps/modules own business semantics, data, labels, actions, permissions, and workflow meaning.**
- **Studio may later compose approved owner components but does not own runtime truth.**
- **Core owns no presentation components.**

The contract is ready for future inventory and enforcement planning. Runtime consolidation is not authorized by this document.

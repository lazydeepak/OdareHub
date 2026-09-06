# View Composition Contract V1 Readiness Audit

**Status:** Architecture discovery audit; no contract or implementation authorized
**Date:** 2026-06-05
**Classification:** B. Partial readiness

## 1. Current State

Susankhya OS has most of the ownership foundations needed for view composition, but it does not yet have one canonical definition of a composed View.

The current word **View** is overloaded across four levels:

1. A route-backed page rendered by a PHP template.
2. A business or operational Surface such as a dashboard, queue, detail page, or work area.
3. A contribution-level render shape identified by `view_kind`.
4. A future Studio view manifest describing route, wrapper, layout kind, data, security, and localization.

These meanings are related, but they are not interchangeable.

Current runtime assembly is hybrid:

- app/module controllers assemble page data and select owner templates
- Shell composers assemble Admin, Operator, and Display host experiences
- apps/modules contribute cards, widgets, actions, and monitoring sections through host-surface services
- `HostSurfaceRegistryService` normalizes contribution taxonomy and orders provider output
- Workspace Profiles shape selected navigation, quick actions, dashboard blocks, and widget-discovery behavior
- per-user override artifacts shape visibility for selected runtime token families
- `ResolvedExperienceConsumerService` supplies visibility decisions to parts of the Admin, Operator, and Display runtime
- many page and dashboard layouts remain explicit PHP template order rather than resolved composition data

Composition is therefore **partly explicit and partly implicit**.

Explicit composition exists where declarations carry:

- `surface_key`
- `widget_key`
- `view_kind`
- `widget_type`
- `placement_zone`
- `interaction_profiles`
- `priority` or `weight`
- owner, route, permission, and empty-state metadata

Implicit composition remains where:

- a template includes sections in fixed source order
- a composer chooses templates through focus flags
- a controller constructs dashboard sections as nested arrays
- a page assigns placement through markup location rather than a stable region key
- a profile stores token lists without a complete component-instance or region model

The current platform can answer many visibility questions, but it cannot yet produce one portable answer to:

> Which View is being rendered, which Surfaces and component instances occupy each region, why are they there, in what order, and which owner supplied every decision?

## 2. Existing Contracts

The following contracts provide a strong foundation.

### Architecture Charter

`docs/architecture/SUSANKHYA-OS-ARCHITECTURE-CHARTER-V1.md` establishes:

- Core is locked
- Shell is generic
- apps own business logic
- apps contribute and Shell composes
- ACL authorizes only
- Workspace Profile shapes experience
- runtime consumes resolved or compiled contracts
- Studio edits but does not own runtime truth
- no hidden duplicate source of truth

### Surface Contribution Contract

`docs/architecture/surface-contribution-contract.md` defines contribution ownership and the pipeline:

```text
App or Module contribution declarations
  -> ACL authorization filter
  -> Workspace Profile shaping
  -> User override shaping
  -> Resolved Experience visibility decision
  -> Shell normalization and confinement
  -> Route-wrapper renderer
```

It establishes owner meaning, Shell normalization, resolved visibility, route confinement, readonly display behavior, owner-scoped assets, and contribution metadata.

### Surface Contribution Runtime Contract

`docs/widget-contribution-runtime-contract.md` defines contribution-level taxonomy:

- `view_kind`
- `widget_type`
- `placement_zone`
- `interaction_profiles`
- `priority` or `weight`

This is useful for items inside a host surface. It does not define a complete page-level View composition.

### Universal Component Contract

`docs/architecture/universal-component-contract-v1.md` defines neutral component anatomy and behavior for cards, KPI cards, dashboard tiles, chart containers, table wraps, quick links, hero/meta cards, action panels, empty states, and badges.

It answers what a shared component is. It does not answer which component instance belongs in which View region.

### View Architecture Vocabulary

`docs/access-control-view-architecture.md` distinguishes:

- Surface
- View Suite
- Interaction Profile
- Access Authority
- Control Scope
- Permission Profile

This vocabulary remains useful, but the document is superseded for current experience resolution by `docs/experience-composition-architecture-plan.md`.

### Experience Resolution

`docs/experience-composition-architecture-plan.md` and `docs/resolved-experience-contract.md` establish:

- owner capability catalogs
- ACL filtering
- Workspace Profile shaping
- per-user overrides
- resolved visibility
- runtime rendering

The current resolved contract is primarily a capability and visibility envelope. It does not yet carry a full ordered region tree or component-instance graph.

### Future View Manifest

`docs/contracts/erp-app-studio/view-manifest.md` is a draft future manifest covering:

- route and owner identity
- surface and wrapper
- broad layout kind
- data intent
- security
- localization

It is intentionally minimal and does not define regions, Surface placement, component bindings, responsive composition, ordering precedence, or resolved runtime output.

### Contract Coverage Conclusion

Existing contracts define the adjacent layers well:

```text
owner capability
  -> authorization and visibility
  -> contribution metadata
  -> universal component anatomy
  -> runtime wrapper
```

The missing layer is the canonical composition object between a visible capability and its rendered component tree.

## 3. Composition Model Findings

### What A View Currently Is

At runtime, a View is usually a route-selected PHP template plus data prepared by a controller or Shell composer.

For app-owned pages, the app/controller generally owns:

- route meaning
- data loading
- labels and actions
- section arrays
- template selection
- fixed page ordering

For host experiences, Shell generally owns:

- wrapper and route-family rendering
- composer orchestration
- contribution collection
- link/action normalization
- renderer selection
- final template assembly

There is no stable runtime `ViewDefinition` or `ResolvedViewComposition` object shared by these paths.

### How Views Are Assembled

Admin composition uses `AdminSurfaceComposer` to combine:

- My Work data
- host-surface regions
- Shell/Platform quick links
- admin dashboard panels
- resolved dashboard block and plugin-card visibility
- admin templates and owner rendering services

Operator composition uses `OperatorSurfaceComposer` to combine:

- Shell navigation
- Workspace Profile quick actions
- role-aware dashboard data
- owner adapters
- focus flags
- direct template includes from Shell and app-owned operator folders

App dashboards such as Manufacturing and SBAIO commonly use controllers to build arrays and templates to render those arrays in source order.

### How Surfaces Are Attached

Host-surface contributions are attached through `core_app_hooks` and app contribution services. The registry matches a declared host `surface` and `region`, invokes the provider, normalizes items, filters route access, and sorts the result.

This is the strongest existing composition mechanism, but it is scoped to contributed host regions. It is not yet the universal page-composition model.

App-owned page sections are usually attached by direct controller/template agreement. They do not consistently declare stable Surface or region identities.

### How Components Appear Inside Surfaces

Components currently appear through three patterns:

1. Direct markup in owner templates.
2. Structured arrays rendered by owner or Shell templates.
3. Contribution objects normalized by a host-surface registry.

The Universal Component Contract gives these structures a future common component vocabulary, but current declarations do not consistently reference universal component contract keys.

### Explicit Versus Implicit Composition

| Concern | Current condition |
|---|---|
| Capability identity | Increasingly explicit in owner catalogs |
| Contribution identity | Explicit for many host widgets/cards |
| Page/View identity | Mixed route, template, and historical Surface terms |
| Region identity | Explicit in host hooks; implicit in many templates |
| Component identity | Defined architecturally, not consistently declared at runtime |
| Placement | `placement_zone` for contributions; markup order elsewhere |
| Visibility | Increasingly resolved through ACL/Profile/override |
| Ordering | Mixed weights, app priority, profile arrays, and source order |
| Data binding | Owner-specific arrays/adapters; no universal binding contract |
| Responsive composition | CSS/template behavior; no composition-level rule |
| Provenance | Partial owner metadata; no complete resolved composition trace |

## 4. Ownership Findings

### Surface Definitions

Business and operational Surface definitions belong to the owning app or module.

Examples:

- Manufacturing owns production, demand, quality, assembly, dispatch, and materials Surfaces.
- SBAIO owns staff, attendance, timecard, payroll, leave, sales, expense, and task Surfaces.
- Platform owns governance and system-administration Surfaces.
- Shell owns host/wrapper Surfaces such as the Admin, Operator, and Display composition frames, but not the business meaning rendered inside them.

Current owner services generally follow this direction, although historical `me` naming and Shell operator templates blur it.

### Surface Placement

Placement ownership is currently split:

- owners propose `region`, `placement_zone`, and weight metadata for contributions
- Shell defines available host regions and performs final host placement
- owner templates place app-local sections directly
- Workspace Profiles can select some dashboard blocks and navigation structures

The missing rule is a single distinction between:

- owner-declared eligible/default placement
- profile-selected placement
- user-adjusted placement
- Shell-normalized final placement

### Surface Visibility

Visibility ownership is the clearest area:

1. owner catalogs declare the capability and requirements
2. ACL authorizes or denies
3. Workspace Profile shapes the authorized experience
4. user override may hide or arrange within that set
5. Resolved Experience is final visibility truth
6. runtime consumes the resolved result

Remaining template checks and compatibility fields are migration debt, not desired ownership.

### Surface Ordering

Ordering has no single owner or precedence rule today.

Current ordering sources include:

- provider `priority` and `weight`
- host-hook `order`
- assigned-app priority
- module-visibility priority
- Workspace Profile array order
- per-user token order
- dashboard block order maps
- controller array order
- PHP template source order
- alphabetical fallback

`HostSurfaceRegistryService` has deterministic ordering for its own region output, but that algorithm is not a universal View ordering contract.

### Target Ownership Boundary

The future boundary should be:

- **Shell:** neutral View composition schema, region mechanics, component placement validation, wrapper confinement, final rendering, and responsive/accessibility composition rules.
- **Apps/modules:** View and Surface identities, business data, component instances, eligible regions, default ordering, labels, actions, permissions, and workflow meaning.
- **Workspace Profile:** role/persona selection, prominence, optional placement/order shaping, density, and interaction-profile choice within allowed owner declarations.
- **User override:** limited hide/show, pin, order, or arrangement inside the authorized and profiled set.
- **Resolved runtime:** compiled final View composition with provenance and denial reasons.
- **Studio:** future editor, preview, diff, validation, and governed apply workflow over owner/profile artifacts.
- **Core:** no presentation components or View composition truth.

## 5. Personalization Findings

### Workspace Profiles

Workspace Profiles are the intended role/persona experience-policy owner.

Current profile fields can shape:

- landing route
- navigation sections
- quick actions
- module visibility
- dashboard blocks
- widget discovery
- interaction-profile intent

Runtime consumption is uneven:

- operator navigation and quick actions have direct profile-aware paths
- admin/display block visibility uses resolved-experience consumers
- dashboard geometry and component placement are not generally profile-driven
- owner app dashboards often remain fixed compositions

Workspace Profiles are ready to select and prioritize declared composition resources, but they do not yet have a canonical View-region/component-instance schema to shape.

### Interaction Profiles

Interaction Profiles correctly describe UX posture rather than permission.

They appear in:

- architecture vocabulary
- Workspace Profile design
- contribution `interaction_profiles`
- widget filtering and normalization

However, the platform does not yet define how an Interaction Profile transforms a complete View composition.

Missing rules include:

- allowed action density changes
- summary-versus-queue prominence
- component variant selection
- readonly transformation
- drill-down posture
- density and information hierarchy
- conflict resolution with Workspace Profile and owner defaults

Interaction Profiles are therefore a useful input taxonomy, not yet a complete composition policy.

### User-Level Customization

`user_surface_overrides` is the preferred artifact path for migrated per-user visibility fields. It prevents continued reliance on inline assignment CSV fields.

Current customization is primarily token-based:

- visible/hidden operator views
- display panels
- admin dashboard blocks
- plugin cards

This is not yet a general user composition model. There is no canonical contract for:

- region movement
- span or size
- component configuration
- breakpoint-specific arrangement
- component duplication rules
- reset/inheritance behavior
- complete provenance

User customization should remain constrained and downstream of ACL and Workspace Profile. It must not create capabilities or business semantics.

## 6. Dashboard Findings

### Admin Dashboard

Admin dashboard composition is the most contract-oriented but also the most layered.

It combines:

- Shell `AdminSurfaceComposer`
- `HostSurfaceRegistryService` regions
- Platform admin panel services
- Manufacturing dashboard block services
- resolved dashboard-block and plugin-card visibility
- block order maps
- owner rendering callbacks
- template source order

Strengths:

- contribution ownership exists
- visibility can be resolved
- block keys and ordering metadata exist
- owner services render business content

Gaps:

- the full dashboard is not represented as one resolved View composition
- region names and block names are historical and dashboard-specific
- rendering alternates between contributions, service callbacks, and template branches
- placement and order precedence are not documented as one law

### Operator Dashboard

Operator composition is strongly Shell-orchestrated and highly explicit in code.

The dashboard contains:

- role-aware tiles built inside the composer
- owner-derived Manufacturing data
- a directly rendered chart
- overview cards
- profile quick actions
- focus-flag template selection

Strengths:

- wrapper confinement is clear
- profile quick actions are supported
- app-owned operator views can be resolved through a registry
- owner adapters isolate some business data

Gaps:

- Shell still constructs business-facing dashboard tile sets
- page sections are fixed template regions without stable Surface keys
- the dashboard is not assembled from a resolved region tree
- focus selection is a large conditional include model
- component placement is not contract-driven

### Manufacturing Dashboards

Manufacturing dashboards are app-owned, which is correct.

Composition usually occurs through:

- controller-built summaries and row sets
- fixed template cards, KPI rows, tables, and action bands
- module-specific dashboard services
- separate host-surface contribution providers for cross-surface reuse

Some data and widget declarations are reusable, especially through module widget registries and `ModuleSurfaceWidgetFactory`. Whole-dashboard composition is not reusable because most page structure remains embedded in templates.

Dashboard sections are sometimes represented as contribution Surfaces or placement zones, but app-local dashboard sections are not consistently modeled as stable Surfaces.

### SBAIO Dashboard

SBAIO builds a section/card hierarchy in its controller, filters cards through module visibility, and renders the resulting arrays in a fixed template.

Strengths:

- business meaning remains SBAIO-owned
- section and card data are structured
- module visibility can filter content
- owner host-surface contributions provide a separate reuse path

Gaps:

- section identity is mostly title-driven rather than stable-key driven
- section order and card order are controller array order
- component types are implied by the template
- the dashboard is not composed through Surface or universal-component declarations
- profile and user arrangement do not shape the app dashboard

### Dashboard Conclusion

Dashboards demonstrate that the platform has reusable **inputs**, but not yet a reusable **composition output**.

Current dashboard sections should not automatically be declared Surfaces. A future contract must distinguish:

- a page-level View
- a business Surface/capability
- a View region
- a contributed component instance
- a purely visual grouping

Treating every card group as a Surface would create excessive identity and ownership complexity.

## 7. Studio Readiness Findings

### Future View Editor Needs

A future View Editor would need:

1. Stable View identity and owner.
2. Route, wrapper, audience, and View Suite metadata.
3. A finite region/slot schema.
4. Stable Surface and component-instance identities.
5. Universal component contract references.
6. Owner-provided data-binding and action contracts.
7. Eligible/default placement rules.
8. Ordering, pinning, grouping, and span rules.
9. Interaction Profile compatibility.
10. Workspace Profile and user-override policy limits.
11. ACL and permission dependencies.
12. Responsive and accessibility constraints.
13. localization keys.
14. versioning and migration behavior.
15. provenance from owner default through resolved output.
16. preview parity with runtime.

### Future Home Composer Needs

A Home Composer would additionally need:

- dashboard/home region definitions
- candidate contribution discovery
- visibility and denial explanations
- role/profile variants
- per-user override boundaries
- readonly Display constraints
- route-wrapper confinement
- deterministic fallback when a component or provider disappears
- empty-region behavior
- conflict and duplicate handling

### Sufficiency Of Current Rules

Current rules are sufficient for Studio to inspect:

- owner capabilities
- contribution metadata
- Workspace Profile selections
- visibility diagnostics
- universal component definitions

They are not sufficient for Studio to safely edit a complete View composition.

Studio would otherwise have to infer structure from PHP templates, historical region names, controller arrays, and renderer-specific behavior. That would make Studio an accidental source of composition truth and violate the Charter.

Studio must not implement a View Editor or Home Composer until owner-owned composition artifacts and the resolved composition output exist independently of Studio.

## 8. Missing Contracts

### 8.1 Canonical View Identity

The platform needs one definition distinguishing:

- View
- Surface
- host Surface
- region/slot
- component instance
- visual grouping
- View Suite

Route path, template file, capability key, and View key must not remain interchangeable identifiers.

### 8.2 View Definition Shape

An owner-owned View definition needs at minimum:

- version
- stable View key
- owner type and key
- route or route family
- wrapper
- Surface/capability reference
- View Suite
- Interaction Profile compatibility
- region schema
- permission and module requirements
- localization
- lifecycle status

### 8.3 Region And Placement Contract

The contract must define:

- stable region keys
- region purpose
- allowed component types
- minimum/maximum cardinality
- eligible wrappers
- default placement
- movement constraints
- empty behavior
- nested-region policy

Existing `placement_zone` values can inform this work but do not cover all page-level composition needs.

### 8.4 Component Instance Contract

A component instance needs:

- stable instance key
- universal component contract key or owner-specific component key
- owner
- data adapter/binding reference
- labels and localization keys
- action and route references
- permission requirements
- state/empty behavior
- placement eligibility
- interaction-profile compatibility
- responsive constraints

### 8.5 Composition Precedence

The platform must document deterministic precedence:

```text
owner View defaults
  -> ACL exclusion
  -> Workspace Profile shaping
  -> user override shaping
  -> Shell normalization and confinement
  -> resolved composition
```

It must separately define precedence for visibility, placement, order, configuration, and component variants.

### 8.6 Resolved View Composition

Runtime needs a versioned, read-only output containing:

- View identity
- wrapper
- resolved regions
- ordered component instances
- visible and hidden items
- owner and source provenance
- applied profile and override
- normalized routes/actions
- diagnostics and fallback reasons

`ResolvedExperience` can be an input or parent envelope, but its current item list is not a full composition tree.

### 8.7 Dashboard Section Semantics

The platform needs rules for deciding whether a dashboard section is:

- a Surface
- a region
- a component instance
- a visual group inside a component

Without this rule, migration would either under-model reusable sections or over-model ordinary markup.

### 8.8 Ordering Ownership

Ordering needs a single law covering:

- owner default weight
- profile order
- user pin/order
- assigned-app priority
- region-local ordering
- tie breakers
- missing-item fallback
- duplicate suppression

### 8.9 Interaction Profile Transformation

The platform needs explicit composition effects for `worker`, `leader`, `admin`, `read_only`, and `display`, while preserving the rule that Interaction Profile never grants permission.

### 8.10 Runtime And Template Migration Boundary

A future contract must state how legacy templates participate:

- templates may remain render adapters
- contracts must not require immediate UI rewrites
- implicit regions can be inventoried before migration
- a contract key must not falsely claim runtime compliance before the renderer consumes it

## 9. Recommendation

### Classification

**B. Partial readiness**

Susankhya OS is ready to define the prerequisites for View Composition Contract V1, but it is not yet ready to formalize the full V1 contract without first resolving terminology and composition-shape gaps.

The architecture direction is sound:

- ownership boundaries are established
- owner contribution contracts exist
- visibility resolution exists
- Workspace Profiles and user overrides have defined roles
- universal component contracts exist
- Shell wrapper and rendering boundaries exist
- Studio governance boundaries exist

The blocking issue is not missing platform direction. It is the absence of a single canonical composition model shared by app Views, host dashboards, profiles, resolved runtime, and future Studio tools.

### Required Prerequisite Work

Complete these architecture-only prerequisites before View Composition Contract V1:

1. Publish a View/Surface/Region terminology and identity resolution.
2. Inventory current Admin, Operator, Display, Manufacturing, and SBAIO View regions and classify each as Surface, region, component instance, or visual group.
3. Define a draft owner-owned `ViewDefinition` shape.
4. Define region and component-instance identity requirements.
5. Define composition precedence for visibility, placement, order, and variants.
6. Define the relationship between `ResolvedExperience` and a future `ResolvedViewComposition`.
7. Reconcile host `placement_zone` taxonomy with page-level region needs.

### Should View Composition Contract V1 Be Next?

**Not immediately as a final V1 contract.**

The next architecture workstream should be a narrow **View Composition Model Foundation** covering terminology, identity, region inventory, precedence, and resolved-output shape. After that foundation is accepted, View Composition Contract V1 should be the next formal contract.

This recommendation does not authorize:

- runtime changes
- template or dashboard migration
- CSS changes
- Studio View Editor work
- Studio Home Composer work
- component rewrites
- app dashboard redesign

## 10. Post-Foundation Update (2026-06-05)

The prerequisite workstream recommended in this audit has now been completed through:

- `docs/architecture/view-composition-model-foundation.md`

Update to readiness:

- **Readiness classification for contract drafting:** **A. Contract-ready (foundation complete)**

Scope note:

- This update changes contract readiness only.
- It does not authorize runtime implementation, Studio implementation, CSS changes, dashboard redesign, component refactor, or personalization-engine rollout.
- app dashboard redesign


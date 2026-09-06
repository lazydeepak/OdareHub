# Surface Contribution Contract Baseline

Status: Active baseline for contribution ownership and composition safety.

Purpose: Define how Apps, Modules, and Plugins contribute resources to Shell and runtime surfaces without creating ownership drift, hidden visibility truth, or unsafe route/action behavior.

This document is contract guidance only. It does not authorize route redesign, runtime behavior changes, or feature additions.

## Charter Laws Protected

This baseline protects Charter v1 laws:

- Apps own business logic.
- Apps contribute; Shell composes.
- Runtime consumes resolved or compiled contracts.
- Studio edits but does not own runtime truth.
- System Tools maintain and validate but do not bypass governance.
- No hidden duplicate source of truth.

## Owner Layers

Contribution ownership is split by layer:

- App or Module owner: owns contribution meaning, data contract, business semantics, permission requirements, and owner-scoped assets.
- Shell owner: owns composition pipeline execution, wrapper confinement, URL and action normalization, and final rendering boundaries.
- Resolved Experience owner: owns final visibility decisions after ACL and Workspace Profile policy shaping.
- Studio owner: owns governed editing workflow only; Studio does not become runtime owner of contributed resources.
- System Tools owner: owns read-only validation and diagnostics of contract boundaries; tooling must not bypass policy or runtime governance.

## Contribution Types In Scope

This contract baseline applies to:

- navigation and sidebar contributions
- operator widgets and cards
- admin cards and blocks
- display panels
- reports
- actions, buttons, and links
- CSS and assets
- permissions
- workspace and landing contributions
- integration and capability contributions

## Canonical Composition Pipeline

```text
App or Module contribution declarations
  -> ACL authorization filter
  -> Workspace Profile shaping
  -> User override shaping
  -> Resolved Experience visibility decision
  -> Shell normalization and confinement checks
  -> Route-wrapper renderer
```

Rules:

1. Contribution declarations come from the owner app or module.
2. ACL enforces allow or deny, not presentation ownership.
3. Workspace Profile and user override shape presentation but cannot reveal denied capability.
4. Resolved Experience is the visibility truth consumed by runtime renderers.
5. Shell applies normalization and confinement before rendering links, actions, or cards.

## Contribution Contract Rules

1. App or Module owns meaning and data.
   - Owner defines what each contribution means, where data comes from, and what business semantics apply.
   - Owner declares required permissions and capability dependencies.

2. Shell owns composition and rendering safety boundary.
   - Shell decides how contribution resources are composed into admin, operator, and display wrappers.
   - Shell enforces wrapper confinement and route-family rules before output.

3. Resolved Experience owns visibility decisions.
   - Visibility must come from resolved contracts, never from ad-hoc contributor checks in templates.
   - Contributions must not create independent visibility toggles that bypass resolved truth.

4. URLs and actions must be normalized by Shell before rendering.
   - Contribution URLs, action links, form targets, and button targets must pass through Shell normalization.
   - Normalization must enforce canonical route families and prevent wrapper escape.

5. Display contributions must be readonly.
   - Display panels may expose status, metrics, and readonly drill references only.
   - No mutation forms, write actions, edit shortcuts, or state-changing controls are permitted.

6. CSS and asset contributions must remain owner-scoped.
   - App or module-specific styles and assets stay under owner paths.
   - Shell styles may include only generic primitives, wrapper chrome, and shared tokens.

7. Contributions must not create hidden visibility truth.
   - No parallel booleans, hardcoded role checks, or bypass flags may become runtime visibility authority.
   - Any transitional compatibility field remains debt until migrated into resolved contracts.

8. Studio edits contribution resources later but does not own them.
   - Studio can analyze, edit, diff, and apply owner resources through governance workflow.
   - Runtime ownership and truth remain with app or module contracts plus resolved visibility.

9. System Tools validate contribution contracts but do not bypass them.
   - Architecture gates and diagnostics may report or fail boundary drift.
   - Tooling cannot authorize hidden bypass routes, hidden visibility sources, or ownership exceptions.

## Contribution-Type Expectations

Navigation and sidebar contributions:

- Owned by app or module catalog declarations.
- Shell composes location and wrapper-safe links.
- Visibility comes from resolved contracts.
- Declarations identify an owner, source key, target URL or route, visibility policy marker, and ordering metadata.

Operator widgets and cards:

- Owner app or module provides data and business meaning.
- Shell confines output to operator route space and wrapper rules.
- Widget/card declarations identify an owner domain or source module, explicit target URL, ordering metadata, and visibility policy marker where applicable.

Admin cards and blocks:

- Owner app or module provides card semantics.
- Shell applies admin wrapper composition and normalized targets.
- Admin block declarations identify the owning app/module, target surface or route, group/region, ordering metadata, and policy marker.

Display panels:

- Owner app or module provides readonly data only.
- Shell enforces readonly rendering and actionless behavior.
- Display declarations identify the display surface/region and must not declare mutation-style action targets.

Reports:

- Owner app or module owns report semantics, parameters, and data meaning.
- Shell may host entry points and rendering composition only.
- Report declarations identify report key, owner, scope, view/export view when applicable, permission, and lifecycle policy.

Actions, buttons, and links:

- Owner declares intent and permission requirements.
- Shell normalizes targets and blocks wrapper escapes.
- Action/link declarations identify target surface or route and must not imply Shell owns business meaning.

CSS and assets:

- Owner-scoped resources stay with owner surfaces.
- Shell keeps shared primitives and wrapper-level tokens only.
- App/module-specific selectors must not be added to Shell CSS.
- Style declarations identify key, owner-scoped path, scope, target surfaces, and order.

Permissions:

- Owner contributes permission keys and capability requirements.
- ACL remains final authorization truth.
- Permission metadata remains authorization input only and must not become presentation truth.

Workspace and landing contributions:

- Owner contributes candidate entries and metadata.
- Resolved Experience and landing policy determine final visibility.
- Workspace and landing entries identify owner, target route, feature/capability key where stable, and policy marker.

Integration and capability contributions:

- Owner declares capability contract and dependencies.
- Technical engines remain mechanics-only and do not absorb business meaning.
- Capability declarations identify owner app/module, capability list, dependencies, and lifecycle contract where stable.

## Contract Shape Readiness Diagnostics

The surface contribution contract gate validates stable declaration shapes without changing runtime behavior. It checks current app and module manifests, navigation catalogs, hook declarations, report metadata, widget/card declarations, style declarations, and contribution scan scope.

Diagnostics are intentionally metadata-focused:

- Owner and target metadata must be explicit enough for System Tools to prove Shell is composing owner resources rather than owning business meaning.
- Surface names, regions, routes, URLs, style surfaces, and lifecycle markers must be present where current declaration formats already support them.
- Display contribution checks remain readonly/actionless.
- CSS and asset declarations must stay owner-scoped and must not point at generated delivery output as source truth.
- Navigation and manifest checks must not replace runtime resolution, ACL authorization, Workspace Profile shaping, or Shell normalization.

## Wrapper Boundary Diagnostics

Operator, display, and admin wrapper diagnostics are read-only boundary checks over current runtime-facing templates, composers, services, and owner contribution folders.

- Operator confinement scans Shell operator views/composers/services and app/module operator views for raw `/apps`, `/ops`, or `/admin` route escapes in links, row targets, redirects, forms, and action targets. Documented admin-switch affordances may remain, but operator work surfaces must stay under `/u/{username}`.
- Display readonly scans Shell display views/composers/services and app/module display views for forms, POST/submit affordances, inline mutation handlers, fetch/XMLHttpRequest usage, wrapper escapes, and write-action targets such as create, edit, update, delete, remove, save, or submit.
- Admin route diagnostics verify `/admin/{username}` remains canonical, `/me` remains a compatibility alias only, bare `/admin` continues through the documented compatibility path, and Shell emitters do not add new primary `/me` links.
- Each wrapper diagnostic carries an explicit scan-scope self-contract so System Tools cannot silently narrow coverage while reporting a pass.

## Shell CSS Ownership Diagnostics

Shell CSS ownership diagnostics are read-only checks over Shell style sources, Shell-owned views, Shell manifest declarations, architecture CSS ownership docs, and Shell public delivery copies.

- Shell CSS may define generic shell, layout, wrapper, topbar/header/sidebar, token/theme variable, utility, accessibility, and focus-state styling.
- Shell CSS must not add app/module business selectors such as Manufacturing, QC, Dispatch, Assembly, Machine, SBAIO, Platform business, Studio editor/business, QR, or Timecard selectors.
- Existing QR and Timecard selectors in Shell CSS are compatibility debt until moved through an approved ownership migration. They may warn, but new Shell CSS/view diff additions using app/module-specific selector prefixes must fail the diagnostic.
- Shell view-local inline styles and `<style>` blocks remain Shell ownership risks unless explicitly approved and recorded against the owning layer.

## Non-Goals

This baseline does not:

- redesign route families
- migrate legacy aliases
- add new runtime contribution systems
- change wrappers or runtime behavior
- move ownership across Core, Shell, Apps, or Modules

## Validation And Enforcement

Use existing architecture gates as enforcement baseline:

- scripts/architecture/run_architecture_gates.sh
- scripts/architecture/check_operator_confinement.sh
- scripts/architecture/check_display_readonly.sh
- scripts/architecture/check_resolved_experience_truth.sh
- scripts/architecture/check_surface_contribution_contracts.sh
- scripts/architecture/check_migration_debt_regressions.sh
- scripts/architecture/check_business_app_module_contracts.sh
- scripts/architecture/check_capability_ownership_boundaries.sh

The contribution-contract diagnostic is read-only. It reports boundary drift and fails when concrete contribution-surface risks are detected, but it does not mutate runtime state or bypass ownership governance.

Related compiler-resolver baseline:

- `docs/architecture/resolved-runtime-contract-pipeline.md`

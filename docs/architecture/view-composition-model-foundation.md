# View Composition Model Foundation

**Status:** Architecture foundation baseline; no runtime implementation authorized
**Date:** 2026-06-05
**Owner:** Architecture governance (Shell + app/module contract boundary)
**References:**

- `docs/architecture/view-composition-contract-readiness-audit.md`
- `docs/architecture/surface-contribution-contract.md`
- `docs/architecture/universal-component-contract-v1.md`
- `docs/architecture/shell-behavior-rendering-contract-v1.md`
- `docs/architecture/SUSANKHYA-OS-ARCHITECTURE-CHARTER-V1.md`

## 1. Purpose

This foundation defines the minimum shared vocabulary required to move from hybrid template/composer assembly to a formal View Composition Contract V1.

It standardizes identity, placement, precedence, and resolved output shape without changing runtime behavior.

## 2. View Identity

A canonical View identity must contain:

1. **view id**
   - Stable key for one view definition (`view_id`).
   - Immutable across presentation-only edits.
2. **route**
   - Canonical runtime route or route family that resolves this view.
   - Route does not replace `view_id`; it is a locator.
3. **owner app/module/plugin**
   - Explicit owner layer and owner key (`owner_type`, `owner_key`).
   - Owner keeps business meaning and data semantics.
4. **interaction profile**
   - Declares compatible interaction posture(s) (for example `worker`, `leader`, `admin`, `read_only`, `display`).
   - Never grants permission; it shapes already-authorized presentation.
5. **workspace profile**
   - Declares profile context allowed to shape the view (role/persona policy layer).
   - Can adjust prominence/arrangement only inside authorized bounds.
6. **access authority**
   - Points to ACL/resolved-experience authority as the visibility gate.
   - View identity cannot create a parallel authorization truth.

## 3. Region Identity

Canonical region keys for composition:

1. `header`
2. `navigation`
3. `workspace`
4. `hero`
5. `primary`
6. `secondary`
7. `aside`
8. `footer`
9. `overlay`
10. `print/export`

Rules:

1. Region keys are stable identifiers, not CSS class names.
2. A region may be absent in a given wrapper/view, but its meaning stays fixed.
3. `print/export` is a composition target for print/export rendering behavior, not a business surface owner.

## 4. Placement Identity

Each placement record must define:

1. **surface id**
   - Stable business/host surface identity being placed.
2. **component id**
   - Stable instance identity for a concrete rendered component.
3. **owner**
   - App/module/plugin owner responsible for meaning, data bindings, labels, actions.
4. **region**
   - One canonical region key from Section 3.
5. **order**
   - Deterministic order value within region scope.
6. **visibility rule**
   - Rule reference resolved by ACL -> Workspace Profile -> user override pipeline.
7. **interaction profile rule**
   - Rule describing interaction-profile compatibility/transform behavior.
8. **responsive behavior rule**
   - Rule reference for allowed responsive adaptations (without changing ownership).

## 5. Placement Precedence

Resolved placement precedence order:

1. Core/Shell mandatory frame
2. Platform-required surfaces
3. App/module declared surfaces
4. Role/workspace profile adjustments
5. User personalization (future only)
6. Studio drafts (never runtime truth until applied)

Rules:

1. Later layers can shape only what earlier authorized layers allow.
2. No layer may bypass ACL/resolved authority.
3. Studio draft state remains non-runtime until governed apply completes.

## 6. Resolved Composition Shape

The resolved runtime composition shape is:

```text
View
  -> Regions
    -> Surfaces
      -> Components
        -> Data bindings/actions
```

Minimum resolved payload expectations:

1. View identity and wrapper context.
2. Region list with deterministic order.
3. Surface placements per region with provenance.
4. Component instances per surface with contract key and owner.
5. Data-binding/action references already normalized and confinement-safe.
6. Visibility outcomes (shown/hidden) with source reason.

## 7. Ownership Rules

1. Shell owns frame regions and composition mechanics.
2. Apps/modules own business surfaces and business meaning.
3. Components follow Universal Component Contract ownership splits.
4. Theme owns values only (not composition identity).
5. Studio may draft/compose through governance workflows but does not own runtime truth.

## 8. Non-goals

This foundation explicitly does not authorize:

1. Runtime implementation.
2. Studio implementation.
3. Dashboard redesign.
4. Component refactor.
5. Personalization engine implementation.

## 9. Readiness Result

This foundation closes the prerequisite vocabulary gap identified in the readiness audit and makes the architecture **contract-ready** for drafting View Composition Contract V1.

Contract-ready means:

1. Terminology and identities are now bounded.
2. Region and placement model baseline is defined.
3. Deterministic precedence is documented.
4. Resolved shape target is explicit.

Contract-ready does **not** mean runtime-ready. Runtime behavior, Studio tooling, and renderer migration remain out of scope for this foundation phase.
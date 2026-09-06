# Shell Approved Style Consumption Boundary

Status: Architecture boundary plan. Documentation only — no implementation authorized.
Date: 2026-05-29

## 1. Purpose

Shell consumption means Shell reads an approved Platform StyleRegistry value and
exposes it as a safe runtime style variable (CSS custom property or data attribute)
so the UI renders the approved style.

This document defines the boundary, ownership, and constraints for that future
consumption step. It does not authorize any Shell code changes, runtime CSS
generation, `public/assets` output, Core changes, ThemeTool changes, or
Visual Customizer behavior changes.

## 2. Ownership

| Role | Owner | File | Must not |
|---|---|---|---|
| Approved registry truth | Platform | `apps/Platform/StyleRegistry/` | Read Studio drafts or request artifacts |
| Resolved value consumption | Shell | `apps/Shell/Style/` | Read Studio drafts, requests, or snapshots |
| Value proposal/edit/preview | Studio | `apps/Studio/Tools/CustomizationStudio/` | Become runtime source of truth |
| Runtime engine | Core (`/app`) | — | Couple to any part of this chain |

### Rules

- **Platform** owns the approved value and `ApprovedStyleRegistry` contract.
  Shell reads from Platform only, never from Studio storage.
- **Shell** may consume resolved approved values and expose them as style
  variables/attributes. Shell must not read Studio draft files, approval
  request artifacts, snapshot files, or any Studio-local storage.
- **Studio** owns the propose-approve-apply workflow. After Apply succeeds,
  Studio's job is done. Studio must not become runtime style truth.
- **Core** remains locked. No chain component may introduce Core dependencies.

## 3. First Consumable Value

- **Socket**: `radius.scale`
- **Source**: Platform `ApprovedStyleRegistry::getValue('radius.scale')`
- **Allowed values**: `sharp`, `soft`, `round`
- **Default** (unset or invalid): `soft` (matching the socket catalog default)

No other socket is authorized for Shell consumption in this phase.

## 4. Consumption Pipeline (future sequence)

```
Platform StyleRegistry
  storage/platform/style-registry/approved-values/radius.scale.json
    ↓
Platform resolver
  ApprovedStyleRegistryContract::getValue('radius.scale')
    returns string|null
    ↓
Shell style consumer
  reads resolved value → maps to CSS variable or data attribute
    ↓
Shell runtime exposure
  CSS custom property on :root/body
  or data attribute on <html>/<body>
    ↓
UI cascade
  CSS picks up variable → renders approved corner scale
```

Each arrow is a distinct concern with its own owner and gate. No step
should be implemented until the upstream step is stable and architecture
diagnostics verify the boundary.

## 5. Runtime Exposure Decision

| Output | Pros | Cons |
|---|---|---|
| CSS custom property only (`--radius-scale`) | Lightweight, standard cascade, no DOM change | Harder to inspect per-element |
| Data attribute only (`data-radius-scale`) | Easy to inspect per-element, testable via CSS attr | Requires element attribute mutation |
| Both | Maximum flexibility | Duplicated concern, sync risk |

**Recommendation**: CSS custom property on `:root` or `body`. Rationale:
- Single source: the CSS cascade distributes the value
- No DOM traversal needed per element
- Standard design-token pattern
- Easy to override per-scope later

Decision must be finalized and gated during implementation. Not decided now.

## 6. What Shell Must Not Do

| Forbidden | Why |
|---|---|
| Read Studio draft files | Studio owns draft/preview only; drafts are not runtime truth |
| Read approval request artifacts | Request artifacts are Studio governance records |
| Read snapshot files | Snapshots are Studio rollback records |
| Read Customization Studio fixture files | Fixtures are Studio test/preview data |
| Treat `public/assets` as source truth | `public/assets` is generated delivery output |
| Mutate Core | Core is locked; must not depend on style chain |
| Mutate app/module CSS | Each app/module owns its scoped CSS |
| Write to Platform registry | Shell is consumer only; writes are Studio's Apply role |
| Generate runtime CSS before diagnostics pass | Architecture gates must validate first |
| Skip Platform approved source | Must only read resolved Platform value |
| Add new editable sockets | Only `radius.scale` is authorized |
| Replace ThemeTool | ThemeTool is separate; not part of this chain |

## 7. Diagnostics Needed Before Implementation

A new architecture gate `check_shell_style_consumption_boundary.sh` must be
added before any Shell consumption code is merged. It must verify:

1. **Shell reads only Platform approved source** — Shell runtime files
   reference `ApprovedStyleRegistry::getValue` only, no Studio path reads.
2. **Shell does not read Studio drafts** — No Shell PHP/JS/CSS references to
   `storage/studio/customization/visual-customizer` or `/Tools/CustomizationStudio`.
3. **Shell does not read approval request or snapshot artifacts** — No Shell
   references to `approval-requests`, `snapshots`, `vc-req-`, or `vc-snap-`.
4. **`public/assets` remains generated output only** — Shell does not write
   to `public/assets` during consumption.
5. **Core untouched** — No Core file references the style chain.
6. **Only `radius.scale` is consumed** — Only `'radius.scale'` is passed to
   `getValue` in the first implementation.

The detailed Shell-level plan is maintained at:
`apps/Shell/Style/Contracts/approved-style-consumption-boundary-plan.md`

## 8. Validation Plan for Future Implementation

| Validation | Method |
|---|---|
| Approved `radius.scale` read from Platform only | Unit test mocks `ApprovedStyleRegistry`; verify no Studio path reads |
| Unset/empty registry falls back safely | `getValue` returns null → default `soft` used |
| Invalid registry value rejected/ignored | `getValue` returns `'bogus'` → fallback to default |
| CSS variable or data attribute exposes resolved value | Render test or DOM assertion |
| No Studio draft/request coupling in runtime path | Architecture gate scan passes |
| Only `radius.scale` consumed | Verify no other socket ID in consumer code |
| No `public/assets` writes | Verify output target is CSS variable only, not file write |
| No Core changes | Architecture gate scan passes; Core diff is empty |
| Architecture gates pass | `scripts/architecture/run_architecture_gates.sh` |
| Deployment readiness passes | `scripts/system/check_deployment_readiness.sh` |

## 9. Non-Goals

| Non-goal | Reason |
|---|---|
| Shell consumption implementation | Planning-only document |
| Runtime CSS generation | Separate future phase |
| `public/assets` output | Separate lifecycle concern |
| Multi-socket consumption | Only `radius.scale` in this phase |
| ThemeTool replacement | ThemeTool is separate; not part of style chain |
| Core changes | Core remains locked |
| Studio behavior changes | Studio's Apply workflow is complete |
| Approval workflow changes | Workflow is complete and stable |
| Rollback implementation | Snapshot data exists; rollback is future |

## 10. Current Chain State

```
Visual Customizer lifecycle (complete, DB-backed):
  radius.scale draft → validation → readiness → request artifact
  → approve/reject/cancel → snapshot → Apply → registry status

Boundary (this document):
  Platform registry (has value) →╌╌╌ Shell consumption →╌╌╌ runtime UI
                                      ↑
                              Not implemented yet.
                              This document plans the boundary.
```

## References

- `apps/Shell/Style/Contracts/approved-style-consumption-boundary-plan.md` — Detailed Shell-level plan
- `docs/architecture/style-customization-chain-checkpoint.md` — Chain-wide checkpoint
- `apps/Platform/StyleRegistry/` — Platform registry owner home
- `apps/Shell/Style/` — Shell style owner home
- `apps/Studio/Tools/CustomizationStudio/` — Studio workflow owner home

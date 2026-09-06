# Approved Style Consumption Boundary Plan

Status: Phase 2B preparation complete. No runtime Shell consumption in this slice.

This plan defines the boundary and contract that Shell must respect when it
begins consuming approved Platform StyleRegistry values at runtime.

Phase 2B delivered:
- `ResolvedStyleConsumer` service (read-only probe + resolve placeholders)
- `ResolvedApprovedStyleContract` value object (resolved value contract)
- `scripts/shell/probe_resolved_style_consumer.php` (read-only probe)
- Hardened boundary diagnostics for consumption preparation layer

Phase 2B does NOT authorize:
- Shell runtime code calling the consumer
- Runtime CSS generation
- Public/assets output
- Data attributes or DOM changes from approved values

This document remains the governing boundary plan. The next phase (2C)
will implement actual Shell runtime consumption when explicitly authorized.

---

## 1. Purpose

Shell consumption means Shell reads an approved Platform registry value and
exposes it as a safe runtime style variable (CSS custom property or data
attribute) so the UI renders the approved style.

The consumption chain:

```
Platform StyleRegistry (approved value)
  → Platform resolver / approved style contract
    → Shell style consumer
      → Shell CSS variable or data attribute
        → runtime UI
```

This is the **last phase** of the customization lifecycle. Apply writes the
approved value to the Platform registry. Consumption reads it back and
makes it visible to users. Consumption must not be implemented until Apply
is stable and all governance/diagnostics are in place.

---

## 2. Ownership

| Role | Owner | Must not |
|---|---|---|
| Approved registry truth | Platform StyleRegistry | Read Studio drafts or request artifacts |
| Resolved value consumption | Shell Style | Read Studio drafts or request artifacts |
| Value proposal/edit/preview | Customization Studio | Become runtime source of truth |

### Rules

- **Platform** owns the approved value file and the `ApprovedStyleRegistry`
  contract. Shell reads from Platform only.
- **Shell** may consume resolved approved values and expose them as style
  variables/attributes. Shell must not read Studio draft files, approval
  request artifacts, snapshot files, or any Studio-local storage.
- **Studio** owns the proposal-to-apply workflow. After Apply succeeds,
  Studio's job is done. Studio must not become runtime style truth.
- **Core** must not be involved in any part of this chain.

---

## 3. First Consumable Value

- **Socket**: `radius.scale`
- **Source**: Platform StyleRegistry `ApprovedStyleRegistry::getValue('radius.scale')`
- **Allowed values**: `sharp`, `soft`, `round`
- **Default** (when registry is empty or unset): `soft` (matching the
  socket catalog default)

No other socket is authorized for Shell consumption in this phase.

---

## 4. Consumption Pipeline (future sequence)

```
Platform StyleRegistry
  storage/platform/style-registry/approved-values/radius.scale.json
    ↓
Platform Resolver (future)
  ApprovedStyleRegistryContract::getValue('radius.scale')
    returns string|null
    ↓
Shell Style Consumer (future)
  ResolvedStyleConsumer / StyleAttributeBuilder
    reads the resolved value
    maps value → CSS variable or data attribute output
    ↓
Shell CSS variable (future)
  e.g. --radius-scale: round;
  or data attribute e.g. data-radius-scale="round"
    ↓
runtime UI (future)
  CSS cascade picks up the variable
  UI renders with approved corner scale
```

Each arrow is a distinct concern with its own owner and gate. No step
should be implemented until the upstream step is stable and the
architecture diagnostics verify the boundary.

---

## 5. What Shell Must Not Do

Shell consumption implementation must enforce all of these constraints:

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

---

## 6. Runtime Output Boundary

The first Shell consumption of `radius.scale` should expose the approved
value as **one** of the following (decision deferred to implementation
slice; documented here for planning):

| Output | Pros | Cons |
|---|---|---|
| CSS custom property only | Lightweight, standard, no DOM change | Harder to inspect per-element |
| `data-*` attribute only | Easy to inspect per-element, testable | Requires element attribute mutation |
| Both | Maximum flexibility | Duplicated concern, harder to keep in sync |

**Recommendation** (non-binding, to be decided in the implementation
slice): CSS custom property on `:root` or `body`. Rationale:
- Single source: the CSS cascade distributes the value
- No DOM traversal needed per element
- Standard pattern for design tokens
- Easy to override per-scope if needed later

The decision must be documented and gated alongside the implementation.

---

## 7. Diagnostics Needed Before Implementation

Before any Shell consumption code is merged, the architecture gate suite
must be extended with a new diagnostic that verifies:

1. **Shell reads only Platform approved source**
   - Scan Shell runtime files for `ApprovedStyleRegistry::getValue`
     references; flag any read from Studio paths.

2. **Shell does not read Studio drafts**
   - Scan Shell PHP/JS/CSS for `storage/studio/customization/visual-customizer`
     or `/Tools/CustomizationStudio` path references.

3. **Shell does not read approval request or snapshot artifacts**
   - Scan Shell runtime files for `approval-requests`, `snapshots`, or
     `vc-req-`/`vc-snap-` patterns.

4. **`public/assets` remains generated output only**
   - Verify Shell does not write to `public/assets` during consumption.

5. **Core untouched**
   - Verify no Core file references the style chain.

6. **Only `radius.scale` is consumed**
   - Verify only `'radius.scale'` is passed to `getValue` in the first
     implementation.

These diagnostics should be added to `scripts/architecture/` as a new
check (e.g., `check_shell_style_consumption_boundary.sh`) and wired into
the aggregate runner `scripts/architecture/run_architecture_gates.sh`.

---

## 8. Validation Plan for Future Implementation

Any future implementation of Shell consumption must prove:

| Validation | Method |
|---|---|
| Approved `radius.scale` value is read from Platform only | Unit test mocks `ApprovedStyleRegistry`; verify no Studio path reads |
| Unset/empty registry falls back safely | Set `getValue` returns null; verify default `soft` is used |
| Invalid registry value is rejected/ignored | Set `getValue` returns `'bogus'`; verify fallback to default |
| Shell CSS variable or data attribute exposes resolved value | Render test or DOM assertion |
| No Studio draft/request coupling in runtime path | Architecture gate scan passes |
| Only `radius.scale` is consumed | Verify no other socket ID in consumer code |
| No `public/assets` writes | Verify output target is CSS variable only, not file write |
| No Core changes | Architecture gate scan passes; Core diff is empty |
| Architecture gates pass | Run `scripts/architecture/run_architecture_gates.sh` |
| Deployment readiness passes | Run `scripts/system/check_deployment_readiness.sh` |

---

## 9. Sequence Diagram (future, read-only)

```
Shell Runtime (future)         Platform StyleRegistry       storage/
     │                              │                         │
     │  getValue('radius.scale')    │                         │
     │ ────────────────────────────>│                         │
     │                              │ ── read ──────────────>│
     │                              │ <── 'round' ───────────│
     │ <── 'round'                   │                         │
     │                              │                         │
     │  resolve to CSS variable     │                         │
     │  :root { --radius-scale: round; }                      │
     │                              │                         │
     │  UI renders with             │                         │
     │  border-radius: var(--radius-scale)                    │
```

---

## 10. What This Slice Does NOT Authorize

This planning document explicitly does NOT authorize:

- Any Shell PHP, CSS, or JS changes
- Any runtime CSS generation
- Any `public/assets` output
- Any Core changes (Core remains locked)
- Any ThemeTool changes
- Any Visual Customizer behavior changes
- Any multi-socket consumption
- Any new editable sockets
- Any Studio ownership of runtime truth
- Any approval or governance bypass
- Any style registry write from Shell

---

## 11. Non-Goals

| Non-goal | Reason |
|---|---|
| Shell consumption implementation | This is a planning-only document |
| Runtime CSS generation | Separate future phase |
| `public/assets` output | Separate lifecycle concern |
| Multi-socket consumption | Only `radius.scale` in this phase |
| ThemeTool replacement | ThemeTool is separate; not part of style chain |
| Core changes | Core remains locked |
| Studio behavior changes | Studio's Apply workflow is complete |
| Approval workflow changes | Workflow is complete and stable |
| Rollback implementation | Snapshot data exists; rollback is future |

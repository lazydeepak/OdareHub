# Visual Customizer v1 Foundation Checkpoint

**Status**: Foundation complete — feature work paused.
**Date**: 2026-05-30

> Visual Customizer v1 foundation is complete enough to pause feature work.

## 1. Current Status Summary

The Visual Customizer lifecycle is fully implemented and DB-backed. All pipes from draft through Platform registry apply are built. Platform StyleRegistry holds approved `radius.scale` values. Shell consumption is documented but not implemented. All architecture gates and deployment readiness checks pass.

Implementation stops here. The remaining work is postponed to future phases.

## 2. Completed Lifecycle Chain

```
draft (Studio-local, file-based)
  → server-side validation (value semantics, allowed sockets)
  → readiness recheck (client can re-validate without refresh)
  → request artifact creation (DB: studio_visual_customizer_requests)
  → review decision (approve/reject/cancel, DB with decision JSON)
  → snapshot capture (DB: studio_visual_customizer_snapshots)
  → Apply to Platform StyleRegistry (ApprovedStyleRegistry::setValue)
  → Platform registry status visibility (read-only from artifacts)
```

Each step is tested, probed, and gated.

## 3. Architecture Boundaries Preserved

| Boundary | Status |
|---|---|
| Studio owns draft/request/workflow | ✅ Confirmed |
| Platform owns approved registry truth | ✅ Confirmed |
| Shell consumption planned but not implemented | ✅ Confirmed |
| `public/assets` is delivery output, not source truth | ✅ Confirmed |
| Core remains locked | ✅ Confirmed |
| All `non_runtime_flags` remain `false` | ✅ Confirmed |
| No app/module CSS mutated | ✅ Confirmed |
| No ThemeTool changes | ✅ Confirmed |

## 4. Intentionally Postponed

The following capabilities are explicitly deferred:

1. **Shell runtime consumption** — Shell does not read Platform StyleRegistry at runtime.
2. **Runtime CSS generation** — No CSS variables or inline styles from approved values.
3. **Rollback** — Snapshot stores `previous_value` but no rollback action exists.
4. **Multi-socket editing** — Only `radius.scale` is editable.
5. **Visual click-to-select components** — No component picker UI.
6. **Theme presets** — No curated theme package support.
7. **App/module scoped styling** — No per-app socket customization.
8. **Advanced CSS tool** — No full CSS editor or injection.
9. **Localization/styling UX expansion** — ja/ne translations not complete.
10. **Public asset generation/export** — No `public/assets` output from style chain.

## 5. Future Phased Plan

### Phase 2A: Stabilization
- Inspect current Visual Customizer UX for rough edges.
- Clean wording only if needed.
- Add more probes/tests only if needed.
- No feature expansion.

### Phase 2B: Shell consumption preparation
- Implement Shell approved resolver placeholder (`ResolvedStyleConsumer`).
- Add read-only resolver probe (like the Platform registry probe).
- Harden consumption boundary diagnostics.
- No UI styling effect yet.

### Phase 2C: First runtime consumption
- Shell consumes `ApprovedStyleRegistry::getValue('radius.scale')`.
- Expose safe CSS custom property on `:root`/`body` or data attribute.
- Fallback to `soft` when unset.
- No `public/assets` output.
- No app/module CSS mutation.

### Phase 2D: Rollback
- Restore previous Platform StyleRegistry value from snapshot `previous_value`.
- No Shell complexity expansion in this phase.

### Phase 2E: Add 2–3 more safe sockets
Possible candidates:
- Spacing density
- Card shadow
- Table density

Only after `radius.scale` is stable in production.

### Phase 3: Rich Customization Studio
- Theme presets (curated package).
- Component click selection in preview.
- Page/component style groups.
- App/module scoped styling.
- Chart/diagram/print sockets.
- Advanced user mode.
- Export/import package support.

## 6. Stop Rule

**Do not continue into Shell runtime consumption or multi-socket customization unless explicitly requested.**

Future agents must treat this checkpoint as the current state of v1. If a task asks to add runtime CSS, wire Shell to Platform registry, add new sockets, implement rollback, or expand the Customization Studio feature set, stop and reference this document first.

## 7. Recommended Next Safe Task

If work resumes later:

> **Start with Shell approved resolver placeholder (Phase 2B), not runtime styling.**

Do not skip to Phase 2C (runtime consumption) without completing the resolver placeholder, probe, and hardened diagnostics first.

## 8. Architecture Diagnostics

The following gates protect the chain and must pass before any Phase 2 work:

| Gate | Purpose |
|---|---|
| `check_customization_studio_boundaries.sh` | Studio runtime boundary |
| `check_shell_style_catalog_boundaries.sh` | Shell style placeholder integrity |
| `check_platform_style_registry_boundaries.sh` | Platform registry placeholder integrity |
| `check_style_chain_parity.sh` | Cross-owner chain disconnection |
| `check_shell_style_consumption_boundary.sh` | Shell consumption read-only diagnostic |
| `check_studio_enforcement_readiness.sh` | Studio governance readiness |
| `check_resolved_experience_truth.sh` | Resolved contract truth |
| `check_migration_debt_regressions.sh` | No new debt |

## 9. Related Documents

- `docs/architecture/style-customization-chain-checkpoint.md` — Chain overview and owner homes.
- `docs/architecture/shell-approved-style-consumption-boundary.md` — Shell consumption boundary.
- `apps/Studio/Tools/CustomizationStudio/Contracts/README.md` — Contract index.
- `apps/Shell/Style/Contracts/approved-style-consumption-boundary-plan.md` — Shell-level plan.
- `scripts/architecture/run_architecture_gates.sh` — Aggregate gate runner.

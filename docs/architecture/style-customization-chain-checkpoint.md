# Style Customization Chain Checkpoint

Status: Architecture checkpoint. Read-only governance note.
Date: 2026-06-03 (updated from 2026-05-30)

## What Exists Now

The style/customization chain is structurally complete with explicit owner homes:

- **CSS Token Editor** — `apps/Studio/Tools/CustomizationStudio/DesignSystem/DesignTokenEditor` — Live CSS token editing tool. General-user editing foundation complete: friendly color display, Simple/Developer mode, WCAG readability indicators, value binding stabilization. Server-side save only. No color picker.
- **Customization Studio** — `apps/Studio/Tools/CustomizationStudio` — Draft/preview/editing workflow for governed style customization (Visual Customizer).
- **Shell Style** — `apps/Shell/Style` — Socket catalog and future consumption boundary.
- **Platform Style Registry** — `apps/Platform/StyleRegistry` — Approved active/default style truth.

### CSS Token Editor Status

| Feature | Status | Notes |
|---|---|---|
| Friendly color display | Complete | Swatches, labels, category badges, var() resolution |
| Simple / Developer mode | Complete | Intent-first Simple mode; exact selector/token mechanics in Developer mode |
| Readability indicators | Complete | WCAG contrast ratio, pair inference, live feedback |
| Pre-save safety checks | Implemented | Readability/visibility tiers are enforced before save; Simple mode blocks severe issues, Developer mode may override with confirmation |
| Value binding stability | Complete | `touchedTokens` guard prevents stale/empty values |
| Color picker | Not implemented | Intentionally deferred |
| Server-side save | Unchanged | POST form to save endpoint only |
| Theme mutation | Unchanged | No theme values, parser, or backend changes |

### Visual Customizer Lifecycle Status

The full Visual Customizer lifecycle is **proven and DB-backed**:

| Phase | Status | Storage |
|---|---|---|
| radius.scale draft | Complete | File-based (Studio-local) |
| Server-side validation | Complete | Transient |
| Readiness recheck | Complete | Transient |
| Request artifact creation | Complete | `studio_visual_customizer_requests` table |
| Approve/reject/cancel | Complete | DB (status + decision JSON column) |
| Snapshot capture | Complete | `studio_visual_customizer_snapshots` table |
| Apply to Platform StyleRegistry | Complete | Platform registry + DB metadata |
| Platform registry status visible | Complete | Read-only from request/snapshot artifacts |
| DB-backed persistence | Complete | Both tables; old file storage inert |
| Shell consumption boundary plan | Complete | Architecture doc (no implementation) |

### Architecture Diagnostics

The following architecture diagnostics protect the chain:

- `scripts/architecture/check_customization_studio_boundaries.sh`
- `scripts/architecture/check_shell_style_catalog_boundaries.sh`
- `scripts/architecture/check_platform_style_registry_boundaries.sh`
- `scripts/architecture/check_style_chain_parity.sh`
- `scripts/architecture/check_shell_style_consumption_boundary.sh`
- `scripts/architecture/check_cte_safety.sh` — CSS Token Editor safety invariants
- `scripts/architecture/check_shell_css_ownership.sh` — Shell CSS boundary
- `scripts/architecture/check_admin_route_contract.sh`
- `scripts/architecture/check_studio_enforcement_readiness.sh`

All diagnostics are wired into aggregate architecture gates:

- `scripts/architecture/run_architecture_gates.sh`

## What Is Read-Only Only

- VisualCustomizerMetadataService reads socket catalogs + preview fixtures (discovery only)
- `scripts/platform/probe_approved_style_registry_contract.php` — 37-assertion probe
- All architecture diagnostics and gates
- CSS Token Editor readability analysis (no auto-correction)

## What Is Placeholder Only

- Shell Style service classes: `StyleSocketCatalog`, `StyleAttributeBuilder`, `StyleVariableBuilder` — empty stubs
- **`ResolvedStyleConsumer`** — **Phase 2B complete** (read-only probe + resolve placeholder implemented; not wired into runtime)
- All 3 Shell Style contract interfaces: `StyleSocketContract`, `StyleConsumerContract`, `StylePrimitiveContract` — empty stubs
- Shell Style Views: `style-attributes.placeholder.php`, `style-variables.placeholder.php` — empty stubs
- Platform `ActiveDefaultStyleResolver`, `StyleRegistrySnapshotService` — empty stubs
- `ResolvedApprovedStyleContract` — value object (Phase 2B: resolved value contract)
- Customization Studio subtools: 7 README-only placeholders (ThemeManager, ComponentStyleEditor, etc.)
- Customization Studio `Resources/socket-catalog/` — README only
- Platform `Resources/registry.placeholder.json` — status marker, no logic

## What Is Intentionally Disconnected

- Shell does **not** read Platform StyleRegistry at runtime
- Shell does **not** render style sockets as CSS variables or data attributes
- Shell does **not** use socket catalog at runtime (all 20 JSON files have `runtime_status: catalog_only_not_consumed`)
- Shell does **not** read Studio drafts, fixtures, snapshots, or approval requests
- Core has **zero coupling** to any part of the style chain
- `public/assets` is delivery output — no style chain writes to it
- CSS Token Editor is standalone — no connection to Visual Customizer, Style Registry, or Shell
- Visual Customizer draft `non_runtime_flags` are all `false` (`apply_enabled`, `runtime_activation_enabled`, `shell_consumption_enabled`, `platform_registry_io_enabled`)

## What Must Not Be Touched Yet

- Shell runtime behavior (no consumption from StyleRegistry)
- Theme values and theme switching
- Core engine (`/app`)
- Color picker (not approved for CSS Token Editor)
- Visual Customizer save/apply expansion beyond `radius.scale`
- New socket catalogs or registry write paths
- `public/assets` source-truth drift

## Risks

1. **Stale manifest status**: Customization Studio `tool.json` says `"status": "disabled_preview_skeleton"` but the Visual Customizer lifecycle is fully enabled with real DB/file/registry writes. Misleading for new developers.
2. **Empty registry storage**: `storage/platform/style-registry/approved-values/` is empty — Apply has been implemented but never executed end-to-end in a real flow. The first Apply will create the first approved value file.
3. **Non-runtime flag risk**: Draft `non_runtime_flags` all say `false`. If any flag is changed to `true` prematurely (e.g., `shell_consumption_enabled`), it could enable runtime consumption before Shell infrastructure is ready.
4. **Shell CSS debt**: QR and timecard selectors in Shell CSS are documented as known debt. Must be migrated to owner apps before adding new Shell style features.
5. **No CSS change validation tests**: No tests specifically validate that Shell CSS changes don't introduce new app/module-specific selectors.
6. **CSS Token Editor is standalone**: It edits `public/assets/theme.css` directly (via server-side save). This is a different mechanism from the governed Customization Studio/Platform StyleRegistry chain. There is no bridge between the two systems.

## Owner Responsibilities

- **CSS Token Editor (Studio)**: Owns live CSS token editing tooling. Server-side save writes to `public/assets/theme.css`. Must not bypass save governance. Must not add color picker or other approved-only features.
- **Customization Studio (Studio)**: Owns draft/edit/preview workflow and propose-approve-apply lifecycle. Must not become runtime style truth.
- **Shell Style (Shell)**: Owns style sockets and future consumption boundary. Must not read Studio drafts or bypass Platform approved truth.
- **Platform/System Style Registry (Platform)**: Owns approved active/default style truth (future runtime source-of-truth owner). Must not treat Studio drafts, request artifacts, or `public/assets` as source truth.
- **Core**: Must remain decoupled from this style chain implementation.

## Next 3 Safe Implementation Slices

### Slice 1 (Recommended): Visual Customizer read-only metadata browse

Let the Visual Customizer browse all 140+ socket catalog entries (not just `radius.scale` in the inspector panel). Display socket details, allowed values, default values, and consumption status. No save/apply/draft behavior changes. Read-only expansion of the existing metadata display.

**Rules**: No save/apply. No Shell runtime consumption. No Style Registry activation. No theme value changes. No Core changes. No color picker.

### Slice 2: Style Registry diagnostic dashboard

Read-only panel showing registry status: what values are approved (or empty), what sockets exist in catalogs, connection status, pending approval requests, and Apply history. Accessible via Studio tool entry. No mutations.

### Slice 3: Fix stale Customization Studio manifest

Update `apps/Studio/Tools/CustomizationStudio/tool.json` status from `"disabled_preview_skeleton"` to an accurate status reflecting the fully implemented Visual Customizer lifecycle. Add a `runtime_behavior` note documenting the enabled Draft/Request/Apply pipeline. One file change, no behavioral impact.

## Current Chain Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│ CSS Token Editor (standalone)                                   │
│ Friendly display + mode + readability + stable values            │
│ Server-side POST save → public/assets/theme.css                 │
│ No bridge to Visual Customizer or StyleRegistry                 │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│ Visual Customizer Lifecycle (DB-backed, proven)                  │
│ draft → validation → readiness → request → approve/reject/cancel │
│ → snapshot → Apply → Platform registry status                    │
│ Only editable socket: radius.scale                               │
└─────────────────────────────────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────────┐
│ Platform StyleRegistry has value ──╌╌╌ Not consumed by Shell     │
│                                        yet                      │
│ ApprovedStyleRegistry::setValue('radius.scale') implemented      │
│ getValue() never called by Shell at runtime                      │
└─────────────────────────────────────────────────────────────────┘
                          │
                          ▼
                  Future: Shell runtime consumption
                  (not implemented yet)
                  Placeholder stubs only
```

## Related Documents

- `docs/architecture/visual-customizer-v1-foundation-checkpoint.md` — v1 foundation checkpoint
- `docs/architecture/css-token-editor-safety-checkpoint.md` — CTE safety invariants
- `docs/architecture/shell-approved-style-consumption-boundary.md` — Shell consumption boundary
- `docs/architecture/shell-style-socket-contract.md` — Shell socket categories
- `docs/architecture/style-registry-ownership-contract.md` — Registry ownership
- `docs/architecture/style-rendering-contract.md` — Runtime rendering rules
- `docs/architecture/surface-contribution-contract.md` — CSS/assets contribution rules
- `docs/architecture/theme-source-compilation-migration.md` — Theme source vs compiled runtime migration contract
- `docs/architecture/known-architecture-debt-and-next-safe-work.md` — Known debt
- `apps/Shell/Style/Contracts/approved-style-consumption-boundary-plan.md` — Shell-level plan
- `apps/Studio/Tools/CustomizationStudio/Contracts/README.md` — Contract index
- `scripts/architecture/run_architecture_gates.sh` — Aggregate gate runner

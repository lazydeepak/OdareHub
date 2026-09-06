# Customization Studio Operating Contract (Refreshed)

Status: Refresh of Slice 2 contract. Authoritative governance document for all customization-related Studio tooling.
Builds on: Theme Architecture Contract v1, Visual Customizer v1 Checkpoint, Style Registry Ownership Contract, Shell Style Socket Contract, Studio Customization Tools Contract, Theme Tool Lifecycle Contract, ResolvedStyleConsumer Contract, Read-Only Consumption Probe Contract, [Registry Read Contract](registry-read-contract.md).

## 1. Canonical Customization Lifecycle

The system defines a single canonical customization lifecycle with five layers:

```
SOURCE LAYER
  ├── Theme source files (resources/themes/**/*.css)
  ├── Owner CSS files (apps/{Owner}/styles/*.css)
  ├── Shell CSS files (apps/Shell/styles/*.css)
  └── Platform effects (apps/Platform/Resources/effects/*.css)
      │
      ▼
TOOL LAYER
  ├── Read-only tools (inspect, browse, analyze)
  ├── Draft tools (create/edit unpublished proposals)
  └── Apply tools (write to source or registry)
      │
      ▼
GOVERNANCE LAYER
  ├── Validation (safety, boundary, schema checks)
  ├── Approval (request/review/approve/reject workflow)
  └── Audit (snapshot, rollback metadata, provenance)
      │
      ▼
REGISTRY LAYER
  ├── Platform Style Registry (approved runtime values)
  └── Theme Registry (approved theme definitions)
      │
      ▼
RUNTIME CONSUMPTION LAYER
  ├── Compiled theme.css (generated from source + registry)
  ├── Published owner CSS (generated from source)
  ├── Shell runtime style consumption (future: reads registry via ResolvedStyleConsumer)
  └── First-boot CSS (static, pre-compiled)
```

Each layer has distinct owners, write permissions, and consumption boundaries.
Every customization tool must be mappable to exactly one position in this lifecycle.

## 2. Tool Positions In The Lifecycle

```
TOOL                    LAYER         READS                WRITES                  GOVERNANCE
───                     ─────         ─────                ──────                  ────────
Token Impact Explorer   Tool (read)   Source files         Nothing                 None (read-only)
CSS Live Editor         Tool (read)   Source/manifest      Nothing                 None (read-only)
CSS Token Editor        Tool (apply)  Source files         Source files + compile  None (direct write)
Theme Tool              Tool (draft)  Source + registry    Draft JSON files        Managed (draft only)
Visual Customizer       Tool→Registry Draft + catalog      Platform Style Registry Managed (full lifecycle)
Platform Style Registry Registry     Its own storage      Its own storage         Owned by Platform
Theme Source Compiler   Tool (apply)  Source files         Compiled theme.css      None (deterministic)
Shell Style Catalog     Registry      (no runtime reads)   (no runtime writes)     Catalog only
```

## 3. Tool Classification Matrix

For each tool, this matrix defines the five critical attributes:

### Token Impact Explorer
- Read-only: All
- Governed: N/A (no write path)
- Draftable: N/A
- Approvable: N/A
- Runtime: None
- Source-of-truth: None (discovers existing truth)

### CSS Live Editor
- Read-only: All
- Governed: N/A (no write path)
- Draftable: N/A
- Approvable: N/A
- Runtime: None (iframed, sandboxed, read-only)
- Source-of-truth: None (discovers existing truth)
- Classification: Architecture complete. Safe to park.

### CSS Token Editor
- Read-only: Preview, source snapshot, verification
- Governed: NO (direct source write + compile bypasses governance)
- Draftable: NO (writes directly to source, no draft stage)
- Approvable: NO (no approval gate before source mutation)
- Runtime: YES (compiled theme.css is consumed by all surfaces)
- Source-of-truth: Mutates canonical theme source files
- Classification: Architecture violation. Must enter governed lifecycle.

### Theme Tool
- Read-only: Preview, token selectors, registry summary
- Governed: N/A in current diagnostic-only state (future lifecycle is deferred)
- Draftable: NO (existing draft JSON is read-only legacy inventory)
- Approvable: NO (no approval or apply path)
- Runtime: NONE (drafts not consumed at runtime)
- Source-of-truth: None; draft JSON is not runtime Appearance truth
- Classification: Truthful diagnostic boundary. Future Appearance authoring lifecycle is deferred.

### Visual Customizer
- Read-only: Preview, socket catalog, request status
- Governed: YES (full lifecycle: draft → validate → request → approve → snapshot → apply)
- Draftable: YES (file-based, Studio-local, radius.scale only)
- Approvable: YES (DB-backed approval workflow)
- Runtime: YES (Apply writes to Platform StyleRegistry; no Shell consumer yet — see [ResolvedStyleConsumer Contract](resolved-style-consumer-contract.md))
- Source-of-truth: Does not own truth; writes to Platform registry
- Classification: Architecture complete but pipeline ends at a dead end (no Shell consumer — ResolvedStyleConsumer Contract defines the future consumer boundary; [Read-Only Consumption Probe Contract](read-only-consumption-probe-contract.md) defines the diagnostic-only readiness check before consumer implementation).

### Platform Style Registry
- Read-only: getValue(), status visibility
- Governed: YES (isWritable() gate, setValue() provenance)
- Draftable: NO (accepts approved values only)
- Approvable: NO (receives from Visual Customizer Apply; no internal approval)
- Runtime: YES (values available but NOT consumed by Shell yet)
- Source-of-truth: YES (owns approved value truth for registered sockets)
- Classification: Implemented but disconnected from Shell runtime.
- Consumer-side read interface: The [Registry Read Contract](registry-read-contract.md) defines the segregated `ApprovedStyleReaderContract` that will bridge registry values to `ResolvedStyleConsumer`.

### Shell Style Catalog
- Read-only: 20 JSON catalog files (runtime_status: catalog_only_not_consumed)
- Governed: N/A (catalog data only, no write path)
- Draftable: N/A
- Approvable: N/A
- Runtime: NONE
- Source-of-truth: NONE (defines what COULD be customized, not what IS)
- Classification: Architecture correct. Not yet connected.

### Theme Source Compiler
- Read-only: N/A (compilation only)
- Governed: N/A (deterministic transformation)
- Draftable: N/A
- Approvable: N/A
- Runtime: YES (public/assets/theme.css is the active runtime artifact)
- Source-of-truth: NOT itself (faithfully reproduces source layer)
- Classification: Architecture correct.

## 4. The Three Parallel Worlds (Addressed)

The audit identified three parallel customization pathways. This contract resolves them by defining a single canonical lifecycle and mapping each tool into it:

### World 1: Theme Source Architecture (Canonical Source Path)

```
resources/themes/**/*.css  →  compile_theme_sources.php  →  theme.css
```

This is the canonical source-of-truth path for theme token values.
All theme tokens ultimately flow through this path.
It is the single source that feeds runtime surfaces with theme CSS variables.

### World 2: CSS Token Editor (Ungoverned Direct Write)

```
CTE preview → CTE save → direct resources/themes/*.css write → compile → theme.css
```

CTE currently writes directly to the same source files that World 1 manages.
This is the fastest path to production but has no governance.
**Resolution**: CTE must enter a governed lifecycle. See Section 7.

### World 3: Visual Customizer → Platform Style Registry (Governed Dead End)

```
draft → validate → request → approve → snapshot → apply → Platform registry → (no Shell consumer — ResolvedStyleConsumer Contract defines the future consumer boundary; Read-Only Consumption Probe Contract defines diagnostic readiness check)
```

This path has full governance but no runtime impact because Shell does not read the registry.
**Resolution**: Shell consumption of Platform Style Registry must be implemented (Phase 2B/2C from Visual Customizer checkpoint). Until then, Visual Customizer Apply writes to a registry that no runtime code reads.

### World 4: Theme Tool (Governed Sideline)

```
draft → draft JSON → (no promotion path)
```

Theme Tool can create and edit drafts but cannot promote them to approved theme source files or the theme registry.
**Resolution**: Theme Tool needs v2 promotion to approved apply (see Theme Tool Lifecycle Contract Section 5-6).

## 5. Source-of-Truth Map

| Concern | Authoritative Source | Authoring Tool | Runtime Artifact |
|---|---|---|---|
| Foundation token values | `resources/themes/foundation.css` | CTE (direct), text editor | `theme.css` |
| Semantic token values | `resources/themes/semantic/semantic.css` | CTE (direct), text editor | `theme.css` |
| Mode overrides (light/dark) | `resources/themes/{light,dark}.css` | CTE (direct), text editor | `theme.css` |
| Style variant overrides | `resources/themes/{style}.css` | CTE (direct), text editor | `theme.css` |
| Approved socket values | `storage/platform/style-registry/approved-values/*.json` | Visual Customizer Apply | (not yet consumed; consumer-side read contract defined by [Registry Read Contract](registry-read-contract.md)) |
| Theme definitions (draft) | `apps/Studio/Tools/CustomizationStudio/Diagnose/ThemeDoctor/Themes/*.json` | Theme Tool draft | None |
| Theme definitions (approved) | `storage/theme_registry/` | (future: Theme Tool v2) | (not yet consumed) |
| Owner component CSS | `{OwnerRoot}/styles/*.css` | Text editor, (future: CssTool) | Published to `public/assets/apps/` |
| Shell rendering CSS | `apps/Shell/styles/*.css` | Text editor | Published to `public/assets/apps/shell/` |
| Special effects | `apps/Platform/Resources/effects/effects-none.css` | (future: Effects tool) | Published to `public/assets/effects/` |
| Socket catalog | `apps/Shell/Style/Resources/socket-catalog/*.json` | None (catalog data) | None |

## 6. What Each Tool Must Not Do

### Token Impact Explorer
Must not: write files, modify DB, call StyleRegistry setValue, register POST routes.

### CSS Live Editor
Must not: edit/preview save/apply selector highlights, execute scripts in iframe (sandbox), navigate top window, submit forms, write files, call DB.

### CSS Token Editor
Must not: bypass source file path validation, write outside `resources/themes/**`, skip backup creation, skip compile verification, skip readability safety check in Simple mode, call exec() without error handling.

### Theme Tool
Must not: write to `resources/themes/**`, write to `public/assets/`, write to `storage/theme_registry/`, register apply/delete/default routes, register POST routes without CSRF.

### Visual Customizer
Must not: enable `shell_consumption_enabled`, `runtime_activation_enabled`, or `platform_registry_io_enabled` flags prematurely; write to Shell CSS files; write to theme source files; register POST routes without CSRF; expand editable sockets beyond `radius.scale` without explicit approval.

### Platform Style Registry
Must not: read Studio draft files; treat `public/assets` as source truth; accept values for unregistered socket IDs; allow writes without provenance context; serve values directly to Shell runtime before Shell consumption is implemented.

## 7. CSS Token Editor Governance Decision

**Question**: Should CSS Token Editor remain a direct source editor, or should it enter the governed draft/approval lifecycle?

**Decision**: CTE must transition from ungoverned direct write to governed lifecycle edit.

### Rationale

1. CTE mutates the canonical source-of-truth for theme tokens (`resources/themes/**/*.css`).
2. These source files feed the compiler that generates `theme.css`, which is consumed by ALL runtime surfaces.
3. CTE is the only tool that bypasses the governance layer entirely -- no draft, no validation gate, no approval, no snapshot, no rollback.
4. All other customization tools (Visual Customizer, Theme Tool) have governance boundaries. CTE's lack of governance is an architecture violation.

### Migration Plan

CTE governance will be added in three phases, preserving the existing save path during transition:

#### Phase 1: Add governance stubs (current, immediately actionable)
- Add pre-save validation gate that checks for existing uncommitted drafts before allowing direct write.
- Add post-save snapshot creation (automatically save a backup with metadata).
- No behavioral change to the save flow yet.

#### Phase 2: Optional draft mode (next implementation slice)
- Add a "propose change" mode that creates a draft instead of writing directly.
- Drafts are stored in `storage/css_token_editor/drafts/` with full provenance.
- Direct save remains available for trusted users (platform_admin bypass).
- Add diff preview between draft and current source.

#### Phase 3: Governed lifecycle (future)
- Drafts require explicit "Apply to source" action.
- Apply creates snapshot, writes source file, triggers compile.
- Direct save path is deprecated and removed.
- CTE lifecycle mirrors Visual Customizer: draft → validate → preview → apply → snapshot.

## 8. Current Architecture Debt Register

| Debt | Owner | Severity | Resolution |
|---|---|---|---|
| CTE writes directly to source without governance | CTE | High | Phase 1-3 migration per Section 7 |
| Shell does not consume Platform StyleRegistry | Shell | High | Phase 2B/2C from Visual Customizer plan |
| Theme Tool drafts have no promotion path | Theme Tool | Medium | Theme Tool v2: approved apply |
| Shell CSS contains business selectors | Shell | Medium | Migrate to owner CSS files |
| Visual Customizer limited to single socket | Visual Customizer | Medium | Expand after radius.scale is stable |
| No Special Effects tooling | Platform | Medium | Future tool under Customization Studio |
| CTE uses exec() for compile | CTE | Medium | Replace with direct PHP compiler invocation |
| Shell CSS is 7500+ lines unconsolidated | Shell | Low | Incremental refactor |
| Duplicate z-index/breakpoint registries | Shell | Low | Consolidate into single registry |
| Two gate scripts not in aggregate runner | Studio | Low | Wire check_theme_tool_lifecycle_contract.sh and check_studio_customization_tools_contract.sh |

## 9. Tool Interaction Diagram

```
┌─────────────────────────────────────────────────────────────────────────┐
│  SOURCE LAYER                                                            │
│                                                                          │
│  resources/themes/    apps/{Owner}/styles/    apps/Shell/styles/          │
│  ├── foundation.css   ├── manufacturing.css   ├── components.css          │
│  ├── semantic.css     ├── coverage.css        ├── operator.css            │
│  ├── light/dark.css   ├── sbaio.css           ├── admin.css               │
│  ├── liquid-glass.css └── ...                 ├── shell.css               │
│  └── paper.css                                └── setup.css               │
│         │                                             │                   │
│         ▼                                             ▼                   │
│  ┌──────────────┐                           ┌──────────────────┐          │
│  │ CTE (apply)  │─── writes to ──────────►  │ Theme Compiler   │          │
│  │ CTE (draft)  │─── (future) ─────────────►│ (deterministic)  │          │
│  └──────────────┘                           └────────┬─────────┘          │
│         ▲                                             │                   │
│         │ reads                                       ▼                   │
│  ┌──────┴───────┐                           ┌──────────────────┐          │
│  │ Token Impact │                           │  theme.css       │          │
│  │ Explorer     │                           │  (compiled)      │          │
│  └──────────────┘                           └────────┬─────────┘          │
│         ▲                                             │                   │
│         │ reads                                       ▼                   │
│  ┌──────┴───────┐                           ┌──────────────────┐          │
│  │ Theme Tool   │                           │  All Surfaces    │          │
│  │ (draft only) │                           │  (read via var()) │          │
│  └──────┴───────┘                           └──────────────────┘          │
│         │                                                                 │
│         │ (no promotion path)                                             │
│         ▼                                                                 │
│  ┌────────────────┐                                                      │
│  │ Draft JSONs    │                                                       │
│  └────────────────┘                                                       │
│                                                                          │
│ ─── GOVERNANCE BOUNDARY ─────────────────────────────────────────────    │
│                                                                          │
│  REGISTRY LAYER                                                          │
│                                                                          │
│  ┌──────────────────────┐     ┌─────────────────────────────┐             │
│  │ Platform Style       │     │ Shell Style Catalog         │             │
│  │ Registry             │     │ (20 JSON files, not         │             │
│  │ (approved values)    │     │  consumed at runtime)       │             │
│  └────────┬─────────────┘     └─────────────────────────────┘             │
│           ▲                                                              │
│           │ writes via Apply                                              │
│  ┌────────┴─────────────┐                                                │
│  │ Visual Customizer    │                                                 │
│  │ (full lifecycle)     │                                                 │
│  └──────────────────────┘                                                 │
│           │                                                              │
│           │ (no consumer yet)                                             │
│           ▼                                                              │
│  ┌──────────────────────┐                                                │
│  │ (future: Shell       │                                                 │
│  │  runtime consumption)│                                                │
│  └──────────────────────┘                                                 │
│                                                                          │
│ ─── READ-ONLY TOOLS ────────────────────────────────────────────────     │
│                                                                          │
│  ┌──────────────────────┐                                                 │
│  │ CSS Live Editor      │   (iframed, sandboxed, read-only)               │
│  │ CSS Selector Tool    │   (placeholder, not implemented)                │
│  └──────────────────────┘                                                 │
└─────────────────────────────────────────────────────────────────────────┘
```

## 10. Ownership Summary

| Concern | Owner | Governance | Runtime Authority |
|---|---|---|---|
| Theme token values | Theme System | CTE (must gain governance) | Source + compiler |
| Owner component CSS | App/Module owner | (future: CssTool) | Published CSS |
| Shell rendering CSS | Shell | Text editor (external) | Published CSS |
| Special effects | Platform | (future: Effects tool) | Published CSS |
| Approved socket values | Platform | Visual Customizer | Not yet consumed |
| Theme definitions (draft) | Studio (Theme Tool) | Theme Tool | None |
| Theme definitions (approved) | Platform | (future: Theme Tool v2) | Not yet consumed |
| Socket catalog | Shell | None (catalog data) | None |
| First-boot CSS | Platform | Compiler | Static pre-compiled |

## 11. Decision Register

1. **CTE governance**: CTE must enter governed lifecycle (Section 7). Three-phase migration. Direct save preserved during transition.
2. **Visual Customizer scope**: Remains `radius.scale` only until Shell consumption exists and is stable. No expansion before Phase 2E.
3. **Theme Tool promotion**: Blocked at v1. v2 (approved apply) deferred until Shell consumption of Platform registry is implemented.
4. **CSS Live Editor**: Architecture complete. Safe to park. No new feature work required.
5. **CSS Selector Tool**: Remains placeholder. No implementation until CTE governance is resolved.
6. **Special Effects**: No tooling yet. Single `effects-none.css` satisfies first-boot requirements. Effects tooling deferred to future phase.
7. **Shell Style Catalog**: Remains catalog-only. No runtime consumption. No changes to 20 JSON files.
8. **Platform Style Registry**: Implemented but disconnected. Shell consumption is the critical missing link.
9. **Shell runtime consumption of Platform registry**: Must be implemented before any multi-socket expansion, Theme Tool promotion, or new customization tool work.

## 12. Forbidden Behaviors

- No Core edits.
- No direct runtime activation from Studio draft without governance.
- No Shell reading Studio draft files.
- No hidden duplicate source of truth.
- No `public/assets` authoring truth.
- No inline style injection into random views.
- No uncontrolled global CSS.
- No app/module bypass of shared style sockets.
- No CTE direct write without backup and compile verification.
- No Visual Customizer expansion beyond `radius.scale` without explicit approval.
- No Theme Tool apply/delete/default routes without full lifecycle contract.
- No new customization POST routes without CSRF.
- No exec() calls without error handling and fallback.
- No color picker in CTE (not approved).

## 13. Related Documents

- `docs/architecture/theme-source-compilation-migration.md` — Theme source vs compiled runtime
- `docs/architecture/style-customization-chain-checkpoint.md` — Chain overview and owner homes
- `docs/architecture/visual-customizer-v1-foundation-checkpoint.md` — Visual Customizer lifecycle checkpoint
- `docs/architecture/css-token-editor-safety-checkpoint.md` — CTE safety invariants
- `docs/architecture/theme-tool-lifecycle-contract.md` — ThemeTool v0/v1/v2 lifecycle
- `docs/architecture/studio-customization-tools-contract.md` — Customization tool boundary definitions
- `docs/architecture/style-registry-ownership-contract.md` — Registry ownership
- `docs/architecture/shell-style-socket-contract.md` — Shell socket categories
- `docs/architecture/style-rendering-contract.md` — Runtime rendering rules
- `docs/architecture/resolved-style-consumer-contract.md` — Future runtime consumer boundary
- `docs/architecture/registry-read-contract.md` — Segregated read-only contract for registry access (ApprovedStyleReaderContract), adapter design, Phase 1 scope, and gate relaxation plan
- `docs/architecture/shell-consumption-contract.md` — Shell-facing consumption planning, Platform Consumption Surface model, Phase 1 radius.scope scope, RSC-S* diagnostics
- `docs/architecture/platform-style-consumption-surface-contract.md` — Platform-owned consumption surface contract, StyleConsumptionSurface API shape, PSC-* diagnostics family (8 codes), and gate implications (planned gate #29)
- `docs/architecture/runtime-style-application-contract.md` — Layer 4 pipeline from consumption surface to Shell rendering: `ResolvedStyleValue` model, identifier→CSS resolution, pre-application validation (10 checks), fallback chain, RSC-S* diagnostics family (11 codes), and application mechanism (CSS custom properties on wrapper elements)
- `docs/architecture/read-only-consumption-probe-contract.md` — Diagnostic-only probe contract before consumer implementation
- `docs/architecture/shell-approved-style-consumption-boundary.md` — Shell consumption boundary
- `apps/Shell/Style/Contracts/approved-style-consumption-boundary-plan.md` — Shell-level plan
- `scripts/architecture/run_architecture_gates.sh` — Aggregate gate runner

## 14. Next Implementation Slice

The highest-leverage next step after the [Read-Only Consumption Probe Contract](read-only-consumption-probe-contract.md) is:

**Read-Only Consumption Probe Boundary Gate Prep.**

Define architecture gate invariants for the future probe script before any probe or consumer implementation.

Not CTE governance (Section 7).
Not multi-socket expansion.
Not Theme Tool v2.
Not Shell runtime consumption wiring.

Specifically:

1. Add boundary gate invariants: probe must not import Studio, must not call compiler, must not write registry/theme/Shell files, must not register routes.
2. Wire gate into aggregate runner after customization-related gates.
3. No probe script implementation until gate exists.
4. No `ResolvedStyleConsumer` implementation until probe contract + gate are in place.

This is the single step that unlocks safe probe implementation:
- Visual Customizer Apply output becomes diagnosable without runtime mutation.
- Future `ResolvedStyleConsumer` gains a validated readiness check.
- Shell consumption wiring remains blocked until probe + gate pass.

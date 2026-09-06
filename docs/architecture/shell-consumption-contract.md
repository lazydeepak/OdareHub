# Shell Consumption Contract

**Status:** Architecture planning only. No implementation authorized. No registry changes. No runtime enablement. No CSS generation. No Shell mutation. No Studio changes.

**Date:** 2026-06-11

**Builds on:**
- [ResolvedStyleConsumer Contract](resolved-style-consumer-contract.md) — consumer ownership, inputs/outputs, read-only guarantees
- [Registry Read Contract](registry-read-contract.md) — read-only registry access, adapter design, diagnostics
- [Read-Only Consumption Probe Contract](read-only-consumption-probe-contract.md) — probe diagnostics and readiness validation
- [ResolvedStyleConsumer Reader Injection Plan](resolved-style-consumer-reader-injection-plan.md) — diagnostic-only reader injection
- [Customization Studio Operating Contract](customization-studio-operating-contract.md) — governance layer and registry ownership
- [Shell Behavior & Rendering Contract V1](shell-behavior-rendering-contract-v1.md) — Shell rendering surfaces, z-layers, breakpoints, overlay contract
- [Universal Component Contract V1](universal-component-contract-v1.md) — component contracts that may consume resolved values

---

## Part A — Consumption Objective

### What Problem Shell Consumption Solves

The style customization chain is currently broken at the final link:

```
Studio Visual Customizer → Apply → Platform Style Registry
    ↓
ApprovedStyleReaderAdapter → ResolvedStyleConsumer (diagnostics-only)
    ↓
❌ Shell — no authorized path to receive or render approved values
```

Registry reads prove the governance pipeline works. Diagnostics prove values are reachable. But Shell cannot change its rendering based on those values — it has no authorized integration point. Shell consumption solves:

1. **Closing the customization loop:** A platform admin approves `radius.scale = sharp` in Studio. Shell renders sharp corners. The user sees the change.
2. **Value of the registry:** Without Shell consumption, the registry is a dead-end store. Its only purpose is diagnostic validation.
3. **Platform governance payoff:** The entire governance investment (Visual Customizer → approval → registry) only delivers value when Shell can consume the result.

### Why Registry Read Alone Is Insufficient

| Operation | Purpose | Limitation |
|---|---|---|
| `readValue('radius.scale')` | Prove the value exists and is reachable | Returns `?string` — a value in memory |
| `isReachable()` | Prove storage is accessible | Returns `bool` — no rendering impact |
| Diagnostics | Report resolution status | Human-readable text. No machine-actionable output for Shell |

Reading a value and *using* a value are architecturally distinct. The consumer reads through a segregated contract; Shell would render through a consumption surface. Mixing the two creates a bypass risk where a diagnostic read becomes an implicit consumption.

### Why Shell Must Remain Style Consumer, Not Style Owner

| Role | Shell's responsibility | Shell must not |
|---|---|---|
| Style consumer | Accept resolved values through Platform-owned surface. Apply as CSS custom properties, data attributes, or style socket values | Read registry directly. Bypass consumer. Author values. Approve values |
| Style owner | CSS selectors, rendering behavior, layout surfaces, responsive rules | Own registry values. Own token definitions. Own theme files |

Shell owns *where* and *how* values are rendered. It does not own *what* the values are. The value-authority chain is:

```
Studio (governance) → Registry (storage) → Consumer (resolution) → Shell (rendering)
```

Shell must never short-circuit this chain.

---

## Part B — Ownership Review

| Owner | Owns | Transferred? |
|---|---|---|
| Theme Source Compiler | Theme source compilation (`scripts/assets/compile_theme_sources.php`) | No |
| Platform Style Registry | Approved values (`storage/platform/style-registry/approved-values/`) | No |
| ResolvedStyleConsumer | Value resolution and diagnostics (`platform/Style/`) | No |
| Shell | CSS selectors, rendering behavior, UI surfaces (`apps/Shell/`) | No |
| Customization Studio | Authoring workflows, approval governance, apply (`apps/Studio/Tools/CustomizationStudio/`) | No |

**No ownership transfers required.**

Shell consumption adds a *rendering contract* between Platform and Shell, but does not transfer ownership. The consumption surface is Platform-owned. Shell is a consumer.

The new relationship:

```
Platform Style Registry (approved values)
    ↓  (reads through ApprovedStyleReaderAdapter)
Platform Consumption Surface  [NEW — Platform-owned]
    ↓  (typed value accessors)
Shell Runtime (CSS variables, data attributes)
```

The Platform Consumption Surface is a new Platform-owned service (under `platform/Style/Consumption/` or equivalent) that wraps the adapter and provides typed value accessors. Shell imports this surface — not the consumer, not the adapter, not the registry.

---

## Part C — Allowed Consumption Scope (Phase 1)

### Candidates evaluated

| Candidate | Governance chain complete? | Catalog entry exists? | Registry allowlisted? | Probe-proven? | Recommendation |
|---|---|---|---|---|---|
| `radius.scale` | ✅ (VC → approval → registry → probe) | ✅ (`shell-style-socket-contract.md`) | ✅ (`ALLOWED_SOCKETS`) | ✅ (probe 5/5 scenarios pass) | **Phase 1** |
| `density.scale` | ❌ (not through VC) | ❌ | ❌ | ❌ | Deferred |
| `typography.scale` | ❌ | ❌ | ❌ | ❌ | Deferred |
| All approved values | ❌ (only radius.scale exists) | Partial | Partial | Partial | Too broad |
| Style maps | ❌ (concept not defined) | ❌ | ❌ | ❌ | Phase 2+ |
| Token bundles | ❌ (concept not defined) | ❌ | ❌ | ❌ | Phase 2+ |

### Recommendation

**`radius.scale` only — Phase 1.**

Rationale:

1. **Proven end-to-end:** The only socket that has completed the full governance chain (Visual Customizer → validation → approval → registry → probe).
2. **Single-value scope:** Keeps the first Shell integration minimal — one CSS custom property, one data attribute, one rendering path to modify.
3. **Safe rollback:** If consumption has unintended rendering effects, only corner radius is affected. No cascading style changes.
4. **Future expansion gate:** Each new socket requires explicit authorization and a new Phase N scope definition.

Phase 1 scope table:

| Property | Value |
|---|---|
| Sockets | `radius.scale` only |
| Registry values | `sharp` (4px), `soft` (8px), `round` (16px) |
| Theme defaults | `soft` (8px — defined in `resources/themes/foundation.css`) |
| Shell surfaces | Workspace container, card components, modal dialogs (see Part F) |

---

## Part D — Consumption Model Options

### Option 1: Direct pull by Shell

Shell templates or services call `ResolvedStyleConsumer` directly during render.

```
Shell template → ResolvedStyleConsumer::consumeValue('radius.scale')
```

| Concern | Assessment |
|---|---|
| Ownership impact | Shell now depends on `Platform\Style\ResolvedStyleConsumer`. Creates tight coupling |
| Governance impact | Low — consumer still reads through adapter |
| Runtime complexity | Lowest — direct method call during render |
| Future scalability | Poor — every Socket requires a new consumer method or a generic `consumeValue()` that bypasses type safety |

**Verdict: Rejected.** Shell must not import `ResolvedStyleConsumer` directly. Creates coupling that bypasses the Platform-owned consumption surface.

### Option 2: Platform push into Shell

Platform emits an event or calls a Shell callback when registry values change.

```
Registry updated → Platform event → Shell callback → Shell re-renders
```

| Concern | Assessment |
|---|---|
| Ownership impact | Shell must register callbacks. Platform must manage subscriber list |
| Governance impact | Low — push is read-only from Shell's perspective |
| Runtime complexity | High — event system, subscriber management, re-render coordination, async boundary |
| Future scalability | High — decoupled, extensible to many sockets |

**Verdict: Rejected for Phase 1.** Over-engineered for a single socket value. Revisit when shell integration covers 3+ sockets with real-time updates.

### Option 3: Platform Consumption Surface (Recommended)

A new Platform-owned service (under `platform/Style/Consumption/`) wraps the adapter and provides typed value accessors. Shell imports only this surface.

```
[Platform-owned]
Platform Consumption Surface
  - resolveRadiusScale(): string  ← typed accessor
  - isRadiusScaleApproved(): bool
  - getConsumptionMap(): array    ← batch snapshot
  - wraps ApprovedStyleReaderAdapter

[Shell imports]
use Platform\Style\Consumption\StyleConsumptionSurface;
$radius = $surface->resolveRadiusScale();  // 'sharp', 'soft', 'round'
```

| Concern | Assessment |
|---|---|
| Ownership impact | Platform owns the consumption surface. Shell imports a Platform interface. No ownership transfer |
| Governance impact | High — surface enforces typed access. No generic `consumeValue()` that could accept unauthorized sockets. Each socket gets its own accessor with its own type constraints |
| Runtime complexity | Low — synchronous method call. No events, no callbacks. Single dependency injection point |
| Future scalability | High — new sockets = new typed accessor. Surface grows by addition, not modification |

**Key constraints:**

1. **The consumption surface must NOT import `ResolvedStyleConsumer`.** It wraps the adapter (`ApprovedStyleReaderContract`) directly. The consumer remains a diagnostic-only tool.
2. **The consumption surface must NOT expose `readValue()` generically.** Each socket gets a typed accessor. No batch read method.
3. **Shell must NOT import the adapter, contract, consumer, or registry.** Only the consumption surface.
4. **The consumption surface is Platform-owned.** Any change to its API requires Platform contract review.

### Option 4: Generated runtime style map

A compile-time or deploy-time script generates a CSS file or JSON map from registry values, which Shell loads at runtime.

```
registry values → compile script → public/assets/consumed-styles.css
Shell loads <link rel="stylesheet" href="/assets/consumed-styles.css">
```

| Concern | Assessment |
|---|---|
| Ownership impact | Low — generated artifact is Platform-owned output |
| Governance impact | Medium — compilation step must be triggered after registry changes. Latency between approval and consumption |
| Runtime complexity | Lowest — static CSS file. No runtime computation |
| Future scalability | Medium — adding new sockets just adds more CSS variables. But requires compilation pipeline changes |

**Verdict: Deferred.** Viable for Phase 2 when multiple sockets are approved and a compilation pipeline is justified. Phase 1 single-value does not need a separate build step.

### Option 5: CSS custom properties via Shell wrapper

Shell renders CSS custom properties from a data payload at the wrapper level (e.g., `.app-shell`).

```
Shell composer receives resolved values → emits <style>.app-shell { --corner-radius: 4px; }</style>
```

| Concern | Assessment |
|---|---|
| Ownership impact | Shell owns the rendering, but values come from Platform. Mixed ownership of the style block |
| Governance impact | Low — inline style blocks are ephemeral, not source-controlled |
| Runtime complexity | Low — PHP string interpolation in composer |
| Future scalability | Medium — scales to N variables. But inline `<style>` blocks bypass CSS pipeline |

**Verdict: Rejected for Phase 1.** Inline `<style>` blocks were explicitly removed from the codebase during the setup externalization work. This would reintroduce them.

### Recommendation

**Option 3 — Platform Consumption Surface.**

- Best ownership alignment (Platform-owned, Shell imports only)
- Strongest governance (typed accessors per socket, no generic read)
- Lowest future risk (new sockets = new typed methods, not new patterns)
- Consistent with existing contract rule: "Shell may call ResolvedStyleConsumer only through a Platform-owned service"

---

## Part E — Runtime Boundary

### Boundary 1: Registry Read ≠ Runtime Consumption

| Aspect | Registry Read | Runtime Consumption |
|---|---|---|
| Purpose | Verify value exists and is reachable | Change how Shell renders |
| Method | `readValue('radius.scale')` | `resolveRadiusScale(): string` |
| Output | `?string` — may be null | `string` — guaranteed value (fallback applied) |
| Error handling | Null + diagnostic code | Never null. Falls back to theme default |
| Consumer impact | Inside `diagnostics()` only | Typed accessor on consumption surface |
| Gate invariant | Allowed in consumer via reader contract | Not yet allowed. Requires new gate section |

### Boundary 2: Runtime Consumption ≠ CSS Mutation

| Aspect | Runtime Consumption | CSS Mutation |
|---|---|---|
| What changes | CSS custom property value at render time | CSS file on disk |
| Lifetime | Per-request / per-session / per-render | Persistent until file is re-edited |
| Example | `.app-shell { --corner-radius: 4px }` emitted in composer | Edit `resources/themes/foundation.css` to change `--radius-sm` |
| Authority | Platform consumption surface tells Shell what value to use | Theme source files are authoring source |
| Gate check | Allowed in Shell composer (future) | Forbidden — only Studio/Tool edit from Studio may mutate CSS files |

### Boundary 3: CSS Mutation ≠ Theme Ownership

| Aspect | CSS Mutation | Theme Ownership |
|---|---|---|
| Scope | A single CSS property at a single integration point | The entire theme definition: tokens, selectors, style variants, media queries |
| Persistence | Runtime-generated. Lost on recompile | Source-controlled. Authoritative |
| Overlap | Runtime value may differ from theme default | Theme default is the foundation. Registry value is an approved overlay |

### Canonical Boundary Diagram

```
Layer 1: Theme Source (authoritative)
   ↓ compile
Layer 2: Compiled theme.css (generated)
   ↓
Layer 3: Registry approved values (additive overlay)
   ↓ read (adapter)
Layer 3a: ResolvedStyleConsumer (diagnostics-only)
   ↓ read (consumption surface)
Layer 3b: Platform Consumption Surface [NEW — typed accessors]
   ↓ inject
Layer 4: Shell Runtime (CSS variables, data attributes)
```

Layer 3a (consumer) and Layer 3b (consumption surface) both read from the same adapter but serve different purposes. The consumer is for diagnostics. The consumption surface is for Shell rendering.

---

## Part F — Shell Integration Surface

### Where consumption would occur

For `radius.scale` Phase 1, consumption integration points:

| Surface | Mechanism | Status |
|---|---|---|
| Workspace container (`.app-shell`) | CSS custom property `--corner-radius` on Shell wrapper | Allowed — affects all child surfaces uniformly |
| Card components (`.card`) | CSS custom property `--card-radius` referencing parent `--corner-radius` | Allowed — Shell-owned shared component (Universal Component Contract) |
| Modal/dialog overlay | CSS custom property `--modal-radius` | Allowed — Shell-owned overlay surface |
| KPI cards | CSS custom property `--kpi-radius` | Allowed — Shell-owned shared component |
| Operator dashboard tiles | CSS custom property via operator.css | Allowed — Shell-owned wrapper chrome |
| Auth/setup surfaces | CSS custom property via auth styles | **Forbidden** — first-boot surfaces must not depend on registry (registry may not exist during setup) |
| App-owned components (Manufacturing cards, QC panels) | App must opt-in by consuming a CSS variable from parent | **Allowed by delegation** — Shell sets `--corner-radius`, apps read it via CSS `var()`. Not a direct Shell-to-app value push |

### Allowed integration points

1. **Shell wrapper chrome only.** The consumption surface feeds values into Shell-owned rendering elements: `.app-shell`, `.topbar`, `.layout-sidebar`, `.operator-shell`, `.display-shell`.
2. **CSS custom properties on Shell wrappers.** Values are rendered as `--corner-radius: {resolved}` on the root shell element. Child elements use `var(--corner-radius)`.
3. **Data attributes on Shell wrappers.** `data-radius-scale="sharp"` emitted on Shell wrapper for JS-based style decisions (future).
4. **Shell composer injection.** The consumption surface is injected into the appropriate Shell composer (AdminSurfaceComposer, OperatorSurfaceComposer) and values are passed to the template.

### Forbidden integration points

1. **Direct registry reads in Shell templates.** Shell must never call `readValue()` or `getValue()`.
2. **Importing `ApprovedStyleReaderAdapter` in Shell.** Only the Platform consumption surface may import the adapter.
3. **Importing `ApprovedStyleReaderContract` in Shell.** Shell must not receive the contract directly.
4. **Importing `ResolvedStyleConsumer` in Shell.** The consumer is diagnostic-only. Shell must not depend on it.
5. **Inline `<style>` blocks.** Values must flow through CSS custom properties on wrapper elements, not generated `<style>` tags. (Matches the existing setup externalization pattern.)
6. **Runtime CSS file generation.** No `file_put_contents` of CSS files during request.
7. **App-specific direct value consumption.** Apps must read values from parent Shell CSS variables. They must not import the consumption surface.

---

## Part G — Future Diagnostics (RSC-S* Family)

A future Shell-facing consumption diagnostics family, parallel to the existing RSC-C* (consumer diagnostics) and RSC-P*/W*/F*/E* (probe diagnostics).

### Naming convention

All Shell consumption diagnostics use the `RSC-S` prefix within the `RSC-` diagnostic family.

| Code | Severity | Meaning | When emitted |
|---|---|---|---|
| **RSC-S001** | PASS | Value resolved and applied to Shell surface | Consumption surface returned a value and Shell rendered it |
| **RSC-S002** | PASS | Fallback produced value; no registry value needed | Registry returned null; theme default or hardcoded fallback used |
| **RSC-S003** | PASS | Shell surface ready for value consumption | Shell composer initialized and consumption surface injected |
| **RSC-S101** | WARN | Consumed value differs from theme default | Registry value does not match `resources/themes/foundation.css` default for the same property |
| **RSC-S102** | WARN | Multiple Shell surfaces consuming same socket | Two or more Shell elements read the same `--corner-radius`. Expected for radius.scale — informational |
| **RSC-S103** | WARN | Value applied but surface has no visible effect | The target CSS property has no visible impact on the surface (e.g., `--corner-radius` on a surface with no corners) |
| **RSC-S201** | FAIL | Shell surface not in render tree | Consumption surface was called but the target Shell wrapper element was not rendered |
| **RSC-S202** | FAIL | Value type mismatch | Registry returned value does not match expected type for the CSS property (e.g., `radius.scale = 4px` but expected `sharp\|soft\|round`) |
| **RSC-S203** | FAIL | Consumption surface not injected | Shell composer did not receive the Platform consumption surface |
| **RSC-S301** | ERROR | Consumption surface failed to resolve value | The adapter returned null AND no fallback was configured |
| **RSC-S302** | ERROR | Runtime consumption enabled but Shell not wired | `runtimeConsumptionEnabled = true` but the Shell consumption pipeline is incomplete |

### Severity mapping

| Category | PASS | WARN | FAIL | ERROR |
|---|---|---|---|---|
| Value resolution | 001 (applied from registry), 002 (fallback used) | 101 (differs from default) | 202 (type mismatch) | 301 (unresolvable) |
| Surface readiness | 003 (surface ready) | 102 (multiple consumers), 103 (no visible effect) | 201 (not in render tree) | 302 (enabled but not wired) |
| Injection status | — | — | 203 (not injected) | — |

### Relationship to Existing Diagnostics

```
Probe scope:       Full system readiness (RSC-P*/W*/F*/E* — 8 codes)
Consumer scope:    Individual resolution (RSC-C* — 5 codes, Phase 1)
Shell scope:       Surface application (RSC-S* — 11 codes, future)
```

Each family addresses a different layer. They coexist. A PASS at the probe level does not guarantee PASS at the Shell level.

---

## Part H — Gate Impact

### What Must Remain Blocked (Permanent)

These invariants in `check_platform_style_consumer_boundary.sh` must never be relaxed:

| Invariant | Reason |
|---|---|
| `isRuntimeConsumptionEnabled() !== true` | Premature enablement would claim readiness without wired Shell |
| `$runtimeConsumptionEnabled !== true` | Same — property-level invariant |
| `diagnostics['runtime_consumption_enabled'] === false` | Diagnostics must report truth |
| No Shell coupling in consumer (non-comment) | Consumer is diagnostic-only. Shell integration happens through consumption surface |
| No filesystem writes | All platform/Style/ files must remain read-only |
| No shell/compiler calls | No exec, shell_exec, compile_theme_sources |
| No route registration | No web routes in platform/Style/ |
| No DB/HTTP side effects | No PDO, curl, HTTP calls |
| No draft/approval access | Governance boundary |

### What May Eventually Be Relaxed

These are NOT authorized now. Listed for future gate update planning:

| Current block | Future allowance | Precondition |
|---|---|---|
| Shell coupling scan (line ~200) | Allow `use Platform\Style\Consumption\StyleConsumptionSurface` in Shell composers | Consumption surface must exist and be committed |
| (implied block on Shell importing any platform/Style/) | Shell may import only `Platform\Style\Consumption\*` | Consumption surface gate must be committed first |
| — | New gate section for Shell consumption boundary (`check_shell_consumption_boundary.sh`) | Shell consumption planning must complete |

### New Invariants Required (Future Gate)

A future `check_shell_consumption_boundary.sh` would enforce:

| Invariant | Scope | Enforcement |
|---|---|---|
| Shell must not import `ResolvedStyleConsumer` | All Shell files | Block `use Platform\Style\ResolvedStyleConsumer` in `apps/Shell/` |
| Shell must not import `ApprovedStyleReaderContract` | All Shell files | Block `use Platform\Style\Contracts` in `apps/Shell/` |
| Shell must not import `ApprovedStyleReaderAdapter` | All Shell files | Block `use Platform\Style\Adapters` in `apps/Shell/` |
| Shell may import only `Platform\Style\Consumption` | Allowlisted Shell composers only | Explicit allowlist of composer files |
| Consumption surface must not be imported by apps | All `apps/` except Shell | Block `use Platform\Style\Consumption` outside `apps/Shell/` |
| Consumption surface must not call `readValue()` | Consumption surface file | Use typed accessor, not generic read |
| Consumption surface must not call `diagnostics()` | Consumption surface file | Diagnostics are consumer's responsibility |

---

## Part I — Risks

| # | Risk | Likelihood | Impact | Mitigation | Classification |
|---|---|---|---|---|---|
| 1 | **Second theme source creation** — Consumption surface emits CSS variables that duplicate or conflict with theme variables | Medium | High | Consumption surface values must be explicitly scoped to approved-value sockets. No generic "emit all registry values" method. Phase 1 is single-socket (`radius.scale`) which maps to `--corner-radius`, a property that theme source does not define as a CSS variable | **Medium** |
| 2 | **Shell becoming registry owner** — Shell code adds write path to registry storage | Low | High | Shell has no write capability. Gate blocks all filesystem writes in Shell. Registry storage is under `storage/platform/style-registry/` which Shell must never access | **Low** |
| 3 | **Consumer bypass** — Shell developer imports adapter or registry directly instead of going through consumption surface | Medium | High | Future `check_shell_consumption_boundary.sh` must block `ApprovedStyleRegistry`, `ApprovedStyleReaderContract`, `ApprovedStyleReaderAdapter`, and `ResolvedStyleConsumer` in all Shell files. Only `Platform\Style\Consumption` surfaces may be imported | **Medium** |
| 4 | **Style drift** — Theme source values change but registry values remain unchanged. Consumed value differs from theme default without administrators realizing | Medium | Low | RSC-S101 (WARN) detects this at diagnostic time. The difference is intentional — the registry value was approved because it differs from the default. Style drift is only a risk if the theme change was meant to be universal (including approved overlays) | **Medium** |
| 5 | **Runtime enablement before contract completion** — Someone sets `runtimeConsumptionEnabled = true` before the Shell consumption surface and gate exist | Low | High | Gate blocks any code path that returns `true` from `isRuntimeConsumptionEnabled()`. Only explicit gate relaxation can unblock this. This risk is already mitigated | **Low** |
| 6 | **Consumption surface becomes god class** — The Platform-owned consumption surface accumulates accessors for every socket, becoming a monolithic dependency | Low | Medium | Each socket accessor is typed and independent. The surface grows by addition. If it exceeds ~20 accessors, split by domain (radius, density, typography) | **Low** |

### Risk Summary

| Risk Level | Count |
|---|---|
| High | 0 |
| Medium | 3 (risks 1, 3, 4) |
| Low | 3 (risks 2, 5, 6) |

---

## Part J — Readiness Classification

**B — More contracts required first.**

The registry read injection is complete and proven (Classification A per the compliance audit). However, Shell consumption requires additional architecture work before any implementation can be authorized.

### Required before Shell implementation

| Step | Status |
|---|---|
| 1. Registry Read Contract | ✅ Complete |
| 2. Registry Read Boundary Gate Update Plan | ✅ Complete |
| 3. Registry Read Boundary Gate Update | ✅ Complete |
| 4. ApprovedStyleReaderContract + Adapter | ✅ Complete |
| 5. ResolvedStyleConsumer Reader Injection Plan | ✅ Complete |
| 6. ResolvedStyleConsumer Reader Injection Implementation | ✅ Complete |
| 7. ResolvedStyleConsumer Registry Read Compliance Audit | ✅ Complete (this session) |
| 8. **Shell Consumption Contract** | ⬅️ **Now** |
| 9. Shell Consumption Boundary Gate Prep | ⬅️ Next |
| 10. **Platform Consumption Surface Contract** | ✅ Complete |
| 11. Platform Consumption Surface Boundary Gate Prep | ⬅️ Next |
| 12. Platform Consumption Surface Implementation | 🔲 Future |
| 13. Shell Consumption Gate Update | 🔲 Future |
| 14. Shell Consumption Integration (typed accessor + composer) | 🔲 Future |
| 15. Runtime consumption enablement | 🔲 Future |

Items 8-10 are planning-only (contract + gate definition, no code). Items 11-15 are implementation.

---

## Part K — Recommended Next Slice

**Shell Consumption Boundary Gate Prep.**

Define a new read-only gate (`check_shell_consumption_boundary.sh`) that protects Shell from:

1. Importing `Platform\Style\ResolvedStyleConsumer` directly
2. Importing `Platform\Style\Contracts\ApprovedStyleReaderContract`
3. Importing `Platform\Style\Adapters\ApprovedStyleReaderAdapter`
4. Importing `Apps\Platform\StyleRegistry` or calling `getValue()`
5. Calling `runtime_consumption_enabled` checks in Shell templates
6. Accessing `storage/platform/style-registry/` paths
7. Writing to any filesystem path from Shell rendering code

This gate must exist BEFORE any Shell-facing consumption planning is implemented. It is a precondition gate — read-only, no implementation authorization, no Shell changes.

The gate planning document should define:

- Exact invariant list (15-20 invariants)
- File scope rules (which Shell directories are scanned)
- Enforcement mechanism (reuse `check_no_matches` pattern from consumer gate)
- Warning/info patterns for forward-looking expectations
- Future relaxation rules (documented but not enabled)

Once the gate plan is committed, the next slice would be **Platform Consumption Surface Contract** — defining the typed accessor service that Shell will eventually import.

**Status:** ✅ Completed. See [Platform Style Consumption Surface Contract](platform-style-consumption-surface-contract.md) for the full contract with API shape, ownership model, diagnostics family, gate implications, and readiness classification.

---

## Deliverable Summary

1. **Recommended consumption model:** Option 3 — Platform Consumption Surface. A Platform-owned service (`platform/Style/Consumption/`) with typed value accessors. Shell imports only this surface. The consumer stays diagnostic-only. The adapter stays the sole bridge to registry.

2. **Ownership verification:** No ownership transfers required. The consumption surface is Platform-owned. Shell remains style consumer, not style owner.

3. **Phase 1 scope recommendation:** `radius.scale` only. Single socket, single CSS custom property (`--corner-radius`), single Shell integration point (Shell wrapper chrome). Only socket with complete governance chain.

4. **Runtime boundary definition:** Three boundaries documented: Registry Read ≠ Runtime Consumption, Runtime Consumption ≠ CSS Mutation, CSS Mutation ≠ Theme Ownership. Layer diagram shows consumer (diagnostics) and consumption surface (rendering) as separate Layer 3a/3b.

5. **Gate implications:** Consumer gate invariants remain permanently blocked. Future Shell consumption gate (`check_shell_consumption_boundary.sh`) required with 15-20 new invariants blocking direct consumer/adapter/registry imports in Shell files.

6. **Risks:** 0 High, 3 Medium (second theme source, consumer bypass, style drift), 3 Low (Shell registry ownership, premature enablement, god class).

7. **Readiness classification:** **B** — More contracts required first. 8 of 15 prerequisite steps complete. After consumption surface implementation complete, see [Runtime Style Application Contract](runtime-style-application-contract.md) for Layer 4 pipeline definition.

8. **Recommended next slice:** Platform Consumption Surface Boundary Gate Prep — define the read-only gate that protects the consumption surface before implementation begins.

9. **Next contract after surface completion:** [Runtime Style Application Contract](runtime-style-application-contract.md) — defines how resolved values from the consumption surface reach Shell rendering through the resolution → validation → application pipeline. The equivalent milestone to Label Designer's Render Pipeline Contract.

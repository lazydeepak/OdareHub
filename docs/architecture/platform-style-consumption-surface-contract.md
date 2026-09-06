# Platform Style Consumption Surface Contract

**Status:** Architecture contract. No implementation authorized. No Shell changes. No runtime consumption. No CSS generation. No theme mutation.

**Date:** 2026-06-11

**Builds on:**
- [Shell Consumption Contract](shell-consumption-contract.md) — selected Option 3 (Platform Consumption Surface), Phase 1 scope, runtime boundaries, Shell integration surface
- [ResolvedStyleConsumer Contract](resolved-style-consumer-contract.md) — consumer ownership, inputs/outputs, read-only guarantees
- [Registry Read Contract](registry-read-contract.md) — read-only registry access, adapter design, diagnostics
- [ResolvedStyleConsumer Reader Injection Plan](resolved-style-consumer-reader-injection-plan.md) — diagnostic-only reader injection
- [Customization Studio Operating Contract](customization-studio-operating-contract.md) — governance layer and registry ownership

---

## Part A — Purpose

### Why the Consumption Surface Exists

The Shell Consumption Contract selected Option 3 (Platform Consumption Surface) as the safe model for bridging approved registry values to Shell rendering. This contract defines that surface.

```text
Shell needs a stable Platform-owned read surface.
Shell must not import registry, reader, adapter, or consumer internals.
Platform must expose only approved, resolved, safe values.
```

The consumption surface is the single authorized entry point through which Shell may receive resolved customization values. It sits between the diagnostics-only consumer and Shell's rendering layer:

```
Layer 3a: ResolvedStyleConsumer (diagnostics-only)  ← exists, compliant
     ↓
Layer 3b: Platform Consumption Surface [THIS CONTRACT]
     ↓  typed accessors, Platform-owned, Shell imports only this
Layer 4: Shell Runtime (CSS variables, data attributes)
```

### Problem It Solves

Without this surface, Shell has four options — all unacceptable:

| Option | Problem |
|---|---|
| Import `ResolvedStyleConsumer` directly | Bypasses Platform ownership. Consumer is diagnostic-only — importing it for rendering conflates two concerns |
| Import `ApprovedStyleReaderContract` directly | Exposes Shell to the read-level contract. No type safety per socket. No governance filters |
| Import `ApprovedStyleReaderAdapter` directly | Shell must know about registry storage, path layout, and adapter internals |
| Call `ApprovedStyleRegistry::getValue()` directly | Shell becomes a direct registry consumer. No governance. No diagnostics. No fallback |

The consumption surface solves all four by providing a Platform-owned abstraction with typed, per-socket accessors.

---

## Part B — Ownership

### Confirmed Ownership Model

| Owner | Owns | Transfer? |
|---|---|---|
| **Platform** | `platform/Style/Consumption/StyleConsumptionSurface.php` — typed accessor service | **New — Platform owned** |
| Shell | CSS selectors, rendering behavior, UI surfaces (`apps/Shell/`) | No change |
| Platform Style Registry | Approved values (`storage/platform/style-registry/approved-values/`) | No change |
| ResolvedStyleConsumer | Value resolution diagnostics (`platform/Style/ResolvedStyleConsumer.php`) | No change |
| ApprovedStyleReaderAdapter | Registry read bridge (`platform/Style/Adapters/`) | No change |
| ApprovedStyleReaderContract | Read-only interface (`platform/Style/Contracts/`) | No change |
| Customization Studio | Authoring, approval governance, apply (`apps/Studio/Tools/CustomizationStudio/`) | No change |
| Theme source files | Theme default values (`resources/themes/`) | No change |

### No Ownership Transfers

The consumption surface is a new Platform-owned asset. It does not transfer ownership from any existing owner.

### Dependency Chain (Future State)

```
ApprovedStyleReaderContract (interface — Platform-owned)
    ↕ implements
ApprovedStyleReaderAdapter (registry bridge — Platform-owned)
    ↕ wraps
Platform Consumption Surface (typed accessors — Platform-owned)  [NEW]
    ↕ imports only this
Shell Runtime (consumer — Shell-owned)
```

No Shell file may depend on any layer above the consumption surface (adapter, contract, consumer, or registry directly).

---

## Part C — Surface API Shape

### Future Interface (Not Yet Implemented)

```php
namespace Platform\Style\Consumption;

class StyleConsumptionSurface
{
    // Phase 1: radius.scale typed accessor
    public function radiusScale(): ?string;

    // Diagnostics
    public function diagnostics(): array;

    // Runtime consumption flag
    public function isRuntimeConsumptionEnabled(): bool;
}
```

### Phase 1 Scope

| Property | Value |
|---|---|
| Typed accessor | `radiusScale(): ?string` |
| Return values | `'sharp'`, `'soft'`, `'round'`, or `null` (registry absent) |
| Underlying socket | `radius.scale` (only socket with complete governance chain) |
| Default fallback | Consumer applies `'soft'` when registry returns `null` |
| CSS variable target | `--corner-radius` on Shell wrapper elements |

### API Design Rules

1. **No generic `readValue(string $socketKey)`.**
   Each socket must have a typed accessor. A generic method would allow callers to request unauthorized sockets without compile-time protection.

2. **No batch read method.**
   No `getConsumptionMap(): array`, no `allValues(): array`. Phase 1 has one socket. Phase N adds one accessor per socket. Batch reads encourage callers to depend on multiple values without explicit authorization.

3. **Return type per accessor is socket-specific.**
   `radiusScale()` returns `?string` because radius.scale values are strings. A future `densityScale()` might return `?float` or an enum. Type safety is enforced at the accessor level.

4. **Null return means registry is unreachable.**
   If the adapter returns `null`, the accessor returns `null`. The consumer's fallback logic applies at the Shell composer level (i.e., Shell decides what to render when no registry value exists — typically the theme default).

### Not on the Surface (Explicitly Excluded)

These methods must NOT appear on the consumption surface:

| Method | Reason |
|---|---|
| `readValue()` | Generic socket read bypasses type safety |
| `writeValue()` | Consumption surface is read-only |
| `setValue()` | Consumption surface is read-only |
| `approveValue()` | Governance belongs in Studio |
| `compileTheme()` | Theme compilation is separate pipeline |
| `getRegistry()` | Exposes registry internals |
| `getConsumerDiagnostics()` | Consumer diagnostics belong to consumer |
| `getProbeResults()` | Probe diagnostics belong to probe |
| `resetConsumption()` | No write path on consumption surface |

---

## Part D — Allowed Inputs

### The Surface May Depend On

| Dependency | Type | Reason |
|---|---|---|
| `ApprovedStyleReaderContract` | Interface | The surface wraps the adapter through the contract interface. This is the sole authorized dependency on the read layer |
| `ApprovedStyleReaderAdapter` | Concrete | Instantiated and injected into the surface. The surface is the **only** non-adapter file that may reference the adapter (see Part H) |
| PHP built-in types | `string`, `bool`, `array`, `null` | Return types and internal state |

### The Surface Must Not Depend On

| Dependency | Reason |
|---|---|
| `ResolvedStyleConsumer` | Consumer is diagnostic-only. Surface must not import consumer. If consumer diagnostics are needed, they are composed at a higher level (e.g., a diagnostics aggregator), not inside the surface |
| Studio services (`Apps\Studio\*`) | Studio is authoring and governance. Surface is runtime consumption. No dependency in either direction |
| Shell services (`Apps\Shell\*`) | Surface is Platform-owned. Depending on Shell would create a circular dependency (Shell imports surface, surface imports Shell) |
| Draft or approval services | Surface reads only approved values. Drafts are not approved. Approvals are governance, not consumption |
| Theme source files (`resources/themes/`) | Theme values are the default baseline. The surface does not read theme files — it reads approved registry values that overlay theme defaults |
| CSS file paths | The surface returns values (strings, numbers). It must not know or care how those values are rendered. CSS rendering is Shell's responsibility |
| Database connections, HTTP clients, filesystem | The surface reads from the adapter, which reads from the registry filesystem. The surface itself has no direct filesystem, DB, or network dependency |

### Dependency Diagram (Authorized)

```
StyleConsumptionSurface
    ↓ depends on
ApprovedStyleReaderContract ← interface only
    ↕ implemented by
ApprovedStyleReaderAdapter
    ↓ depends on
ApprovedStyleRegistry::getValue()  ← sole registry read path
```

The surface never touches the registry directly. It goes through the contract → adapter → registry chain.

---

## Part E — Shell Contract

### Shell May Eventually Import

Shell may import **only** the consumption surface:

```php
use Platform\Style\Consumption\StyleConsumptionSurface;
```

### Shell Must Not Import

These imports are permanently forbidden in all Shell files:

| Prohibited Import | Reason |
|---|---|
| `use Platform\Style\ResolvedStyleConsumer` | Consumer is diagnostic-only. Shell must not depend on it |
| `use Platform\Style\Contracts\ApprovedStyleReaderContract` | Exposes Shell to read-level contract. No type safety per socket |
| `use Platform\Style\Adapters\ApprovedStyleReaderAdapter` | Exposes registry storage internals. Adapter is Platform-internal |
| `use Apps\Platform\StyleRegistry\*` | Direct registry access bypasses all governance |
| `use Platform\Style\Consumption\*` (wildcard) | Only `StyleConsumptionSurface` is authorized. No wildcard imports |

### Shell Import Location (Future)

Only Shell composers may import the surface:

| File | Role |
|---|---|
| `apps/Shell/Composers/AdminSurfaceComposer.php` | Admin wrapper chrome — may inject surface for radius.scale on `.app-shell` |
| `apps/Shell/Composers/OperatorSurfaceComposer.php` | Operator wrapper chrome — may inject surface for operator radius |
| `apps/Shell/Composers/DisplaySurfaceComposer.php` | Display/kiosk wrapper — may inject surface for display radius |

Shell templates, services, and view files must not import the surface. Only the three wrapper composers.

### Enforcement Rule

The future `check_platform_style_consumption_surface_boundary.sh` gate must enforce:

1. `use Platform\Style\Consumption\StyleConsumptionSurface` is allowed ONLY in:
   - `apps/Shell/Composers/AdminSurfaceComposer.php`
   - `apps/Shell/Composers/OperatorSurfaceComposer.php`
   - `apps/Shell/Composers/DisplaySurfaceComposer.php`
2. All other Shell files must not import any `Platform\Style\*` namespace.
3. No app outside `apps/Shell/` may import `Platform\Style\Consumption\*`.

---

## Part F — Runtime Boundary

### Boundary 1: Surface Exists ≠ Shell Applies Values

| State | Meaning | Authorized? |
|---|---|---|
| Surface class exists | Platform owns the file. No Shell dependency yet | ✅ Yes — implementation step |
| Surface injected into Shell composer | Shell has access to the surface | ⛔ No — requires Shell consumption gate update |
| Surface value rendered as CSS variable | Shell displays approved value | ⛔ No — requires runtime consumption enablement |
| `isRuntimeConsumptionEnabled() === true` | System claims consumption readiness | ⛔ No — requires Shell integration contract completion |

The consumption surface implementation is a standalone Platform-owned deliverable. It does not require Shell integration. It does not require runtime enablement. It must exist and pass its own gate before any Shell-facing work begins.

### Boundary 2: Value Visible ≠ CSS Mutation

| Operation | What Changes | Lifetime | Authority |
|---|---|---|---|
| `radiusScale()` returns `'sharp'` | PHP string in memory | Per-request | Registry approved value |
| Shell renders `--corner-radius: 4px` | CSS custom property on `.app-shell` element | Per-request (rendered at composer level) | Shell composer reads from surface |
| Edit `resources/themes/foundation.css` | CSS file on disk | Persistent | Theme source authority |
| Write to `public/assets/theme.css` | Compiled CSS artifact | Persistent until recompile | Compiler authority |

Reading a value from the consumption surface (Boundary 1) is not the same as rendering it (Boundary 2). The Shell consumption gate must block rendering until explicitly authorized.

### Boundary 3: Diagnostic Value ≠ Runtime Style Override

| Flow | What Happens | Status |
|---|---|---|
| Consumer diagnostics read `radius.scale` | `ResolvedStyleConsumer::diagnostics()` returns RSC-C002 (value found) | ✅ Compliant — diagnostics-only |
| Consumption surface returns `'sharp'` | `StyleConsumptionSurface::radiusScale()` returns `'sharp'` | 🔲 Future — needs gate |
| Shell uses returned value to set `--corner-radius` | CSS custom property rendered on wrapper | 🔲 Future — needs runtime enablement |
| Shell bypasses surface and reads registry directly | Shell calls `getValue()` | ⛔ Blocked — gate invariant |

Diagnostics prove the value exists. Surface accessors prove the value is reachable through a typed interface. Neither is a style override. Only the Shell rendering step (Layer 4) applies the value as a style.

### Canonical Pipeline

```
Studio Visual Customizer → Apply → Platform Style Registry
    ↓
ApprovedStyleReaderAdapter → readValue('radius.scale')
    ↓
ResolvedStyleConsumer (diagnostics-only — RSC-C*)
    ↓
Platform Consumption Surface [THIS CONTRACT — typed accessors]
    ↓  (future: Shell imports only this)
Shell Composer → CSS variable on wrapper → Child elements via var()
```

---

## Part G — Diagnostics

### Future Diagnostics Family (PSC-*)

A new diagnostics prefix for the Platform Consumption Surface, parallel to the existing RSC-C* (consumer), RSC-P*/W*/F*/E* (probe), and RSC-S* (Shell consumption) families.

### Naming Convention

All Platform Consumption Surface diagnostics use the `PSC-` prefix.

| Code | Severity | Meaning | When Emitted |
|---|---|---|---|
| **PSC-P001** | PASS | Consumption surface available and initialized | Surface constructed successfully, adapter reachable |
| **PSC-P002** | PASS | `radius.scale` resolved successfully | `radiusScale()` returned a non-null value |
| **PSC-P003** | PASS | `isRuntimeConsumptionEnabled()` returns `false` — invariant satisfied | Diagnostic check confirms runtime consumption is disabled |
| **PSC-W001** | WARN | `radius.scale` absent | `radiusScale()` returned `null` — registry has no approved value for radius.scale |
| **PSC-W002** | WARN | Adapter reachable but socket not in registry | `isReachable() === true` but `readValue('radius.scale') === null` |
| **PSC-F001** | FAIL | Consumer unavailable | The surface could not instantiate or reach the adapter through the contract |
| **PSC-F002** | FAIL | Runtime consumption enabled but Shell not wired | `isRuntimeConsumptionEnabled() === true` but no Shell composer has been updated to consume |
| **PSC-E001** | ERROR | Forbidden dependency detected | Surface code imports Studio, Shell, theme files, CSS paths, or any forbidden dependency |

### Diagnostics Severity Map

| Category | PASS | WARN | FAIL | ERROR |
|---|---|---|---|---|
| Surface availability | 001 (initialized) | — | 001 (consumer unavailable) | — |
| Value resolution | 002 (radius.scale resolved) | 001 (absent), 002 (not in registry) | — | — |
| Runtime boundary | 003 (disabled) | — | 002 (enabled but not wired) | — |
| Dependency safety | — | — | — | 001 (forbidden dependency) |

### Relationship to Existing Diagnostics

```
Probe scope:        Full system readiness (RSC-P*/W*/F*/E* — 8 codes)
Consumer scope:     Individual resolution (RSC-C* — 5 codes)
Shell scope:        Surface application (RSC-S* — 11 codes, future)
Consumption surface: Surface availability and value access (PSC-* — 8 codes)
```

Each family addresses a different layer. PSC diagnostics validate that the consumption surface itself is healthy. They are independent of RSC-C* (consumer) and RSC-S* (Shell rendering) diagnostics.

---

## Part H — Gate Impact

### Future Gate Required

A new architecture gate is required:

```
scripts/architecture/check_platform_style_consumption_surface_boundary.sh
```

### Invariants the Gate Must Protect

#### Hard blocks (permanent)

| Invariant | Check | Reason |
|---|---|---|
| Surface is Platform-owned | File must be under `platform/Style/Consumption/` | Platform ownership invariant |
| No writes | Block `file_put_contents`, `fwrite`, `unlink`, `rename`, `mkdir` | Read-only surface |
| No Shell imports | Block `use Apps\\Shell\\*` | No circular dependency |
| No Studio imports | Block `use Apps\\Studio\\*` | Studio is governance, not consumption |
| No registry direct dependency | Block `use Apps\\Platform\\StyleRegistry` | Must go through adapter/contract chain |
| No `readValue()` call by name | Block `readValue\(` | Surface must use typed accessor, not generic read |
| No `setValue()` / `isWritable()` | Block `setValue\(`, `isWritable\(` | Read-only surface |
| No `diagnostics()` call | Block `diagnostics\(` (unless defining its own) | Consumer diagnostics belong to consumer. Surface has its own PSC-* family |
| No draft/approval access | Block `draft`, `approval` | Governance boundary |
| No theme/compiler calls | Block `compile_theme`, `exec`, `shell_exec` | No compilation dependency |
| No routes | Block `Route::` | No web routes |
| No runtime flag enablement | `isRuntimeConsumptionEnabled()` must return `false` | Premature enablement guard |
| No DB/HTTP | Block `PDO`, `mysqli`, `curl_`, `stream_context_create` | No side effects |

#### Positive expectations (warning until implemented)

| Invariant | Check | Notes |
|---|---|---|
| Surface imports `ApprovedStyleReaderContract` | `use Platform\\Style\\Contracts\\ApprovedStyleReaderContract` | Adapter dependency via contract interface |
| Surface imports `ApprovedStyleReaderAdapter` | `use Platform\\Style\\Adapters\\ApprovedStyleReaderAdapter` | Adapter is sole registry bridge |
| Surface has `radiusScale()` method | `function radiusScale` | Phase 1 typed accessor |
| Surface has `diagnostics()` method | `function diagnostics` | PSC-* diagnostic family |
| Surface has `isRuntimeConsumptionEnabled()` | `function isRuntimeConsumptionEnabled` | Runtime flag |

### Shell Gate Relaxation

The existing `check_shell_consumption_boundary.sh` gate currently blocks all `Platform\Style\*` imports in Shell files. After the consumption surface exists and its gate passes:

| Current block | Future allowance | Precondition |
|---|---|---|
| `Platform\Style\*` blocked in all Shell files | Shell may import `Platform\Style\Consumption\StyleConsumptionSurface` in 3 allowlisted composer files | Consumption surface gate passes. Shell consumption gate updated with allowlist |

### Relationship to Existing Gates

| Gate | Relationship |
|---|---|
| `check_platform_style_consumer_boundary.sh` (gate #27) | Unchanged. Consumer remains diagnostic-only. No consumption surface changes to this gate |
| `check_shell_consumption_boundary.sh` (gate #28) | Must be updated when consumption surface is implemented. Add allowlist for 3 composer files |
| `check_platform_style_consumption_surface_boundary.sh` (future gate, planned #29) | **New gate.** Protects the consumption surface itself |

---

## Part I — Non-Goals

The consumption surface contract explicitly does NOT authorize:

| Non-Goal | Why |
|---|---|
| **Runtime application** | Surface returns values. Applying them to CSS is Shell's responsibility and requires separate authorization |
| **CSS generation** | Surface must not generate, write, or modify CSS files |
| **CSS mutation** | Surface must not alter `public/assets/theme.css`, `resources/themes/*`, or any `apps/*/styles/*.css` files |
| **Theme compilation** | Surface must not call `scripts/assets/compile_theme_sources.php` or any compiler |
| **Shell integration** | The surface is Platform-owned. Shell integration (composer injection, CSS variable rendering) is a separate slice with its own gate |
| **Visual Customizer expansion** | The surface reads approved registry values. It does not extend or modify the Studio Visual Customizer |
| **CTE governance migration** | The CSS Token Editor remains under its own governance model. The consumption surface does not change CTE save authority |
| **Special Effects tooling** | Special Effects is a separate concept not yet defined. The consumption surface does not establish or preempt its architecture |
| **Multi-socket expansion** | Phase 1 is `radius.scale` only. Adding sockets requires explicit Phase N authorization with governance chain proof |
| **Registry schema changes** | The surface reads existing approved values. It does not change how the registry stores, validates, or approves values |
| **Draft read access** | The surface reads only approved values. Draft values are never exposed through the consumption surface |
| **Probe replacement** | The CLI probe (`scripts/platform/probe_resolved_style_consumer.php`) is a separate tool. The surface does not replace or absorb probe functionality |

---

## Part J — Readiness Classification

**B — More gates needed first.**

The Shell Consumption Contract (Part J) defined a 14-step prerequisite sequence. The Platform Consumption Surface Contract is step 10:

| Step | Status |
|---|---|
| 1–7. Registry Read + Consumer Injection | ✅ Complete |
| 8. Shell Consumption Contract | ✅ Complete |
| 9. Shell Consumption Boundary Gate Prep | ✅ Complete |
| **10. Platform Consumption Surface Contract** | ⬅️ **Now** |
| 11. Platform Consumption Surface Boundary Gate Prep | 🔲 Next |
| 12. Platform Consumption Surface Implementation | 🔲 Future |
| 13. Shell Consumption Gate Update | 🔲 Future |
| 14. Shell Consumption Integration | 🔲 Future |

### Classification Rationale

| Criterion | Assessment |
|---|---|
| Architecture defined | ✅ All 10 parts defined (A–J) |
| Ownership clear | ✅ Platform-owned. No transfers |
| API shape frozen | ✅ Phase 1 `radius.scale` only. No generic `readValue()`. Typed accessors |
| Gate plan specified | ✅ Part H defines 13+ hard block invariants + 5 positive expectations |
| Cross-references updated | ✅ (see Cross References) |
| Shell integration not authorized | ✅ Part I: Shell integration is a non-goal |
| Runtime consumption not authorized | ✅ `isRuntimeConsumptionEnabled()` must return `false` |
| No implementation started | ✅ Contract-only. No code generated |

### What Must Happen Before Implementation

1. **Platform Consumption Surface Boundary Gate Prep** — Define `check_platform_style_consumption_surface_boundary.sh` with the 13 hard block invariants and 5 positive expectations from Part H.
2. **Gate update to `run_architecture_gates.sh`** — Wire the new gate into the aggregate runner (planned position: gate #29, after `check_shell_consumption_boundary.sh`).
3. **Gate-runner-contract.md update** — Add ordering rationale.
4. **Architecture-gate-coverage-index.md update** — Add gate #29 entry, renumber subsequent gates.

Only after items 1–4 are complete can the surface be implemented.

---

## Cross References

Updated in this slice:

- `docs/architecture/shell-consumption-contract.md` — Part K (Deliverable Summary) should reference the Platform Consumption Surface Contract as step 10. Part J readiness table item 10 updated.
- `docs/architecture/resolved-style-consumer-contract.md` — Part L (Cross References) should add `platform-style-consumption-surface-contract.md` entry.
- `docs/architecture/registry-read-contract.md` — Part K (Cross References) should add `platform-style-consumption-surface-contract.md` entry.
- `docs/architecture/customization-studio-operating-contract.md` — Section 13 (Related Documents) should add `platform-style-consumption-surface-contract.md` entry.

This contract builds on:

- `docs/architecture/runtime-style-application-contract.md` — defines how consumption surface values (Layer 3b) reach Shell rendering (Layer 4) through the resolution → validation → application pipeline.

---

## Validation

```bash
git diff --check
```

No PHP or shell lint needed — contract-only slice. No code generated.

---

## Deliverable Summary

| # | Item | Value |
|---|---|---|
| 1 | **Contract path** | `docs/architecture/platform-style-consumption-surface-contract.md` |
| 2 | **API shape** | `StyleConsumptionSurface` with `radiusScale(): ?string`, `diagnostics(): array`, `isRuntimeConsumptionEnabled(): bool`. No generic `readValue()`. No batch reads. No write methods |
| 3 | **Ownership model** | Platform owns the consumption surface (`platform/Style/Consumption/`). No ownership transfers. Shell remains style consumer. Platform owns the surface. Consumer stays diagnostic-only |
| 4 | **Shell import rule** | Shell may import only `Platform\Style\Consumption\StyleConsumptionSurface`. Must not import `ResolvedStyleConsumer`, `ApprovedStyleReaderContract`, `ApprovedStyleReaderAdapter`, or any `Apps\Platform\StyleRegistry` type. Only 3 Shell composers are allowlisted for import |
| 5 | **Runtime boundary** | Three boundaries: surface exists ≠ Shell applies values; value visible ≠ CSS mutation; diagnostic value ≠ runtime style override. `isRuntimeConsumptionEnabled()` must return `false` until Shell integration contract completes |
| 6 | **Diagnostics family** | 8 codes (PSC-P001–P003, PSC-W001–W002, PSC-F001–F002, PSC-E001). Severity: 3 PASS, 2 WARN, 2 FAIL, 1 ERROR |
| 7 | **Gate implications** | New gate required (`check_platform_style_consumption_surface_boundary.sh`, planned #29): 13+ hard block invariants, 5 positive expectations. Existing gates #27 (consumer) unchanged, #28 (Shell consumption) must add allowlist after implementation |
| 8 | **Readiness classification** | **B** — More gates needed first. Step 10 of 14 in the Shell Consumption Contract prerequisite sequence. After surface gate complete, see [Runtime Style Application Contract](runtime-style-application-contract.md) for the Layer 4 pipeline that builds on this surface |
| 9 | **Recommended next slice** | **Platform Consumption Surface Boundary Gate Prep** — define the read-only gate that protects the consumption surface before any implementation begins. Gate must block: non-Platform ownership, writes, Shell/Studio imports, registry direct dependency, generic reads, drafts/approvals, theme/compiler calls, routes, DB/HTTP, and runtime flag enablement |

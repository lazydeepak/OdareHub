# Runtime Style Application Contract

**Status:** Architecture contract with Platform skeleton implemented. No Shell changes. No runtime application enabled. No CSS mutation. No theme changes. No registry changes.

**Date:** 2026-06-11

**Builds on:**
- [Platform Style Consumption Surface Contract](platform-style-consumption-surface-contract.md) — typed accessor service that returns resolved values
- [Shell Consumption Contract](shell-consumption-contract.md) — consumption model, runtime boundaries, integration surface, RSC-S diagnostic family
- [ResolvedStyleConsumer Contract](resolved-style-consumer-contract.md) — consumer ownership, diagnostics-only guarantee
- [Registry Read Contract](registry-read-contract.md) — read-only registry access, adapter design
- [Shell Behavior & Rendering Contract V1](shell-behavior-rendering-contract-v1.md) — Shell rendering surfaces, z-layers, breakpoints, wrapper chrome
- [Universal Component Contract V1](universal-component-contract-v1.md) — component contracts that may consume resolved style values

---

## Part A — Purpose

### Why Runtime Style Application Exists

The style customization chain has five layers, four of which are now defined and implemented:

```
Layer 1: Platform Style Registry (approved values)           ← exists, governed
Layer 2: ApprovedStyleReaderAdapter + Contract (read bridge) ← exists, compliant
Layer 3a: ResolvedStyleConsumer (diagnostics-only)           ← exists, compliant
Layer 3b: Platform Consumption Surface (typed accessors)     ← exists, compliant
Layer 4: Shell Runtime (CSS variables, data attributes)      ← ❌ NO CONTRACT
```

This contract defines Layer 4 — how a resolved value from the consumption surface reaches Shell rendering without bypassing ownership boundaries, mutating CSS files, or transferring theme authority.

### Problem It Solves

The consumption surface returns `radiusScale(): ?string` producing values like `'sharp'`, `'soft'`, or `'round'`. These are abstract identifiers — they are not CSS. Shell needs concrete rendering values:

| Abstract identifier | CSS value | CSS property | Unit |
|---|---|---|---|
| `sharp` | `4px` | `--corner-radius` | px |
| `soft` | `8px` | `--corner-radius` | px |
| `round` | `16px` | `--corner-radius` | px |

The Runtime Style Application Contract defines:

1. **Resolution** — how abstract identifiers become concrete CSS values.
2. **Application** — how concrete values are emitted on Shell wrapper elements.
3. **Fallback** — what happens when no registry value exists.
4. **Boundary** — what must NOT happen during application (no CSS mutation, no inline styles, no theme file changes).

### Pipeline Analogy

This contract is the style equivalent of the Label Render Pipeline Contract:

| Dimension | Label Pipeline | Style Pipeline |
|---|---|---|
| Source | Owner resources + data payload | Platform Style Registry + theme defaults |
| Resolution | Template → fields → data binding | Socket → value → CSS mapping |
| Validation | Pre-render checklist (14 checks) | Pre-application checklist (10 checks) |
| Output | PDF, PNG, ZPL, HTML preview | CSS custom properties, data attributes |
| Fallback | Missing field = render block | Missing value = theme default |
| Ownership | Owner invokes, Platform renders | Platform resolves, Shell applies |

---

## Part B — Runtime Pipeline

### Canonical Value Flow

```
Platform Style Registry (approved-values/radius.scale)
    ↓  read (ApprovedStyleReaderAdapter)
ApprovedStyleReaderContract::readValue('radius.scale')
    ↓  returns ?string ('sharp', 'soft', 'round', or null)
Platform Consumption Surface::radiusScale()
    ↓  returns ?string (no fallback — null means registry absent)
ResolvedStyle Application Pipeline [THIS CONTRACT]
    ↓  1. resolve CSS value from identifier (sharp → 4px)
    ↓  2. validate application preconditions
    ↓  3. build ResolvedStyleValue model
    ↓  4. emit CSS custom property on Shell wrapper
Shell Runtime (--corner-radius on admin .layout-main in Phase 1)
    ↓  inherited by all child surfaces via CSS var()
Child Components (card, modal, KPI, tile, etc.)
```

### Stage Definitions

| Stage | Input | Output | Owner | Location |
|---|---|---|---|---|
| 1. Read | Socket key (`radius.scale`) | `?string` identifier (`'sharp'`, `'soft'`, `'round'`, `null`) | Platform | Adapter (pre-existing) |
| 2. Surface | `?string` identifier | `?string` identifier (pass-through) | Platform | Consumption surface (pre-existing) |
| **3. Resolve** | **`?string` identifier** | **`ResolvedStyleValue` model** | **Platform** | **New — pipeline service** |
| **4. Validate** | **`ResolvedStyleValue` model** | **Validation result (PASS/WARN/FAIL/ERROR)** | **Platform** | **New — validation service** |
| **5. Apply** | **ResolvedStyleValue + surface context** | **CSS custom property on wrapper element** | **Shell** | **Composer or wrapper service** |
| 6. Render | CSS custom property | Visual effect on child elements | Shell | CSS (pre-existing) |

Stages 3–5 are defined by this contract. Stage 6 is standard CSS inheritance and is already operational.

### Stage 3 — Value Resolution

The resolution stage maps abstract identifiers to concrete CSS values:

```
radius.scale identifier → CSS value:
  'sharp' → '4px'
  'soft'  → '8px'
  'round' → '16px'
  null    → '8px' (theme default fallback)
```

Resolution rules:

1. **Identifier-to-CSS mapping is Platform-owned.** The mapping lives in the pipeline service, not in Shell CSS. Shell receives only the final CSS value string.
2. **Null input means registry absent.** The resolver applies the theme default as fallback. It does not return null.
3. **Unknown identifier is a FAIL.** If the adapter returns a value not in the known set (e.g., `'extreme'`), the resolver rejects it and uses fallback.
4. **Mapping is Phase 1 specific.** Future sockets get their own mappings. No generic mapping function.

### Stage 4 — Pre-Application Validation

Before any CSS property is emitted, the pipeline must verify:

| # | Check | Severity | Blocks application? |
|---|---|---|---|
| 1 | Consumption surface is loaded and reachable | FAIL | Yes |
| 2 | `isRuntimeConsumptionEnabled()` returns `true` | FAIL | Yes |
| 3 | Identifier is a known value for the socket | FAIL | Yes |
| 4 | Resolved CSS value is non-empty and valid | FAIL | Yes |
| 5 | Target Shell wrapper element is rendered (context check) | WARN | No |
| 6 | CSS custom property name is in the allowlist for the socket | FAIL | Yes |
| 7 | No forbidden dependency detected in the pipeline | ERROR | Yes |
| 8 | Shell composer has the surface injected | FAIL | Yes |
| 9 | Fallback is used (registry absent) | INFO | No |
| 10 | Value differs from theme default | WARN | No |

Validation produces a result with:

| Field | Meaning |
|---|---|
| `socket_key` | The requested socket (`radius.scale`) |
| `status` | `APPLIED`, `FALLBACK`, `BLOCKED` |
| `identifier` | Original identifier from registry or `null` |
| `resolved_css_value` | Concrete CSS value string |
| `css_property` | Target CSS custom property name |
| `severity` | Highest severity across all checks |
| `checks` | Array of individual check results |
| `applied` | Boolean — `true` only if no FAIL or ERROR |

### Stage 5 — Application

The application stage emits the resolved CSS value as a custom property on the Shell wrapper element.

```css
/* Shell wrapper element receives: */
.app-shell {
  --corner-radius: 8px;   /* resolved from radius.scale */
}
```

Application rules:

1. **One CSS custom property per socket.** No batch emission. `radius.scale` → `--corner-radius` only.
2. **Property name is fixed per socket.** Defined by this contract. Not configurable at runtime.
3. **Value is a concrete CSS value string.** `4px`, `8px`, `16px`. Not an abstract identifier.
4. **Phase 1 application point is the admin Shell wrapper element.** The
   [Shell Insertion Planning Contract](shell-insertion-planning-contract.md)
   selects `.layout-main` only. Operator and display wrappers are deferred and
   require later contracts.
5. **No inline `<style>` blocks.** Values are emitted as element-level `style` attributes on the wrapper (via PHP composer) or through a CSS custom property on a wrapper-level stylesheet.
6. **No `!important`.** Application must not override explicit user or theme styles. CSS custom properties cascade naturally.
7. **No file writes.** Application is per-request, in-memory. No CSS files are generated, modified, or deleted.

---

## Part C — Ownership

### Confirmed Ownership Model

| Owner | Owns | Transfer? |
|---|---|---|
| **Platform** | Value resolution pipeline (stages 3–4), identifier-to-CSS mapping, validation service | **New — Platform owned** |
| **Platform** | Consumption surface (stage 2) | No change |
| **Platform** | Adapter + reader contract (stage 1) | No change |
| **Shell** | Application on wrapper element (stage 5), CSS variables, data attributes | No change — Shell always owned rendering |
| **Shell** | CSS selectors that read `--corner-radius` via `var()` | No change |
| **Theme** | Default values for `--radius-sm`, `--radius-md`, `--radius-lg` in `resources/themes/foundation.css` | No change |
| **Registry** | Approved values (`storage/platform/style-registry/approved-values/`) | No change |
| **Studio** | Authoring, approval governance, apply | No change |

### No Ownership Transfers

The pipeline adds Platform-owned resolution and validation services. Shell's rendering ownership is unchanged — Shell always decided where and how CSS properties are rendered. The pipeline simply provides the value.

### Dependency Chain

```
Platform Style Registry (values)
    ↕
ApprovedStyleReaderAdapter (read bridge)
    ↕
ApprovedStyleReaderContract (interface)
    ↕
Platform Consumption Surface (typed accessors)  [platform/Style/Consumption/]
    ↕
Platform Style Resolution Pipeline  [NEW — platform/Style/Resolution/]  ← THIS CONTRACT
    ↕  Platform-owned, Shell-consumed
Shell Composer (Admin/Operator/Display)  [apps/Shell/Composers/]
    ↓
Shell Wrapper Element (CSS custom property)
    ↓
Child Components (via var())
```

---

## Part D — ResolvedStyleValue Model

### Definition

A canonical read-only value object representing a fully resolved style value ready for application:

```php
namespace Platform\Style\Resolution;

class ResolvedStyleValue
{
    public function __construct(
        public readonly string $socketKey,        // 'radius.scale'
        public readonly string $cssProperty,      // '--corner-radius'
        public readonly string $cssValue,         // '8px'
        public readonly ?string $identifier,      // 'soft', null if fallback
        public readonly bool $fromRegistry,       // true if value came from registry
        public readonly string $source,           // 'registry', 'theme_default', 'hardcoded'
        public readonly array $diagnostics,       // application-stage diagnostics
    ) {}
}
```

### Field Rules

| Field | Always set? | Constraint |
|---|---|---|
| `socketKey` | Yes | Must match a known socket key |
| `cssProperty` | Yes | Must be `--corner-radius` for Phase 1 |
| `cssValue` | Yes | Must be a valid CSS length or keyword |
| `identifier` | Yes | May be `null` when registry absent |
| `fromRegistry` | Yes | `true` if value resolved from registry, `false` if fallback |
| `source` | Yes | One of `'registry'`, `'theme_default'`, `'hardcoded'` |
| `diagnostics` | Yes | Array of Stage 4 validation check results |

### Serialization

A `toArray(): array` method must be available for diagnostics output, debugging, and potential caching. It is NOT for runtime rendering.

---

## Part E — Value Resolution Map (Phase 1)

### radius.scale Identifier → CSS Value

| Identifier | CSS value | Meaning | Theme default match? |
|---|---|---|---|
| `sharp` | `4px` | Minimal rounding | No (theme default is `8px`) |
| `soft` | `8px` | Gentle rounding | **Yes** (matches `--radius-md` in foundation.css) |
| `round` | `16px` | Heavy rounding | No |
| `null` | `8px` | Fallback when registry absent | **Yes** |

### CSS Property Mapping

| Socket | CSS property | Affects | Default value |
|---|---|---|---|
| `radius.scale` | `--corner-radius` | Card corners, modal corners, KPI corners, tile corners, button corners, input corners | `8px` |

### Value Constraint

- `--corner-radius` must always resolve to a valid CSS `<length>` value.
- Negative values are invalid and must be rejected at Stage 4.
- Zero (`0px`) is valid but unusual — would produce square corners regardless of identifier.

---

## Part F — Fallback Chain

### Priority Order

When a value is requested for application, the following fallback chain is evaluated:

```text
1. Registry approved value for radius.scale     ← most authoritative
2. Theme default from resources/themes/         ← second authority
3. Hardcoded pipeline default                    ← last resort
```

| Stage | Source | Value | Condition |
|---|---|---|---|
| 1 | Registry | `'sharp'`, `'soft'`, or `'round'` | Registry exists and socket has approved value |
| 2 | Theme default | `8px` (via `--radius-md`) | Registry absent or socket not in registry |
| 3 | Hardcoded fallback | `8px` | Neither registry nor theme sources available |

### Fallback Behavior

| Condition | Source | identifier | cssValue | fromRegistry | source |
|---|---|---|---|---|---|
| Registry reachable + socket has value | Registry | `'soft'` | `'8px'` | `true` | `'registry'` |
| Registry reachable + socket absent | Theme default | `null` | `'8px'` | `false` | `'theme_default'` |
| Registry unreachable | Hardcoded fallback | `null` | `'8px'` | `false` | `'hardcoded'` |

### Theme Default Resolution

The theme default is read from `resources/themes/foundation.css` if available. If theme sources are not compiled or the CSS variable `--radius-md` is not resolvable, the pipeline falls through to the hardcoded fallback.

Theme default resolution is **read-only**. It does not parse, compile, or modify theme files. It reads the value from the compiled `public/assets/theme.css` if available, or from the source `resources/themes/foundation.css` directly.

---

## Part G — Diagnostics (RSC-S* Family)

### Naming Convention

Shell consumption diagnostics use the `RSC-S` prefix, as defined in the Shell Consumption Contract (Part G).

### Phase 1 Diagnostic Codes

The `RSC-S001` through `RSC-S011` family is reserved for the future active Shell
application phase. A disabled Platform skeleton must not emit these codes because
doing so would imply that Shell application checks have actually run.

| Code | Severity | Meaning | When emitted |
|---|---|---|---|
| **RSC-S001** | PASS | Value resolved and applied to Shell surface | Pipeline completed successfully with registry value |
| **RSC-S002** | PASS | Fallback produced value; no registry value needed | Registry absent or socket not found; theme default or hardcoded fallback used |
| **RSC-S003** | PASS | Shell surface ready for value consumption | Shell composer initialized and pipeline reachable |
| **RSC-S004** | WARN | Consumed value differs from theme default | Registry value produces CSS value different from `--radius-md` (`8px`) |
| **RSC-S005** | WARN | Multiple Shell surfaces consuming same socket | Expected for radius.scale — informational |
| **RSC-S006** | WARN | Value applied but surface has no visible effect | `--corner-radius` set but no child element has `border-radius: var(--corner-radius)` |
| **RSC-S007** | FAIL | Shell surface not in render tree | Pipeline called but the target Shell wrapper element was not rendered |
| **RSC-S008** | FAIL | Value type mismatch | Identifier does not map to any known CSS value (e.g., `'extreme'`) |
| **RSC-S009** | FAIL | Pipeline not injected | Shell composer did not receive the resolution pipeline service |
| **RSC-S010** | ERROR | Value unresolvable | Fallback chain exhausted — no registry, no theme default, no hardcoded fallback |
| **RSC-S011** | ERROR | Runtime consumption enabled but Shell not wired | `isRuntimeConsumptionEnabled() === true` but no Shell composer has been updated |

### Disabled Skeleton Diagnostic Codes

Until Shell insertion and runtime application are explicitly authorized, the
Platform skeleton emits only placeholder-safe diagnostics:

| Code | Severity | Meaning |
|---|---|---|
| **RSA-P001** | PASS | Runtime style application skeleton initialized |
| **RSA-P002** | PASS | Fixed `radius.scale` mapping and fallback available |
| **RSA-W001** | WARN | Runtime application remains disabled |
| **RSA-W002** | PASS/WARN | Identifier absent/supported, or unsupported value fell back safely |

### Diagnostic Severity Map

| Category | PASS | WARN | FAIL | ERROR |
|---|---|---|---|---|
| Value resolution | 001 (applied from registry), 002 (fallback used) | 004 (differs from default) | 008 (type mismatch) | 010 (unresolvable) |
| Surface readiness | 003 (surface ready) | 005 (multiple consumers), 006 (no visible effect) | 007 (not in render tree) | 011 (enabled but not wired) |
| Injection status | — | — | 009 (not injected) | — |

### Relationship to Existing Diagnostics

```
RSC-P* (probe):     Full system readiness — 8 codes
RSC-C* (consumer):  Individual value resolution — 5 codes
PSC-*  (surface):   Consumption surface availability — 8 codes
RSC-S* (shell):     Shell application — 11 codes  [THIS CONTRACT — Phase 1]
```

Each family is independent. A PASS at the probe level does not guarantee PASS at the Shell application level.

---

## Part H — Shell Integration Surface

### Where Application Occurs

For `radius.scale` Phase 1, the application integration points:

| Surface | Element | CSS property | Allowlist status |
|---|---|---|---|
| Admin wrapper | `.layout-main` | `--corner-radius` | **Phase 1 selected target** |
| Operator wrapper | `.operator-shell` | `--corner-radius` | Deferred — not authorized in Phase 1 |
| Display wrapper | `.display-shell` | `--corner-radius` | Deferred — not authorized in Phase 1 |
| Card components | `.card` | inherits via `var(--corner-radius)` | Allowed — Shell-owned universal component |
| Modal/dialog | `.modal-overlay` | inherits via `var(--corner-radius)` | Allowed — Shell-owned overlay |
| KPI tiles | `.kpi-card` | inherits via `var(--corner-radius)` | Allowed — Shell-owned universal component |
| Dashboard tiles | `.dashboard-tile` | inherits via `var(--corner-radius)` | Allowed — Shell-owned universal component |
| Auth/setup surfaces | `body` | `--corner-radius` | **Forbidden** — must not depend on registry |
| App-owned components | App-specific | may read `var(--corner-radius)` from parent | **Allowed by delegation** — Shell sets, child apps read |

### Application Mechanism

The value is rendered as an inline style attribute on the wrapper element:

```html
<div class="layout-main" style="--corner-radius: 8px">
```

This is emitted by the Shell composer. The composer receives the resolved value from the pipeline, not from the consumption surface directly.

### Alternative: Data Attribute

For JS-accessible style decisions:

```html
<div class="app-shell" data-radius-scale="soft">
```

Data attributes are secondary — they enable JS-based styling (future) but are not required for CSS rendering. Phase 1 may omit data attributes if CSS variable inheritance is sufficient.

### Allowed Entry Points

Phase 1 permits only the selected admin wrapper boundary:

| File | Role |
|---|---|
| `public/views/layouts/header.php` | Future insertion target for `--corner-radius` on admin `.layout-main`; not yet authorized for implementation |

No operator, display, shared-root, workspace, app, module, or plugin insertion is
authorized in Phase 1. The future boundary gate must define the narrow data
handoff without permitting direct registry, reader, adapter, consumer, or
consumption-surface dependencies in this layout.

### Forbidden Integration Points

| Forbidden | Reason |
|---|---|
| Importing consumption surface directly in composer | Composer must use the pipeline service, not the surface directly |
| Importing adapter, contract, or consumer in composer | Layer violation |
| Inline `<style>` blocks in HTML templates | Reintroduces pattern that was explicitly removed during setup externalization |
| Runtime CSS file generation | No `file_put_contents`, CSS file creation, or stylesheet injection |
| JS-based style application in Phase 1 | Phase 1 is CSS-only. JS application introduces timing and specificity issues |
| App-specific direct pipeline calls | Apps must read `--corner-radius` from parent via `var()`. They must not call the pipeline |
| Theme source mutation | No edits to `resources/themes/*.css` from the pipeline |
| Registry writes | The pipeline is read-only. No `setValue()`, `approve()`, or `delete()` |

---

## Part I — Runtime Safety Guarantees

### Guarantee 1: No CSS File Mutation

The pipeline must never:
- Write to `public/assets/theme.css`
- Write to `resources/themes/*.css`
- Write to `apps/*/styles/*.css`
- Write to any `.css` file on disk
- Create new CSS files in any directory

The pipeline operates entirely in memory per request. Values are emitted as element-level style attributes or data attributes. No filesystem writes are ever performed.

### Guarantee 2: No Theme Ownership Transfer

The pipeline reads theme defaults for fallback but never modifies theme source or compiled output. Theme values remain authoritative for:
- Theme default values (what the system looks like without customization)
- Theme token definitions (what values are available)
- Theme compilation (how source files produce runtime CSS)

### Guarantee 3: No Registry Bypass

Shell composers must go through the full pipeline to receive values. They must not:
- Call `ApprovedStyleRegistry::getValue()` directly
- Call `ApprovedStyleReaderAdapter::readValue()` directly
- Call `StyleConsumptionSurface::radiusScale()` directly
- Read `storage/platform/style-registry/` paths directly

The pipeline is the only authorized entry point for Shell application.

### Guarantee 4: No Inline Style Proliferation

Values are emitted **only** on the wrapper element (`.app-shell`, `.operator-shell`, `.display-shell`). Child elements use CSS inheritance. This prevents:
- Hundreds of elements with inline `style` attributes
- Specificity conflicts between inline styles and CSS selectors
- Diffuse mutation points that are hard to audit

### Guarantee 5: Fail Closed

If any stage of the pipeline fails:
1. The error is logged in diagnostics.
2. The fallback value is used (never an empty or invalid CSS value).
3. No CSS property is emitted if the resolved value is invalid.
4. The Shell renders with theme defaults — never with broken or missing styles.

---

## Part J — Gate Impact

### New Gate Invariants Required

A future `check_runtime_style_application_boundary.sh` must enforce:

| # | Invariant | Scope | Enforcement |
|---|---|---|---|
| 1 | Application service is Platform-owned | `platform/Style/Runtime/` | Optional file + namespace check |
| 2 | No CSS file writes | All pipeline files | Block `file_put_contents`, `fwrite` targeting `.css` paths |
| 3 | No theme file writes | All pipeline files | Block write access to `resources/themes/` |
| 4 | No registry writes | All pipeline files | Block `setValue()`, `approve()`, `delete()` |
| 5 | No consumption surface import in Shell composers (direct) | Composer files | Block `use Platform\Style\Consumption` (composers use pipeline, not surface) |
| 6 | No adapter import in Shell | All Shell files | Block `use Platform\Style\Adapters` |
| 7 | No contract import in Shell | All Shell files | Block `use Platform\Style\Contracts` |
| 8 | No generic token engine or arbitrary CSS mapping | Runtime application file | Block generic token/style map/bundle patterns |
| 9 | Phase 1 mapping is exact | Runtime application file | Enforce `sharp`→`4px`, `soft`→`8px`, `round`→`16px`, default `8px`, with no extra match arms |
| 10 | Runtime application depends only on consumption surface | Runtime application file | Enforce sole import and constructor dependency `StyleConsumptionSurface`; block consumer/reader/registry/Studio dependencies |
| 11 | No Shell coupling from Platform application | Runtime application file | Block Shell namespace/runtime/composer/layout/operator/admin references |
| 12 | No Shell runtime wiring | Shell and layout PHP | Block `RuntimeStyleApplication` and `Platform\Style\Runtime` references |
| 13 | Exact public API and disabled flag | Runtime application file | Allow constructor plus `cornerRadius()`, `diagnostics()`, `isRuntimeApplicationEnabled()` only; require hardcoded `false` |
| 14 | Diagnostic semantics are phase-correct | Runtime application file | Require `RSA-*` placeholder codes while disabled and reserve `RSC-S001` through `RSC-S011` for active Shell application |
| 15 | No runtime CSS compilation | Runtime application file | Block shell execution and generated asset/theme paths |

### Pre-Existing Gate Updates

| Gate | Required change |
|---|---|
| `check_platform_style_consumption_surface_boundary.sh` (gate #29) | Add invariant: Surface must not be imported directly by Shell composers. Only pipeline service may import surface |
| `check_shell_consumption_boundary.sh` (gate #28) | Add allowlist for pipeline service import in 3 composer files. Update Shell composer block to allow `Platform\Style\Resolution` |

### Gate Relaxation Sequence

| Step | Action | Precondition |
|---|---|---|
| 1 | Create `check_runtime_style_application_boundary.sh` | This contract committed |
| 2 | Wire into aggregate runner as gate #30 | Gate script exists |
| 3 | Update consumer surface gate to block direct Shell import of surface | Surface gate passes |
| 4 | Update shell consumption gate to allow pipeline import in composers | Pipeline gate passes |
| 5 | Implement pipeline skeleton | Gates 1–4 complete |
| 6 | Enable runtime consumption flag | Full Shell integration complete |

---

## Part K — Non-Goals

This contract does not authorize:

| Non-Goal | Why |
|---|---|
| **CSS file generation** | All values are per-request, in-memory. No `.css` files are created |
| **Theme source editing** | Theme files remain authoritative and immutable by the pipeline |
| **Registry schema changes** | The registry stores values as strings. The pipeline maps them to CSS. Registry structure is unchanged |
| **Multi-socket expansion** | Phase 1 is `radius.scale` only. New sockets require explicit Phase N authorization |
| **JS-based style application** | Phase 1 uses CSS custom properties only. JS application is future scope |
| **Shell CSS refactor** | The pipeline does not require changes to Shell CSS selectors, component classes, or responsive rules |
| **Universal component redesign** | Components already use `var(--corner-radius)` patterns or can adopt them without structural changes |
| **Operator/Admin shell unification** | The pipeline works with both existing shell models. They remain separate |
| **Studio adaptation** | Studio continues to author and approve values. The pipeline is a runtime concern, not a Studio concern |
| **Real-time value propagation** | Values are resolved per-request. No WebSocket, SSE, or push mechanism for live updates |
| **Caching layer** | No Redis, Memcached, or APCu caching of resolved values in Phase 1 |
| **Consumption surface replacement** | The pipeline builds on the consumption surface. It does not replace or bypass it |
| **Inline style expansion beyond wrapper** | Only the wrapper element gets inline style. No child elements receive inline `--corner-radius` |
| **Data attribute expansion** | Phase 1 may omit data attributes entirely if CSS-only is sufficient |

---

## Part L — Relationship to Existing Contracts

This contract is consistent with and referenced by:

| Contract | Relationship |
|---|---|
| `platform-style-consumption-surface-contract.md` | Pipeline imports and builds on the surface. Surface returns `?string`, pipeline resolves to concrete CSS |
| `shell-consumption-contract.md` | Defines integration surface, RSC-S diagnostics family, runtime boundaries. This contract implements those definitions |
| `resolved-style-consumer-contract.md` | Consumer remains diagnostic-only. Pipeline does not import or depend on consumer |
| `registry-read-contract.md` | Pipeline reads registry through surface → adapter chain. No direct registry access |
| `shell-behavior-rendering-contract-v1.md` | Defines wrapper elements (`.app-shell`, `.operator-shell`, `.display-shell`) where values are applied |
| `universal-component-contract-v1.md` | Components consume `--corner-radius` via `var()`. No component changes required |
| `read-only-consumption-probe-contract.md` | Probe validates readiness. Pipeline is the final link the probe would validate |
| `customization-studio-operating-contract.md` | Studio governance produces the approved values that the pipeline consumes. No pipeline-to-Studio coupling |

---

## Part M — Readiness Classification

**A — RuntimeStyleApplication skeleton compliant; Shell insertion planning allowed.**

This classification authorizes planning only. Runtime application remains disabled,
and no Shell wiring, CSS mutation, theme mutation, registry mutation, or runtime
output change is authorized.

### Prerequisite Status

| Step | Status |
|---|---|
| 1. Registry Read Contract | ✅ Complete |
| 2. Registry Read Boundary Gate | ✅ Complete |
| 3. ApprovedStyleReaderContract + Adapter | ✅ Complete |
| 4. ResolvedStyleConsumer Reader Injection | ✅ Complete |
| 5. Shell Consumption Contract | ✅ Complete |
| 6. Shell Consumption Boundary Gate | ✅ Complete |
| 7. Platform Consumption Surface Contract | ✅ Complete |
| 8. Platform Consumption Surface Boundary Gate | ✅ Complete |
| 9. Platform Consumption Surface Implementation | ✅ Complete |
| 10. Platform Consumption Surface Compliance Checkpoint | ✅ Complete (classified A) |
| 11. Runtime Style Application Contract | ✅ Complete |
| 12. Runtime Style Application Boundary Gate | ✅ Complete and hardened |
| 13. RuntimeStyleApplication Skeleton | ✅ Complete |
| 14. RuntimeStyleApplication Compliance Re-Audit | ✅ Complete (classified A) |
| **15. Shell Insertion Planning Contract** | ✅ Complete (classified A) |
| **16. Shell Insertion Boundary Gate Prep** | ✅ Complete |
| **17. ShellInsertion Implementation Skeleton** | ✅ Complete |
| **18. ShellInsertion Compliance Audit** | ✅ Complete (classified B; gate hardening required) |
| **19. ShellInsertion Boundary Gate Hardening** | ✅ Complete |
| **20. ShellInsertion Compliance Re-Audit** | ✅ Complete (classified A) |
| **21. Rendered Admin Proof Planning Contract** | ✅ Complete (classified A) |
| **22. Rendered Admin Proof Boundary Gate Prep** | ✅ Complete |
| **23. Rendered Admin Proof Implementation Skeleton** | 🔲 Not started |
| 24. Rendered Admin Proof Execution | 🔲 Future |
| 25. Production Shell Integration | 🔲 Future |
| 26. Runtime application enablement | 🔲 Future |

### Classification Rationale

| Criterion | Assessment |
|---|---|
| Pipeline defined | ✅ 6 stages (read → surface → resolve → validate → apply → render) |
| Ownership clear | ✅ Platform owns resolution/validation, Shell owns application/rendering |
| Value resolution defined | ✅ radius.scale identifier → CSS value mapping (sharp→4px, soft→8px, round→16px) |
| Fallback chain defined | ✅ Registry → theme default → hardcoded fallback |
| Diagnostics family defined | ✅ RSC-S001 through RSC-S011 |
| Gate impact specified | ✅ 15 new invariants + 2 existing gate updates |
| Shell integration specified | ✅ 3 allowlisted composers, wrapper-only application |
| Safety guarantees specified | ✅ 5 guarantees (no CSS mutation, no theme transfer, no registry bypass, no inline proliferation, fail closed) |
| Runtime skeleton implemented | ✅ Platform-only fixed mapping; application disabled and Shell unwired |
| Skeleton diagnostics | ✅ `RSA-*` only; reserved for disabled skeleton diagnostics |
| Active Shell diagnostics | ✅ `RSC-S*` reserved for future active Shell application |
| Runtime application state | ✅ Disabled |
| Shell wiring state | ✅ No Shell wiring exists |

---

## Part N — Recommended Next Slice

**Rendered Admin Proof Implementation Skeleton**

Create only the allowlisted proof object and synthetic CLI harness skeletons
defined by the
[Rendered Admin Proof Planning Contract](rendered-admin-proof-planning-contract.md)
and protected by `check_rendered_admin_proof_boundary.sh`. Do not render a proof
fragment, modify Shell, enable runtime application, or apply production styles.

---

## Deliverable Summary

| # | Item | Value |
|---|---|---|
| 1 | **Contract path** | `docs/architecture/runtime-style-application-contract.md` |
| 2 | **Pipeline stages** | 6 stages: Read → Surface → Resolve → Validate → Apply → Render |
| 3 | **Value resolution** | `sharp`→`4px`, `soft`→`8px`, `round`→`16px` for `radius.scale` → `--corner-radius` |
| 4 | **Fallback chain** | Registry → theme default (`8px`) → hardcoded fallback (`8px`) |
| 5 | **Application mechanism** | CSS custom property on Shell wrapper element via composer inline style |
| 6 | **Phase 1 insertion target** | Admin `.layout-main` in `public/views/layouts/header.php`; operator and display deferred |
| 7 | **Ownership model** | Platform owns value resolution (stages 3–4). Shell owns application (stage 5) |
| 8 | **Diagnostics families** | `RSA-*` for the disabled skeleton; `RSC-S*` reserved for future active Shell application |
| 9 | **Safety guarantees** | 5 guarantees: no CSS mutation, no theme transfer, no registry bypass, no inline proliferation, fail closed |
| 10 | **Gate impact** | 15 new invariants for future `check_runtime_style_application_boundary.sh` + 2 existing gate updates |
| 11 | **Readiness classification** | **A** — RuntimeStyleApplication skeleton compliant; Shell insertion planning allowed |
| 12 | **Recommended next slice** | Rendered Admin Proof Implementation Skeleton |

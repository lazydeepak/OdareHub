# ResolvedStyleConsumer Contract

Status: Architecture contract. No implementation authorized. No runtime consumption authorized. No Shell behavior changes authorized.
Date: 2026-06-11
Supersedes: `docs/architecture/shell-approved-style-consumption-boundary.md` (consumption boundary plan, now formalized as contract)
Builds on: Customization Studio Operating Contract, Style Registry Ownership Contract, Shell Style Socket Contract, Style Rendering Contract, Style Customization Chain Checkpoint, [Registry Read Contract](registry-read-contract.md)

## Part A — Purpose

ResolvedStyleConsumer exists to:

```
read approved values
resolve runtime view
provide diagnostics
expose read-only runtime state
```

It is the single authorized consumer of the Platform Style Registry at runtime. It bridges the gap between:

```
Platform Style Registry (approved values)
        ↓
ResolvedStyleConsumer
        ↓
Shell Runtime (CSS variables, data attributes, style sockets)
```

It does not exist to:

```
author values
approve values
store values
mutate values
edit themes
edit registry entries
generate CSS
compile themes
modify selectors
```

## Part B — Ownership

### Themes own

Theme definitions, theme source files (`resources/themes/**/*.css`), theme manifests, compiled theme artifacts (`public/assets/theme.css`).

### Shell owns

Shell CSS (`apps/Shell/styles/*.css`), surface rendering, layout behavior, responsive behavior, selector definitions, style sockets.

### Apps/Modules own

Owner CSS (`apps/{Owner}/styles/*.css`), owner selectors, owner rendering, owner view templates.

### Platform owns

Platform Style Registry (`apps/Platform/StyleRegistry/`), `ResolvedStyleConsumer`, runtime style resolution, runtime diagnostics, the read-only consumption contract.

### Studio owns

Drafting, approval governance, inspection, diagnostics, snapshot/rollback.

### Core owns

No presentation ownership. Governance, ACL, path-safety, audit only.

### Ownership Enforcement

- `ResolvedStyleConsumer` lives under `apps/Platform/StyleRegistry/` — not Shell, not Studio, not Core.
- Shell may call `ResolvedStyleConsumer` only through a Platform-owned service. Shell must not read registry storage directly.
- Studio must not call `ResolvedStyleConsumer` at runtime. Studio diagnostics may reference its interface but must not depend on its implementation.

## Part C — Inputs

### Allowed

- Approved registry values — written by the governance layer (Visual Customizer Apply), stored in `storage/platform/style-registry/approved-values/`
- Active theme information — current theme style preference, available style set, fallback preference
- Runtime context — surface type (admin/operator/display/auth), view identity, current user preference

### Forbidden

- Draft values — unapproved Studio proposals, never consumed at runtime
- Unapproved values — any value that has not passed governance approval
- Direct Studio state — editor sessions, temporary overrides, preview payloads
- Temporary editor state — CTE unsaved changes, Visual Customizer work-in-progress
- Database theme-preference state — preference normalization belongs to ThemePreferenceService, not ResolvedStyleConsumer

## Part D — Outputs

### Future outputs

```
resolved token map       — key/value pairs of approved style values
resolved diagnostics     — per-token source/resolution/fallback status
runtime consumption report — summary of what was resolved, what fell back, what is missing
```

### Not outputs

```
CSS generation           — theme compilation belongs to compile_theme_sources.php
theme compilation        — belongs to Scripts Assets tooling
selector mutation        — Shell/app/module CSS is source-owned, not registry-driven
editor payloads          — Studio owns editor state, not runtime
```

## Part E — Read-Only Guarantee

`ResolvedStyleConsumer` must be:

- **Read-only** — never writes to disk, database, registry storage, or session
- **Deterministic** — same inputs always produce same outputs; no random, time-based, or state-dependent behavior
- **Side-effect free** — no file creation, no database writes, no cache mutations, no session changes, no event dispatch

It may never:

```
write registry values
change themes
approve drafts
persist runtime state
modify any file
create any file
```

## Part F — Diagnostics

### Diagnostic categories

| Category | Scope |
|---|---|
| source_resolution | Can the registry be read? Are values accessible? |
| missing_values | Required tokens missing from registry |
| theme_mismatch | Active theme style does not match registry-declared style |
| registry_mismatch | Registry values exist but are stale or inconsistent |
| owner_mismatch | Requested owner has no registry values |
| runtime_fallback | A token fell back to default because registry had no value |

### Severity model

| Severity | Meaning | Render blocking |
|---|---|---|
| PASS | Value resolved cleanly | No |
| WARN | Resolved but with non-critical caveat | No |
| FAIL | Value missing or unresolvable; fallback used | No (fallback exists) |
| ERROR | Registry unreachable or corrupted; no fallback path | Yes |

## Part G — Runtime Boundaries

### ResolvedStyleConsumer may

```
read approved values from the Platform Style Registry
read active theme context (style, preference, fallback)
read runtime surface context (admin/operator/display/auth)
produce diagnostics per diagnostic category
```

### ResolvedStyleConsumer may not

```
load Studio services
write registry data
edit themes
modify Shell CSS
modify owner CSS
write to any file
call exec() or shell_exec()
make HTTP requests
access database
modify session state
```

## Part H — Relationship To Theme System

Themes remain authoritative for all source-driven styling.

`ResolvedStyleConsumer` does not replace:

```
theme source files       (resources/themes/**/*.css)
theme compiler           (scripts/assets/compile_theme_sources.php)
theme manifests          (resources/themes/theme-manifest.json)
compiled theme.css       (public/assets/theme.css)
```

`ResolvedStyleConsumer` consumes approved runtime state only — values that have passed through governance, been applied to the registry, and are ready for consumption. These values are overlays on top of theme defaults, not replacements for theme source.

Resolution precedence at runtime:

```
1. Theme source values (foundation → semantic → style variant)
2. Compiled theme.css (generated from source)
3. Approved registry overlay values (consumed by ResolvedStyleConsumer)
4. User preference (theme-preference, not style values)
```

Layer 3 is additive — it may override specific tokens on top of theme defaults but must never replace theme source as the primary style definition.

## Part I — Relationship To Platform Style Registry

Platform Style Registry remains source-of-record for approved customization values.

```
Registry storage: storage/platform/style-registry/approved-values/
Registry ownership: apps/Platform/StyleRegistry
Registry governance: Customization Studio Apply workflow
```

`ResolvedStyleConsumer` is only a consumer.

It is never a second source of truth.

If the registry is empty (no values written yet), `ResolvedStyleConsumer` returns empty resolution — it does not fabricate values, does not fall back to drafts, does not read theme source directly, and does not generate defaults.

Registry reads flow through `ApprovedStyleReaderContract` (defined by the [Registry Read Contract](registry-read-contract.md)) — a segregated read-only interface that exposes only `readValue()` and `isReachable()`. The consumer must never receive the full `ApprovedStyleRegistryContract` (which includes `setValue` and `isWritable`). See the registry-read contract for adapter design, Phase 1 scope, diagnostics (RSC-C* family), and gate relaxation plan.

## Part J — Future Read-Only Consumption Probe

The future probe is defined by the [Read-Only Consumption Probe Contract](read-only-consumption-probe-contract.md).

The probe answers one question:

```
Can the future ResolvedStyleConsumer read approved values safely?
```

Not:

```
Can Shell apply approved values?
```

The probe must be:

- CLI-executable (`scripts/platform/probe_resolved_style_consumer.php`)
- Diagnostic-only — no runtime rendering path
- Idempotent — same output on every run with same registry state
- Safe on empty registry — must not error when no approved values exist

See the probe contract for full scope: inputs, outputs, diagnostics model (RSC-P001 through RSC-E002), exit codes, safety rules, and boundary invariants.

## Part K — Explicit Non-Goals

No:

```
runtime style application         — Shell is not authorized to apply registry values in this phase
theme mutation                    — theme source remains authoritative
registry mutation                 — only governance layer writes to registry
token editing                     — Studio owns drafting
draft creation                    — Studio owns drafting
approval workflow                 — Studio owns governance
CSS generation                    — compile_theme_sources.php owns compilation
selector generation               — owner CSS is source-written only
theme compilation                 — Scripts Assets tooling owns compilation
runtime performance optimization — not scoped until implementation phase
caching layer                     — no cache, no opcache, no redis in this contract
database reads                    — ResolvedStyleConsumer reads file storage only
HTTP API                          — no REST, no CLI-to-HTTP bridge for registry values
```

## Part L — Cross References

This contract formalizes and supersedes:
- `docs/architecture/shell-approved-style-consumption-boundary.md` — the earlier consumption boundary plan is now a formal contract

This contract builds on and must remain consistent with:
- `docs/architecture/customization-studio-operating-contract.md` — Part B ownership model, Part I governance layer
- `docs/architecture/style-registry-ownership-contract.md` — Platform registry ownership and read-only contract provision
- `docs/architecture/shell-style-socket-contract.md` — Shell style socket definitions that ResolvedStyleConsumer feeds
- `docs/architecture/style-rendering-contract.md` — runtime consumption boundary and preview/rendering model
- `docs/architecture/style-customization-chain-checkpoint.md` — chain completeness and CTE/Visual Customizer status
- `docs/architecture/theme-source-compilation-migration.md` — theme source/compile/runtime layers
- `docs/architecture/shell-behavior-rendering-contract-v1.md` — Shell rendering behavior and overlay/z-index/breakpoint models
- `docs/architecture/universal-component-contract-v1.md` — universal component contracts that may consume resolved style values
- `docs/architecture/read-only-consumption-probe-contract.md` — diagnostic-only probe contract that validates readiness before consumer implementation
- `docs/architecture/registry-read-contract.md` — segregated read-only contract for registry access, adapter design, diagnostics (RSC-C* family), and gate relaxation plan
- `docs/architecture/shell-consumption-contract.md` — Shell-facing consumption planning, Platform Consumption Surface model, RSC-S* diagnostics, Phase 1 scope
- `docs/architecture/platform-style-consumption-surface-contract.md` — Platform-owned consumption surface defining `StyleConsumptionSurface` with typed accessors (Phase 1: `radiusScale()`), PSC-* diagnostics family (8 codes), and gate implications (planned gate #29)
- `docs/architecture/runtime-style-application-contract.md` — defines Layer 4 pipeline from surface values to Shell rendering: value resolution, pre-application validation, `ResolvedStyleValue` model, fallback chain, RSC-S* diagnostics family, and application mechanism (CSS custom properties on wrapper elements)

New references to add in sibling documents:
- `customization-studio-operating-contract.md` Section 5 (Runtime Consumption Layer): link to this contract
- `style-registry-ownership-contract.md`: link to this contract as the consumer side of registry ownership
- `shell-style-socket-contract.md`: link to this contract as the feed source for socket values
- `style-rendering-contract.md`: link to this contract as the formal consumer boundary
- `registry-read-contract.md`: Part K cross-references (already references this contract)

## Part M — Registry Read Contract

The [Registry Read Contract](registry-read-contract.md) defines the segregated read-only contract (`ApprovedStyleReaderContract`) that authorizes future registry reads by `ResolvedStyleConsumer`. It covers:

- **Interface shape:** `readValue(string $socketKey): ?string` and `isReachable(): bool` — Phase 1 only, no batch or metadata methods.
- **Adapter design:** `ApprovedStyleReaderAdapter` in `platform/Style/Services/` as the sole bridge to `Apps\Platform\StyleRegistry`.
- **Phase 1 scope:** Single-value read for `radius.scale` only. Batch reads, metadata, and catalog scans deferred.
- **Resolution precedence:** Theme source → compiled theme.css → approved registry overlay (layer 3) → future runtime projection (layer 4, not authorized).
- **Diagnostics:** RSC-C001 through RSC-C303 (13 codes, 4 severity levels) alongside the probe's existing RSC-P/W/F/E codes.
- **Gate relaxation plan:** Specific invariant changes required before registry reads are unblocked.
- **Security:** Path confinement, no writes, no Studio/Shell imports, no DB/HTTP, no route registration.

The read contract is a prerequisite for consumer registry-read implementation. It must exist and be committed before any gate relaxation or reader adapter work begins.

The [Registry Read Boundary Gate Update Plan](registry-read-boundary-gate-update-plan.md) builds on this contract to define the exact gate invariant changes, file scope rules, 22 still-forbidden pattern groups, and the 6-step implementation sequence for safely relaxing the boundary gate.

## Part N — Platform Consumption Surface Contract

The [Platform Style Consumption Surface Contract](platform-style-consumption-surface-contract.md) defines the Platform-owned service (`StyleConsumptionSurface`) that Shell will eventually import for approved customization values. It covers:

- **API shape:** Typed accessor per socket — `radiusScale(): ?string` (Phase 1). No generic `readValue()`. No batch reads. No write methods.
- **Ownership:** Platform owns the surface (`platform/Style/Consumption/`). No ownership transfers. Shell imports only this surface.
- **Shell import rule:** Shell must not import `ResolvedStyleConsumer`, `ApprovedStyleReaderContract`, `ApprovedStyleReaderAdapter`, or `Apps\Platform\StyleRegistry`. Only `Platform\Style\Consumption\StyleConsumptionSurface` in 3 allowlisted Shell composers.
- **Runtime boundary:** Surface exists ≠ Shell applies values. Value visible ≠ CSS mutation. Diagnostic value ≠ runtime style override. `isRuntimeConsumptionEnabled()` must return `false`.
- **Diagnostics:** PSC-P001–PSC-E001 (8 codes: 3 PASS, 2 WARN, 2 FAIL, 1 ERROR).
- **Gate impact:** New gate required (`check_platform_style_consumption_surface_boundary.sh`, planned #29) with 13+ hard block invariants and 5 positive expectations. Existing gates #27 (consumer) and #28 (Shell consumption) remain unchanged until implementation.

The consumption surface contract is a prerequisite for Shell consumption implementation. It must exist and be committed before any gate preparation or surface implementation begins.

## Deliverable Summary

| # | Item | Value |
|---|---|---|
| 1 | Contract path | `docs/architecture/resolved-style-consumer-contract.md` |
| 2 | Ownership model | Platform owns ResolvedStyleConsumer. Themes own source. Shell owns sockets. Studio owns governance. |
| 3 | Inputs | Approved registry values, active theme information, runtime context |
| 4 | Outputs | Resolved token map, resolved diagnostics, runtime consumption report |
| 5 | Read-only guarantees | Read-only, deterministic, side-effect free. No writes, no mutations, no persistence. |
| 6 | Diagnostics model | 6 categories (source_resolution through runtime_fallback). 4 severity levels (PASS/WARN/FAIL/ERROR). |
| 7 | Registry relationship | Consumer only. Never second source of truth. Empty registry = empty resolution. |
| 8 | Theme relationship | Additive overlay on theme defaults. Never replaces theme source. Layer 3 of 4 in resolution precedence. |
| 9 | Future probe definition | CLI-executable probe answering "Can the future consumer read approved values safely?" — see [Read-Only Consumption Probe Contract](read-only-consumption-probe-contract.md) |

### Recommended next slice

```
Registry Read Boundary Gate Update Plan
```

An architecture plan that specifies the exact `check_platform_style_consumer_boundary.sh` invariant changes, the adapter scan rules, and the sequence for relaxing the gate without enabling runtime consumption. This plan must exist before any gate or implementation work begins.

**Status:** ✅ Completed. See [Registry Read Boundary Gate Update Plan](registry-read-boundary-gate-update-plan.md).

This replaces the previously recommended "Read-Only Consumption Probe Boundary Gate Prep" slice, which was completed in an earlier session (see [Read-Only Consumption Probe Contract](read-only-consumption-probe-contract.md)).

### Next After That

```
Registry Read Boundary Gate Update
```

Modify `check_platform_style_consumer_boundary.sh` per the gate update plan. Not registry-read implementation.

### Next After That

```
Platform Consumption Surface Contract
```

**Status:** ✅ Completed. See [Platform Style Consumption Surface Contract](platform-style-consumption-surface-contract.md) for the full contract with API shape, ownership model, PSC-* diagnostics family, gate implications, and readiness classification.

### Next After That

```
Platform Consumption Surface Boundary Gate Prep
```

Define `check_platform_style_consumption_surface_boundary.sh` with 13+ hard block invariants and 5 positive expectations before any consumption surface implementation.

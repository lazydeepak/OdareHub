# Shell Insertion Planning Contract

**Status:** Planning contract, hardened boundary gate, and disabled Platform
skeleton complete. No Shell insertion. No runtime application enablement. No
CSS, theme, registry, public asset, or runtime output mutation.

**Date:** 2026-06-12

**Builds on:**
- [Runtime Style Application Contract](runtime-style-application-contract.md)
- [Shell Consumption Contract](shell-consumption-contract.md)
- [Platform Style Consumption Surface Contract](platform-style-consumption-surface-contract.md)
- [Shell Behavior & Rendering Contract V1](shell-behavior-rendering-contract-v1.md)

---

## Part A — Insertion Purpose

### Why Shell Insertion Is Needed

`RuntimeStyleApplication` converts the governed `radius.scale` identifier into a
concrete CSS value, but it does not own HTML rendering. A resolved value cannot
affect presentation until a Shell-owned wrapper emits the allowlisted CSS custom
property.

The skeleton alone is intentionally insufficient because it:

1. has no Shell dependency;
2. does not select or modify wrapper markup;
3. keeps `isRuntimeApplicationEnabled()` hardcoded to `false`;
4. emits only skeleton-safe `RSA-*` diagnostics; and
5. performs no runtime output mutation.

Shell must remain the consumer, not the owner, of runtime style resolution.
Platform owns the mapping and fallback. Shell owns only the location and safe
rendering of the resolved value.

### Canonical Future Handoff

```text
Registry approved value
    ↓
StyleConsumptionSurface
    ↓
RuntimeStyleApplication::cornerRadius()
    ↓ concrete allowlisted value
Shell-owned admin wrapper
    ↓
--corner-radius
```

This contract authorizes preparation of the boundary gate only. It does not
authorize any handoff or markup change.

---

## Part B — Phase 1 Insertion Target

### Selected Target

**Admin shell wrapper only.**

The concrete Phase 1 boundary is the existing `.layout-main` wrapper rendered by:

```text
public/views/layouts/header.php
```

No other wrapper is authorized in Phase 1. In particular, this contract does not
authorize insertion into:

- operator shell wrappers;
- display shell wrappers;
- shared/global `body` or `.layout-shell` roots;
- workspace or app-owned surface wrappers;
- auth, setup, recovery, maintenance, print, or output surfaces.

### Why This Is the Safest Target

The admin wrapper is the narrowest governed Shell surface that can prove the
handoff without affecting operator or display production paths. `.layout-main`
already encloses the admin topbar and workspace content, so one inherited custom
property can reach Shell-owned descendants without child-level insertion.

Selecting a shared Shell root would expand the blast radius immediately.
Selecting operator or display wrappers would alter role-specific production
surfaces before the insertion boundary is proven. Selecting arbitrary workspace
wrappers would permit app-level proliferation.

---

## Part C — Allowed Value

Phase 1 permits exactly one socket and one CSS custom property:

| Socket | Identifier | Resolved value | CSS custom property |
|---|---|---|---|
| `radius.scale` | `sharp` | `4px` | `--corner-radius` |
| `radius.scale` | `soft` | `8px` | `--corner-radius` |
| `radius.scale` | `round` | `16px` | `--corner-radius` |
| `radius.scale` | absent or unsupported | `8px` fallback | `--corner-radius` |

No arbitrary socket, property name, unit, raw CSS value, token bundle, style map,
or batch application is permitted.

---

## Part D — Insertion Model

### Options

| Model | Assessment | Phase 1 decision |
|---|---|---|
| Inline style variable on wrapper | Applies one concrete value at the owner-controlled boundary; naturally inherited; no file mutation | **Selected** |
| Data attribute on wrapper | Carries an identifier but does not apply CSS without JS or selector mappings | Rejected |
| CSS class on wrapper | Requires duplicate identifier-to-class and class-to-value mappings in Shell CSS | Rejected |
| Generated `<style>` block | Broadens selector reach and reintroduces generated style blocks | Forbidden |
| Compiled CSS mutation | Writes persistent assets, creates synchronization concerns, and transfers runtime values into theme output | Forbidden |

### Selected Model

The future insertion may add only one element-level declaration to the selected
admin wrapper:

```html
<div class="layout-main" style="--corner-radius: 8px">
```

The example is illustrative and is not implemented by this contract.

The future implementation must:

1. obtain the concrete value only from `RuntimeStyleApplication::cornerRadius()`;
2. accept only `4px`, `8px`, or `16px`;
3. emit only the fixed property name `--corner-radius`;
4. escape the rendered attribute value;
5. avoid generic style arrays, token maps, property maps, or arbitrary style
   string concatenation; and
6. add no inline style declarations to child elements.

An element-level custom property is not permission for broad inline styling.
The future gate must treat every other property or target as a violation.

---

## Part E — Ownership Boundaries

| Owner | Responsibility |
|---|---|
| **Shell** | Owns `.layout-main`, wrapper markup, selectors, escaping, and the eventual element-level custom property insertion |
| **Platform** | Owns `RuntimeStyleApplication`, the fixed identifier mapping, fallback, and runtime application state |
| **Registry** | Owns approved `radius.scale` values |
| **Theme source** | Remains authoritative for default visual design and all source CSS |
| **Studio** | Owns governance, analysis, approval, and diagnostics tooling only; it does not render or apply the value |

Shell must not own or duplicate the `sharp`/`soft`/`round` mapping. Platform must
not own or modify Shell markup. Registry and theme ownership remain unchanged.

---

## Part F — Runtime Enablement Rule

`RuntimeStyleApplication::isRuntimeApplicationEnabled()` remains hardcoded to
`false`.

It may become `true` only in a separately authorized runtime enablement slice
after all of the following are complete:

1. this planning contract is accepted;
2. `check_shell_insertion_boundary.sh` exists, is wired into the aggregate
   architecture runner, and passes;
3. a separate Shell insertion implementation slice adds only the selected admin
   wrapper handoff;
4. a compliance audit proves no other Shell surface or dependency was added;
5. rendered-output validation proves only `--corner-radius` with an allowlisted
   value can be emitted; and
6. rollback behavior is defined and verified.

Creating the insertion gate or inserting the disabled handoff does not itself
authorize enablement. No configuration, environment toggle, database flag, or
feature flag is justified for Phase 1. Any future flag requires its own contract
and must fail closed.

---

## Part G — Diagnostics

Shell insertion preparation uses the `RSI-*` family. These are boundary and
readiness diagnostics, not claims that runtime style application occurred.

| Code | Severity | Meaning |
|---|---|---|
| `RSI-P001` | PASS | Selected admin wrapper boundary is uniquely identified |
| `RSI-P002` | PASS | Fixed `--corner-radius` insertion model is enforced |
| `RSI-P003` | PASS | Shell depends only on `RuntimeStyleApplication` at the planned boundary |
| `RSI-W001` | WARN | Runtime application remains disabled |
| `RSI-W002` | WARN | Planned Shell insertion is absent |
| `RSI-F001` | FAIL | An unapproved wrapper or Shell surface is targeted |
| `RSI-F002` | FAIL | A socket, property, or value outside the Phase 1 allowlist appears |
| `RSI-F003` | FAIL | Shell imports a forbidden registry, reader, adapter, consumer, Studio, or app dependency |
| `RSI-F004` | FAIL | Broad inline styles, generic maps, or arbitrary CSS application appear |
| `RSI-E001` | ERROR | Runtime application is enabled before the insertion boundary is authorized |
| `RSI-E002` | ERROR | CSS, theme, registry, asset, file, DB, HTTP, route, or command side effects appear |

Diagnostic families remain separate:

```text
RSA-*   disabled RuntimeStyleApplication skeleton
RSI-*   Shell insertion boundary readiness
RSC-S*  future active Shell application
```

`RSI-*` must never report a value as visually applied. `RSC-S*` remains reserved
until the active Shell application phase.

---

## Part H — Future Gate Impact

### Planned Gate

```text
scripts/architecture/check_shell_insertion_boundary.sh
```

The future gate must pass while Shell insertion is absent. Once insertion exists,
it must enforce:

1. exactly one insertion target: the admin `.layout-main` wrapper;
2. exactly one Platform runtime dependency:
   `Platform\Style\Runtime\RuntimeStyleApplication`;
3. exactly one public value call: `cornerRadius()`;
4. exactly one emitted property: `--corner-radius`;
5. exactly three concrete values: `4px`, `8px`, `16px`, with `8px` fallback
   remaining Platform-owned;
6. no insertion into operator, display, shared root, workspace, auth, setup,
   print, or app-owned wrappers;
7. no extra inline declaration and no child-level style insertion;
8. runtime application remains disabled until a later enablement slice; and
9. `RSI-*` diagnostics are present without emitting active `RSC-S*` success.

### Dependencies Still Blocked

Shell must not import or reference:

```text
ApprovedStyleRegistry
ApprovedStyleReaderContract
ApprovedStyleReaderAdapter
ResolvedStyleConsumer
StyleConsumptionSurface
storage/platform/style-registry
approved-values
Apps\Studio
```

Shell may eventually depend only on `RuntimeStyleApplication` at the exact
allowlisted insertion boundary.

### Operations Still Blocked

The future gate must continue blocking:

- CSS, theme, and public asset writes or generation;
- registry mutation or direct registry reads;
- file, database, HTTP, route, or command side effects;
- generated `<style>` blocks;
- broad inline style attributes;
- generic token, style, CSS variable, or bundle maps;
- arbitrary CSS properties or values; and
- JavaScript-driven style application.

The gate must enforce structure and behavior, not marker presence alone.

---

## Part I — Non-Goals

This contract does not authorize:

- a full theme system;
- generic CSS variables;
- all style sockets;
- a live UI editor;
- Visual Customizer expansion;
- CSS Token Editor governance migration;
- print or output integration;
- operator or display insertion;
- Shell code changes;
- runtime application enablement;
- CSS, theme, registry, asset, or runtime output mutation.

---

## Part J — Readiness Classification

**A — Shell insertion gate planning allowed.**

The target, insertion model, value allowlist, ownership boundary, diagnostics
family, enablement prerequisites, and future gate responsibilities are now
defined narrowly enough to prepare a boundary gate.

This classification permits gate preparation only. It does not permit Shell
insertion or runtime enablement.

---

## Part K — Recommended Next Slice

**Rendered Admin Proof Planning Contract — Complete**

The [Rendered Admin Proof Planning Contract](rendered-admin-proof-planning-contract.md)
defines a synthetic, process-local proof for the admin `.layout-main` boundary,
and its boundary gate is complete. The next slice is **Rendered Admin Proof
Implementation Skeleton**.

---

## Deliverable Summary

| Item | Decision |
|---|---|
| Contract path | `docs/architecture/shell-insertion-planning-contract.md` |
| Selected insertion target | Admin shell wrapper: `.layout-main` in `public/views/layouts/header.php` |
| Selected insertion model | One element-level inline CSS custom property on the wrapper |
| Allowed value | `radius.scale`: `sharp`→`4px`, `soft`→`8px`, `round`→`16px`, fallback `8px` |
| Allowed variable | `--corner-radius` only |
| Ownership | Shell renders; Platform resolves; Registry approves; Theme remains authoritative; Studio governs only |
| Runtime enablement | Remains hardcoded `false` until gate, insertion, audit, rendered validation, and rollback prerequisites pass |
| Diagnostics | `RSI-*` for insertion readiness; separate from `RSA-*` and `RSC-S*` |
| Future gate | `check_shell_insertion_boundary.sh` |
| Readiness | **A — Shell insertion gate planning allowed** |
| Recommended next slice | **Rendered Admin Proof Implementation Skeleton** |
